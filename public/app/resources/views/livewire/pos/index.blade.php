<div wire:poll.5s>
    <x-header title="Point of Sale" subtitle="Create invoices for customers" size="text-xl">
        @if($isWholesale)
            <x-slot:actions>
                <x-badge value="WHOLESALE" class="badge-info" />
            </x-slot:actions>
        @endif
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Product Selection (always visible) -->
        <div class="lg:col-span-2 pb-20 lg:pb-0">
            <x-input icon="o-magnifying-glass" placeholder="Search products, SKU or barcode..." wire:model.live.debounce="search" clearable class="mb-3" />

            @if($searchWasFuzzy)
                {{-- Near-misses must never look like an exact hit on a dispensing screen. --}}
                <div class="alert alert-warning py-2 mb-3 text-sm gap-2">
                    <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
                    <span>
                        No exact match for <span class="font-semibold">"{{ $search }}"</span> —
                        showing closest names. <span class="font-semibold">Check carefully before adding.</span>
                    </span>
                </div>
            @endif

            <div class="grid grid-cols-2 gap-2">
                @forelse($products as $product)
                    @php $stock = $product->batches->sum('quantity'); @endphp
                    <button
                        wire:click="addToCart({{ $product->id }})"
                        @class([
                            'p-2 rounded-lg border text-left transition-all active:scale-95',
                            'border-base-300' => $stock > 0 && ! $searchWasFuzzy,
                            'border-warning border-dashed' => $stock > 0 && $searchWasFuzzy,
                            'border-error/30 opacity-50' => $stock == 0,
                        ])
                        @disabled($stock == 0)
                    >
                        <div class="font-semibold text-xs sm:text-sm truncate">{{ $product->name }}</div>
                        <div class="text-primary font-bold text-sm">
                            @if($isWholesale && $product->wholesale_price)
                                ₦{{ number_format($product->wholesale_price, 2) }}
                            @else
                                ₦{{ number_format($product->selling_price, 2) }}
                            @endif
                        </div>
                        <div class="text-xs text-base-content/60">Stock: {{ $stock }}</div>
                    </button>
                @empty
                    <div class="col-span-full text-center py-8 text-base-content/60">
                        @if($search)
                            Nothing matches "{{ $search }}" — try fewer words.
                        @else
                            No products found.
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Desktop Cart (hidden on mobile) -->
        <div class="hidden lg:block">
            @include('livewire.pos._cart')
        </div>
    </div>

    <!-- Desktop Invoices (hidden on mobile) -->
    <div class="hidden lg:block">
        @include('livewire.pos._invoices')
    </div>

    <!-- Mobile floating action bar -->
    <div class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-base-100 border-t border-base-300 shadow-lg">
        <div class="flex">
            <button onclick="document.getElementById('mobile-invoices-modal').showModal()" class="flex-1 flex items-center justify-center gap-2 py-3 active:bg-base-200 relative">
                <x-icon name="o-clipboard-document-list" class="w-6 h-6" />
                @if($myInvoices->count())
                    <span class="absolute top-1 right-1/2 translate-x-5 badge badge-xs badge-warning">{{ $myInvoices->count() }}</span>
                @endif
            </button>
            <div class="w-px bg-base-300"></div>
            <button onclick="document.getElementById('mobile-cart-modal').showModal()" class="flex-1 flex items-center justify-center gap-2 py-3 active:bg-base-200 relative">
                <x-icon name="o-shopping-cart" class="w-6 h-6" />
                @if(count($cart))
                    <span class="absolute top-1 right-1/2 translate-x-5 badge badge-xs badge-primary">{{ count($cart) }}</span>
                @endif
                @if(count($cart))
                    <span class="text-sm font-bold text-primary">₦{{ number_format($cartTotal, 0) }}</span>
                @endif
            </button>
        </div>
    </div>

    <!-- Mobile Cart Modal -->
    <dialog id="mobile-cart-modal" class="modal modal-bottom">
        <div class="modal-box max-h-[90vh] flex flex-col p-0">
            <div class="flex items-center justify-between px-4 py-3 border-b border-base-200 shrink-0">
                <h3 class="font-bold text-lg">Cart ({{ count($cart) }})</h3>
                <form method="dialog"><button class="btn btn-sm btn-circle btn-ghost">✕</button></form>
            </div>
            <div class="overflow-y-auto flex-1 px-4 py-3">
                @include('livewire.pos._cart')
            </div>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <!-- Mobile Invoices Modal -->
    <dialog id="mobile-invoices-modal" class="modal modal-bottom">
        <div class="modal-box max-h-[90vh] flex flex-col p-0">
            <div class="flex items-center justify-between px-4 py-3 border-b border-base-200 shrink-0">
                <h3 class="font-bold text-lg">My Invoices</h3>
                <form method="dialog"><button class="btn btn-sm btn-circle btn-ghost">✕</button></form>
            </div>
            <div class="overflow-y-auto flex-1 px-4 py-3">
                @include('livewire.pos._invoices-mobile')
            </div>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    {{-- Create Customer Modal — lives here so it's never nested inside a <dialog> --}}
    <x-modal wire:model="createCustomerModal" title="New Customer" box-class="max-w-sm">
        <x-form wire:submit="createCustomer">
            <x-input label="Name" wire:model="newCustomerName" placeholder="Full name" required autofocus />
            <x-input label="Phone" wire:model="newCustomerPhone" placeholder="08012345678" />
            <x-input label="Email" wire:model="newCustomerEmail" type="email" placeholder="optional" />
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.createCustomerModal = false" />
                <x-button label="Create & Select" type="submit" class="btn-primary" icon="o-user-plus" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    @script
    <script>
        $wire.on('invoice-paid', () => {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = 600;
                gain.gain.value = 0.3;
                osc.start();
                osc.stop(ctx.currentTime + 0.15);
                setTimeout(() => {
                    const o2 = ctx.createOscillator();
                    const g2 = ctx.createGain();
                    o2.connect(g2);
                    g2.connect(ctx.destination);
                    o2.frequency.value = 900;
                    g2.gain.value = 0.3;
                    o2.start();
                    o2.stop(ctx.currentTime + 0.15);
                    setTimeout(() => {
                        const o3 = ctx.createOscillator();
                        const g3 = ctx.createGain();
                        o3.connect(g3);
                        g3.connect(ctx.destination);
                        o3.frequency.value = 1200;
                        g3.gain.value = 0.3;
                        o3.start();
                        o3.stop(ctx.currentTime + 0.25);
                    }, 200);
                }, 200);
            } catch(e) {}
        });
    </script>
    @endscript
</div>
