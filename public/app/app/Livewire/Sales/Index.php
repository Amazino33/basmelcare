<?php

namespace App\Livewire\Sales;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\StockMovement;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use WithPagination;
    use Toast;

    public string $search = '';
    public string $period = 'today';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $tab = 'pos';
    public bool $detailsDrawer = false;
    public ?int $viewSaleId = null;
    public ?int $viewOrderId = null;

    // Return
    public bool $returnModal = false;
    public ?int $returnSaleId = null;
    public array $returnQtys = [];
    public array $returnableQtys = [];
    public string $returnReason = '';

    /**
     * How this return is paid back: store credit, or cash out of the till.
     *
     * Forced to cash for a walk-in, because there is no account to credit. The
     * value is re-decided when the return is processed rather than trusted
     * from here.
     */
    public string $refundMethod = SaleReturn::CREDIT;
    public string $returnError  = '';

    public function updatedReturnQtys(): void
    {
        $this->returnError = '';
    }

    public function updatingTab(): void
    {
        $this->resetPage();
        $this->detailsDrawer = false;
    }

    public function viewDetails($id): void
    {
        $this->viewSaleId = $id;
        $this->viewOrderId = null;
        $this->detailsDrawer = true;
    }

    public function viewOrderDetails($id): void
    {
        $this->viewOrderId = $id;
        $this->viewSaleId = null;
        $this->detailsDrawer = true;
    }

    /**
     * Refunds, Wi-Fi revocation and handover move money or entitlements, so they
     * are limited to admin and branch_manager. Everyone else may view sales.
     * (This check existed but was never applied to any action — it is now.)
     */
    public function isElevated(): bool
    {
        return (bool) array_intersect(
            auth()->user()->role ?? [],
            ['admin', 'branch_manager']
        );
    }

    private function denyUnlessElevated(): bool
    {
        if ($this->isElevated()) {
            return false;
        }

        $this->error('Only an admin or branch manager can do that.');

        return true;
    }

    private function isCashier(): bool
    {
        return !$this->isElevated()
            && in_array('cashier', auth()->user()->role ?? []);
    }

    public function completeHandover(int $saleId): void
    {
        if ($this->denyUnlessElevated()) return;

        $sale = Sale::find($saleId);

        if (!$sale || $sale->status !== 'paid') {
            $this->error('Sale not found or already completed.');
            return;
        }

        $sale->update(['status' => 'completed']);
        $this->success('Handover completed — sale marked as given to customer.');
    }

    /**
     * Revoke the free Wi-Fi access tied to a receipt (e.g. after a refund or
     * dispute). Flags the sale locally, then pushes the revoke to HiFastLink so
     * the device can no longer reconnect.
     */
    public function revokeWifi($saleId): void
    {
        if ($this->denyUnlessElevated()) return;

        $sale = Sale::find($saleId);

        if (! $sale || ! $sale->voucher_redeemed_at) {
            $this->error('This receipt has no active internet access to revoke.');
            return;
        }

        if ($sale->voucher_revoked_at) {
            $this->warning('This receipt\'s internet access is already revoked.');
            return;
        }

        $sale->update(['voucher_revoked_at' => now()]);

        $pushed = \App\Services\HifastlinkService::revoke($sale->wifi_code ?? $sale->invoice_number);

        if ($pushed) {
            $this->success('Internet access revoked. The device can no longer reconnect.');
        } else {
            $this->warning('Access revoked here, but HiFastLink could not be reached — verify the integration settings.');
        }
    }

    public function openReturn(int $saleId): void
    {
        if ($this->denyUnlessElevated()) return;

        $sale = Sale::with('saleItems.product', 'saleItems.batch')->findOrFail($saleId);

        // A walk-in has no account for store credit to sit on, so their refund
        // is cash out of the till. That is allowed unless the pharmacy has
        // chosen to tie every return to a named person.
        $requireCustomer = AppSetting::bool('return_require_customer', false);
        if ($requireCustomer && ! $sale->customer_id) {
            $this->error('This pharmacy only accepts returns from registered customers. Attach a customer to the sale, or turn the rule off under Settings → Returns.');
            return;
        }

        if (!in_array($sale->status, ['completed', 'paid'])) {
            $this->error('Returns can only be processed on paid or completed sales.');
            return;
        }

        $windowHours = (int) AppSetting::get('return_window_hours', 48);
        if ($sale->created_at->diffInHours(now()) > $windowHours) {
            $this->error("Returns are only allowed within {$windowHours} hours of the sale.");
            return;
        }

        $this->returnQtys      = [];
        $this->returnableQtys  = [];

        foreach ($sale->saleItems as $item) {
            $alreadyReturned               = SaleReturnItem::where('sale_item_id', $item->id)->sum('quantity_returned');
            $returnable                    = $item->quantity - $alreadyReturned;
            $this->returnableQtys[$item->id] = max(0, $returnable);
            $this->returnQtys[$item->id]     = 0;
        }

        $this->returnSaleId = $saleId;
        $this->returnReason = '';
        $this->returnError  = '';

        // Cash is the only refund a walk-in can be given, so it is not offered
        // as a choice - there is nowhere else for the money to go.
        $this->refundMethod = $sale->customer_id ? SaleReturn::CREDIT : SaleReturn::CASH;

        $this->returnModal  = true;
    }

    public function processReturn(): void
    {
        if ($this->denyUnlessElevated()) return;

        $sale = Sale::with('saleItems.product', 'saleItems.batch', 'customer')->findOrFail($this->returnSaleId);

        $requireCustomer = AppSetting::bool('return_require_customer', false);
        if ($requireCustomer && ! $sale->customer_id) {
            $this->error('This pharmacy only accepts returns from registered customers.');
            return;
        }

        // Decided here rather than trusted from the form: a walk-in cannot be
        // credited whatever the screen was showing, because there is no account
        // to credit. Getting this wrong would put goods back on the shelf and
        // give the customer nothing.
        $method = $sale->customer_id
            ? ($this->refundMethod === SaleReturn::CASH ? SaleReturn::CASH : SaleReturn::CREDIT)
            : SaleReturn::CASH;

        $windowHours = (int) AppSetting::get('return_window_hours', 48);
        if ($sale->created_at->diffInHours(now()) > $windowHours) {
            $this->error("Return window of {$windowHours} hours has passed.");
            return;
        }

        if (collect($this->returnQtys)->sum(fn($v) => (int) $v) <= 0) {
            $this->returnError = 'Select at least one item to return.';
            return;
        }

        $totalCredit  = 0.0;
        $saleReturnId = null;

        try {
            DB::transaction(function () use ($sale, $method, &$totalCredit, &$saleReturnId) {
                $saleReturn = SaleReturn::create([
                    'sale_id'       => $sale->id,
                    'processed_by'  => auth()->id(),
                    'reason'        => $this->returnReason ?: null,
                    'total_credit'  => 0,
                    'refund_method' => $method,
                    'refunded_at'   => now(),
                ]);

                foreach ($sale->saleItems as $item) {
                    $qty = (int) ($this->returnQtys[$item->id] ?? 0);
                    if ($qty <= 0) continue;

                    $alreadyReturned = (int) SaleReturnItem::where('sale_item_id', $item->id)->sum('quantity_returned');
                    $maxReturnable   = $item->quantity - $alreadyReturned;

                    if ($qty > $maxReturnable) {
                        throw new \RuntimeException(
                            $maxReturnable > 0
                                ? "Only {$maxReturnable} unit(s) of \"{$item->product->name}\" can still be returned."
                                : "\"{$item->product->name}\" has already been fully returned."
                        );
                    }

                    $subtotal     = $qty * (float) $item->unit_price;
                    $totalCredit += $subtotal;

                    SaleReturnItem::create([
                        'sale_return_id'    => $saleReturn->id,
                        'sale_item_id'      => $item->id,
                        'product_id'        => $item->product_id,
                        'batch_id'          => $item->batch_id,
                        'quantity_returned' => $qty,
                        'unit_price'        => $item->unit_price,
                        'subtotal'          => $subtotal,
                    ]);

                    // batch_id is NOT NULL on sale_items and its foreign key
                    // cascades, so a line always has a live batch. Refusing
                    // rather than skipping matters anyway: silently carrying on
                    // would pay the refund and leave the goods off the shelf,
                    // which is exactly the fault that is hard to notice.
                    if (! $item->batch) {
                        throw new \RuntimeException(
                            'The batch "' . ($item->product->name ?? 'item') . '" was sold from no longer exists, '
                            . 'so it cannot be put back. Add the stock by hand and record the refund separately.'
                        );
                    }

                    {
                        $item->batch->increment('quantity', $qty);
                        StockMovement::create([
                            'batch_id'  => $item->batch_id,
                            'quantity'  => $qty,
                            'type'      => 'return',
                            'reference' => "Return from Sale #{$sale->id}",
                            'user_id'   => auth()->id(),
                        ]);
                    }
                }

                $saleReturn->update(['total_credit' => $totalCredit]);

                // Only a credit refund touches the account. Cash has already
                // left the drawer by the time the slip prints.
                if ($method === SaleReturn::CREDIT && $sale->customer_id && $totalCredit > 0) {
                    $sale->customer->increment('credit_balance', $totalCredit);
                }

                $saleReturnId = $saleReturn->getKey();
            });
        } catch (\RuntimeException $e) {
            $this->returnError = $e->getMessage();
            return;
        } catch (\Throwable $e) {
            // Logged, because the alternative is what happened here: a return
            // reported as not working, with nothing recorded to say why.
            Log::error('[Return] Sale ' . $sale->invoice_number . ' failed: ' . $e->getMessage(), [
                'sale_id' => $sale->id,
                'user_id' => auth()->id(),
                'qtys'    => $this->returnQtys,
            ]);

            $this->returnError = 'Return could not be processed, and nothing was changed. '
                . 'Please try again, or tell an admin to check the logs.';
            return;
        }

        $this->returnModal  = false;
        $this->returnSaleId = null;
        $this->returnQtys   = [];
        // Say which it was. "Credited to customer account" on a cash refund
        // would send the cashier looking for a balance that does not exist.
        $this->success($method === SaleReturn::CASH
            ? 'Return processed. Give the customer ₦' . number_format($totalCredit, 2) . ' from the till.'
            : 'Return processed. ₦' . number_format($totalCredit, 2) . ' credited to customer account.');

        $this->dispatch('open-return-receipt', url: route('return.receipt', $saleReturnId));

        // A cash refund is settled at the counter and needs no message; there
        // is also no new balance to quote.
        $phone = $method === SaleReturn::CREDIT ? $sale->customer?->phone : null;
        if ($phone && $totalCredit > 0) {
            try {
                $pharmacyName  = AppSetting::get('pharmacy_name', 'BasmelCare');
                $newBalance    = $sale->customer->fresh()->credit_balance;
                $message = "Hi {$sale->customer->name}, a return of \u{20A6}" . number_format($totalCredit, 2)
                    . " has been credited to your {$pharmacyName} account."
                    . " Your new credit balance is \u{20A6}" . number_format($newBalance, 2)
                    . ". Ref: RT-" . str_pad($saleReturnId, 5, '0', STR_PAD_LEFT) . ".";
                app(WhatsAppService::class)->send($phone, $message);
            } catch (\Throwable $e) {
                Log::error('[WhatsApp Return] ' . $e->getMessage());
            }
        }
    }

    private function periodQuery($query)
    {
        return match ($this->period) {
            'today'     => $query->whereDate('created_at', today()),
            'yesterday' => $query->whereDate('created_at', today()->subDay()),
            'week'      => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month'     => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
            'year'      => $query->whereYear('created_at', now()->year),
            'custom'    => $query->whereBetween('created_at', [
                ($this->dateFrom ? \Carbon\Carbon::parse($this->dateFrom)->startOfDay() : now()->startOfDay()),
                ($this->dateTo   ? \Carbon\Carbon::parse($this->dateTo)->endOfDay()     : now()->endOfDay()),
            ]),
            default => $query,
        };
    }

    public function render()
    {
        $elevated   = $this->isElevated();
        $isCashier  = $this->isCashier();
        $userId     = auth()->id();

        $scopeFn = fn($q) => $q; // all staff see all sales

        // --- POS Sales ---
        $posHeaders = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'created_at', 'label' => 'Date'],
            ['key' => 'user.name', 'label' => 'By'],
            ['key' => 'customer.name', 'label' => 'Customer'],
            ['key' => 'total_amount', 'label' => 'Total'],
            ['key' => 'payment_method', 'label' => 'Payment'],
            ['key' => 'status', 'label' => 'Status'],
        ];

        $salesQuery = Sale::with('user', 'customer')
            ->tap($scopeFn)
            ->when($this->search, fn($q) => $q->where('id', $this->search)
                ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$this->search}%"))
                ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$this->search}%")));

        $filteredSales = $this->periodQuery(clone $salesQuery)->whereIn('status', ['paid', 'completed']);
        // Net of discount — total_amount is the pre-discount figure.
        $totalRevenue = (float) (clone $filteredSales)->sum(DB::raw('total_amount - COALESCE(coupon_discount, 0)'));
        $totalTransactions = $filteredSales->count();

        $filteredItems = SaleItem::whereHas('sale', function ($q) use ($scopeFn) {
            $this->periodQuery($q)->whereIn('status', ['paid', 'completed'])->tap($scopeFn);
        });
        $totalCost = 0;
        $totalItemsSold = 0;
        foreach ((clone $filteredItems)->get() as $item) {
            $totalCost += $item->cost_price * $item->quantity;
            $totalItemsSold += $item->quantity;
        }
        $totalProfit = $totalRevenue - $totalCost;
        $avgSale = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

        // What each method ACTUALLY took, read from payment_details.
        //
        // This previously summed total_amount grouped by payment_method, which
        // is the billed figure: it included discounts never charged and balances
        // sold on credit, and a split payment put its whole value under "split".
        // Staff read this row as the drawer, so it has to be money tendered.
        // Sales with no recorded method still had money taken, but part of it
        // may never have arrived. Outstanding debt per sale lets that bucket
        // report cash actually collected rather than the amount billed.
        $owedPerSale = \App\Models\Debt::query()
            ->selectRaw('sale_id, SUM(COALESCE(amount_owed, 0) - COALESCE(amount_paid, 0)) AS still_owed')
            ->groupBy('sale_id')
            ->pluck('still_owed', 'sale_id');

        $collected = ['cash' => 0.0, 'card' => 0.0, 'transfer' => 0.0];

        // Billed on this sale, less anything still outstanding on it.
        $actuallyTaken = function ($sale) use ($owedPerSale) {
            $billed = (float) $sale->total_amount - (float) ($sale->coupon_discount ?? 0);

            return max(0, $billed - (float) ($owedPerSale[$sale->id] ?? 0));
        };

        $creditUsed = 0.0;
        $changeGiven = 0.0;
        $unrecorded  = 0.0;

        foreach ((clone $filteredSales)->get(['id', 'total_amount', 'coupon_discount', 'payment_details']) as $paid) {
            $details = $paid->payment_details;
            $details = is_string($details) ? json_decode($details, true) : $details;

            if (! is_array($details)) {
                // Older sales stored no breakdown; report rather than guess.
                $unrecorded += $actuallyTaken($paid);
                continue;
            }

            $matched = false;

            foreach (array_keys($collected) as $method) {
                if (isset($details[$method]) && is_numeric($details[$method])) {
                    $collected[$method] += (float) $details[$method];
                    $matched = true;
                }
            }

            if (isset($details['credit']) && is_numeric($details['credit'])) {
                $creditUsed += (float) $details['credit'];
            }

            if (isset($details['change_given']) && is_numeric($details['change_given'])) {
                $changeGiven += (float) $details['change_given'];
            }

            if (! $matched) {
                $unrecorded += $actuallyTaken($paid);
            }
        }

        // Change is handed back in cash, so it comes off the cash line itself —
        // otherwise the three method figures sum to more than Cash Collected.
        $collected['cash'] -= $changeGiven;

        // Repayments on older debts taken during this period are real money in.
        // The opening part-payment is excluded: it is already counted above from
        // the sale. Each repayment is attributed to the method it was taken by,
        // not assumed to be cash.
        $repaidByMethod = ['cash' => 0.0, 'card' => 0.0, 'transfer' => 0.0];

        $repayments = $this->periodQuery(\App\Models\DebtPayment::query())
            ->where('at_point_of_sale', false)
            ->selectRaw('payment_method, SUM(amount) AS total')
            ->groupBy('payment_method')
            ->get();

        foreach ($repayments as $repayment) {
            $method = strtolower((string) $repayment->payment_method);

            if (array_key_exists($method, $repaidByMethod)) {
                $repaidByMethod[$method] += (float) $repayment->total;
                $collected[$method]      += (float) $repayment->total;
            }
        }

        $cashCollected = array_sum($collected);

        $sales = $this->periodQuery(clone $salesQuery)->latest()->paginate(20);

        $viewSale = $this->viewSaleId
            ? Sale::with('saleItems.product', 'saleItems.batch', 'user', 'customer')->find($this->viewSaleId)
            : null;

        $returnableSale   = $this->returnSaleId
            ? Sale::with('saleItems.product', 'saleItems.batch', 'customer')->find($this->returnSaleId)
            : null;
        $returnWindowHours = (int) AppSetting::get('return_window_hours', 48);

        // --- Pending Handover (paid but not yet handed to customer) ---
        // All staff can see and complete handovers regardless of who made the sale
        $handoverQuery = Sale::with('user', 'customer')
            ->where('status', 'paid')
            ->when($this->search, fn($q) => $q->where('invoice_number', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$this->search}%"))
                ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$this->search}%")));

        $pendingHandoverCount = (clone $handoverQuery)->count();
        $pendingHandoverTotal = (clone $handoverQuery)->sum('total_amount');
        $handoverSales = (clone $handoverQuery)->latest()->paginate(20);

        // --- Online Orders ---
        $onlineHeaders = [
            ['key' => 'order_number', 'label' => 'Order #'],
            ['key' => 'created_at', 'label' => 'Date'],
            ['key' => 'customer_display', 'label' => 'Customer'],
            ['key' => 'total_amount', 'label' => 'Total'],
            ['key' => 'payment_method', 'label' => 'Payment'],
            ['key' => 'status', 'label' => 'Status'],
        ];

        $onlineQuery = Order::with('customer', 'claimedByUser')
            ->when($this->search, fn($q) => $q
                ->where('order_number', 'like', "%{$this->search}%")
                ->orWhere('guest_name', 'like', "%{$this->search}%")
                ->orWhere('guest_phone', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$this->search}%")));

        $completedOnline = $this->periodQuery(clone $onlineQuery)->whereIn('status', ['completed', 'ready']);
        $onlineRevenue = $completedOnline->sum('total_amount');
        $onlineTransactions = $completedOnline->count();
        $pendingOnlineCount = $this->periodQuery(Order::query())->whereIn('status', ['pending', 'processing'])->count();

        $onlinePaymentBreakdown = $this->periodQuery(Order::whereIn('status', ['completed', 'ready']))
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('payment_method')
            ->get();

        $onlineOrders = $this->periodQuery(clone $onlineQuery)->latest()->paginate(20);

        $viewOrder = $this->viewOrderId
            ? Order::with('customer', 'items.product', 'claimedByUser')->find($this->viewOrderId)
            : null;

        // --- Returns ---
        // Recorded since the feature was built, but with nowhere to look at
        // them: the only trace was the printed slip. A return moves stock and
        // money, so it belongs in the same history as the sale it undoes.
        $returnsQuery = SaleReturn::with(['sale.customer', 'processor', 'items.product'])
            ->when($this->search, fn ($q) => $q
                ->where('id', $this->search)
                ->orWhereHas('sale', fn ($sa) => $sa->where('id', $this->search)
                    ->orWhere('invoice_number', 'like', "%{$this->search}%"))
                ->orWhereHas('sale.customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%")));

        $periodReturns = $this->periodQuery(clone $returnsQuery);

        $returns = (clone $periodReturns)->latest('id')->paginate(15, ['*'], 'returnsPage');

        // Split by how the money went back, because the two are not the same
        // thing: cash left the drawer, credit is owed and leaves it later.
        $returnsTotal = (float) (clone $periodReturns)->sum('total_credit');
        $returnsCash  = (float) (clone $periodReturns)
            ->where('refund_method', SaleReturn::CASH)->sum('total_credit');

        $returnsCount = (clone $periodReturns)->count();

        // Units back on the shelf, which is the stock question rather than the
        // money one.
        $returnedUnits = (int) SaleReturnItem::whereIn(
            'sale_return_id', (clone $periodReturns)->select('sale_returns.id')
        )->sum('quantity_returned');

        return view('livewire.sales.index', [
            'returns'              => $returns,
            'returnsTotal'         => $returnsTotal,
            'returnsCash'          => $returnsCash,
            'returnsCredit'        => round($returnsTotal - $returnsCash, 2),
            'returnsCount'         => $returnsCount,
            'returnedUnits'        => $returnedUnits,
            'returnableSale'       => $returnableSale,
            'returnWindowHours'    => $returnWindowHours,
            'elevated'             => $elevated,
            'isCashier'            => $isCashier,
            'posHeaders'           => $posHeaders,
            'sales'                => $sales,
            'viewSale'             => $viewSale,
            'totalRevenue'         => $totalRevenue,
            'totalProfit'          => $totalProfit,
            'totalTransactions'    => $totalTransactions,
            'totalItemsSold'       => $totalItemsSold,
            'avgSale'              => $avgSale,
            'collected'            => $collected,
            'cashCollected'        => $cashCollected,
            'creditUsed'           => $creditUsed,
            'unrecordedCash'       => $unrecorded,
            'onlineHeaders'        => $onlineHeaders,
            'onlineOrders'         => $onlineOrders,
            'viewOrder'            => $viewOrder,
            'onlineRevenue'        => $onlineRevenue,
            'onlineTransactions'   => $onlineTransactions,
            'pendingOnlineCount'   => $pendingOnlineCount,
            'onlinePaymentBreakdown' => $onlinePaymentBreakdown,
            'handoverSales'        => $handoverSales,
            'pendingHandoverCount' => $pendingHandoverCount,
            'pendingHandoverTotal' => $pendingHandoverTotal,
        ]);
    }
}
