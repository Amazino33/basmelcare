<?php

namespace App\Livewire\Shop;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Getting back to an order you have lost the link to.
 *
 * The tracking link is unguessable, which is what keeps one customer's order
 * out of the next customer's hands - and also what makes it useless the moment
 * a guest closes the tab. They have no account to log into and nothing to look
 * the order up by.
 *
 * So: the order number, which is on their receipt and in any message we sent
 * them, plus the phone number they gave. The number alone is guessable - they
 * run in sequence - so the phone is the half that actually proves anything.
 *
 * Which makes this an oracle if it is left open: someone with a list of phone
 * numbers could walk the order numbers and learn who buys what. Hence the
 * throttle, on the phone number rather than the order number, because the
 * phone is the thing being tested.
 */
#[Layout('layouts.public')]
class FindOrder extends Component
{
    public string $order_number = '';
    public string $phone = '';

    public function find()
    {
        $this->validate([
            'order_number' => 'required|string|max:40',
            'phone'        => 'required|string|max:20',
        ], [], [
            'order_number' => 'order number',
            'phone'        => 'phone number',
        ]);

        $normalised = Customer::normalisePhone($this->phone);

        $key = 'find-order:' . ($normalised ?: request()->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('order_number', 'Too many tries. Wait a few minutes and try again.');

            return null;
        }

        RateLimiter::hit($key, 900);

        $order = $this->match($normalised);

        if (! $order) {
            // The same message whether the order does not exist or the phone
            // does not match it, so this cannot be used to find out which
            // order numbers are real.
            $this->addError('order_number', 'We could not find an order with those details.');

            return null;
        }

        RateLimiter::clear($key);

        return $this->redirect(route('order.status', $order->public_token));
    }

    /**
     * The order, if the phone given is the one attached to it.
     *
     * A guest's number is on the order itself; a customer's is on their
     * record. Both are compared in normalised form, because people write the
     * same number as 0803..., 234803... and +234 803 ... on different days.
     */
    private function match(?string $normalised): ?Order
    {
        if (! $normalised) {
            return null;
        }

        $order = Order::with('customer')
            ->where('order_number', trim($this->order_number))
            ->first();

        if (! $order) {
            return null;
        }

        $onOrder = array_filter([
            Customer::normalisePhone($order->guest_phone),
            Customer::normalisePhone($order->delivery_phone),
            Customer::normalisePhone($order->customer?->phone),
        ]);

        return in_array($normalised, $onOrder, true) ? $order : null;
    }

    public function render()
    {
        return view('livewire.shop.find-order');
    }
}
