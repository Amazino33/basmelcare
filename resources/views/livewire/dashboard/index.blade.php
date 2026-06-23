<div>
    <x-header title="Dashboard" subtitle="BasmelCare Pharmacy Overview" size="text-xl" />

    <!-- Setup Progress Bar -->
    @if($setupProgress['percent'] < 100)
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
        <x-stat
            title="Today's Sales"
            value="₦{{ number_format($totalSalesToday, 2) }}"
            description="{{ $salesCountToday }} transactions"
            icon="o-banknotes"
            color="text-primary"
            class="text-sm"
        />
        <x-stat
            title="Today's Profit"
            value="₦{{ number_format($todayProfit, 2) }}"
            description="Revenue - cost"
            icon="o-arrow-trending-up"
            color="{{ $todayProfit >= 0 ? 'text-success' : 'text-error' }}"
            class="text-sm"
        />
        <x-stat
            title="Products"
            value="{{ $totalProducts }}"
            description="{{ $totalStock }} in stock"
            icon="o-cube"
            color="text-info"
            class="text-sm"
        />
        <x-stat
            title="Out of Stock"
            value="{{ $outOfStock }}"
            description="Need restocking"
            icon="o-exclamation-circle"
            color="text-error"
            class="text-sm"
        />
    </div>

    <!-- Potential Profit -->
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
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Expiry Alerts -->
        <x-card title="Expiry Alerts" subtitle="Within 90 days">
            @if($expiredBatches > 0)
                <x-alert title="{{ $expiredBatches }} expired!" icon="o-exclamation-triangle" class="alert-error mb-3" />
            @endif

            @forelse($expiringBatches as $batch)
                <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
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
                </div>
            @empty
                <div class="text-center py-4 text-base-content/60 text-sm">No products expiring soon.</div>
            @endforelse

            @if($expiringBatches->count())
                <div class="mt-2">
                    <x-button label="View All" link="{{ route('expiry-alerts.index') }}" class="btn-xs btn-ghost" icon="o-arrow-right" />
                </div>
            @endif
        </x-card>

        <!-- Low Stock -->
        <x-card title="Low Stock" subtitle="Below reorder level">
            @forelse($lowStockProducts as $product)
                <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-xs sm:text-sm truncate">{{ $product->name }}</div>
                        <div class="text-xs text-base-content/60">{{ $product->category?->name }}</div>
                    </div>
                    <div class="text-right ml-2 shrink-0">
                        <div class="text-sm font-bold text-warning">{{ $product->batches->sum('quantity') }}</div>
                        <div class="text-xs text-base-content/60">/ {{ $product->reorder_level }}</div>
                    </div>
                </div>
            @empty
                <div class="text-center py-4 text-base-content/60 text-sm">All stocked.</div>
            @endforelse
        </x-card>

        <!-- Recent Sales -->
        <x-card title="Recent Sales" class="lg:col-span-2">
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
        </x-card>
    </div>

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
