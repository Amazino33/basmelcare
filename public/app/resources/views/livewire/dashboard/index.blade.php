<div>
    @if($isContentOnly)
    {{-- ── Image Uploader Dashboard ── --}}
    <x-header title="Dashboard" subtitle="Product image coverage" size="text-xl" />

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <x-stat
            title="Still Missing"
            value="{{ $contentMissing }}"
            description="products need a photo"
            icon="o-photo"
            color="{{ $contentMissing > 0 ? 'text-warning' : 'text-success' }}"
            class="text-sm h-full"
        />
        <x-stat
            title="Done"
            value="{{ $contentDone }}"
            description="of {{ $contentTotal }} products"
            icon="o-check-circle"
            color="text-success"
            class="text-sm h-full"
        />
        <x-stat
            title="Coverage"
            value="{{ $contentPercent }}%"
            description="catalogue with images"
            icon="o-chart-pie"
            color="text-info"
            class="text-sm h-full"
        />
        <x-stat
            title="Updated Today"
            value="{{ $contentAddedToday }}"
            description="images touched"
            icon="o-arrow-up-tray"
            color="text-primary"
            class="text-sm h-full"
        />
    </div>

    <x-card class="mb-5">
        <div class="flex justify-between items-end mb-2">
            <span class="text-sm font-semibold">Catalogue coverage</span>
            <span class="text-sm text-base-content/60 tabular-nums">{{ $contentDone }} / {{ $contentTotal }}</span>
        </div>
        <progress
            class="progress {{ $contentPercent === 100 ? 'progress-success' : 'progress-primary' }} w-full"
            value="{{ $contentPercent }}" max="100"></progress>
    </x-card>

    <x-card title="Next up — products without an image">
        @forelse($contentQueue as $product)
            <div class="flex items-center gap-3 p-2 border-b border-base-200 last:border-0">
                <div class="w-9 h-9 rounded-lg bg-base-200 flex items-center justify-center shrink-0">
                    <x-icon name="o-photo" class="w-4 h-4 text-base-content/30" />
                </div>
                <a href="https://www.google.com/search?q={{ urlencode($product->name) }}&tbm=isch"
                   target="_blank" rel="noopener noreferrer"
                   class="flex-1 min-w-0 text-sm font-medium text-primary hover:underline truncate">
                    {{ $product->name }}
                </a>
            </div>
        @empty
            <div class="text-center py-8 text-base-content/50 text-sm">
                <x-icon name="o-check-circle" class="w-10 h-10 mx-auto mb-2 text-success opacity-40" />
                Every product has an image. Nothing left to do.
            </div>
        @endforelse

        <div class="mt-3">
            <x-button label="Go to Product Images" link="{{ route('media.index') }}"
                      class="btn-sm btn-primary" icon="o-photo" />
        </div>
    </x-card>

    @elseif($isPromoterOnly)
    {{-- ── Promoter Dashboard ── --}}
    <x-header title="Dashboard" subtitle="Your referral activity" size="text-xl" />

    <div class="grid grid-cols-3 gap-4 mb-6">
        <x-stat
            title="Registered Today"
            value="{{ $myCustomersToday }}"
            description="new customers"
            icon="o-user-plus"
            color="text-primary"
        />
        <x-stat
            title="Total Earned"
            value="₦{{ number_format($myTotalEarned, 2) }}"
            description="all time"
            icon="o-currency-dollar"
            color="text-success"
        />
        <x-stat
            title="Pending Payout"
            value="₦{{ number_format($myPending, 2) }}"
            description="awaiting payment"
            icon="o-clock"
            color="{{ $myPending > 0 ? 'text-warning' : 'text-base-content/40' }}"
        />
    </div>

    <x-card title="Recently Registered Customers">
        @forelse($myRecentCustomers as $commission)
            <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                <div class="min-w-0 flex-1">
                    <div class="font-semibold text-sm">{{ $commission->customer->name }}</div>
                    <div class="text-xs text-base-content/60">{{ $commission->created_at->format('M d, Y · H:i') }}</div>
                </div>
                <span class="font-bold text-primary text-sm ml-4 shrink-0">
                    ₦{{ number_format($commission->amount, 2) }}
                </span>
            </div>
        @empty
            <div class="text-center py-8 text-base-content/40 text-sm">
                No customers registered yet — head to Customers to get started.
            </div>
        @endforelse
        <div class="mt-3 flex gap-2">
            <x-button label="Register Customer" link="{{ route('customers.index') }}" class="btn-sm btn-primary" icon="o-plus" />
            <x-button label="My Commissions" link="{{ route('commissions.index') }}" class="btn-sm btn-ghost" icon="o-currency-dollar" />
        </div>
    </x-card>

    @else
    {{-- ── Standard Pharmacy Dashboard ── --}}
    <x-header title="Dashboard" subtitle="BasmelCare Pharmacy Overview" size="text-xl">
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-2">
                <button wire:click="$set('dateFilter', 'today')"
                    class="btn btn-sm {{ $dateFilter === 'today' ? 'btn-primary' : 'btn-ghost' }}">Today</button>
                <button wire:click="$set('dateFilter', 'yesterday')"
                    class="btn btn-sm {{ $dateFilter === 'yesterday' ? 'btn-primary' : 'btn-ghost' }}">Yesterday</button>
                <button wire:click="$set('dateFilter', 'custom')"
                    class="btn btn-sm {{ $dateFilter === 'custom' ? 'btn-primary' : 'btn-ghost' }}">Custom</button>
                @if($dateFilter === 'custom')
                    <input type="date" wire:model.live="dateFrom" class="input input-sm input-bordered w-36" />
                    <span class="text-xs text-base-content/50">to</span>
                    <input type="date" wire:model.live="dateTo" class="input input-sm input-bordered w-36" />
                @endif
            </div>
        </x-slot:actions>
    </x-header>

    <!-- Setup Progress Bar (admin/pharmacist/branch_manager only) -->
    @if($setupProgress['percent'] < 100 && array_intersect(auth()->user()->role ?? [], ['admin', 'pharmacist', 'branch_manager']))
        <x-card class="mb-4 border-primary/30">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                <div>
                    <div class="font-semibold text-sm">Complete Your Pharmacy Profile</div>
                    <div class="text-xs text-base-content/60">{{ $setupProgress['completed'] }} of {{ $setupProgress['total'] }} steps</div>
                </div>
                <x-button label="Complete Setup" wire:click="openWizard" icon="o-arrow-right" class="btn-primary btn-sm" />
            </div>
            <x-progress value="{{ $setupProgress['percent'] }}" max="100" class="progress-primary" />
        </x-card>
    @endif

    <!-- Stats Row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <a href="{{ route('sales.index') }}" class="block">
            <x-stat
                title="POS Sales · {{ $periodLabel }}"
                value="₦{{ number_format($totalSalesToday, 2) }}"
                description="{{ $salesCountToday }} transactions"
                icon="o-banknotes"
                color="text-primary"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full"
            />
        </a>
        @if(array_intersect(auth()->user()->role ?? [],['admin', 'pharmacist', 'branch_manager']))
        <a href="{{ route('reports.index') }}" class="block">
            <x-stat
                title="Profit · {{ $periodLabel }}"
                value="₦{{ number_format($todayProfit, 2) }}"
                description="Revenue - cost"
                icon="o-arrow-trending-up"
                color="{{ $todayProfit >= 0 ? 'text-success' : 'text-error' }}"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full"
            />
        </a>
        @endif
        <a href="{{ route('products.index') }}" class="block">
            <x-stat
                title="Products"
                value="{{ $totalProducts }}"
                description="{{ $totalStock }} in stock"
                icon="o-cube"
                color="text-info"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full"
            />
        </a>
        <a href="{{ route('products.index', ['stockFilter' => 'out_of_stock']) }}" class="block">
            <x-stat
                title="Out of Stock"
                value="{{ $outOfStock }}"
                description="Need restocking"
                icon="o-exclamation-circle"
                color="text-error"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full"
            />
        </a>
    </div>

    <!-- Online Orders Stats (visible to roles that process online orders) -->
    @if(array_intersect(auth()->user()->role ?? [],['admin', 'pharmacist', 'branch_manager', 'sales']))
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
        <a href="{{ route('online-orders.index') }}" class="block">
            <x-stat
                title="Online Sales · {{ $periodLabel }}"
                value="₦{{ number_format($todayOnlineRevenue, 2) }}"
                description="{{ $todayOnlineCount }} orders"
                icon="o-globe-alt"
                color="text-secondary"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full"
            />
        </a>
        <a href="{{ route('online-orders.index') }}" class="block">
            <x-stat
                title="Pending Online"
                value="{{ $pendingOnlineOrders }}"
                description="Awaiting processing"
                icon="o-clock"
                color="{{ $pendingOnlineOrders > 0 ? 'text-warning' : 'text-base-content/40' }}"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full"
            />
        </a>
        <a href="{{ route('reports.index') }}" class="col-span-2 lg:col-span-1 block bg-base-100 rounded-lg p-4 shadow-sm hover:bg-base-200 transition-colors cursor-pointer">
            <div class="flex items-center justify-between h-full">
                <div>
                    <div class="text-sm text-base-content/60">Combined · {{ $periodLabel }}</div>
                    <div class="text-xl font-bold text-primary">₦{{ number_format($totalSalesToday + $todayOnlineRevenue, 2) }}</div>
                    <div class="text-xs text-base-content/60">POS + Online</div>
                </div>
                <x-icon name="o-chart-bar" class="w-10 h-10 text-primary/20" />
            </div>
        </a>
    </div>

    @if($pendingOnlineOrders > 0)
        <div class="alert alert-warning mb-4 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <x-icon name="o-exclamation-triangle" class="w-5 h-5 shrink-0" />
                <div>
                    <div class="font-semibold text-sm">{{ $pendingOnlineOrders }} online {{ $pendingOnlineOrders === 1 ? 'order' : 'orders' }} waiting</div>
                    <div class="text-xs opacity-80">Unclaimed and awaiting a staff member to process.</div>
                </div>
            </div>
            <a href="{{ route('online-orders.index') }}" class="btn btn-sm btn-warning shrink-0">
                View <x-icon name="o-arrow-right" class="w-4 h-4 inline" />
            </a>
        </div>
    @endif
    @endif

    <!-- Potential Profit -->
    @if(array_intersect(auth()->user()->role ?? [],['admin', 'pharmacist', 'branch_manager']))
    <x-card title="Potential Profit" class="mb-4">
        <div class="grid grid-cols-3 gap-2">
            <div class="text-center p-2 sm:p-4 bg-base-200 rounded-lg">
                <div class="text-xs text-base-content/60">Revenue</div>
                <div class="text-sm sm:text-xl font-bold text-primary">₦{{ number_format($potentialRevenue, 0) }}</div>
            </div>
            <div class="text-center p-2 sm:p-4 bg-base-200 rounded-lg">
                <div class="text-xs text-base-content/60">Cost</div>
                <div class="text-sm sm:text-xl font-bold text-error">₦{{ number_format($potentialCost, 0) }}</div>
            </div>
            <div class="text-center p-2 sm:p-4 bg-base-200 rounded-lg">
                <div class="text-xs text-base-content/60">Profit</div>
                <div class="text-sm sm:text-xl font-bold text-success">₦{{ number_format($potentialProfit, 0) }}</div>
            </div>
        </div>
        <div class="mt-2">
            <x-button label="View Reports" link="{{ route('reports.index') }}" class="btn-xs btn-ghost" icon="o-arrow-right" />
        </div>
    </x-card>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Expiry Alerts -->
        <x-card title="Expiry Alerts" subtitle="Within 90 days">
            @if($expiredBatches > 0)
                <a href="{{ route('expiry-alerts.index') }}" class="block">
                    <x-alert title="{{ $expiredBatches }} expired!" icon="o-exclamation-triangle" class="alert-error mb-3 hover:opacity-80 transition-opacity" />
                </a>
            @endif

            @forelse($expiringBatches as $batch)
                <a href="{{ route('expiry-alerts.index') }}" class="flex justify-between items-center p-2 border-b border-base-200 last:border-0 hover:bg-base-200 transition-colors rounded">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-xs sm:text-sm truncate">{{ $batch->product->name }}</div>
                        <div class="text-xs text-base-content/60">{{ $batch->batch_number }} | Qty: {{ $batch->quantity }}</div>
                    </div>
                    <div class="ml-2 shrink-0">
                        @php $days = (int) now()->diffInDays($batch->expiry_date, false); @endphp
                        <x-badge value="{{ $days }}d" @class([
                            'badge-xs sm:badge-sm',
                            'badge-error' => $days <= 30,
                            'badge-warning' => $days > 30 && $days <= 60,
                            'badge-info' => $days > 60,
                        ]) />
                    </div>
                </a>
            @empty
                <div class="text-center py-4 text-base-content/60 text-sm">No products expiring soon.</div>
            @endforelse

            <div class="mt-2">
                <x-button label="View All" link="{{ route('expiry-alerts.index') }}" class="btn-xs btn-ghost" icon="o-arrow-right" />
            </div>
        </x-card>

        <!-- Low Stock -->
        <x-card title="Low Stock" subtitle="Below reorder level">
            @forelse($lowStockProducts as $product)
                <a href="{{ route('products.index', ['stockFilter' => 'low_stock']) }}" class="flex justify-between items-center p-2 border-b border-base-200 last:border-0 hover:bg-base-200 transition-colors rounded">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-xs sm:text-sm truncate">{{ $product->name }}</div>
                        <div class="text-xs text-base-content/60">{{ $product->category?->name }}</div>
                    </div>
                    <div class="text-right ml-2 shrink-0">
                        <div class="text-sm font-bold text-warning">{{ $product->batches->sum('quantity') }}</div>
                        <div class="text-xs text-base-content/60">/ {{ $product->reorder_level }}</div>
                    </div>
                </a>
            @empty
                <div class="text-center py-4 text-base-content/60 text-sm">All stocked.</div>
            @endforelse
            <div class="mt-2">
                <x-button label="View Products" link="{{ route('products.index', ['stockFilter' => 'low_stock']) }}" class="btn-xs btn-ghost" icon="o-arrow-right" />
            </div>
        </x-card>

        <!-- Recent Sales -->
        <x-card title="POS Sales · {{ $periodLabel }}">
            @forelse($recentSales as $sale)
                <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-xs sm:text-sm">{{ $sale->invoice_number ?? 'Sale #' . $sale->id }}</div>
                        <div class="text-xs text-base-content/60 truncate">{{ $sale->created_at->format('M d, H:i') }} | {{ $sale->customer?->name ?? 'Walk-in' }}</div>
                    </div>
                    <div class="text-right ml-2 shrink-0">
                        <div class="font-bold text-sm text-primary">₦{{ number_format($sale->total_amount, 2) }}</div>
                    </div>
                </div>
            @empty
                <div class="text-center py-4 text-base-content/60 text-sm">No sales yet.</div>
            @endforelse
            <div class="mt-2">
                <x-button label="All Sales" link="{{ route('sales.index') }}" class="btn-xs btn-ghost" icon="o-arrow-right" />
            </div>
        </x-card>

        @if(array_intersect(auth()->user()->role ?? [],['admin', 'pharmacist', 'branch_manager', 'sales']))
        <!-- Recent Online Orders -->
        <x-card title="Online Orders · {{ $periodLabel }}">
            @forelse($recentOnlineOrders as $order)
                <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-xs sm:text-sm">{{ $order->order_number }}</div>
                        <div class="text-xs text-base-content/60 truncate">
                            {{ $order->created_at->format('M d, H:i') }} |
                            {{ $order->customer?->name ?? $order->guest_name ?? 'Guest' }}
                        </div>
                    </div>
                    <div class="text-right ml-2 shrink-0 space-y-1">
                        <div class="font-bold text-sm text-secondary">₦{{ number_format($order->total_amount, 2) }}</div>
                        <x-badge :value="ucfirst($order->status)" @class([
                            'badge-xs',
                            'badge-warning'  => in_array($order->status, ['pending', 'processing']),
                            'badge-info'     => $order->status === 'ready',
                            'badge-success'  => $order->status === 'completed',
                            'badge-error'    => $order->status === 'cancelled',
                        ]) />
                    </div>
                </div>
            @empty
                <div class="text-center py-4 text-base-content/60 text-sm">No online orders yet.</div>
            @endforelse
            <div class="mt-2">
                <x-button label="All Online Orders" link="{{ route('online-orders.index') }}" class="btn-xs btn-ghost" icon="o-arrow-right" />
            </div>
        </x-card>
        @endif
    </div>

    @endif
    {{-- end standard dashboard --}}

    <!-- Setup Wizard Modal -->
    <x-modal wire:model="showWizard" title="Setup Wizard" box-class="max-w-lg" persistent>
        <x-steps wire:model="wizardStep" class="mb-4">
            <x-step step="1" text="Info" />
            <x-step step="2" text="WhatsApp" />
            <x-step step="3" text="Done" />
        </x-steps>

        @if($wizardStep === 1)
            <x-form wire:submit="saveStep1">
                <div class="space-y-3">
                    <x-input label="Pharmacy Name" wire:model="pharmacy_name" placeholder="e.g. BasmelCare Pharmacy" />
                    <x-input label="Phone" wire:model="pharmacy_phone" placeholder="e.g. 08012345678" />
                    <x-input label="Email" wire:model="pharmacy_email" type="email" placeholder="e.g. info@basmelcare.com" />
                    <x-textarea label="Address" wire:model="pharmacy_address" placeholder="Full address" rows="2" />
                </div>
                <x-slot:actions>
                    <x-button label="Cancel" @click="$wire.showWizard = false" />
                    <x-button label="Next" type="submit" class="btn-primary" icon="o-arrow-right" />
                </x-slot:actions>
            </x-form>
        @endif

        @if($wizardStep === 2)
            <x-form wire:submit="saveStep2">
                <div class="space-y-3">
                    <x-alert title="Connect WhatsApp for notifications" icon="o-chat-bubble-left-right" class="alert-info" />
                    <x-toggle label="Enable WhatsApp" wire:model="wawp_enabled" />
                    <x-input label="WAWP Instance ID" wire:model="wawp_instance_id" />
                    <x-input label="WAWP Access Token" wire:model="wawp_access_token" type="password" />
                </div>
                <x-slot:actions>
                    <x-button label="Skip" wire:click="skipWhatsApp" />
                    <x-button label="Finish" type="submit" class="btn-primary" icon="o-check" />
                </x-slot:actions>
            </x-form>
        @endif

        @if($wizardStep === 3)
            <div class="text-center py-4">
                <x-icon name="o-check-circle" class="w-12 h-12 text-success mx-auto mb-3" />
                <div class="text-lg font-bold mb-1">Setup Complete!</div>
                <div class="text-sm text-base-content/60 mb-3">Update anytime in Settings.</div>
                <div class="bg-base-200 rounded-lg p-3 text-left space-y-1 text-sm">
                    <div class="flex justify-between"><span class="text-base-content/60">Name:</span> <span class="font-semibold">{{ $pharmacy_name ?: '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-base-content/60">Phone:</span> <span>{{ $pharmacy_phone ?: '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-base-content/60">Email:</span> <span>{{ $pharmacy_email ?: '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-base-content/60">WhatsApp:</span> <span>{{ $wawp_enabled ? 'Enabled' : 'Not configured' }}</span></div>
                </div>
            </div>
            <div class="flex justify-end mt-3">
                <x-button label="Dashboard" wire:click="finishWizard" class="btn-primary" icon="o-arrow-right" />
            </div>
        @endif
    </x-modal>
</div>
