<?php

namespace App\Livewire\Customers;

use App\Models\AppSetting;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\MedicalRecord;
use App\Models\PromoterCode;
use App\Models\ReferralCommission;
use App\Services\WhatsAppService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithPagination, WithFileUploads;

    public string $search = '';
    public string $name = '';
    public string $type = 'retail';
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public string $notes = '';
    public ?int $customerId = null;
    public bool $modal = false;

    // OTP verification for promoter commissions
    public bool $otpModal = false;
    public string $otpCode = '';
    public string $otpError = '';
    public ?int $pendingCommissionCustomerId = null;
    public string $pendingPhone = '';

    // Wi-Fi code handover
    public bool $codeModal = false;
    public ?int $issuedCodeId = null;
    public string $issuedCode = '';
    public bool $codeSent = false;
    public bool $codeRedeemed = false;
    public bool $noSmartDevice = false;
    public string $otpChannel = '';
    public float $earnedAmount = 0;

    // Customer profile drawer
    public ?int $viewCustomerId = null;
    public bool $profileDrawer = false;

    // Medical record form
    public string $mr_title = '';
    public string $mr_type = 'prescription';
    public string $mr_details = '';
    public string $mr_date = '';
    public string $mr_note = '';
    public $mr_file = null;
    public bool $mrModal = false;

    /**
     * A "pure" promoter — someone whose only role is promoter. Staff who also
     * hold an operational role keep their normal, unrestricted access.
     */
    private function isPromoter(): bool
    {
        $roles = auth()->user()->role ?? [];

        return in_array('promoter', $roles)
            && !array_intersect($roles, ['admin', 'pharmacist', 'branch_manager', 'sales', 'cashier']);
    }

    /** Medical records are clinical: only a pharmacist or admin may write them. */
    public function canEditMedicalRecords(): bool
    {
        return (bool) array_intersect(auth()->user()->role ?? [], ['admin', 'pharmacist']);
    }

    /** Branch managers may read records for oversight, but not change them. */
    public function canViewMedicalRecords(): bool
    {
        return (bool) array_intersect(auth()->user()->role ?? [], ['admin', 'pharmacist', 'branch_manager']);
    }

    public function create()
    {
        $this->reset(['name', 'type', 'phone', 'email', 'address', 'notes', 'customerId']);
        $this->modal = true;
    }

    public function save()
    {
        $user       = auth()->user();
        $isPromoter = $this->isPromoter();

        // Promoters may only ever create; they cannot edit existing records.
        if ($isPromoter && $this->customerId) {
            $this->modal = false;
            $this->error('Promoters cannot edit customer records.');
            return;
        }

        $this->validate([
            'name'    => 'required|string|max:255',
            'type'    => 'required|in:retail,wholesale',
            'phone'   => ($isPromoter && !$this->customerId) ? 'required|string|max:20' : 'nullable|string|max:20',
            'email'   => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'notes'   => 'nullable|string',
        ]);

        $phone = Customer::normalizePhone($this->phone);

        // Reject a number already on file, in any spelling.
        if ($phone) {
            $clash = Customer::where('phone', $phone)
                ->when($this->customerId, fn($q) => $q->whereKeyNot($this->customerId))
                ->first();

            if ($clash) {
                $this->addError('phone', 'This phone number is already registered to ' . $clash->name . '.');
                return;
            }
        }

        // A promoter must not verify against a phone they control themselves.
        if ($isPromoter && $phone && $phone === Customer::normalizePhone($user->phone)) {
            $this->addError('phone', 'You cannot register a customer using your own phone number.');
            return;
        }

        $data = [
            'name'    => $this->name,
            'type'    => $this->type,
            'phone'   => $phone,
            'email'   => $this->email,
            'address' => $this->address,
            'notes'   => $this->notes,
        ];

        if ($this->customerId) {
            Customer::findOrFail($this->customerId)->update($data);
            $this->modal = false;
            $this->success('Customer updated.');
            $this->reset(['name', 'type', 'phone', 'email', 'address', 'notes', 'customerId']);
            return;
        }

        $customer = Customer::create(array_merge($data, ['registered_by' => auth()->id()]));

        if ($isPromoter) {
            $otp  = $customer->generateOtp();
            // How the OTP reached them tells us whether they can use Wi-Fi at all.
            $this->otpChannel = $this->sendOtp($customer->phone, $otp);
            $sent = $this->otpChannel !== WhatsAppService::FAILED;

            $this->modal = false;
            $this->reset(['name', 'type', 'phone', 'email', 'address', 'notes', 'customerId']);

            if ($sent) {
                $this->pendingCommissionCustomerId = $customer->id;
                $this->pendingPhone  = $customer->phone;
                $this->otpCode  = '';
                $this->otpError = '';
                $this->otpModal = true;
            } else {
                $this->warning('Customer added, but OTP could not be sent — check WhatsApp/SMS settings. No commission logged.');
            }
            return;
        }

        $this->modal = false;
        $this->success('Customer added.');
        $this->reset(['name', 'type', 'phone', 'email', 'address', 'notes', 'customerId']);
    }

    public function confirmOtp(): void
    {
        if (empty(trim($this->otpCode))) {
            $this->otpError = 'Please enter the OTP.';
            return;
        }

        // pendingCommissionCustomerId is a public property and therefore
        // client-controlled — never trust it without re-checking ownership.
        $customer = Customer::where('id', $this->pendingCommissionCustomerId)
            ->where('registered_by', auth()->id())
            ->first();

        if (!$customer) {
            $this->otpModal = false;
            $this->resetPendingOtp();
            $this->error('Customer not found, or not registered by you.');
            return;
        }

        if (PromoterCode::where('customer_id', $customer->id)->exists()) {
            $this->otpModal = false;
            $this->resetPendingOtp();
            $this->error('A Wi-Fi code has already been issued for this customer.');
            return;
        }

        if ($customer->otpAttemptsExhausted()) {
            $this->otpError = 'Too many incorrect attempts. Tap Resend to get a new code.';
            return;
        }

        if (!$customer->verifyOtp(trim($this->otpCode))) {
            $remaining = max(0, Customer::OTP_MAX_ATTEMPTS - (int) $customer->fresh()->otp_attempts);
            $this->otpError = $remaining > 0
                ? "Incorrect or expired OTP. {$remaining} attempt(s) left."
                : 'Too many incorrect attempts. Tap Resend to get a new code.';
            return;
        }

        $customer->clearOtp();

        // Reached only by SMS while WhatsApp was working: the number has no
        // WhatsApp, so no device that can use the Wi-Fi. A degraded fallback
        // (WhatsApp down or unconfigured) tells us nothing, so it is NOT
        // treated this way — those customers still get a code as normal.
        $noSmartDevice = $this->otpChannel === WhatsAppService::VIA_SMS;

        try {
            $code = PromoterCode::create([
                'code'          => PromoterCode::generateCode(),
                'user_id'       => auth()->id(),
                'customer_id'   => $customer->id,
                'delivered_via' => $this->otpChannel,
                'valid_until'   => today(),
                // Never usable, so don't leave a live code lying around.
                'revoked_at'    => $noSmartDevice ? now() : null,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            $this->otpModal = false;
            $this->resetPendingOtp();
            $this->error('A Wi-Fi code has already been issued for this customer.');
            return;
        }

        $this->issuedCodeId  = $code->id;
        $this->issuedCode    = $code->code;
        $this->noSmartDevice = $noSmartDevice;
        $this->codeRedeemed  = false;

        if ($noSmartDevice) {
            // They can never connect, so the promoter is paid now rather than
            // being penalised for the customer's handset.
            $this->earnedAmount = $this->recordCommission($customer);
            $this->codeRedeemed = true;
            $this->codeSent     = $this->sendNoDeviceMessage($customer->phone);
        } else {
            $this->codeSent = $this->sendCode($customer->phone, $code->code);
        }

        $this->otpModal  = false;
        $this->codeModal = true;
        $this->otpCode   = '';
        $this->otpError  = '';
    }

    /** Records the promoter's commission for this customer, returning the amount. */
    private function recordCommission(Customer $customer): float
    {
        $amount = (float) AppSetting::get('commission_amount', 100);

        try {
            ReferralCommission::create([
                'user_id'     => auth()->id(),
                'customer_id' => $customer->id,
                'amount'      => $amount,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique (user_id, customer_id) — already paid for this customer.
            return (float) ReferralCommission::where('user_id', auth()->id())
                ->where('customer_id', $customer->id)->value('amount');
        }

        return $amount;
    }

    /**
     * A feature-phone customer gets no Wi-Fi code — it would be useless to them
     * — but the coupon still works at the counter, so that is worth sending.
     */
    private function sendNoDeviceMessage(string $phone): bool
    {
        $name    = AppSetting::get('pharmacy_name', 'BasmelCare');
        $message = "Welcome to {$name}!";

        if ($offer = $this->couponMessage()) {
            $message .= ' ' . $offer;
        } else {
            $message .= ' Thank you for registering with us.';
        }

        return app(WhatsAppService::class)->send($phone, $message);
    }

    /**
     * Polled by the code modal so the promoter sees the connection land while
     * they are still with the customer. Stops once redeemed.
     */
    public function checkRedemption(): void
    {
        if ($this->codeRedeemed || ! $this->issuedCodeId) {
            return;
        }

        $code = PromoterCode::find($this->issuedCodeId);

        if ($code?->redeemed_at) {
            $this->codeRedeemed = true;
            $this->earnedAmount = (float) (ReferralCommission::where('user_id', auth()->id())
                ->where('customer_id', $code->customer_id)
                ->value('amount') ?? 0);
        }
    }

    public function closeCodeModal(): void
    {
        $this->codeModal = false;
        $this->issuedCodeId = null;
        $this->issuedCode = '';
        $this->codeRedeemed = false;
        $this->noSmartDevice = false;
        $this->otpChannel = '';
        $this->earnedAmount = 0;
        $this->resetPendingOtp();
    }

    private function sendCode(string $phone, string $code): bool
    {
        $name  = AppSetting::get('pharmacy_name', 'BasmelCare');
        $hours = (int) AppSetting::get('voucher_validity_hours', 24);

        $message = "Welcome to {$name}! Your free Wi-Fi code is: *{$code}*. "
            . "Connect to the {$name} network and enter it to get {$hours} hours of internet.";

        if ($offer = $this->couponMessage()) {
            $message .= ' ' . $offer;
        }

        return app(WhatsAppService::class)->send($phone, $message);
    }

    /**
     * The coupon line for the welcome message, or null.
     *
     * Returns nothing unless the chosen coupon can actually be redeemed today —
     * texting an expired or used-up code sends customers to the counter for a
     * discount they cannot get, and the promoter wears the complaint.
     */
    private function couponMessage(): ?string
    {
        $code = trim((string) AppSetting::get('promoter_coupon_code', ''));

        if ($code === '') {
            return null;
        }

        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon || ! $coupon->isAdvertisable()) {
            return null;
        }

        $name    = AppSetting::get('pharmacy_name', 'BasmelCare');
        $line    = "Show this code at {$name} for {$coupon->offerSummary()}: *{$coupon->code}*.";
        $conditions = $coupon->conditionsSummary();

        return $conditions ? $line . ' ' . $conditions : $line;
    }

    private function resetPendingOtp(): void
    {
        $this->pendingCommissionCustomerId = null;
        $this->pendingPhone = '';
        $this->otpCode  = '';
        $this->otpError = '';
    }

    public function resendOtp(): void
    {
        $customer = Customer::where('id', $this->pendingCommissionCustomerId)
            ->where('registered_by', auth()->id())
            ->first();

        if (!$customer || !$customer->phone) {
            $this->otpError = 'Cannot resend — customer not found, or not registered by you.';
            return;
        }

        if (ReferralCommission::where('customer_id', $customer->id)->exists()) {
            $this->otpModal = false;
            $this->resetPendingOtp();
            $this->error('A commission has already been recorded for this customer.');
            return;
        }

        // Throttle so resend can't be used to burn SMS credit or spam a number.
        if ($customer->otp_sent_at && $customer->otp_sent_at->diffInSeconds(now()) < 60) {
            $wait = 60 - $customer->otp_sent_at->diffInSeconds(now());
            $this->otpError = "Please wait {$wait}s before requesting another code.";
            return;
        }

        $otp  = $customer->generateOtp();
        $sent = $this->sendOtp($customer->phone, $otp);

        if ($sent) {
            $this->otpError = '';
            $this->info('OTP resent.');
        } else {
            $this->otpError = 'Failed to resend — check WhatsApp/SMS settings.';
        }
    }

    public function skipOtp(): void
    {
        $customer = Customer::where('id', $this->pendingCommissionCustomerId)
            ->where('registered_by', auth()->id())
            ->first();
        $customer?->clearOtp();

        $this->otpModal = false;
        $this->resetPendingOtp();
        $this->warning('Customer added without verification. No commission logged.');
    }

    /** @return string one of the WhatsAppService VIA_* / FAILED constants */
    private function sendOtp(string $phone, string $otp): string
    {
        $name    = AppSetting::get('pharmacy_name', 'BasmelCare');
        $message = "Your {$name} registration code is: *{$otp}*. Valid for 10 minutes.";

        return app(WhatsAppService::class)->deliver($phone, $message);
    }

    public function edit($id)
    {
        if ($this->isPromoter()) {
            $this->error('Promoters cannot edit customer records.');
            return;
        }

        $customer = Customer::findOrFail($id);
        $this->customerId = $customer->id;
        $this->name = $customer->name;
        $this->type = $customer->type;
        $this->phone = $customer->phone ?? '';
        $this->email = $customer->email ?? '';
        $this->address = $customer->address ?? '';
        $this->notes = $customer->notes ?? '';
        $this->modal = true;
    }

    public function delete($id)
    {
        if ($this->isPromoter()) {
            $this->error('Promoters cannot delete customer records.');
            return;
        }

        Customer::findOrFail($id)->delete();
        $this->success('Customer deleted.');
    }

    public function viewProfile($id)
    {
        // Promoters may only open profiles of customers they registered.
        if ($this->isPromoter()
            && !Customer::where('id', $id)->where('registered_by', auth()->id())->exists()) {
            $this->error('You can only view customers you registered.');
            return;
        }

        $this->viewCustomerId = $id;
        $this->profileDrawer = true;
    }

    public function openMedicalRecord()
    {
        if (! $this->canEditMedicalRecords()) {
            $this->error('Only a pharmacist or admin can add medical records.');
            return;
        }

        $this->reset(['mr_title', 'mr_type', 'mr_details', 'mr_date', 'mr_note', 'mr_file']);
        $this->mr_date = now()->format('Y-m-d');
        $this->mrModal = true;
    }

    public function saveMedicalRecord()
    {
        if (! $this->canEditMedicalRecords()) {
            $this->error('Only a pharmacist or admin can add medical records.');
            return;
        }

        $this->validate([
            'mr_title' => 'required|string|max:255',
            'mr_type' => 'required|string|max:100',
            'mr_details' => 'nullable|string',
            'mr_date' => 'required|date',
            'mr_note' => 'nullable|string',
            'mr_file' => 'nullable|file|max:5120',
        ]);

        $filePath = $this->mr_file?->store('medical-records', 'public');

        MedicalRecord::create([
            'customer_id' => $this->viewCustomerId,
            'recorded_by' => auth()->id(),
            'title' => $this->mr_title,
            'type' => $this->mr_type,
            'details' => $this->mr_details,
            'record_date' => $this->mr_date,
            'file_path' => $filePath,
            'note' => $this->mr_note,
        ]);

        $this->mrModal = false;
        $this->success('Medical record added.');
        $this->reset(['mr_title', 'mr_type', 'mr_details', 'mr_date', 'mr_note', 'mr_file']);
    }

    public function deleteMedicalRecord($id)
    {
        if (! $this->canEditMedicalRecords()) {
            $this->error('Only a pharmacist or admin can delete medical records.');
            return;
        }

        MedicalRecord::findOrFail($id)->delete();
        $this->success('Medical record deleted.');
    }

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'registered_by_name', 'label' => 'Registered By'],
        ];

        $isPromoter = $this->isPromoter();

        // Promoters see only the customers they registered, and none of the
        // clinical or financial history attached to them.
        $viewCustomer = null;

        if ($this->viewCustomerId) {
            $relations = [];

            if (! $isPromoter) {
                $relations = [
                    'sales' => fn($q) => $q->latest()->limit(10),
                    'orders' => fn($q) => $q->with('items.product')->latest()->limit(10),
                    'debts' => fn($q) => $q->whereIn('status', ['unpaid', 'partial']),
                    'appointments' => fn($q) => $q->with('staff')->latest()->limit(5),
                ];

                // Not merely hidden — never loaded for staff without clinical access.
                if ($this->canViewMedicalRecords()) {
                    $relations['medicalRecords'] = fn($q) => $q->latest();
                    $relations[] = 'medicalRecords.recorder';
                }
            }

            $viewCustomer = Customer::with($relations)
                ->when($isPromoter, fn($q) => $q->where('registered_by', auth()->id()))
                ->find($this->viewCustomerId);
        }

        return view('livewire.customers.index', [
            'headers' => $headers,
            'isPromoter' => $isPromoter,
            'canViewRecords' => $this->canViewMedicalRecords(),
            'canEditRecords' => $this->canEditMedicalRecords(),
            'customers' => Customer::with('registeredBy')
                ->when($isPromoter, fn($q) => $q->where('registered_by', auth()->id()))
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->latest()->paginate(20),
            'viewCustomer' => $viewCustomer,
        ]);
    }
}
