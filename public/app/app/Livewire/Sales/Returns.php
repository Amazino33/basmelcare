<?php

namespace App\Livewire\Sales;

use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

/**
 * Everything that came back or was requested for return.
 *
 * Sales staff trigger returns, creating a pending request. An auditor or
 * branch manager reviews and approves or rejects the return. Once approved,
 * stock is returned to the shelf, cash/credit is disbursed, and a receipt slip
 * is generated.
 */
class Returns extends Component
{
    use WithPagination;
    use Toast;

    #[Url]
    public string $search = '';

    /** all | cash | credit */
    #[Url]
    public string $methodFilter = 'all';

    /** all | pending | approved | rejected */
    #[Url]
    public string $statusFilter = 'all';

    #[Url]
    public string $period = 'month';

    public ?int $viewId = null;
    public bool $detailDrawer = false;

    public bool $rejectModal = false;
    public ?int $rejectReturnId = null;
    public string $rejectionReason = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedMethodFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedPeriod(): void { $this->resetPage(); }

    public function canApproveOrReject(): bool
    {
        return (bool) array_intersect(
            auth()->user()->role ?? [],
            ['auditor', 'branch_manager', 'admin']
        );
    }

    public function viewReturn(int $id): void
    {
        $this->viewId       = $id;
        $this->detailDrawer = true;
    }

    public function closeDetail(): void
    {
        $this->detailDrawer = false;
        $this->viewId       = null;
    }

    public function approveReturn(int $id): void
    {
        if (! $this->canApproveOrReject()) {
            $this->error('Only an auditor or branch manager can approve returns.');
            return;
        }

        $saleReturn = SaleReturn::with(['sale.customer', 'items.batch', 'items.product'])->findOrFail($id);

        if (! $saleReturn->isPending()) {
            $this->error('This return is not pending approval.');
            return;
        }

        try {
            $saleReturn->finalize(auth()->user());
            $this->success('Return RT-' . str_pad($saleReturn->id, 5, '0', STR_PAD_LEFT) . ' approved and processed.');
        } catch (\Throwable $e) {
            $this->error('Failed to approve return: ' . $e->getMessage());
        }
    }

    public function openRejectModal(int $id): void
    {
        if (! $this->canApproveOrReject()) {
            $this->error('Only an auditor or branch manager can reject returns.');
            return;
        }

        $this->rejectReturnId  = $id;
        $this->rejectionReason = '';
        $this->rejectModal     = true;
    }

    public function rejectReturn(): void
    {
        if (! $this->canApproveOrReject()) {
            $this->error('Only an auditor or branch manager can reject returns.');
            return;
        }

        if (! $this->rejectReturnId) {
            return;
        }

        $saleReturn = SaleReturn::findOrFail($this->rejectReturnId);

        if (! $saleReturn->isPending()) {
            $this->error('This return is not pending approval.');
            $this->rejectModal = false;
            return;
        }

        try {
            $saleReturn->reject(auth()->user(), $this->rejectionReason ?: null);
            $this->success('Return RT-' . str_pad($saleReturn->id, 5, '0', STR_PAD_LEFT) . ' rejected.');
            $this->rejectModal     = false;
            $this->rejectReturnId  = null;
            $this->rejectionReason = '';
        } catch (\Throwable $e) {
            $this->error('Failed to reject return: ' . $e->getMessage());
        }
    }

    private function range(): array
    {
        return match ($this->period) {
            'today' => [today()->startOfDay(), today()->endOfDay()],
            'week'  => [now()->startOfWeek(), now()->endOfWeek()],
            'year'  => [now()->startOfYear(), now()->endOfYear()],
            'all'   => [Carbon::create(2000), now()->endOfDay()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    public function render()
    {
        [$from, $to] = $this->range();

        $base = SaleReturn::with(['sale.customer', 'processor', 'approver', 'rejector', 'items.product'])
            ->whereBetween('created_at', [$from, $to])
            ->when($this->methodFilter !== 'all', fn ($q) => $q->where('refund_method', $this->methodFilter))
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn ($q) => $q
                ->where('id', $this->search)
                ->orWhereHas('sale', fn ($s) => $s->where('invoice_number', 'like', "%{$this->search}%"))
                ->orWhereHas('sale.customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%"))
                ->orWhereHas('items.product', fn ($p) => $p->where('name', 'like', "%{$this->search}%")));

        $approvedBase = (clone $base)->where('status', SaleReturn::STATUS_APPROVED);
        $total = (float) (clone $approvedBase)->sum('total_credit');
        $cash  = (float) (clone $approvedBase)->where('refund_method', SaleReturn::CASH)->sum('total_credit');

        $pendingBase = SaleReturn::where('status', SaleReturn::STATUS_PENDING);
        $pendingCount = (clone $pendingBase)->count();
        $pendingTotal = (float) (clone $pendingBase)->sum('total_credit');

        return view('livewire.sales.returns', [
            'returns'      => (clone $base)->latest('id')->paginate(20),
            'count'        => (clone $approvedBase)->count(),
            'total'        => $total,
            'cash'         => $cash,
            'credit'       => round($total - $cash, 2),
            'pendingCount' => $pendingCount,
            'pendingTotal' => $pendingTotal,
            'units'        => (int) SaleReturnItem::whereIn(
                'sale_return_id', (clone $approvedBase)->select('sale_returns.id')
            )->sum('quantity_returned'),
            'viewReturn'   => $this->viewId
                ? SaleReturn::with(['sale.customer', 'processor', 'approver', 'rejector', 'items.product', 'items.batch'])->find($this->viewId)
                : null,
        ]);
    }
}
