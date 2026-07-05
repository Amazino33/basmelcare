@if(count($cart))
    <div class="space-y-2 max-h-60 lg:max-h-96 overflow-y-auto">
        @foreach($cart as $key => $item)
            <div class="flex items-center gap-2 p-2 rounded bg-base-200">
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-xs truncate">{{ $item['name'] }}</div>
                    <div class="text-xs text-base-content/60">
                        ₦{{ number_format($item['unit_price'], 2) }} × {{ $item['qty'] }}
                        @if($item['unit_price'] < $item['retail_price'])
                            <span class="text-info">(w/s)</span>
                        @endif
                    </div>
                </div>
                <input type="number" wire:change="updateQty('{{ $key }}', $event.target.value)" value="{{ $item['qty'] }}" min="1" max="{{ $item['max_qty'] }}" class="input input-xs input-bordered w-14 text-center" />
                <x-button icon="o-x-mark" wire:click="removeFromCart('{{ $key }}')" class="btn-xs btn-ghost text-error" />
            </div>
        @endforeach
    </div>

    <x-hr />

    <div class="text-right text-lg font-bold text-primary mb-3">
        ₦{{ number_format($cartTotal, 2) }}
    </div>

    {{-- Customer search (fully client-side filtering via Alpine.js) --}}
    <div class="mt-2"
        x-data="{
            all: {{ $customers->toJson() }},
            search: '',
            open: false,
            get results() {
                if (!this.search) return [];
                const q = this.search.toLowerCase();
                return this.all.filter(c =>
                    c.name.toLowerCase().includes(q) ||
                    (c.phone && c.phone.includes(q))
                ).slice(0, 15);
            }
        }"
        @click.outside="open = false; search = ''">

        <label class="label py-1"><span class="label-text text-sm font-semibold">Customer</span></label>

        @if($selectedCustomer)
            <div class="flex items-center gap-2 input input-bordered input-sm w-full pr-1">
                <span class="flex-1 text-sm truncate">{{ $selectedCustomer->name }}
                    @if($selectedCustomer->phone)
                        <span class="text-base-content/50 text-xs ml-1">{{ $selectedCustomer->phone }}</span>
                    @endif
                </span>
                <button wire:click="clearCustomer" class="btn btn-ghost btn-xs text-error shrink-0">✕</button>
            </div>
        @else
            <div class="relative">
                <input
                    type="text"
                    x-model="search"
                    @focus="open = true"
                    @input="open = true"
                    placeholder="Search name or phone..."
                    class="input input-bordered input-sm w-full"
                    autocomplete="off"
                />

                <div x-show="open && results.length > 0"
                    class="absolute z-200 w-full bg-base-100 border border-base-300 rounded-lg shadow-2xl mt-1 max-h-52 overflow-y-auto">
                    <template x-for="c in results" :key="c.id">
                        <button
                            @mousedown.prevent="$wire.selectCustomer(c.id); open = false; search = ''"
                            class="w-full text-left px-3 py-2 hover:bg-base-200 border-b border-base-200 last:border-0 transition-colors">
                            <div class="font-semibold text-sm" x-text="c.name"></div>
                            <div class="text-xs text-base-content/50" x-text="c.phone ?? ''"></div>
                        </button>
                    </template>
                </div>
            </div>
        @endif
    </div>
    <x-textarea label="Note" wire:model="note" placeholder="Optional" class="mt-2" rows="2" />

    <x-button label="Create Invoice" wire:click="createInvoice" icon="o-document-text" class="btn-primary btn-block mt-3" wire:confirm="Create this invoice?" />
    <button wire:click="clearCart" wire:confirm="Clear all items?" class="btn btn-ghost btn-xs text-error btn-block mt-1">
        <x-icon name="o-trash" class="w-3 h-3" /> Clear Cart
    </button>
@else
    @if($lastSale)
        <div class="text-center py-4">
            <x-icon name="o-check-circle" class="w-10 h-10 mx-auto mb-2 text-success" />
            <p class="font-semibold text-sm mb-1">{{ $lastSale->invoice_number }}</p>
            <p class="text-xs text-base-content/60 mb-3">Invoice created. Print for customer.</p>
            <x-button label="Print Invoice" link="{{ route('invoice.show', $lastSale->id) }}" icon="o-printer" class="btn-sm btn-primary" external />
            <x-button label="New Invoice" wire:click="$set('lastSaleId', null)" class="btn-sm btn-ghost mt-2" icon="o-plus" />
        </div>
    @else
        <div class="text-center py-6 text-base-content/60">
            <x-icon name="o-shopping-cart" class="w-10 h-10 mx-auto mb-2 opacity-30" />
            <p class="text-sm">Cart is empty</p>
        </div>
    @endif
@endif
