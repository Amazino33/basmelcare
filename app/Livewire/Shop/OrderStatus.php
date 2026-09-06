<?php

namespace App\Livewire\Shop;

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Where an order has got to, for the person who placed it.
 *
 * One page rather than two. The old confirmation page said "Order Placed!"
 * and then went stale - a customer who came back an hour later saw the same
 * screen and had no way of knowing whether anybody had picked it up. This is
 * the same page a minute after checkout and a day after checkout; only what
 * it says changes.
 *
 * Reached by the order's token, never by its id. Anyone holding the link can
 * read the order, which is the point: a guest has no account to log into, and
 * a link they can keep is the only thing that works for them. The id-addressed
 * pages this replaces let anyone read anyone's order by counting upwards.
 */
#[Layout('layouts.public')]
class OrderStatus extends Component
{
    public Order $order;

    /** True only on the redirect straight out of checkout. */
    public bool $justPlaced = false;

    public function mount(Order $order): void
    {
        $this->order = $order;
        $this->justPlaced = request()->boolean('placed');
    }

    public function render()
    {
        // Polled rather than pushed, which is the pattern everywhere else
        // here. A customer watching this page sees the pharmacy pick the
        // order up and send it out without having to reload.
        return view('livewire.shop.order-status', [
            'order' => $this->order->fresh(['items.product']),
        ]);
    }
}
