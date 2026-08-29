<?php

namespace App\Livewire\Shop;

use App\Models\Customer;
use App\Models\InsuranceSubscription;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\CartService;
use App\Services\InsuranceCover;
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

    /**
     * The cover this customer could draw on for what is in the basket.
     *
     * Only for a signed-in customer: cover belongs to a named person, and a
     * guest checkout has nobody to charge it to. Returns null when there is
     * nothing to say, so the page is unchanged for everybody else.
     */
    public function coverQuote(): ?array
    {
        if (! InsuranceCover::enabled()) {
            return null;
        }

        $customer = Auth::guard('customer')->user();

        if (! $customer) {
            return null;
        }

        $subscription = InsuranceSubscription::forCustomer($customer->id);

        if (! $subscription) {
            return null;
        }

        return app(InsuranceCover::class)->quote($subscription, $this->coverLines());
    }

    /**
     * The basket, priced for the cover calculation.
     *
     * Cost comes from what the product's stock is currently worth rather than
     * from the order line, because an online order has no batch allocated to
     * it until the pharmacy picks it. Booking nothing would flatter the cover
     * report into showing free medicine as costless.
     */
    private function coverLines(): array
    {
        $cart = new CartService();

        $products = Product::with(['batches' => fn ($q) => $q->where('quantity', '>', 0)])
            ->whereIn('id', collect($cart->get())->pluck('product_id'))
            ->get()
            ->keyBy('id');

        return collect($cart->get())->map(function ($item) use ($products) {
            $product = $products->get($item['product_id']);
            $unitCost = (float) ($product?->batches->max('cost_price') ?? 0);

            return [
                'product'  => $product,
                'subtotal' => (float) $item['price'] * (int) $item['quantity'],
                'cost'     => $unitCost * (int) $item['quantity'],
            ];
        })->all();
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

        $coverLines = $this->coverLines();

        $order = DB::transaction(function () use ($cart, $customer, $subtotal, $deliveryFee, $prescriptionPath, $coverLines) {
            // Cover is spent as the order is placed, not when it is paid for.
            // Two orders minutes apart would otherwise each be promised the
            // same allowance, and the second customer would be undercharged
            // with nothing left to draw on. Cancelling gives it back.
            $covered = 0.0;
            $subscription = ($customer && InsuranceCover::enabled())
                ? InsuranceSubscription::forCustomer($customer->id)
                : null;

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'customer_id' => $customer?->id,
                'guest_name' => $this->checkout_mode === 'guest' ? $this->guest_name : null,
                'guest_email' => $this->checkout_mode === 'guest' ? $this->guest_email : null,
                'guest_phone' => $this->checkout_mode === 'guest' ? $this->guest_phone : null,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                // Filled in below, once the cover has actually been taken.
                'total_amount' => $subtotal + $deliveryFee,
                'fulfillment_type' => $this->fulfillment_type,
                'payment_method' => $this->payment_method,
                'payment_status' => 'pending',
                'status' => 'pending',
                'delivery_address' => $this->fulfillment_type === 'delivery' ? $this->delivery_address : null,
                'delivery_phone' => $this->fulfillment_type === 'delivery' ? $this->delivery_phone : ($this->checkout_mode === 'guest' ? $this->guest_phone : $customer?->phone),
                'note' => $this->note,
                'prescription_path' => $prescriptionPath,
                // A prescription was required, so a pharmacist has to see it
                // before this order can be prepared. Null where none is
                // needed, which is not the same as pending.
                'prescription_status' => $prescriptionPath ? 'pending' : null,
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

            if ($subscription && $subscription->isClaimable()) {
                $result  = app(InsuranceCover::class)->apply($subscription, $coverLines, orderId: $order->id);
                $covered = (float) $result['covered'];
            }

            if ($covered > 0) {
                // Delivery is a service, not medicine, so cover never touches
                // it - the customer pays the fee whatever their plan.
                $order->update([
                    'insurance_covered'         => $covered,
                    'insurance_subscription_id' => $subscription->id,
                    'total_amount'              => max(0, $subtotal - $covered) + $deliveryFee,
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
            'coverQuote' => $this->coverQuote(),
        ]);
    }
}
