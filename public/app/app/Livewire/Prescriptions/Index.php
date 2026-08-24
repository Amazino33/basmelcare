<?php

namespace App\Livewire\Prescriptions;

use App\Models\Order;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

/**
 * The pharmacist's queue of online orders carrying a prescription.
 *
 * The customer uploads one at checkout; until now nobody qualified opened it.
 * An order sits here until a pharmacist approves or rejects it, and staff
 * cannot mark it ready in the meantime.
 *
 * Approving is restricted to the pharmacist alone - not admin - so the record
 * of who authorised a dispensing means what it says. The cost is that orders
 * wait when no pharmacist is on duty, which is the intended trade.
 */
class Index extends Component
{
    use Toast, WithPagination;

    /** waiting | reviewed */
    #[Url]
    public string $filter = 'waiting';

    public ?int $viewOrderId = null;

    public bool $rejecting = false;
    public string $rejectionNote = '';

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    private function canReview(): bool
    {
        return in_array('pharmacist', auth()->user()->role ?? [], true);
    }

    public function viewOrder(int $orderId): void
    {
        $this->viewOrderId = $orderId;
        $this->rejecting = false;
        $this->rejectionNote = '';
        $this->resetValidation();
    }

    public function closeOrder(): void
    {
        $this->viewOrderId = null;
        $this->rejecting = false;
        $this->rejectionNote = '';
    }

    public function approve(int $orderId): void
    {
        // Checked here, not only in the route: this is the action that puts a
        // name against a dispensing decision.
        if (! $this->canReview()) {
            $this->error('Only a pharmacist can approve a prescription.');

            return;
        }

        $order = Order::findOrFail($orderId);

        if (! $order->awaitingPrescriptionReview()) {
            $this->error('This order has already been reviewed.');

            return;
        }

        $order->update([
            'prescription_status'      => 'approved',
            'prescription_reviewed_by' => auth()->id(),
            'prescription_reviewed_at' => now(),
            'prescription_note'        => null,
        ]);

        $this->closeOrder();
        $this->success('Prescription approved. The order can now be prepared.');
    }

    public function startReject(): void
    {
        $this->rejecting = true;
    }

    public function reject(int $orderId): void
    {
        if (! $this->canReview()) {
            $this->error('Only a pharmacist can reject a prescription.');

            return;
        }

        // A refusal without a reason is not a decision anyone can act on -
        // the customer has to be told something, and the next person needs
        // to know what was wrong.
        $this->validate([
            'rejectionNote' => 'required|string|min:5|max:500',
        ], [], ['rejectionNote' => 'reason']);

        $order = Order::findOrFail($orderId);

        if (! $order->awaitingPrescriptionReview()) {
            $this->error('This order has already been reviewed.');

            return;
        }

        $order->update([
            'prescription_status'      => 'rejected',
            'prescription_reviewed_by' => auth()->id(),
            'prescription_reviewed_at' => now(),
            'prescription_note'        => trim($this->rejectionNote),
        ]);

        $this->closeOrder();
        $this->success('Prescription rejected. The order cannot be dispensed.');
    }

    public function render()
    {
        $orders = Order::query()
            ->with(['customer', 'items.product', 'prescriptionReviewer'])
            ->whereNotNull('prescription_status')
            ->when(
                $this->filter === 'waiting',
                fn ($q) => $q->where('prescription_status', 'pending'),
                fn ($q) => $q->whereIn('prescription_status', ['approved', 'rejected'])
            )
            ->orderBy('created_at', $this->filter === 'waiting' ? 'asc' : 'desc')
            ->paginate(15);

        return view('livewire.prescriptions.index', [
            'orders'       => $orders,
            'waitingCount' => Order::where('prescription_status', 'pending')->count(),
            'viewOrder'    => $this->viewOrderId
                ? Order::with(['customer', 'items.product', 'prescriptionReviewer'])->find($this->viewOrderId)
                : null,
            'canReview'    => $this->canReview(),
        ]);
    }
}
