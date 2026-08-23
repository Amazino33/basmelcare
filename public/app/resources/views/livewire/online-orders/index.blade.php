<div wire:poll.5s>
    <x-header title="Online Orders" subtitle="Process customer orders from the website" size="text-xl" />

    <!-- Filter tabs -->
    <div class="flex overflow-x-auto gap-2 mb-4 scrollbar-hide">
        <button wire:click="$set('filter', 'new')" @class(['btn btn-sm', 'btn-primary' => $filter === 'new', 'btn-ghost border border-base-300' => $filter !== 'new'])>
            New
            @if($newCount > 0)
                <span class="badge badge-xs badge-error">{{ $newCount }}</span>
            @endif
        </button>
        <button wire:click="$set('filter', 'mine')" @class(['btn btn-sm', 'btn-primary' => $filter === 'mine', 'btn-ghost border border-base-300' => $filter !== 'mine'])>My Orders</button>
        <button wire:click="$set('filter', 'processing')" @class(['btn btn-sm', 'btn-primary' => $filter === 'processing', 'btn-ghost border border-base-300' => $filter !== 'processing'])>Processing</button>
        <button wire:click="$set('filter', 'ready')" @class(['btn btn-sm', 'btn-primary' => $filter === 'ready', 'btn-ghost border border-base-300' => $filter !== 'ready'])>Ready</button>
        <button wire:click="$set('filter', 'dispatched')" @class(['btn btn-sm', 'btn-primary' => $filter === 'dispatched', 'btn-ghost border border-base-300' => $filter !== 'dispatched'])>Dispatched</button>
        <button wire:click="$set('filter', 'completed')" @class(['btn btn-sm', 'btn-primary' => $filter === 'completed', 'btn-ghost border border-base-300' => $filter !== 'completed'])>Completed</button>
        <button wire:click="$set('filter', 'all')" @class(['btn btn-sm', 'btn-primary' => $filter === 'all', 'btn-ghost border border-base-300' => $filter !== 'all'])>All</button>
    </div>

    <!-- Orders list -->
    <div class="space-y-3">
        @forelse($orders as $order)
            <div @class([
                'card border p-4',
                'bg-warning/5 border-warning/30' => $order->status === 'pending' && !$order->claimed_by,
                'bg-info/5 border-info/30' => $order->status === 'processing',
                'bg-primary/5 border-primary/30' => $order->status === 'ready',
                'bg-purple-500/5 border-purple-500/30' => $order->status === 'dispatched',
                'bg-base-100 border-base-200' => in_array($order->status, ['completed', 'cancelled']),
            ])>
                <div class="flex justify-between items-start">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-sm">{{ $order->order_number }}</span>
                            <span @class([
                                'badge badge-xs',
                                'badge-warning'  => $order->status === 'pending',
                                'badge-info'     => $order->status === 'processing',
                                'badge-primary'  => $order->status === 'ready',
                                'badge-ghost'    => $order->status === 'dispatched',
                                'badge-success'  => $order->status === 'completed',
                                'badge-error'    => $order->status === 'cancelled',
                            ])>{{ ucfirst($order->status) }}</span>
                            <span @class([
                                'badge badge-xs',
                                'badge-warning' => $order->payment_status === 'pending',
                                'badge-success' => $order->payment_status === 'paid',
                            ])>{{ $order->payment_status === 'pending' ? 'COD' : 'Paid' }}</span>
                            @if($order->cashier_verified_at)
                                <span class="badge badge-xs badge-success">Cashier ✓</span>
                            @endif
                        </div>

                        <div class="text-xs text-base-content/60 mt-1">
                            {{ $order->customer?->name ?? $order->guest_name ?? 'Guest' }}
                            | {{ $order->customer?->phone ?? $order->guest_phone ?? '' }}
                            | {{ ucfirst($order->fulfillment_type) }}
                        </div>

                        <div class="text-xs text-base-content/60 mt-0.5">
                            {{ $order->items->count() }} items | {{ $order->created_at->diffForHumans() }}
                        </div>

                        @if($order->claimed_by)
                            <div class="text-xs mt-1">
                                <span class="text-info font-semibold">Claimed by: {{ $order->claimedByUser?->name }}</span>
                            </div>
                        @endif

                        @if($order->status === 'dispatched' && $order->delivery_person_name)
                            <div class="text-xs mt-1 text-purple-600 dark:text-purple-400 font-medium">
                                Rider: {{ $order->delivery_person_name }}
                                @if($order->delivery_person_phone) · {{ $order->delivery_person_phone }}@endif
                            </div>
                        @endif
                    </div>

                    <div class="text-right ml-3 shrink-0">
                        <div class="font-bold text-primary">₦{{ number_format($order->total_amount, 0) }}</div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-wrap gap-2 mt-3">
                    <x-button icon="o-eye" wire:click="viewDetails({{ $order->id }})" class="btn-xs btn-ghost" tooltip="Details" />

                    {{-- Print invoice (once order is claimed) --}}
                    @if($order->claimed_by)
                        <a href="{{ route('order.invoice', $order->id) }}" target="_blank" class="btn btn-xs btn-ghost tooltip" data-tip="Print Invoice">
                            <x-icon name="o-printer" class="w-3 h-3" />
                        </a>
                    @endif

                    @if(!$order->claimed_by && in_array($order->status, ['pending', 'processing']))
                        <x-button label="Claim" wire:click="claimOrder({{ $order->id }})" class="btn-xs btn-primary" icon="o-hand-raised" wire:confirm="Claim this order? You'll be responsible for processing it." />
                    @endif

                    @if($order->claimed_by === auth()->id())
                        @if($order->status === 'processing')
                            <x-button label="Ready" wire:click="markReady({{ $order->id }})" class="btn-xs btn-info" icon="o-check" />
                        @endif

                        {{-- Dispatch — delivery orders that cashier has approved --}}
                        @if($order->status === 'ready' && $order->cashier_verified_at && $order->fulfillment_type === 'delivery')
                            <x-button label="Dispatch" wire:click="openDispatch({{ $order->id }})" class="btn-xs btn-primary" icon="o-truck" />
                        @endif

                        {{-- Complete — pickup orders that cashier verified + paid --}}
                        @if($order->status === 'ready' && $order->cashier_verified_at && $order->payment_status === 'paid' && $order->fulfillment_type === 'pickup')
                            <x-button label="Complete" wire:click="completeOrder({{ $order->id }})" class="btn-xs btn-success" icon="o-check-circle" wire:confirm="Mark as completed and handed over?" />
                        @endif

                        {{-- Awaiting cashier hint --}}
                        @if($order->status === 'ready' && !$order->cashier_verified_at)
                            <span class="text-xs text-warning italic self-center">Awaiting cashier verification</span>
                        @endif

                        @if($order->status !== 'completed' && $order->status !== 'dispatched')
                            <x-button icon="o-x-mark" wire:click="cancelOrder({{ $order->id }})" class="btn-xs btn-ghost text-error" tooltip="Cancel" wire:confirm="Cancel this order?" />
                        @endif
                    @endif

                    {{-- Print receipt after cashier verify --}}
                    @if($order->cashier_verified_at && in_array($order->status, ['ready', 'dispatched', 'completed']))
                        <a href="{{ route('order.receipt', $order->id) }}" target="_blank" class="btn btn-xs btn-outline btn-success">
                            <x-icon name="o-document-check" class="w-3 h-3" /> Receipt
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center py-10 text-base-content/60">
                <x-icon name="o-inbox" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                <p class="text-sm">No orders in this view</p>
            </div>
        @endforelse
    </div>

    <!-- Order Details Modal -->
    <x-modal wire:model="detailsModal" title="Order Details" box-class="max-w-lg">
        @if($viewOrder)
            <div class="space-y-3">
                <div class="bg-base-200 rounded-lg p-3">
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><span class="text-base-content/60">Order:</span> <span class="font-bold">{{ $viewOrder->order_number }}</span></div>
                        <div><span class="text-base-content/60">Status:</span>
                            <span @class(['badge badge-xs', 'badge-warning' => $viewOrder->status === 'pending', 'badge-info' => $viewOrder->status === 'processing', 'badge-primary' => $viewOrder->status === 'ready', 'badge-success' => $viewOrder->status === 'completed', 'badge-error' => $viewOrder->status === 'cancelled'])>{{ ucfirst($viewOrder->status) }}</span>
                        </div>
                        <div><span class="text-base-content/60">Customer:</span> {{ $viewOrder->customer?->name ?? $viewOrder->guest_name ?? 'Guest' }}</div>
                        <div><span class="text-base-content/60">Phone:</span> {{ $viewOrder->customer?->phone ?? $viewOrder->guest_phone ?? '—' }}</div>
                        <div><span class="text-base-content/60">Type:</span> {{ ucfirst($viewOrder->fulfillment_type) }}</div>
                        <div><span class="text-base-content/60">Payment:</span> {{ ucfirst($viewOrder->payment_method ?? '—') }} ({{ ucfirst($viewOrder->payment_status) }})</div>
                    </div>

                    @if($viewOrder->delivery_address)
                        <div class="mt-2 text-sm">
                            <span class="text-base-content/60">Address:</span> {{ $viewOrder->delivery_address }}
                        </div>
                    @endif

                    @if($viewOrder->note)
                        <div class="mt-2 text-sm">
                            <span class="text-base-content/60">Note:</span> {{ $viewOrder->note }}
                        </div>
                    @endif

                    @if($viewOrder->claimed_by)
                        <div class="mt-2 text-sm">
                            <span class="text-base-content/60">Claimed by:</span> <span class="font-semibold text-info">{{ $viewOrder->claimedByUser?->name }}</span>
                            <span class="text-base-content/40">{{ $viewOrder->claimed_at?->diffForHumans() }}</span>
                        </div>
                    @endif
                    @if($viewOrder->cashier_verified_at)
                        <div class="mt-2 text-sm">
                            <span class="text-base-content/60">Verified by cashier:</span>
                            <span class="font-semibold text-success">{{ $viewOrder->verifiedBy?->name ?? '—' }}</span>
                            <span class="text-base-content/40">{{ $viewOrder->cashier_verified_at->format('d/m H:i') }}</span>
                        </div>
                    @endif
                    @if($viewOrder->dispatched_at)
                        <div class="mt-2 text-sm">
                            <span class="text-base-content/60">Dispatched:</span>
                            <span class="font-semibold">{{ $viewOrder->dispatched_at->format('d/m H:i') }}</span>
                            · {{ $viewOrder->delivery_person_name }}
                            @if($viewOrder->delivery_person_phone)({{ $viewOrder->delivery_person_phone }})@endif
                        </div>
                    @endif
                </div>

                <div class="text-sm font-semibold">Items</div>
                @foreach($viewOrder->items as $item)
                    <div class="flex justify-between items-center p-2 bg-base-200 rounded text-sm">
                        <div>
                            <div class="font-semibold">{{ $item->product->name }}</div>
                            <div class="text-xs text-base-content/60">{{ $item->quantity }} × ₦{{ number_format($item->unit_price, 2) }}</div>
                        </div>
                        <span class="font-bold">₦{{ number_format($item->subtotal, 2) }}</span>
                    </div>
                @endforeach

                <div class="border-t border-base-200 pt-2">
                    <div class="flex justify-between text-sm"><span class="text-base-content/60">Subtotal</span><span>₦{{ number_format($viewOrder->subtotal, 2) }}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-base-content/60">Delivery</span><span>{{ $viewOrder->delivery_fee > 0 ? '₦' . number_format($viewOrder->delivery_fee, 2) : 'Free' }}</span></div>
                    <div class="flex justify-between font-bold text-base mt-1"><span>Total</span><span class="text-primary">₦{{ number_format($viewOrder->total_amount, 2) }}</span></div>
                </div>

                @if($viewOrder->prescription_path)
                    <a href="{{ Storage::disk('public_site')->url($viewOrder->prescription_path) }}" class="btn btn-ghost btn-sm btn-block" target="_blank">
                        <x-icon name="o-document" class="w-4 h-4" /> View Prescription
                    </a>
                @endif
            </div>
        @endif
    </x-modal>

    <!-- Dispatch Modal -->
    <x-modal wire:model="dispatchModal" title="Assign & Dispatch" box-class="max-w-sm">
        @if($dispatchingOrder)
            <div class="bg-base-200 rounded-lg p-3 mb-4 text-sm">
                <div class="font-bold">{{ $dispatchingOrder->order_number }}</div>
                <div class="text-xs text-base-content/60">
                    {{ $dispatchingOrder->customer?->name ?? $dispatchingOrder->guest_name ?? 'Guest' }}
                    · ₦{{ number_format($dispatchingOrder->total_amount, 2) }}
                    @if($dispatchingOrder->isCod()) · <span class="text-warning font-semibold">COD — rider collects on delivery</span>@endif
                </div>
                @if($dispatchingOrder->delivery_address)
                    <div class="text-xs mt-1"><span class="text-base-content/60">Address:</span> {{ $dispatchingOrder->delivery_address }}</div>
                @endif
            </div>

            <x-form wire:submit="dispatchOrder">
                @if($deliveryUsers->count() > 0)
                    <x-choices-offline
                        label="Delivery Staff (optional)"
                        wire:model.live="dispatch_name"
                        :options="$deliveryUsers->map(fn($u) => ['id' => $u->name, 'name' => $u->name . ($u->phone ? ' · ' . $u->phone : '')])"
                        option-value="id"
                        option-label="name"
                        placeholder="Select or type below..."
                        single
                        searchable
                        hint="Selecting will fill the name below"
                    />
                @endif

                <x-input
                    wire:model="dispatch_name"
                    label="Rider Name"
                    placeholder="e.g. Ahmed"
                    required
                />
                <x-input
                    wire:model="dispatch_phone"
                    label="Rider Phone (optional)"
                    placeholder="08012345678"
                />

                <x-slot:actions>
                    <x-button label="Cancel" @click="$wire.dispatchModal = false" />
                    <x-button label="Dispatch Now" type="submit" class="btn-primary" icon="o-truck" />
                </x-slot:actions>
            </x-form>
        @endif
    </x-modal>

    @script
    <script>
        $wire.on('new-online-order', () => {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                [400, 600, 800, 1000].forEach((freq, i) => {
                    setTimeout(() => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.frequency.value = freq;
                        gain.gain.value = 0.2;
                        osc.start();
                        osc.stop(ctx.currentTime + 0.15);
                    }, i * 150);
                });
            } catch(e) {}
        });
    </script>
    @endscript
</div>
