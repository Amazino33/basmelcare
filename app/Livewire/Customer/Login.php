<?php

namespace App\Livewire\Customer;

use App\Models\Customer;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

#[Layout('layouts.public')]
class Login extends Component
{
    use Toast;

    public string $identifier = '';
    public string $otp = '';
    public bool $otpSent = false;
    public bool $usePassword = false;
    public string $password = '';

    private function findCustomer(): ?Customer
    {
        $input = trim($this->identifier);

        return Customer::where('email', $input)
            ->orWhere('phone', $input)
            ->orWhere('phone', '0' . ltrim($input, '234'))
            ->orWhere('phone', '234' . ltrim($input, '0'))
            ->first();
    }

    public function sendOtp()
    {
        $this->validate(['identifier' => 'required|string']);

        $customer = $this->findCustomer();

        if (!$customer) {
            $this->addError('identifier', 'No account found with this email or phone.');
            return;
        }

        $otp = $customer->generateOtp();

        if ($customer->phone) {
            $whatsapp = new WhatsAppService();
            $whatsapp->send($customer->phone, "Your BasmelCare login code is: {$otp}\n\nThis code expires in 10 minutes.");
        }

        $this->otpSent = true;
        $this->success('OTP sent to your WhatsApp!');
    }

    public function verifyOtp()
    {
        $this->validate(['otp' => 'required|string|size:6']);

        $customer = $this->findCustomer();

        if (!$customer || !$customer->verifyOtp($this->otp)) {
            $this->addError('otp', 'Invalid or expired OTP.');
            return;
        }

        $customer->clearOtp();

        if (!$customer->email_verified_at) {
            $customer->update(['email_verified_at' => now()]);
        }

        Auth::guard('customer')->login($customer, true);
        $this->redirect('/account');
    }

    public function loginWithPassword()
    {
        $this->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $customer = $this->findCustomer();

        if ($customer && Auth::guard('customer')->attempt(['email' => $customer->email, 'password' => $this->password], true)) {
            session()->regenerate();
            $this->redirect('/account');
        } else {
            $this->addError('password', 'Invalid credentials.');
        }
    }

    public function render()
    {
        return view('livewire.customer.login');
    }
}
