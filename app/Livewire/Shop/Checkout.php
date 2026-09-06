<?php

namespace App\Livewire\Shop;

use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\InsuranceSubscription;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\CartService;
use App\Services\InsuranceCover;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

    /**
     * Which area this is going to.
     *
     * The fee follows from it, so the customer sees what delivery costs while
     * they are still choosing rather than at the end.
     */
    public ?int $delivery_zone_id = null;
    public string $payment_method = 'paystack';
    public string $note = '';
    public $prescription = null;

    /**
     * The zone list, held for the request.
     *
     * Protected, so Livewire never sends it to the browser or takes it back:
     * what the shop delivers to is not something the page may tell the server.
     */
    protected $zones = null;

    public function mount()
    {
        // Nothing to choose from means the shop is collection only. Offering
        // delivery and then refusing it at the last step wastes the
        // customer's time.
        if (! DeliveryZone::deliveryOffered()) {
            $this->fulfillment_type = 'pickup';
        }

        $zones = $this->zones();

        // One area serves everybody, so there is no choice to make.
        if ($zones->count() === 1) {
            $this->delivery_zone_id = $zones->first()->id;
        }

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

    /** The areas a customer may choose from. */
    public function zones()
    {
        // Held for the request: the summary, the fee, the validation rule and
        // the select all ask for this list, and it is the same list each time.
        return $this->zones ??= DeliveryZone::active()->get();
    }

    /**
     * What delivery costs on this order.
     *
     * One definition, used by the summary the customer reads and by the order
     * that is written. Working it out separately in each place is how the
     * figure on screen and the figure charged drift apart.
     */
    public function deliveryFee(): float
    {
        if ($this->fulfillment_type !== 'delivery') {
            return 0.0;
        }

        $zone = $this->zone();

        return $zone ? $zone->feeFor((float) (new CartService())->subtotal()) : 0.0;
    }

    /** The chosen zone, if it is one the shop still delivers to. */
    public function zone(): ?DeliveryZone
    {
        if (! $this->delivery_zone_id) {
            return null;
        }

        return $this->zones()->firstWhere('id', $this->delivery_zone_id);
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

            // Checked against the active zones rather than the table, so an
            // area the pharmacy has stopped serving cannot be ordered to by
            // anyone holding the page open from before.
            $rules['delivery_zone_id'] = [
                'required',
                Rule::in($this->zones()->pluck('id')->all()),
            ];
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
        $zone = $this->zone();
        $deliveryFee = $this->deliveryFee();
        $subtotal = $cart->subtotal();
        $prescriptionPath = $this->prescription?->store('prescriptions', 'public');

        $coverLines = $this->coverLines();

        $order = DB::transaction(function () use ($cart, $customer, $subtotal, $deliveryFee, $zone, $prescriptionPath, $coverLines) {
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
                'delivery_zone_id' => $this->fulfillment_type === 'delivery' ? $zone?->id : null,
                // The area is copied onto the order, not just pointed at, so
                // renaming a zone never rewrites what an old order said.
                'delivery_area' => $this->fulfillment_type === 'delivery' ? $zone?->name : null,
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
            $this->redirect(route('order.pay', $order->public_token));
        } else {
            // ?placed is only what makes the page say "Order placed" rather
            // than reporting the stage; it grants no access of its own.
            $this->redirect(route('order.status', $order->public_token) . '?placed=1');
        }
    }

    public function render()
    {
        $cart = new CartService();
        $deliveryFee = $this->deliveryFee();

        return view('livewire.shop.checkout', [
            'items' => $cart->get(),
            'subtotal' => $cart->subtotal(),
            'deliveryFee' => $deliveryFee,
            'total' => $cart->subtotal() + $deliveryFee,
            'itemCount' => $cart->count(),
            'requiresPrescription' => $cart->requiresPrescription(),
            'coverQuote' => $this->coverQuote(),
            'zones' => $this->zones(),
            'chosenZone' => $this->zone(),
            'deliveryOffered' => DeliveryZone::deliveryOffered(),
            'freeOver' => DeliveryZone::freeOver(),
        ]);
    }
}
