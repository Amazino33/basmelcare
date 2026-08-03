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

    private function isElevated(): bool
    {
        return (bool) array_intersect(
            auth()->user()->role ?? [],
            ['admin', 'pharmacist', 'branch_manager']
        );
    }

    private function isCashier(): bool
    {
        return !$this->isElevated()
            && in_array('cashier', auth()->user()->role ?? []);
    }

    public function completeHandover(int $saleId): void
    {
        $sale = Sale::find($saleId);

        if (!$sale || $sale->status !== 'paid') {
            $this->error('Sale not found or already completed.');
            return;
        }

        if (!$this->isElevated() && $sale->user_id !== auth()->id()) {
            $this->error('You can only complete handover for your own sales.');
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

        $pushed = \App\Services\HifastlinkService::revoke($sale->invoice_number);

        if ($pushed) {
            $this->success('Internet access revoked. The device can no longer reconnect.');
        } else {
            $this->warning('Access revoked here, but HiFastLink could not be reached — verify the integration settings.');
        }
    }

    public function openReturn(int $saleId): void
    {
        $sale = Sale::with('saleItems.product', 'saleItems.batch')->findOrFail($saleId);

        $requireCustomer = AppSetting::bool('return_require_customer', true);
        if ($requireCustomer && !$sale->customer_id) {
            $this->error('This sale has no customer attached. Attach a customer to the sale before processing a return.');
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
        $this->returnModal  = true;
    }

    public function processReturn(): void
    {
        $sale = Sale::with('saleItems.product', 'saleItems.batch', 'customer')->findOrFail($this->returnSaleId);

        $requireCustomer = AppSetting::bool('return_require_customer', true);
        if ($requireCustomer && !$sale->customer_id) {
            $this->error('No customer attached to this sale.');
            return;
        }

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
            DB::transaction(function () use ($sale, &$totalCredit, &$saleReturnId) {
                $saleReturn = SaleReturn::create([
                    'sale_id'      => $sale->id,
                    'processed_by' => auth()->id(),
                    'reason'       => $this->returnReason ?: null,
                    'total_credit' => 0,
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

                    if ($item->batch_id) {
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

                if ($sale->customer_id && $totalCredit > 0) {
                    $sale->customer->increment('credit_balance', $totalCredit);
                }

                $saleReturnId = $saleReturn->getKey();
            });
        } catch (\RuntimeException $e) {
            $this->returnError = $e->getMessage();
            return;
        } catch (\Throwable) {
            $this->returnError = 'Return could not be processed. Please try again or contact support.';
            return;
        }

        $this->returnModal  = false;
        $this->returnSaleId = null;
        $this->returnQtys   = [];
        $this->success('Return processed. ₦' . number_format($totalCredit, 2) . ' credited to customer account.');

        $this->dispatch('open-return-receipt', url: route('return.receipt', $saleReturnId));

        $phone = $sale->customer?->phone;
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
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
            'year' => $query->whereYear('created_at', now()->year),
            default => $query,
        };
    }

    public function render()
    {
        $elevated   = $this->isElevated();
        $isCashier  = $this->isCashier();
        $userId     = auth()->id();

        // How to scope non-elevated users: sales person → user_id, cashier → cashier_id
        $scopeFn = function ($q) use ($elevated, $isCashier, $userId) {
            if (!$elevated) {
                $isCashier
                    ? $q->where('cashier_id', $userId)
                    : $q->where('user_id', $userId);
            }
        };

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

        $filteredSales = $this->periodQuery(clone $salesQuery)->where('status', 'completed');
        $totalRevenue = $filteredSales->sum('total_amount');
        $totalTransactions = $filteredSales->count();

        $filteredItems = SaleItem::whereHas('sale', function ($q) use ($scopeFn) {
            $this->periodQuery($q)->where('status', 'completed')->tap($scopeFn);
        });
        $totalCost = 0;
        $totalItemsSold = 0;
        foreach ((clone $filteredItems)->get() as $item) {
            $totalCost += $item->cost_price * $item->quantity;
            $totalItemsSold += $item->quantity;
        }
        $totalProfit = $totalRevenue - $totalCost;
        $avgSale = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

        $paymentBreakdown = $this->periodQuery(
            Sale::where('status', 'completed')->tap($scopeFn)
            )->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('payment_method')
            ->get();

        $sales = $this->periodQuery(clone $salesQuery)->latest()->paginate(20);

        $viewSale = $this->viewSaleId
            ? Sale::with('saleItems.product', 'saleItems.batch', 'user', 'customer')->find($this->viewSaleId)
            : null;

        $returnableSale   = $this->returnSaleId
            ? Sale::with('saleItems.product', 'saleItems.batch', 'customer')->find($this->returnSaleId)
            : null;
        $returnWindowHours = (int) AppSetting::get('return_window_hours', 48);

        // --- Pending Handover (paid but not yet handed to customer) ---
        $handoverQuery = Sale::with('user', 'customer')
            ->where('status', 'paid')
            ->tap($scopeFn)
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

        return view('livewire.sales.index', [
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
            'paymentBreakdown'     => $paymentBreakdown,
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
