<?php

namespace App\Livewire;

use App\Models\Appointment;
use App\Models\AppSetting;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\MedicalRecord;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromoterCode;
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
     * Roles that entitle someone to the full pharmacy dashboard. Pharmacist is
     * deliberately absent — it is a clinical role and sees no revenue or profit.
     */
    private const OPERATIONAL = ['admin', 'branch_manager', 'sales', 'cashier'];

    /** Focused roles, in the order their panels should stack. */
    private const SPECIALIST = ['pharmacist', 'inventory_manager', 'promoter', 'content'];

    private function roles(): array
    {
        return array_unique(auth()->user()->role ?? []);
    }

    /**
     * Panels to stack for someone who holds only focused roles. Anyone with an
     * operational role does wider work and gets the full dashboard instead, so
     * this returns nothing for them.
     */
    private function specialistPanels(): array
    {
        if (array_intersect($this->roles(), self::OPERATIONAL)) {
            return [];
        }

        return array_values(array_intersect(self::SPECIALIST, $this->roles()));
    }

    private function contentData(): array
    {
        $total   = Product::count();
        $missing = Product::where(fn($q) => $q->whereNull('image')->orWhere('image', ''))->count();
        $done    = $total - $missing;

        return [
            'contentTotal'    => $total,
            'contentDone'     => $done,
            'contentMissing'  => $missing,
            'contentPercent'  => $total > 0 ? (int) round(($done / $total) * 100) : 0,
            'contentAddedToday' => Product::whereDate('updated_at', today())
                ->whereNotNull('image')->where('image', '!=', '')->count(),
            'contentQueue'    => Product::where(fn($q) => $q->whereNull('image')->orWhere('image', ''))
                ->latest()->limit(8)->get(),
        ];
    }

    /**
     * Clinical view: patients and drug safety. No revenue, profit or stock value.
     */
    private function pharmacistData(): array
    {
        $expiring = Batch::with('product')
            ->where('quantity', '>', 0)
            ->whereBetween('expiry_date', [now(), now()->addDays(90)])
            ->orderBy('expiry_date')
            ->limit(6)
            ->get();

        return [
            'phNewPatients'   => Customer::whereDate('created_at', today())->count(),
            'phTotalPatients' => Customer::count(),
            'phRecordsToday'  => MedicalRecord::whereDate('created_at', today())->count(),
            'phAppointments'  => Appointment::whereDate('scheduled_at', today())
                ->whereIn('status', ['scheduled', 'confirmed'])->count(),

            'phExpired'       => Batch::where('quantity', '>', 0)
                ->where('expiry_date', '<', now())->count(),
            'phExpiringSoon'  => $expiring->count(),
            'phExpiringList'  => $expiring,

            'phOutOfStock'    => Product::withSum('batches as stock', 'quantity')->get()
                ->filter(fn($p) => (int) ($p->stock ?? 0) === 0)->count(),

            'phTodayAppointments' => Appointment::with('customer', 'staff')
                ->whereDate('scheduled_at', today())
                ->orderBy('scheduled_at')
                ->limit(6)
                ->get(),
        ];
    }

    private function promoterData(): array
    {
        $user   = auth()->user();
        $userId = $user->id;

        $earned = (float) ReferralCommission::where('user_id', $userId)->sum('amount');
        $paid   = (float) ReferralCommission::where('user_id', $userId)->whereNotNull('paid_at')->sum('amount');

        return [
            'myProgress'        => $user->promoterProgressOn(today()),
            'myTotalEarned'     => $earned,
            'myPending'         => $earned - $paid,
            'myRecentCodes'     => PromoterCode::with('customer')
                ->where('user_id', $userId)
                ->whereDate('created_at', today())
                ->latest()
                ->limit(8)
                ->get(),
        ];
    }

    /**
     * Stock-focused view: no revenue, no profit — only what the person
     * responsible for inventory needs to act on.
     */
    private function inventoryData(): array
    {
        // One query with a subquery sum, rather than loading every batch.
        $products = Product::withSum('batches as stock', 'quantity')->get();

        $outOfStock = $products->filter(fn($p) => (int) ($p->stock ?? 0) === 0);
        $lowStock   = $products->filter(
            fn($p) => (int) ($p->stock ?? 0) > 0 && (int) $p->stock <= (int) $p->reorder_level
        );

        $inStockBatches = Batch::where('quantity', '>', 0);

        return [
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
        ];
    }

    public function render()
    {
        // Focused roles get their own stacked panels and none of the sales
        // queries below. Someone holding two focused roles gets both panels.
        if ($panels = $this->specialistPanels()) {
            $data = ['panels' => $panels];

            if (in_array('pharmacist', $panels))        $data += $this->pharmacistData();
            if (in_array('inventory_manager', $panels)) $data += $this->inventoryData();
            if (in_array('promoter', $panels))          $data += $this->promoterData();
            if (in_array('content', $panels))           $data += $this->contentData();

            return view('livewire.dashboard.index', $data);
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

        return view('livewire.dashboard.index', [
            'panels' => [],
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
        ]);
    }
}
