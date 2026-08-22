<div>
    @php
        // MaryUI's x-stat is built for desktop: px-5 padding, a fixed w-9 icon and
        // a whitespace-nowrap title. On a 360px phone that leaves ~66px for text,
        // so titles clip and ₦ values overflow. Compact it below sm, full size above.
        $stat = 'px-3 py-3 sm:px-5 sm:py-4'
              . ' [&_svg]:w-7 [&_svg]:h-7 sm:[&_svg]:w-9 sm:[&_svg]:h-9'
              . ' [&_.gap-3]:gap-2 sm:[&_.gap-3]:gap-3'
              . ' [&_.font-black]:text-base sm:[&_.font-black]:text-xl'
              . ' [&_.whitespace-nowrap]:whitespace-normal [&_.whitespace-nowrap]:leading-tight'
              . ' [&_.stat-desc]:hidden sm:[&_.stat-desc]:block';
    @endphp

    @if($panels)
    {{-- ── Focused-role dashboard: one panel per role held ── --}}
    @php $multi = count($panels) > 1; @endphp
    <x-header title="Dashboard" size="text-xl" :subtitle="$multi ? 'Your work areas' : match($panels[0]) {
        'auditor'           => 'Money in, money out, profit',
        'pharmacist'        => 'Patients and drug safety',
        'inventory_manager' => 'Stock overview',
        'promoter'          => 'Your referral activity',
        'content'           => 'Product image coverage',
    }" />

    @if(in_array('auditor', $panels))
    @if($multi)<div class="divider text-xs text-base-content/50 uppercase tracking-wide">Finance</div>@endif

    <p class="text-xs text-base-content/50 mb-3">{{ $audPeriod }} · {{ number_format($audSales) }} settled sale(s)</p>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <x-stat title="Revenue" value="₦{{ number_format($audRevenue, 2) }}"
            description="money earned" icon="o-arrow-down-tray" color="text-success"
            class="text-sm h-full {{ $stat }}" />
        <x-stat title="Cost of Goods" value="₦{{ number_format($audCogs, 2) }}"
            description="what it cost us" icon="o-arrow-up-tray" color="text-error"
            class="text-sm h-full {{ $stat }}" />
        <x-stat title="Gross Profit" value="₦{{ number_format($audGross, 2) }}"
            description="{{ number_format($audMargin, 1) }}% margin" icon="o-chart-bar" color="text-primary"
            class="text-sm h-full {{ $stat }}" />
        <x-stat title="Net Profit" value="₦{{ number_format($audNet, 2) }}"
            description="after expenses"
            icon="{{ $audNet >= 0 ? 'o-arrow-trending-up' : 'o-arrow-trending-down' }}"
            color="{{ $audNet >= 0 ? 'text-success' : 'text-error' }}"
            class="text-sm h-full {{ $stat }}" />
    </div>

    @if($audExpenses == 0)
        <div class="alert alert-warning py-2 mb-4 text-sm gap-2">
            <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
            <span>No expenses recorded this month, so net profit equals gross profit.</span>
        </div>
    @endif

    <x-card title="Go deeper">
        <p class="text-sm text-base-content/70 mb-3">
            Every figure above is built from individual sales — open Financial Records
            to see each one with its cost and margin.
        </p>
        <div class="flex flex-wrap gap-2">
            <x-button label="Financial Records" link="{{ route('finance.index') }}" class="btn-sm btn-primary" icon="o-banknotes" />
            <x-button label="Export Reports" link="{{ route('reports.index') }}" class="btn-sm btn-ghost" icon="o-document-chart-bar" />
        </div>
    </x-card>
    @endif

    @if(in_array('pharmacist', $panels))
    @if($multi)<div class="divider text-xs text-base-content/50 uppercase tracking-wide">Clinical</div>@endif

    @if($phExpired > 0)
        <a href="{{ route('expiry-alerts.index') }}" class="block mb-4">
            <x-alert title="{{ $phExpired }} batch(es) already expired — do not dispense"
                     icon="o-exclamation-triangle" class="alert-error hover:opacity-80 transition-opacity" />
        </a>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <a href="{{ route('customers.index') }}" class="block">
            <x-stat title="Patients Today" value="{{ $phNewPatients }}"
                description="{{ number_format($phTotalPatients) }} on file"
                icon="o-user-plus" color="text-primary"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full {{ $stat }}" />
        </a>
        <a href="{{ route('appointments.index') }}" class="block">
            <x-stat title="Appointments" value="{{ $phAppointments }}"
                description="scheduled today"
                icon="o-calendar" color="text-info"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full {{ $stat }}" />
        </a>
        <a href="{{ route('expiry-alerts.index') }}" class="block">
            <x-stat title="Expiring Soon" value="{{ $phExpiringSoon }}"
                description="within 90 days"
                icon="o-clock"
                color="{{ $phExpiringSoon > 0 ? 'text-warning' : 'text-success' }}"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full {{ $stat }}" />
        </a>
        <a href="{{ route('inventory.index') }}" class="block">
            <x-stat title="Out of Stock" value="{{ $phOutOfStock }}"
                description="cannot be dispensed"
                icon="o-exclamation-circle"
                color="{{ $phOutOfStock > 0 ? 'text-error' : 'text-success' }}"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full {{ $stat }}" />
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <x-card title="Today's Appointments">
            @forelse($phTodayAppointments as $appt)
                <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-sm truncate">{{ $appt->customer?->name ?? $appt->title }}</div>
                        <div class="text-xs text-base-content/60">
                            {{ $appt->scheduled_at->format('H:i') }}
                            @if($appt->staff) · {{ $appt->staff->name }} @endif
                        </div>
                    </div>
                    <span @class([
                        'badge badge-sm shrink-0 ml-3',
                        'badge-info'    => $appt->status === 'scheduled',
                        'badge-primary' => $appt->status === 'confirmed',
                        'badge-success' => $appt->status === 'completed',
                        'badge-error'   => $appt->status === 'cancelled',
                        'badge-warning' => $appt->status === 'no_show',
                    ])>{{ ucfirst(str_replace('_', ' ', $appt->status)) }}</span>
                </div>
            @empty
                <div class="text-center py-6 text-base-content/50 text-sm">No appointments today.</div>
            @endforelse
            <div class="mt-2">
                <x-button label="All Appointments" link="{{ route('appointments.index') }}" class="btn-xs btn-ghost" icon="o-arrow-right" />
            </div>
        </x-card>

        <x-card title="Expiring Soon" subtitle="Check before dispensing">
            @forelse($phExpiringList as $batch)
                <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-sm truncate">{{ $batch->product?->name ?? '—' }}</div>
                        <div class="text-xs text-base-content/60">
                            Batch {{ $batch->batch_number ?? '—' }} · {{ $batch->quantity }} units
                        </div>
                    </div>
                    <span class="text-xs font-semibold shrink-0 ml-3 {{ $batch->expiry_date->diffInDays(now()) <= 30 ? 'text-error' : 'text-warning' }}">
                        {{ $batch->expiry_date->format('d M Y') }}
                    </span>
                </div>
            @empty
                <div class="text-center py-6 text-base-content/50 text-sm">Nothing expiring in the next 90 days.</div>
            @endforelse
            <div class="mt-2">
                <x-button label="All Expiry Alerts" link="{{ route('expiry-alerts.index') }}" class="btn-xs btn-ghost" icon="o-arrow-right" />
            </div>
        </x-card>
    </div>
    @endif

    @if(in_array('content', $panels))
    @if($multi)<div class="divider text-xs text-base-content/50 uppercase tracking-wide">Product Images</div>@endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <x-stat
            title="Still Missing"
            value="{{ $contentMissing }}"
            description="products need a photo"
            icon="o-photo"
            color="{{ $contentMissing > 0 ? 'text-warning' : 'text-success' }}"
            class="text-sm h-full {{ $stat }}"
        />
        <x-stat
            title="Done"
            value="{{ $contentDone }}"
            description="of {{ $contentTotal }} products"
            icon="o-check-circle"
            color="text-success"
            class="text-sm h-full {{ $stat }}"
        />
        <x-stat
            title="Coverage"
            value="{{ $contentPercent }}%"
            description="catalogue with images"
            icon="o-chart-pie"
            color="text-info"
            class="text-sm h-full {{ $stat }}"
        />
        <x-stat
            title="Updated Today"
            value="{{ $contentAddedToday }}"
            description="images touched"
            icon="o-arrow-up-tray"
            color="text-primary"
            class="text-sm h-full {{ $stat }}"
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
    @endif

    @if(in_array('inventory_manager', $panels))
    @if($multi)<div class="divider text-xs text-base-content/50 uppercase tracking-wide mt-6">Stock</div>@endif

    @if($invExpired > 0)
        <a href="{{ route('expiry-alerts.index') }}" class="block mb-4">
            <x-alert title="{{ $invExpired }} batch(es) already expired — remove from shelves"
                     icon="o-exclamation-triangle" class="alert-error hover:opacity-80 transition-opacity" />
        </a>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
        <a href="{{ route('products.index') }}" class="block">
            <x-stat title="Products" value="{{ $invProducts }}"
                description="{{ number_format($invStockUnits) }} units in stock"
                icon="o-cube" color="text-info"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full {{ $stat }}" />
        </a>
        <a href="{{ route('products.index', ['stockFilter' => 'out_of_stock']) }}" class="block">
            <x-stat title="Out of Stock" value="{{ $invOutOfStock }}"
                description="need restocking"
                icon="o-exclamation-circle"
                color="{{ $invOutOfStock > 0 ? 'text-error' : 'text-success' }}"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full {{ $stat }}" />
        </a>
        <a href="{{ route('products.index', ['stockFilter' => 'low_stock']) }}" class="block">
            <x-stat title="Low Stock" value="{{ $invLowStock }}"
                description="at or below reorder level"
                icon="o-arrow-trending-down"
                color="{{ $invLowStock > 0 ? 'text-warning' : 'text-success' }}"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full {{ $stat }}" />
        </a>
        <a href="{{ route('purchase-orders.index') }}" class="block">
            <x-stat title="Awaiting Delivery" value="{{ $invAwaitingDelivery }}"
                description="purchase orders open"
                icon="o-truck" color="text-primary"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full {{ $stat }}" />
        </a>
    </div>

    <div class="grid grid-cols-2 gap-3 mb-5">
        <a href="{{ route('expiry-alerts.index') }}" class="block">
            <x-stat title="Expiring in 90 Days" value="{{ $invExpiringSoon }}"
                description="batches to move or return"
                icon="o-clock"
                color="{{ $invExpiringSoon > 0 ? 'text-warning' : 'text-success' }}"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full {{ $stat }}" />
        </a>
        <a href="{{ route('inventory.index') }}" class="block">
            <x-stat title="Stock Value" value="₦{{ number_format($invStockValue, 2) }}"
                description="at cost price"
                icon="o-banknotes" color="text-info"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full {{ $stat }}" />
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <x-card title="Expiring Soon" subtitle="Within 90 days">
            @forelse($invExpiringBatches as $batch)
                <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-sm truncate">{{ $batch->product?->name ?? '—' }}</div>
                        <div class="text-xs text-base-content/60">
                            Batch {{ $batch->batch_number ?? '—' }} · {{ $batch->quantity }} units
                        </div>
                    </div>
                    <span class="text-xs font-semibold shrink-0 ml-3 {{ $batch->expiry_date->diffInDays(now()) <= 30 ? 'text-error' : 'text-warning' }}">
                        {{ $batch->expiry_date->format('d M Y') }}
                    </span>
                </div>
            @empty
                <div class="text-center py-6 text-base-content/50 text-sm">Nothing expiring in the next 90 days.</div>
            @endforelse
            <div class="mt-2">
                <x-button label="All Expiry Alerts" link="{{ route('expiry-alerts.index') }}" class="btn-xs btn-ghost" icon="o-arrow-right" />
            </div>
        </x-card>

        <x-card title="Low Stock" subtitle="At or below reorder level">
            @forelse($invLowStockList as $product)
                <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-sm truncate">{{ $product->name }}</div>
                        <div class="text-xs text-base-content/60">Reorder level {{ $product->reorder_level }}</div>
                    </div>
                    <span class="text-xs font-bold text-warning shrink-0 ml-3 tabular-nums">{{ (int) $product->stock }} left</span>
                </div>
            @empty
                <div class="text-center py-6 text-base-content/50 text-sm">No products are running low.</div>
            @endforelse
            <div class="mt-2">
                <x-button label="Create Purchase Order" link="{{ route('purchase-orders.index') }}" class="btn-xs btn-ghost" icon="o-arrow-right" />
            </div>
        </x-card>
    </div>

    @endif

    @if(in_array('promoter', $panels))
    @if($multi)<div class="divider text-xs text-base-content/50 uppercase tracking-wide mt-6">Referrals</div>@endif

    {{-- Target progress: connected customers, which is what actually pays --}}
    <x-card class="mb-4">
        <div class="flex justify-between items-end mb-2">
            <div>
                <span class="text-sm font-semibold">Today's target</span>
                <p class="text-xs text-base-content/60">Customers connected to Wi-Fi</p>
            </div>
            <div class="text-right">
                <span class="text-2xl font-bold tabular-nums {{ $myProgress['redeemed'] >= $myProgress['target'] ? 'text-success' : 'text-primary' }}">
                    {{ $myProgress['redeemed'] }}
                </span>
                <span class="text-sm text-base-content/50 tabular-nums">/ {{ $myProgress['target'] }}</span>
            </div>
        </div>
        <progress class="progress {{ $myProgress['redeemed'] >= $myProgress['target'] ? 'progress-success' : 'progress-primary' }} w-full"
                  value="{{ $myProgress['percent'] }}" max="100"></progress>

        @if($myProgress['stalled'] > 0)
            <p class="text-xs text-warning mt-2">
                <x-icon name="o-exclamation-triangle" class="w-3.5 h-3.5 inline align-text-bottom" />
                {{ $myProgress['stalled'] }} customer(s) given a code but not yet connected — they don't count until they're online.
            </p>
        @endif
    </x-card>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6">
        <x-stat
            title="Codes Given"
            value="{{ $myProgress['issued'] }}"
            description="today"
            icon="o-ticket"
            color="text-primary"
            class="h-full {{ $stat }}"
        />
        <x-stat
            title="Total Earned"
            value="₦{{ number_format($myTotalEarned, 2) }}"
            description="all time"
            icon="o-currency-dollar"
            color="text-success"
            class="h-full {{ $stat }}"
        />
        <x-stat
            title="Pending Payout"
            value="₦{{ number_format($myPending, 2) }}"
            description="awaiting payment"
            icon="o-clock"
            color="{{ $myPending > 0 ? 'text-warning' : 'text-base-content/40' }}"
            class="h-full col-span-2 sm:col-span-1 {{ $stat }}"
        />
    </div>

    <x-card title="Today's Customers">
        @forelse($myRecentCodes as $code)
            <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                <div class="min-w-0 flex-1">
                    <div class="font-semibold text-sm truncate">{{ $code->customer?->name ?? '—' }}</div>
                    <div class="text-xs text-base-content/60">
                        <span class="font-mono">{{ $code->code }}</span> · {{ $code->created_at->format('H:i') }}
                    </div>
                </div>
                @if($code->redeemed_at)
                    <span class="badge badge-success badge-sm shrink-0 ml-3">Connected</span>
                @else
                    <span class="badge badge-ghost badge-sm shrink-0 ml-3">Not yet</span>
                @endif
            </div>
        @empty
            <div class="text-center py-8 text-base-content/40 text-sm">
                No one registered yet today — head to Customers to get started.
            </div>
        @endforelse
        <div class="mt-3 flex flex-wrap gap-2">
            <x-button label="Register Customer" link="{{ route('customers.index') }}" class="btn-sm btn-primary" icon="o-plus" />
            <x-button label="My Commissions" link="{{ route('commissions.index') }}" class="btn-sm btn-ghost" icon="o-currency-dollar" />
        </div>
    </x-card>
    @endif

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

    @php $seesRevenue = (bool) array_intersect(auth()->user()->role ?? [], ['admin', 'pharmacist', 'branch_manager', 'sales', 'cashier']); @endphp

    @if($seesRevenue)
        {{-- The money story, left to right, in the order it happens:
             expected → less discounts → less owed → what reached the drawer. --}}
        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 mb-2">
            <x-stat
                title="Expected Sales"
                value="₦{{ number_format($expectedSales, 2) }}"
                description="{{ $salesCountToday }} sales, before discount"
                icon="o-receipt-percent"
                color="text-base-content"
                class="text-sm h-full {{ $stat }}"
            />

            <x-stat
                title="Discounts"
                value="₦{{ number_format($discountsGiven, 2) }}"
                description="given to customers"
                icon="o-tag"
                color="{{ $discountsGiven > 0 ? 'text-warning' : 'text-base-content/40' }}"
                class="text-sm h-full {{ $stat }}"
            />

            <a href="{{ route('debt-book.index') }}" class="block">
                <x-stat
                    title="Owed"
                    value="₦{{ number_format($owedFromPeriod, 2) }}"
                    description="unpaid on these sales"
                    icon="o-clock"
                    color="{{ $owedFromPeriod > 0 ? 'text-error' : 'text-base-content/40' }}"
                    class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full {{ $stat }}"
                />
            </a>

            <x-stat
                title="Money Collected"
                value="₦{{ number_format($cashCollectedToday, 2) }}"
                description="actually received"
                icon="o-wallet"
                color="text-success"
                class="text-sm h-full {{ $stat }}"
            />

            @if(array_intersect(auth()->user()->role ?? [],['admin', 'pharmacist', 'branch_manager']))
                <a href="{{ route('reports.index') }}" class="block">
                    <x-stat
                        title="Profit"
                        value="₦{{ number_format($todayProfit, 2) }}"
                        description="after cost of goods"
                        icon="{{ $todayProfit >= 0 ? 'o-arrow-trending-up' : 'o-arrow-trending-down' }}"
                        color="{{ $todayProfit >= 0 ? 'text-success' : 'text-error' }}"
                        class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full {{ $stat }}"
                    />
                </a>
            @endif
        </div>

        {{-- Spell out the arithmetic, so a gap between expected and collected
             reads as an explanation rather than a discrepancy. --}}
        <p class="text-xs text-base-content/50 mb-5 tabular-nums">
            ₦{{ number_format($expectedSales, 2) }} expected
            − ₦{{ number_format($discountsGiven, 2) }} discount
            − ₦{{ number_format($owedFromPeriod, 2) }} owed
            @if($oldDebtRepaid > 0)
                + ₦{{ number_format($oldDebtRepaid, 2) }} older debt repaid
            @endif
            = <span class="font-semibold text-base-content/70">₦{{ number_format($cashCollectedToday, 2) }} collected</span>
        </p>
    @endif

    {{-- Stock at a glance. Kept brief: Low Stock and Expiry below carry detail. --}}
    <div class="grid grid-cols-2 gap-3 mb-5">
        <a href="{{ route('products.index') }}" class="block">
            <x-stat
                title="Products"
                value="{{ $totalProducts }}"
                description="{{ $totalStock }} in stock"
                icon="o-cube"
                color="text-info"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full {{ $stat }}"
            />
        </a>
        <a href="{{ route('products.index', ['stockFilter' => 'out_of_stock']) }}" class="block">
            <x-stat
                title="Out of Stock"
                value="{{ $outOfStock }}"
                description="need restocking"
                icon="o-exclamation-circle"
                color="{{ $outOfStock > 0 ? 'text-error' : 'text-success' }}"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full {{ $stat }}"
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
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full {{ $stat }}"
            />
        </a>
        <a href="{{ route('online-orders.index') }}" class="block">
            <x-stat
                title="Pending Online"
                value="{{ $pendingOnlineOrders }}"
                description="Awaiting processing"
                icon="o-clock"
                color="{{ $pendingOnlineOrders > 0 ? 'text-warning' : 'text-base-content/40' }}"
                class="text-sm hover:bg-base-200 transition-colors cursor-pointer h-full {{ $stat }}"
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

        @if($seesRevenue)
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
        @endif

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

    {{-- Hot products: the same sales read three ways --}}
    @if(array_intersect(auth()->user()->role ?? [], ['admin', 'branch_manager']))
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
            @foreach([
                ['key' => 'byUnits',   'title' => 'Most units sold',  'sub' => 'what walks out of the door', 'metric' => 'units',   'money' => false],
                ['key' => 'byRevenue', 'title' => 'Most revenue',     'sub' => 'biggest sellers by value',   'metric' => 'revenue', 'money' => true],
                ['key' => 'byProfit',  'title' => 'Most profit',      'sub' => 'what actually earns',        'metric' => 'profit',  'money' => true],
            ] as $panel)
                <x-card :title="$panel['title']" :subtitle="$panel['sub']">
                    @forelse($hot[$panel['key']] as $i => $row)
                        <div class="flex items-center gap-3 p-2 border-b border-base-200 last:border-0">
                            <span class="text-xs font-bold text-base-content/30 w-4 shrink-0 tabular-nums">{{ $i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium truncate">{{ $row->name }}</div>
                                <div class="text-xs text-base-content/50">
                                    {{ $row->times_sold }} {{ \Illuminate\Support\Str::plural('sale', $row->times_sold) }}
                                    @if($row->times_sold == 1 && $row->units > 5)
                                        <span class="text-warning">· one bulk order</span>
                                    @endif
                                </div>
                            </div>
                            <span class="text-sm font-bold shrink-0 tabular-nums {{ $panel['money'] ? 'text-primary' : '' }}">
                                @if($panel['money'])
                                    ₦{{ number_format((float) $row->{$panel['metric']}, 0) }}
                                @else
                                    {{ number_format((float) $row->{$panel['metric']}) }}
                                @endif
                            </span>
                        </div>
                    @empty
                        <div class="text-center py-6 text-base-content/40 text-sm">No sales in this period.</div>
                    @endforelse
                </x-card>
            @endforeach
        </div>

        @if($hot['any'] && $hot['byUnits']->first() && $hot['byProfit']->first()
            && $hot['byUnits']->first()->id !== $hot['byProfit']->first()->id)
            {{-- Worth saying out loud: the busiest product is not the earner. --}}
            <div class="alert alert-info py-2 mt-3 text-sm gap-2">
                <x-icon name="o-light-bulb" class="w-4 h-4 shrink-0" />
                <span>
                    <strong>{{ $hot['byUnits']->first()->name }}</strong> moves the most units,
                    but <strong>{{ $hot['byProfit']->first()->name }}</strong> earns you the most
                    (₦{{ number_format((float) $hot['byProfit']->first()->profit, 0) }}).
                </span>
            </div>
        @endif

        <x-card title="Asked for, not stocked" subtitle="Searches at the till that found nothing" class="mt-4">
            @forelse($missedDemand as $miss)
                <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                    <div class="min-w-0">
                        <div class="text-sm font-medium">{{ $miss->term }}</div>
                        <div class="text-xs text-base-content/50">
                            last {{ $miss->last_searched_at?->diffForHumans() }}
                            @if($miss->lastUser) · {{ $miss->lastUser->name }} @endif
                        </div>
                    </div>
                    <span class="badge badge-warning badge-sm shrink-0">
                        {{ $miss->times }}&times;
                    </span>
                </div>
            @empty
                <div class="text-center py-6 text-base-content/50 text-sm">
                    Nothing so far — every search at the till found a product.
                </div>
            @endforelse
            <p class="text-xs text-base-content/50 mt-2">
                These are sales you could not make. Sales reports can never show them.
            </p>
        </x-card>
    @endif

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
