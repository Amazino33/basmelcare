<?php

namespace App\Livewire\Shop;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

#[Layout('layouts.public')]
class Checkout extends Component
{
    use Toast, WithFileUploads;

    public string $checkout_mode = 'guest';
    public bool $isLoggedIn = false;

    // Guest fields
    public string $guest_name = '';
    public string $guest_email = '';
    public string $guest_phone = '';

    // Login fields
    public string $login_email = '';
    public string $login_password = '';

    // Shared fields
    public string $fulfillment_type = 'delivery';
    public string $delivery_address = '';
    public string $delivery_phone = '';
    public string $payment_method = 'paystack';
    public string $note = '';
    public $prescription = null;

    public function mount()
    {
        $customer = Auth::guard('customer')->user();
        if ($customer) {
            $this->isLoggedIn = true;
            $this->checkout_mode = 'account';
            $this->delivery_address = $customer->address ?? '';
            $this->delivery_phone = $customer->phone ?? '';
        }
    }

    public function loginAndCheckout()
    {
        $this->validate([
            'login_email' => 'required|string',
            'login_password' => 'required|string',
        ]);

        $customer = Customer::where('email', $this->login_email)
            ->orWhere('phone', $this->login_email)
            ->first();

        if ($customer && Auth::guard('customer')->attempt(['email' => $customer->email, 'password' => $this->login_password], true)) {
            $this->isLoggedIn = true;
            $this->checkout_mode = 'account';
            $this->delivery_address = $customer->address ?? '';
            $this->delivery_phone = $customer->phone ?? '';
            $this->success('Signed in! Continue checkout.');
        } else {
            $this->addError('login_password', 'Invalid credentials.');
        }
    }

    public function placeOrder()
    {
        $cart = new CartService();

        if (count($cart->get()) === 0) {
            $this->error('Cart is empty.');
            return;
        }

        $rules = [
            'fulfillment_type' => 'required|in:delivery,pickup',
            'payment_method' => 'required|in:paystack,pay_on_delivery',
            'note' => 'nullable|string|max:500',
        ];

        if ($this->fulfillment_type === 'delivery') {
            $rules['delivery_address'] = 'required|string|max:500';
            $rules['delivery_phone'] = 'required|string|max:20';
        }

        if ($this->checkout_mode === 'guest') {
            $rules['guest_name'] = 'required|string|max:255';
            $rules['guest_email'] = 'nullable|email|max:255';
            $rules['guest_phone'] = 'required|string|max:20';
        }

        if ($cart->requiresPrescription()) {
            $rules['prescription'] = 'required|file|max:5120';
        }

        $this->validate($rules);

        $customer = Auth::guard('customer')->user();
        $deliveryFee = $this->fulfillment_type === 'delivery' ? 1500 : 0;
        $subtotal = $cart->subtotal();
        $prescriptionPath = $this->prescription?->store('prescriptions', 'public');

        $order = DB::transaction(function () use ($cart, $customer, $subtotal, $deliveryFee, $prescriptionPath) {
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'customer_id' => $customer?->id,
                'guest_name' => $this->checkout_mode === 'guest' ? $this->guest_name : null,
                'guest_email' => $this->checkout_mode === 'guest' ? $this->guest_email : null,
                'guest_phone' => $this->checkout_mode === 'guest' ? $this->guest_phone : null,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $subtotal + $deliveryFee,
                'fulfillment_type' => $this->fulfillment_type,
                'payment_method' => $this->payment_method,
                'payment_status' => 'pending',
                'status' => 'pending',
                'delivery_address' => $this->fulfillment_type === 'delivery' ? $this->delivery_address : null,
                'delivery_phone' => $this->fulfillment_type === 'delivery' ? $this->delivery_phone : ($this->checkout_mode === 'guest' ? $this->guest_phone : $customer?->phone),
                'note' => $this->note,
                'prescription_path' => $prescriptionPath,
            ]);

            foreach ($cart->get() as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'subtotal' => $item['price'] * $item['quantity'],
                ]);
            }

            return $order;
        });

        $cart->clear();

        if ($this->payment_method === 'paystack') {
            $this->redirect('/order/' . $order->id . '/pay');
        } else {
            $this->redirect('/order/' . $order->id . '/confirmation');
        }
    }

    public function render()
    {
        $cart = new CartService();
        $deliveryFee = $this->fulfillment_type === 'delivery' ? 1500 : 0;

        return view('livewire.shop.checkout', [
            'items' => $cart->get(),
            'subtotal' => $cart->subtotal(),
            'deliveryFee' => $deliveryFee,
            'total' => $cart->subtotal() + $deliveryFee,
            'itemCount' => $cart->count(),
            'requiresPrescription' => $cart->requiresPrescription(),
        ]);
    }
}
