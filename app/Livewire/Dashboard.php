<?php

namespace App\Livewire;

use App\Models\AppSetting;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Livewire\Component;

class Dashboard extends Component
{
    public bool $showWizard = false;
    public int $wizardStep = 1;

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
        $this->pharmacy_name = AppSetting::get('pharmacy_name', '');
        $this->pharmacy_phone = AppSetting::get('pharmacy_phone', '');
        $this->pharmacy_email = AppSetting::get('pharmacy_email', '');
        $this->pharmacy_address = AppSetting::get('pharmacy_address', '');
        $this->wawp_instance_id = AppSetting::get('wawp_instance_id', '');
        $this->wawp_access_token = AppSetting::get('wawp_access_token', '');
        $this->wawp_enabled = AppSetting::bool('wawp_enabled', false);
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

    public function render()
    {
        $todaySales = Sale::whereDate('created_at', today())->where('status', 'completed');
        $totalSalesToday = $todaySales->sum('total_amount');
        $salesCountToday = $todaySales->count();

        $todayItems = SaleItem::whereHas('sale', fn($q) => $q->whereDate('created_at', today())->where('status', 'completed'));
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
            ->where('status', 'completed')
            ->latest()
            ->limit(5)
            ->get();

        $setupProgress = $this->getSetupProgress();

        return view('livewire.dashboard.index', [
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
            'setupProgress' => $setupProgress,
        ]);
    }
}
