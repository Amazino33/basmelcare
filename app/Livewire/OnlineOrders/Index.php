<?php

namespace App\Livewire\OnlineOrders;

use App\Models\Order;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public string $filter = 'new';
    public ?int $viewOrderId = null;
    public bool $detailsModal = false;
    public int $lastNewCount = 0;

    public function mount()
    {
        $this->lastNewCount = Order::whereNull('claimed_by')->whereIn('status', ['pending', 'processing'])->count();
    }

    public function claimOrder($orderId)
    {
        $order = Order::findOrFail($orderId);

        if ($order->claimed_by && $order->claimed_by !== auth()->id()) {
            $this->error('This order has already been claimed by another staff member.');
            return;
        }

        $order->update([
            'claimed_by' => auth()->id(),
            'claimed_at' => now(),
            'status' => 'processing',
        ]);

        $this->success('Order ' . $order->order_number . ' claimed. You can now process it.');
    }

    public function markReady($orderId)
    {
        $order = Order::findOrFail($orderId);

        if ($order->claimed_by !== auth()->id()) {
            $this->error('You can only update orders you claimed.');
            return;
        }

        $order->update(['status' => 'ready']);
        $this->success('Order marked as ready for ' . ($order->fulfillment_type === 'delivery' ? 'delivery' : 'pickup') . '.');
    }

    public function completeOrder($orderId)
    {
        $order = Order::findOrFail($orderId);

        if ($order->claimed_by !== auth()->id()) {
            $this->error('You can only complete orders you claimed.');
            return;
        }

        $order->update(['status' => 'completed']);
        $this->success('Order ' . $order->order_number . ' completed.');
    }

    public function cancelOrder($orderId)
    {
        $order = Order::findOrFail($orderId);

        if ($order->claimed_by && $order->claimed_by !== auth()->id() && auth()->user()->role !== 'admin') {
            $this->error('You can only cancel orders you claimed.');
            return;
        }

        $order->update(['status' => 'cancelled']);
        $this->success('Order cancelled.');
    }

    public function viewDetails($orderId)
    {
        $this->viewOrderId = $orderId;
        $this->detailsModal = true;
    }

    public function render()
    {
        $query = Order::with('customer', 'claimedByUser', 'items.product');

        if ($this->filter === 'new') {
            $query->whereNull('claimed_by')->whereIn('status', ['pending', 'processing']);
        } elseif ($this->filter === 'mine') {
            $query->where('claimed_by', auth()->id())->whereNotIn('status', ['completed', 'cancelled']);
        } elseif ($this->filter === 'all') {
            // show all
        } else {
            $query->where('status', $this->filter);
        }

        $orders = $query->latest()->get();

        $newCount = Order::whereNull('claimed_by')->whereIn('status', ['pending', 'processing'])->count();
        if ($newCount > $this->lastNewCount && $this->lastNewCount > 0) {
            $this->dispatch('new-online-order');
            $this->success('New online order received!');
        }
        $this->lastNewCount = $newCount;

        $viewOrder = $this->viewOrderId
            ? Order::with('customer', 'claimedByUser', 'items.product')->find($this->viewOrderId)
            : null;

        return view('livewire.online-orders.index', [
            'orders' => $orders,
            'newCount' => $newCount,
            'viewOrder' => $viewOrder,
        ]);
    }
}
