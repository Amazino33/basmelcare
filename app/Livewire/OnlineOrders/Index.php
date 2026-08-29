<?php

namespace App\Livewire\OnlineOrders;

use App\Models\Batch;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public string $filter = 'new';
    public ?int $viewOrderId = null;
    public bool $detailsModal = false;
    public int $lastNewCount = 0;

    // Dispatch
    public ?int $dispatchingOrderId = null;
    public bool $dispatchModal = false;
    public string $dispatch_name = '';
    public string $dispatch_phone = '';

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
        $order = Order::with('items')->findOrFail($orderId);

        if ($order->claimed_by !== auth()->id()) {
            $this->error('You can only update orders you claimed.');
            return;
        }

        // Check stock availability before deducting
        foreach ($order->items as $item) {
            $product = Product::with('batches')->find($item->product_id);
            $available = $product->batches->sum('quantity');
            if ($available < $item->quantity) {
                $this->error('Not enough stock for ' . $product->name . ' (need ' . $item->quantity . ', have ' . $available . ').');
                return;
            }
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $product = Product::with(['batches' => fn($q) => $q->where('quantity', '>', 0)->orderBy('expiry_date')])->find($item->product_id);
                $remaining = $item->quantity;

                foreach ($product->batches as $batch) {
                    if ($remaining <= 0) break;
                    $deduct = min($remaining, $batch->quantity);
                    $batch->decrement('quantity', $deduct);
                    StockMovement::create([
                        'batch_id' => $batch->id,
                        'quantity' => -$deduct,
                        'type' => 'sale',
                        'reference' => $order->order_number,
                        'user_id' => auth()->id(),
                    ]);
                    $remaining -= $deduct;
                }
            }

            $order->update(['status' => 'ready']);
        });

        $this->success('Order marked as ready. Stock deducted.');
    }

    public function completeOrder($orderId)
    {
        $order = Order::findOrFail($orderId);

        if ($order->claimed_by !== auth()->id()) {
            $this->error('You can only complete orders you claimed.');
            return;
        }

        if (is_null($order->cashier_verified_at)) {
            $this->error('Cashier has not verified this order yet. Bring it to the cashier first.');
            return;
        }

        if ($order->payment_status !== 'paid') {
            $this->error('Payment has not been collected. Ensure cashier has processed payment.');
            return;
        }

        $order->update(['status' => 'completed']);
        $this->success('Order ' . $order->order_number . ' completed.');
    }

    public function openDispatch(int $orderId): void
    {
        $this->dispatchingOrderId = $orderId;
        $this->dispatch_name  = '';
        $this->dispatch_phone = '';
        $this->dispatchModal  = true;
    }

    public function dispatchOrder(): void
    {
        $order = Order::findOrFail($this->dispatchingOrderId);

        if ($order->claimed_by !== auth()->id()) {
            $this->error('You can only dispatch orders you claimed.');
            return;
        }

        if (is_null($order->cashier_verified_at)) {
            $this->error('Cashier has not approved this order for dispatch yet.');
            return;
        }

        $this->validate([
            'dispatch_name'  => 'required|string|max:100',
            'dispatch_phone' => 'nullable|string|max:20',
        ]);

        $order->update([
            'delivery_person_name'  => $this->dispatch_name,
            'delivery_person_phone' => $this->dispatch_phone ?: null,
            'dispatched_at'         => now(),
            'status'                => 'dispatched',
        ]);

        $this->dispatchModal = false;
        $this->success('Order ' . $order->order_number . ' dispatched to ' . $this->dispatch_name . '.');
    }

    public function cancelOrder($orderId)
    {
        $order = Order::with('items')->findOrFail($orderId);

        if ($order->claimed_by && $order->claimed_by !== auth()->id() && !auth()->user()->hasRole('admin')) {
            $this->error('You can only cancel orders you claimed.');
            return;
        }

        DB::transaction(function () use ($order) {
            // Restore stock if it was deducted (status was ready)
            if ($order->status === 'ready') {
                foreach ($order->items as $item) {
                    $product = Product::with(['batches' => fn($q) => $q->orderBy('expiry_date')])->find($item->product_id);
                    $batch = $product->batches->first();
                    if ($batch) {
                        $batch->increment('quantity', $item->quantity);
                        StockMovement::create([
                            'batch_id' => $batch->id,
                            'quantity' => $item->quantity,
                            'type' => 'return',
                            'reference' => $order->order_number . ' (cancelled)',
                            'user_id' => auth()->id(),
                        ]);
                    }
                }
            }

            // Cover was spent when the order was placed, so cancelling has to
            // give it back - otherwise a customer loses the month's allowance
            // to an order the pharmacy could not fill.
            if ($order->insurance_covered > 0 && $order->insurance_subscription_id) {
                \App\Models\InsuranceSubscription::with('plan')
                    ->find($order->insurance_subscription_id)
                    ?->refund((float) $order->insurance_covered);

                \App\Models\InsuranceClaim::where('order_id', $order->id)->delete();

                $order->update([
                    'insurance_covered' => 0,
                    'total_amount'      => (float) $order->subtotal + (float) $order->delivery_fee,
                ]);
            }

            $order->update(['status' => 'cancelled']);
        });

        $this->success('Order cancelled.' . ($order->status === 'ready' ? ' Stock restored.' : ''));
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
            ? Order::with('customer', 'claimedByUser', 'items.product', 'verifiedBy')->find($this->viewOrderId)
            : null;

        $dispatchingOrder = $this->dispatchingOrderId
            ? Order::with('customer', 'items.product')->find($this->dispatchingOrderId)
            : null;

        $deliveryUsers = \App\Models\User::whereJsonContains('role', 'delivery')->get(['id', 'name', 'phone']);

        return view('livewire.online-orders.index', [
            'orders'           => $orders,
            'newCount'         => $newCount,
            'viewOrder'        => $viewOrder,
            'dispatchingOrder' => $dispatchingOrder,
            'deliveryUsers'    => $deliveryUsers,
        ]);
    }
}
