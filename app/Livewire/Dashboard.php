<?php

namespace App\Livewire;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Livewire\Component;

class Dashboard extends Component
{
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
        ]);
    }
}
