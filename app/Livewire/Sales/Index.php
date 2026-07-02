<?php

namespace App\Livewire\Sales;

use App\Models\Order;
use App\Models\Sale;
use App\Models\SaleItem;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $period = 'today';
    public string $tab = 'pos';
    public bool $detailsDrawer = false;
    public ?int $viewSaleId = null;
    public ?int $viewOrderId = null;

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
        // --- POS Sales ---
        $posHeaders = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'created_at', 'label' => 'Date'],
            ['key' => 'user.name', 'label' => 'Cashier'],
            ['key' => 'customer.name', 'label' => 'Customer'],
            ['key' => 'total_amount', 'label' => 'Total'],
            ['key' => 'payment_method', 'label' => 'Payment'],
            ['key' => 'status', 'label' => 'Status'],
        ];

        $salesQuery = Sale::with('user', 'customer')
            ->when($this->search, fn($q) => $q->where('id', $this->search)
                ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$this->search}%"))
                ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$this->search}%")));

        $filteredSales = $this->periodQuery(clone $salesQuery)->where('status', 'completed');
        $totalRevenue = $filteredSales->sum('total_amount');
        $totalTransactions = $filteredSales->count();

        $filteredItems = SaleItem::whereHas('sale', function ($q) {
            $this->periodQuery($q)->where('status', 'completed');
        });
        $totalCost = 0;
        $totalItemsSold = 0;
        foreach ((clone $filteredItems)->get() as $item) {
            $totalCost += $item->cost_price * $item->quantity;
            $totalItemsSold += $item->quantity;
        }
        $totalProfit = $totalRevenue - $totalCost;
        $avgSale = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

        $paymentBreakdown = $this->periodQuery(Sale::where('status', 'completed'))
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('payment_method')
            ->get();

        $sales = $this->periodQuery(clone $salesQuery)->latest()->paginate(20);

        $viewSale = $this->viewSaleId
            ? Sale::with('saleItems.product', 'saleItems.batch', 'user', 'customer')->find($this->viewSaleId)
            : null;

        // --- Pending Handover (paid but not yet handed to customer) ---
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

        return view('livewire.sales.index', [
            'posHeaders' => $posHeaders,
            'sales' => $sales,
            'viewSale' => $viewSale,
            'totalRevenue' => $totalRevenue,
            'totalProfit' => $totalProfit,
            'totalTransactions' => $totalTransactions,
            'totalItemsSold' => $totalItemsSold,
            'avgSale' => $avgSale,
            'paymentBreakdown' => $paymentBreakdown,
            'onlineHeaders' => $onlineHeaders,
            'onlineOrders' => $onlineOrders,
            'viewOrder' => $viewOrder,
            'onlineRevenue' => $onlineRevenue,
            'onlineTransactions' => $onlineTransactions,
            'pendingOnlineCount' => $pendingOnlineCount,
            'onlinePaymentBreakdown' => $onlinePaymentBreakdown,
            'handoverSales' => $handoverSales,
            'pendingHandoverCount' => $pendingHandoverCount,
            'pendingHandoverTotal' => $pendingHandoverTotal,
        ]);
    }
}
