<?php

namespace App\Livewire\Finance;

use App\Models\Expense;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The financial picture: what was earned, what it cost, and what actually
 * moved in and out of the till.
 *
 * Trading and cash are reported separately and never mixed. Buying ₦500,000 of
 * stock and selling half of it is a profitable month on a trading basis and a
 * heavy outflow on a cash basis; presenting either number as "profit" would be
 * wrong. Every figure here is traceable to the sales it came from.
 */
class Index extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $preset = 'this_month';

    public function mount(): void
    {
        if ($this->from === '' || $this->to === '') {
            $this->applyPreset($this->preset);
        }
    }

    public function applyPreset(string $preset): void
    {
        $this->preset = $preset;

        [$from, $to] = match ($preset) {
            'today'      => [today(), today()],
            'yesterday'  => [today()->subDay(), today()->subDay()],
            'this_week'  => [today()->startOfWeek(), today()],
            'last_month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            'this_year'  => [today()->startOfYear(), today()],
            default      => [today()->startOfMonth(), today()],
        };

        $this->from = $from->format('Y-m-d');
        $this->to   = $to->format('Y-m-d');
    }

    public function updatedFrom(): void { $this->preset = 'custom'; }
    public function updatedTo(): void   { $this->preset = 'custom'; }

    private function range(): array
    {
        return [
            Carbon::parse($this->from)->startOfDay(),
            Carbon::parse($this->to)->endOfDay(),
        ];
    }

    /** Only settled sales count — pending invoices are not income. */
    private function settledSales($from, $to)
    {
        return Sale::whereIn('status', ['paid', 'completed'])
            ->whereBetween('created_at', [$from, $to]);
    }

    private function figures(): array
    {
        [$from, $to] = $this->range();

        // ── Trading ──────────────────────────────────────────────────
        // total_amount is the pre-discount figure; the coupon is held in its
        // own column and taken off at payment, so it must be deducted here or
        // revenue is overstated on every discounted sale.
        $revenue = (float) $this->settledSales($from, $to)
            ->sum(DB::raw('total_amount - COALESCE(coupon_discount, 0)'));

        $refunds = Schema::hasTable('sale_returns')
            ? (float) DB::table('sale_returns')->whereBetween('created_at', [$from, $to])->sum('total_credit')
            : 0.0;

        $cogs = (float) DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sales.status', ['paid', 'completed'])
            ->whereBetween('sales.created_at', [$from, $to])
            ->sum(DB::raw('sale_items.cost_price * sale_items.quantity'));

        // Returned goods come back into stock, so their cost is no longer a cost.
        $returnedCost = 0.0;
        if (Schema::hasTable('sale_return_items')) {
            $returnedCost = (float) DB::table('sale_return_items')
                ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
                ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
                ->whereBetween('sale_returns.created_at', [$from, $to])
                ->sum(DB::raw('sale_items.cost_price * sale_return_items.quantity_returned'));
        }

        $netRevenue = $revenue - $refunds;
        $netCogs    = $cogs - $returnedCost;
        $gross      = $netRevenue - $netCogs;

        // whereDate, not a string range: expense_date can carry a time component,
        // and '2026-08-20 00:00:00' sorts AFTER the string '2026-08-20', which
        // silently drops the current day's expenses.
        $expenses = Schema::hasTable('expenses')
            ? (float) Expense::whereDate('expense_date', '>=', $from)
                ->whereDate('expense_date', '<=', $to)->sum('amount')
            : 0.0;

        // ── Cash ─────────────────────────────────────────────────────
        // Derived from settled columns rather than payment_details, whose JSON
        // shape varies between split, part-payment and single-method sales.
        $newDebt = Schema::hasTable('debts')
            ? (float) DB::table('debts')->whereBetween('created_at', [$from, $to])->sum('amount_owed')
            : 0.0;

        $debtRepaid = Schema::hasTable('debt_payments')
            ? (float) DB::table('debt_payments')->whereBetween('created_at', [$from, $to])->sum('amount')
            : 0.0;

        $creditPaidOut = Schema::hasTable('credit_payouts')
            ? (float) DB::table('credit_payouts')->whereBetween('created_at', [$from, $to])->sum('amount')
            : 0.0;

        $stockPurchases = Schema::hasTable('purchase_orders')
            ? (float) DB::table('purchase_orders')
                ->whereIn('status', ['received', 'partially_received'])
                ->whereBetween('updated_at', [$from, $to])
                ->sum('total_amount')
            : 0.0;

        $collected = $netRevenue - $newDebt + $debtRepaid - $creditPaidOut;
        $paidOut   = $expenses + $stockPurchases;

        return [
            // Trading
            'revenue'      => $revenue,
            'refunds'      => $refunds,
            'netRevenue'   => $netRevenue,
            'cogs'         => $netCogs,
            'gross'        => $gross,
            'grossMargin'  => $netRevenue > 0 ? ($gross / $netRevenue) * 100 : 0.0,
            'expenses'     => $expenses,
            'netProfit'    => $gross - $expenses,
            'netMargin'    => $netRevenue > 0 ? (($gross - $expenses) / $netRevenue) * 100 : 0.0,

            // Cash
            'newDebt'        => $newDebt,
            'debtRepaid'     => $debtRepaid,
            'creditPaidOut'  => $creditPaidOut,
            'stockPurchases' => $stockPurchases,
            'collected'      => $collected,
            'paidOut'        => $paidOut,
            'netCash'        => $collected - $paidOut,

            'saleCount'    => $this->settledSales($from, $to)->count(),

            // Shown so the auditor can account for every invoice number. A gap
            // in the sequence with no visible reason is what fraud looks like.
            'cancelledCount' => Sale::where('status', 'cancelled')
                ->whereBetween('created_at', [$from, $to])->count(),
            'pendingCount'   => Sale::where('status', 'pending')
                ->whereBetween('created_at', [$from, $to])->count(),
        ];
    }

    public function render()
    {
        [$from, $to] = $this->range();

        // EVERY sale, whatever its status — cancelled and pending invoices are
        // listed too, so the invoice sequence has no unexplained gaps. Only
        // settled sales feed the figures above; the rest contribute nothing.
        //
        // Ordered by invoice number so the sequence reads straight down and a
        // missing one is obvious.
        $sales = Sale::with('customer', 'user')
            ->whereBetween('created_at', [$from, $to])
            ->withSum('saleItems as cogs', DB::raw('cost_price * quantity'))
            ->orderByDesc('created_at')
            ->orderByDesc('invoice_number')
            ->paginate(25);

        return view('livewire.finance.index', [
            'f'     => $this->figures(),
            'sales' => $sales,
            'expensesRecorded' => Schema::hasTable('expenses')
                ? Expense::whereDate('expense_date', '>=', $from)->whereDate('expense_date', '<=', $to)->count()
                : 0,
        ]);
    }
}
