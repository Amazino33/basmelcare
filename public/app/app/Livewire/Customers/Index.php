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

    public function create()
    {
        $this->reset(['name', 'type', 'phone', 'email', 'address', 'notes', 'customerId']);
        $this->modal = true;
    }

    public function save()
    {
        $user = auth()->user();
        $isPromoter = in_array('promoter', $user->role ?? [])
            && !array_intersect($user->role ?? [], ['admin', 'pharmacist', 'branch_manager', 'sales', 'cashier']);

        $this->validate([
            'name'    => 'required|string|max:255',
            'type'    => 'required|in:retail,wholesale',
            'phone'   => ($isPromoter && !$this->customerId) ? 'required|string|max:20' : 'nullable|string|max:20',
            'email'   => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'notes'   => 'nullable|string',
        ]);

        $data = [
            'name'    => $this->name,
            'type'    => $this->type,
            'phone'   => $this->phone,
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

        $customer = Customer::find($this->pendingCommissionCustomerId);
        if (!$customer) {
            $this->otpModal = false;
            $this->error('Customer not found.');
            return;
        }

        if (!$customer->verifyOtp(trim($this->otpCode))) {
            $this->otpError = 'Incorrect or expired OTP. Try again or tap Resend.';
            return;
        }

        $customer->clearOtp();

        $amount = (float) AppSetting::get('commission_amount', 100);
        ReferralCommission::create([
            'user_id'     => auth()->id(),
            'customer_id' => $customer->id,
            'amount'      => $amount,
        ]);

        $this->otpModal = false;
        $this->pendingCommissionCustomerId = null;
        $this->pendingPhone = '';
        $this->otpCode  = '';
        $this->otpError = '';

        $symbol = AppSetting::get('currency_symbol', '₦');
        $this->success("Phone verified! {$symbol}" . number_format($amount, 2) . ' commission earned.');
    }

    public function resendOtp(): void
    {
        $customer = Customer::find($this->pendingCommissionCustomerId);
        if (!$customer || !$customer->phone) {
            $this->otpError = 'Cannot resend — customer or phone not found.';
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
        $customer = Customer::find($this->pendingCommissionCustomerId);
        $customer?->clearOtp();

        $this->otpModal = false;
        $this->pendingCommissionCustomerId = null;
        $this->pendingPhone = '';
        $this->otpCode  = '';
        $this->otpError = '';
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
        Customer::findOrFail($id)->delete();
        $this->success('Customer deleted.');
    }

    public function viewProfile($id)
    {
        $this->viewCustomerId = $id;
        $this->profileDrawer = true;
    }

    public function openMedicalRecord()
    {
        $this->reset(['mr_title', 'mr_type', 'mr_details', 'mr_date', 'mr_note', 'mr_file']);
        $this->mr_date = now()->format('Y-m-d');
        $this->mrModal = true;
    }

    public function saveMedicalRecord()
    {
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

        $viewCustomer = $this->viewCustomerId
            ? Customer::with([
                'medicalRecords' => fn($q) => $q->latest(),
                'medicalRecords.recorder',
                'sales' => fn($q) => $q->latest()->limit(10),
                'orders' => fn($q) => $q->with('items.product')->latest()->limit(10),
                'debts' => fn($q) => $q->whereIn('status', ['unpaid', 'partial']),
                'appointments' => fn($q) => $q->with('staff')->latest()->limit(5),
            ])->find($this->viewCustomerId)
            : null;

        return view('livewire.customers.index', [
            'headers' => $headers,
            'customers' => Customer::with('registeredBy')
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->latest()->paginate(20),
            'viewCustomer' => $viewCustomer,
        ]);
    }
}
