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

    /** Invoice opened in the detail drawer. */
    public ?int $viewSaleId = null;
    public bool $saleDrawer = false;

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

    public function viewSale(int $saleId): void
    {
        $this->viewSaleId = $saleId;
        $this->saleDrawer = true;
    }

    public function closeSale(): void
    {
        $this->saleDrawer = false;
        $this->viewSaleId = null;
    }

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

    /**
     * How money was actually taken: cash, card or transfer.
     *
     * payment_details is JSON written by the till, and its shape has changed
     * over time — older sales carry a different structure or none at all. Any
     * amount we cannot attribute to a method is reported as "not recorded"
     * rather than dropped, because a breakdown that silently omits two thirds
     * of the money is worse for an auditor than no breakdown at all.
     *
     * Store credit is deliberately excluded: it is credit being spent, taken
     * on an earlier day, not money arriving today.
     */
    private function collectionMethods($from, $to): array
    {
        $methods = ['cash' => 0.0, 'card' => 0.0, 'transfer' => 0.0];
        $storeCredit = 0.0;
        $changeGiven = 0.0;
        $attributed  = 0.0;
        $withMethod  = 0;

        $sales = $this->settledSales($from, $to)->get(['id', 'total_amount', 'coupon_discount', 'payment_details']);

        foreach ($sales as $sale) {
            $pd = $sale->payment_details;
            $pd = is_string($pd) ? json_decode($pd, true) : $pd;

            if (! is_array($pd)) {
                continue;   // no breakdown recorded
            }

            $found = false;

            foreach (array_keys($methods) as $method) {
                if (isset($pd[$method]) && is_numeric($pd[$method])) {
                    $methods[$method] += (float) $pd[$method];
                    $found = true;
                }
            }

            if (isset($pd['credit']) && is_numeric($pd['credit'])) {
                $storeCredit += (float) $pd['credit'];
            }

            if (isset($pd['change_given']) && is_numeric($pd['change_given'])) {
                $changeGiven += (float) $pd['change_given'];
            }

            if ($found) {
                $withMethod++;
                $attributed += (float) $sale->total_amount - (float) ($sale->coupon_discount ?? 0);
            }
        }

        // Debt repayments record their own method and are real money taken today.
        $debtByMethod = ['cash' => 0.0, 'card' => 0.0, 'transfer' => 0.0];
        if (Schema::hasTable('debt_payments')) {
            $rows = DB::table('debt_payments')
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw('payment_method, SUM(amount) AS total')
                ->groupBy('payment_method')
                ->get();

            foreach ($rows as $row) {
                $key = strtolower((string) $row->payment_method);
                if (array_key_exists($key, $debtByMethod)) {
                    $debtByMethod[$key] += (float) $row->total;
                    $methods[$key]      += (float) $row->total;
                }
            }
        }

        $settledTotal = (float) $sales->sum(
            fn($s) => (float) $s->total_amount - (float) ($s->coupon_discount ?? 0)
        );

        return [
            'byMethod'      => $methods,
            'debtByMethod'  => $debtByMethod,
            'methodTotal'   => array_sum($methods),
            'storeCredit'   => $storeCredit,
            'changeGiven'   => $changeGiven,
            // What the till took but never labelled with a method.
            'unrecorded'    => max(0, $settledTotal - $attributed),
            'salesWithMethod' => $withMethod,
            'salesTotal'      => $sales->count(),
            'settledTotal'    => $settledTotal,
        ];
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
            'methods'      => $this->collectionMethods($from, $to),

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

        $viewSale = $this->viewSaleId
            ? Sale::with(['customer', 'user', 'cashier', 'saleItems.product'])->find($this->viewSaleId)
            : null;

        return view('livewire.finance.index', [
            'f'     => $this->figures(),
            'viewSale' => $viewSale,
            'sales' => $sales,
            'expensesRecorded' => Schema::hasTable('expenses')
                ? Expense::whereDate('expense_date', '>=', $from)->whereDate('expense_date', '<=', $to)->count()
                : 0,
        ]);
    }
}
