<div>
    <x-header title="Dashboard" subtitle="BasmelCare Pharmacy Overview" />

    <!-- Stats Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-stat
            title="Today's Sales"
            value="₦{{ number_format($totalSalesToday, 2) }}"
            description="{{ $salesCountToday }} transactions"
            icon="o-banknotes"
            color="text-primary"
        />
        <x-stat
            title="Today's Profit"
            value="₦{{ number_format($todayProfit, 2) }}"
            description="Revenue minus cost"
            icon="o-arrow-trending-up"
            color="{{ $todayProfit >= 0 ? 'text-success' : 'text-error' }}"
        />
        <x-stat
            title="Total Products"
            value="{{ $totalProducts }}"
            description="{{ $totalStock }} units in stock"
            icon="o-cube"
            color="text-info"
        />
        <x-stat
            title="Out of Stock"
            value="{{ $outOfStock }}"
            description="Products need restocking"
            icon="o-exclamation-circle"
            color="text-error"
        />
    </div>

    <!-- Potential Profit -->
    <x-card title="Potential Profit (Unsold Stock)" class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center p-4 bg-base-200 rounded-lg">
                <div class="text-sm text-base-content/60">Potential Revenue</div>
                <div class="text-2xl font-bold text-primary">₦{{ number_format($potentialRevenue, 2) }}</div>
            </div>
            <div class="text-center p-4 bg-base-200 rounded-lg">
                <div class="text-sm text-base-content/60">Total Cost</div>
                <div class="text-2xl font-bold text-error">₦{{ number_format($potentialCost, 2) }}</div>
            </div>
            <div class="text-center p-4 bg-base-200 rounded-lg">
                <div class="text-sm text-base-content/60">Expected Profit</div>
                <div class="text-2xl font-bold text-success">₦{{ number_format($potentialProfit, 2) }}</div>
            </div>
        </div>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Expiry Alerts -->
        <x-card title="Expiry Alerts" subtitle="Expiring within 90 days">
            @if($expiredBatches > 0)
                <x-alert title="{{ $expiredBatches }} batch(es) already expired!" icon="o-exclamation-triangle" class="alert-error mb-3" />
            @endif

            @forelse($expiringBatches as $batch)
                <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                    <div>
                        <div class="font-semibold text-sm">{{ $batch->product->name }}</div>
                        <div class="text-xs text-base-content/60">Batch: {{ $batch->batch_number }} | Qty: {{ $batch->quantity }}</div>
                    </div>
                    <div>
                        @php $days = (int) now()->diffInDays($batch->expiry_date, false); @endphp
                        <x-badge value="{{ $days }}d left" @class([
                            'badge-error' => $days <= 30,
                            'badge-warning' => $days > 30 && $days <= 60,
                            'badge-info' => $days > 60,
                        ]) />
                    </div>
                </div>
            @empty
                <div class="text-center py-4 text-base-content/60">No products expiring soon.</div>
            @endforelse

            @if($expiringBatches->count())
                <div class="mt-3">
                    <x-button label="View All" link="{{ route('expiry-alerts.index') }}" class="btn-sm btn-ghost" icon="o-arrow-right" />
                </div>
            @endif
        </x-card>

        <!-- Low Stock -->
        <x-card title="Low Stock Products" subtitle="Below reorder level">
            @forelse($lowStockProducts as $product)
                <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                    <div>
                        <div class="font-semibold text-sm">{{ $product->name }}</div>
                        <div class="text-xs text-base-content/60">{{ $product->category?->name }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-bold text-warning">{{ $product->batches->sum('quantity') }} left</div>
                        <div class="text-xs text-base-content/60">Reorder: {{ $product->reorder_level }}</div>
                    </div>
                </div>
            @empty
                <div class="text-center py-4 text-base-content/60">All products are well stocked.</div>
            @endforelse
        </x-card>

        <!-- Recent Sales -->
        <x-card title="Recent Sales" subtitle="Last 5 transactions" class="lg:col-span-2">
            @forelse($recentSales as $sale)
                <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                    <div>
                        <div class="font-semibold text-sm">Sale #{{ $sale->id }}</div>
                        <div class="text-xs text-base-content/60">{{ $sale->created_at->format('M d, H:i') }} | {{ $sale->user->name }} | {{ $sale->customer?->name ?? 'Walk-in' }}</div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-primary">₦{{ number_format($sale->total_amount, 2) }}</div>
                        <x-badge :value="ucfirst($sale->payment_method)" class="badge-ghost badge-sm" />
                    </div>
                </div>
            @empty
                <div class="text-center py-4 text-base-content/60">No sales yet.</div>
            @endforelse
        </x-card>
    </div>
</div>
