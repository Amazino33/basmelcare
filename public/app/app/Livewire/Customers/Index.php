<?php

namespace App\Livewire\Customers;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\MedicalRecord;
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
            $sent = $this->sendOtp($customer->phone, $otp);

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

        if (ReferralCommission::where('customer_id', $customer->id)->exists()) {
            $this->otpModal = false;
            $this->resetPendingOtp();
            $this->error('A commission has already been recorded for this customer.');
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

        $amount = (float) AppSetting::get('commission_amount', 100);

        try {
            ReferralCommission::create([
                'user_id'     => auth()->id(),
                'customer_id' => $customer->id,
                'amount'      => $amount,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique (user_id, customer_id) — a concurrent double-submit.
            $this->otpModal = false;
            $this->resetPendingOtp();
            $this->error('A commission has already been recorded for this customer.');
            return;
        }

        $this->otpModal = false;
        $this->resetPendingOtp();

        $symbol = AppSetting::get('currency_symbol', '₦');
        $this->success("Phone verified! {$symbol}" . number_format($amount, 2) . ' commission earned.');
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

    private function sendOtp(string $phone, string $otp): bool
    {
        $name    = AppSetting::get('pharmacy_name', 'BasmelCare');
        $message = "Your {$name} registration code is: *{$otp}*. Valid for 10 minutes.";
        return app(WhatsAppService::class)->send($phone, $message);
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
        if ($this->isPromoter()) {
            $this->error('Promoters cannot access medical records.');
            return;
        }

        $this->reset(['mr_title', 'mr_type', 'mr_details', 'mr_date', 'mr_note', 'mr_file']);
        $this->mr_date = now()->format('Y-m-d');
        $this->mrModal = true;
    }

    public function saveMedicalRecord()
    {
        if ($this->isPromoter()) {
            $this->error('Promoters cannot access medical records.');
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
        if ($this->isPromoter()) {
            $this->error('Promoters cannot access medical records.');
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
            $relations = $isPromoter
                ? []
                : [
                    'medicalRecords' => fn($q) => $q->latest(),
                    'medicalRecords.recorder',
                    'sales' => fn($q) => $q->latest()->limit(10),
                    'orders' => fn($q) => $q->with('items.product')->latest()->limit(10),
                    'debts' => fn($q) => $q->whereIn('status', ['unpaid', 'partial']),
                    'appointments' => fn($q) => $q->with('staff')->latest()->limit(5),
                ];

            $viewCustomer = Customer::with($relations)
                ->when($isPromoter, fn($q) => $q->where('registered_by', auth()->id()))
                ->find($this->viewCustomerId);
        }

        return view('livewire.customers.index', [
            'headers' => $headers,
            'isPromoter' => $isPromoter,
            'customers' => Customer::with('registeredBy')
                ->when($isPromoter, fn($q) => $q->where('registered_by', auth()->id()))
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->latest()->paginate(20),
            'viewCustomer' => $viewCustomer,
        ]);
    }
}
