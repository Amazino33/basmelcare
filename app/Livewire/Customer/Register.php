<?php

namespace App\Livewire\Customer;

use App\Models\Customer;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

#[Layout('layouts.public')]
class Register extends Component
{
    use Toast;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:customers,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $customer = Customer::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'password' => $this->password,
            'type' => 'retail',
        ]);

        $otp = $customer->generateOtp();

        $whatsapp = new WhatsAppService();
        $whatsapp->send($this->phone, "Welcome to BasmelCare! Your verification code is: {$otp}\n\nThis code expires in 10 minutes.");

        Auth::guard('customer')->login($customer, true);
        $this->redirect('/account');
    }

    public function render()
    {
        return view('livewire.customer.register');
    }
}
