<?php

namespace App\Livewire;

use App\Models\AppSetting;
use App\Models\Batch;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\ReferralCommission;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
    public bool $showWizard = false;
    public int $wizardStep = 1;

    public string $dateFilter = 'today'; // today | yesterday | custom
    public string $dateFrom = '';
    public string $dateTo = '';

    // Step 1: Pharmacy Info
    public string $pharmacy_name = '';
    public string $pharmacy_phone = '';
    public string $pharmacy_email = '';
    public string $pharmacy_address = '';

    // Step 2: WhatsApp
    public string $wawp_instance_id = '';
    public string $wawp_access_token = '';
    public bool $wawp_enabled = false;

    public function mount()
    {
        $this->dateFrom = today()->format('Y-m-d');
        $this->dateTo   = today()->format('Y-m-d');

        $this->pharmacy_name = AppSetting::get('pharmacy_name', '');
        $this->pharmacy_phone = AppSetting::get('pharmacy_phone', '');
        $this->pharmacy_email = AppSetting::get('pharmacy_email', '');
        $this->pharmacy_address = AppSetting::get('pharmacy_address', '');
        $this->wawp_instance_id = AppSetting::get('wawp_instance_id', '');
        $this->wawp_access_token = AppSetting::get('wawp_access_token', '');
        $this->wawp_enabled = AppSetting::bool('wawp_enabled', false);
    }

    private function getDateRange(): array
    {
        return match ($this->dateFilter) {
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'custom'    => [
                ($this->dateFrom ? \Carbon\Carbon::parse($this->dateFrom) : now())->startOfDay(),
                ($this->dateTo   ? \Carbon\Carbon::parse($this->dateTo)   : now())->endOfDay(),
            ],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }

    private function getPeriodLabel(): string
    {
        return match ($this->dateFilter) {
            'yesterday' => 'Yesterday',
            'custom'    => $this->dateFrom === $this->dateTo
                ? \Carbon\Carbon::parse($this->dateFrom)->format('M j, Y')
                : \Carbon\Carbon::parse($this->dateFrom)->format('M j') . ' – ' . \Carbon\Carbon::parse($this->dateTo)->format('M j, Y'),
            default => 'Today',
        };
    }

    public function openWizard()
    {
        $this->wizardStep = 1;
        $this->showWizard = true;
    }

    public function saveStep1()
    {
        $this->validate([
            'pharmacy_name' => 'required|string|max:255',
            'pharmacy_phone' => 'nullable|string|max:20',
            'pharmacy_email' => 'nullable|email|max:255',
            'pharmacy_address' => 'nullable|string|max:500',
        ]);

        AppSetting::set('pharmacy_name', $this->pharmacy_name);
        AppSetting::set('pharmacy_phone', $this->pharmacy_phone);
        AppSetting::set('pharmacy_email', $this->pharmacy_email);
        AppSetting::set('pharmacy_address', $this->pharmacy_address);

        $this->wizardStep = 2;
    }

    public function saveStep2()
    {
        AppSetting::set('wawp_instance_id', $this->wawp_instance_id);
        AppSetting::set('wawp_access_token', $this->wawp_access_token);
        AppSetting::set('wawp_enabled', $this->wawp_enabled ? '1' : '0');

        $this->wizardStep = 3;
    }

    public function finishWizard()
    {
        $this->showWizard = false;
    }

    public function skipWhatsApp()
    {
        $this->wizardStep = 3;
    }

    private function getSetupProgress(): array
    {
        $fields = [
            'pharmacy_name' => AppSetting::get('pharmacy_name', ''),
            'pharmacy_phone' => AppSetting::get('pharmacy_phone', ''),
            'pharmacy_email' => AppSetting::get('pharmacy_email', ''),
            'pharmacy_address' => AppSetting::get('pharmacy_address', ''),
            'wawp_enabled' => AppSetting::bool('wawp_enabled', false),
        ];

        $completed = 0;
        $total = count($fields);

        foreach ($fields as $value) {
            if (!empty($value)) $completed++;
        }

        return [
            'percent' => $total > 0 ? round(($completed / $total) * 100) : 0,
            'completed' => $completed,
            'total' => $total,
        ];
    }

    /**
     * A specialised dashboard applies only when the user holds nothing but
     * that role. Any additional role means they do wider work and get the
     * full pharmacy dashboard instead.
     */
    private function hasOnlyRole(string $role): bool
    {
        $roles = array_unique(auth()->user()->role ?? []);

        return $roles === [$role];
    }

    private function contentDashboard()
    {
        $total   = Product::count();
        $missing = Product::where(fn($q) => $q->whereNull('image')->orWhere('image', ''))->count();
        $done    = $total - $missing;

        return view('livewire.dashboard.index', [
            'isContentOnly'   => true,
            'isPromoterOnly'  => false,
            'isInventoryOnly' => false,
            'contentTotal'    => $total,
            'contentDone'     => $done,
            'contentMissing'  => $missing,
            'contentPercent'  => $total > 0 ? (int) round(($done / $total) * 100) : 0,
            'contentAddedToday' => Product::whereDate('updated_at', today())
                ->whereNotNull('image')->where('image', '!=', '')->count(),
            'contentQueue'    => Product::where(fn($q) => $q->whereNull('image')->orWhere('image', ''))
                ->latest()->limit(8)->get(),
        ]);
    }

    /**
     * Stock-focused view: no revenue, no profit — only what the person
     * responsible for inventory needs to act on.
     */
    private function inventoryDashboard()
    {
        // One query with a subquery sum, rather than loading every batch.
        $products = Product::withSum('batches as stock', 'quantity')->get();

        $outOfStock = $products->filter(fn($p) => (int) ($p->stock ?? 0) === 0);
        $lowStock   = $products->filter(
            fn($p) => (int) ($p->stock ?? 0) > 0 && (int) $p->stock <= (int) $p->reorder_level
        );

        $inStockBatches = Batch::where('quantity', '>', 0);

        return view('livewire.dashboard.index', [
            'isContentOnly'   => false,
            'isPromoterOnly'  => false,
            'isInventoryOnly' => true,

            'invProducts'   => $products->count(),
            'invStockUnits' => (int) Batch::sum('quantity'),
            'invOutOfStock' => $outOfStock->count(),
            'invLowStock'   => $lowStock->count(),

            'invStockValue' => (float) (clone $inStockBatches)->sum(DB::raw('quantity * cost_price')),
            'invExpired'    => (clone $inStockBatches)->where('expiry_date', '<', now())->count(),
            'invExpiringSoon' => (clone $inStockBatches)
                ->whereBetween('expiry_date', [now(), now()->addDays(90)])->count(),
            'invAwaitingDelivery' => PurchaseOrder::whereIn('status', ['sent', 'partially_received'])->count(),

            'invExpiringBatches' => Batch::with('product')
                ->where('quantity', '>', 0)
                ->whereBetween('expiry_date', [now(), now()->addDays(90)])
                ->orderBy('expiry_date')
                ->limit(6)
                ->get(),
            'invLowStockList' => $lowStock->sortBy('stock')->take(6)->values(),
        ]);
    }

    public function render()
    {
        // Specialised roles get their own view and none of the sales queries below.
        if ($this->hasOnlyRole('content')) {
            return $this->contentDashboard();
        }

        if ($this->hasOnlyRole('inventory_manager')) {
            return $this->inventoryDashboard();
        }

        [$from, $to] = $this->getDateRange();
        $periodLabel  = $this->getPeriodLabel();

        $todaySales = Sale::whereBetween('created_at', [$from, $to])->whereIn('status', ['paid', 'completed']);
        $totalSalesToday = $todaySales->sum('total_amount');
        $salesCountToday = $todaySales->count();

        $todayItems = SaleItem::whereHas('sale', fn($q) => $q->whereBetween('created_at', [$from, $to])->whereIn('status', ['paid', 'completed']));
        $todayRevenue = (clone $todayItems)->sum('subtotal');
        $todayCost = 0;
        foreach ((clone $todayItems)->get() as $item) {
            $todayCost += $item->cost_price * $item->quantity;
        }
        $todayProfit = $todayRevenue - $todayCost;

        $totalProducts = Product::count();
        $totalStock = Batch::sum('quantity');

        $lowStockProducts = Product::with('category', 'batches')
            ->get()
            ->filter(fn($p) => $p->batches->sum('quantity') <= $p->reorder_level && $p->batches->sum('quantity') > 0)
            ->take(5);

        $outOfStock = Product::with('batches')
            ->get()
            ->filter(fn($p) => $p->batches->sum('quantity') == 0)
            ->count();

        $expiringBatches = Batch::with('product')
            ->where('quantity', '>', 0)
            ->where('expiry_date', '<=', now()->addDays(90))
            ->where('expiry_date', '>=', now())
            ->orderBy('expiry_date')
            ->limit(5)
            ->get();

        $expiredBatches = Batch::where('quantity', '>', 0)
            ->where('expiry_date', '<', now())
            ->count();

        $potentialRevenue = 0;
        $potentialCost = 0;
        foreach (Product::with('batches')->get() as $product) {
            foreach ($product->batches as $batch) {
                if ($batch->quantity > 0 && $batch->expiry_date->isFuture()) {
                    $potentialRevenue += $product->selling_price * $batch->quantity;
                    $potentialCost += $batch->cost_price * $batch->quantity;
                }
            }
        }
        $potentialProfit = $potentialRevenue - $potentialCost;

        $recentSales = Sale::with('user', 'customer')
            ->whereIn('status', ['paid', 'completed'])
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->limit(5)
            ->get();

        $todayOnlineRevenue = Order::whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['completed', 'ready'])
            ->sum('total_amount');
        $todayOnlineCount = Order::whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['completed', 'ready'])
            ->count();
        $pendingOnlineOrders = Order::whereNull('claimed_by')
            ->whereIn('status', ['pending', 'processing'])
            ->count();
        $recentOnlineOrders = Order::with('customer')
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->limit(5)
            ->get();

        $setupProgress = $this->getSetupProgress();

        // Promoter commission snapshot
        $userId            = auth()->id();
        $myCustomersToday  = ReferralCommission::where('user_id', $userId)->whereDate('created_at', today())->count();
        $myTotalEarned     = (float) ReferralCommission::where('user_id', $userId)->sum('amount');
        $myPending         = $myTotalEarned - (float) ReferralCommission::where('user_id', $userId)->whereNotNull('paid_at')->sum('amount');
        $myRecentCustomers = ReferralCommission::with('customer')->where('user_id', $userId)->latest()->limit(5)->get();

        return view('livewire.dashboard.index', [
            'isContentOnly'   => false,
            'isInventoryOnly' => false,
            'isPromoterOnly'  => $this->hasOnlyRole('promoter'),
            'periodLabel' => $periodLabel,
            'totalSalesToday' => $totalSalesToday,
            'salesCountToday' => $salesCountToday,
            'todayProfit' => $todayProfit,
            'totalProducts' => $totalProducts,
            'totalStock' => $totalStock,
            'outOfStock' => $outOfStock,
            'lowStockProducts' => $lowStockProducts,
            'expiringBatches' => $expiringBatches,
            'expiredBatches' => $expiredBatches,
            'potentialProfit' => $potentialProfit,
            'potentialRevenue' => $potentialRevenue,
            'potentialCost' => $potentialCost,
            'recentSales' => $recentSales,
            'todayOnlineRevenue' => $todayOnlineRevenue,
            'todayOnlineCount' => $todayOnlineCount,
            'pendingOnlineOrders' => $pendingOnlineOrders,
            'recentOnlineOrders' => $recentOnlineOrders,
            'setupProgress'      => $setupProgress,
            'myCustomersToday'   => $myCustomersToday,
            'myTotalEarned'      => $myTotalEarned,
            'myPending'          => $myPending,
            'myRecentCustomers'  => $myRecentCustomers,
        ]);
    }
}
