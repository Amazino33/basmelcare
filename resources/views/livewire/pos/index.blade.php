<div>
    <x-header title="Point of Sale" subtitle="Create a new sale">
        @if($isWholesale)
            <x-slot:actions>
                <x-badge value="WHOLESALE CUSTOMER" class="badge-info badge-lg" />
            </x-slot:actions>
        @endif
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Product Selection -->
        <div class="lg:col-span-2">
            <x-card title="Products">
                <x-slot:menu>
                    <x-input icon="o-magnifying-glass" placeholder="Search products..." wire:model.live.debounce="search" clearable class="w-64" />
                </x-slot:menu>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    @forelse($products as $product)
                        @php $stock = $product->batches->sum('quantity'); @endphp
                        <button
                            wire:click="addToCart({{ $product->id }})"
                            @class([
                                'p-3 rounded-lg border text-left transition-all hover:shadow-md',
                                'border-base-300 hover:border-primary' => $stock > 0,
                                'border-error/30 opacity-50 cursor-not-allowed' => $stock == 0,
                            ])
                            @disabled($stock == 0)
                        >
                            <div class="font-semibold text-sm truncate">{{ $product->name }}</div>
                            <div class="text-primary font-bold">
                                @if($isWholesale && $product->wholesale_price)
                                    ₦{{ number_format($product->wholesale_price, 2) }}
                                    <span class="text-xs line-through text-base-content/40">₦{{ number_format($product->selling_price, 2) }}</span>
                                @else
                                    ₦{{ number_format($product->selling_price, 2) }}
                                @endif
                            </div>
                            <div class="text-xs text-base-content/60">
                                Stock: {{ $stock }}
                                @if($product->wholesale_price && $product->wholesale_min_qty && !$isWholesale)
                                    | W/S: {{ $product->wholesale_min_qty }}+
                                @endif
                            </div>
                        </button>
                    @empty
                        <div class="col-span-full text-center py-8 text-base-content/60">No products found.</div>
                    @endforelse
                </div>
            </x-card>
        </div>

        <!-- Cart -->
        <div>
            <x-card title="Cart" subtitle="{{ count($cart) }} item(s)">
                @if(count($cart))
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        @foreach($cart as $key => $item)
                            <div class="flex items-center gap-2 p-2 rounded bg-base-200">
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-sm truncate">{{ $item['name'] }}</div>
                                    <div class="text-xs text-base-content/60">
                                        ₦{{ number_format($item['unit_price'], 2) }} × {{ $item['qty'] }}
                                        @if($item['unit_price'] < $item['retail_price'])
                                            <span class="text-info">(wholesale)</span>
                                        @endif
                                    </div>
                                </div>
                                <input type="number" wire:change="updateQty('{{ $key }}', $event.target.value)" value="{{ $item['qty'] }}" min="1" max="{{ $item['max_qty'] }}" class="input input-xs input-bordered w-16 text-center" />
                                <x-button icon="o-x-mark" wire:click="removeFromCart('{{ $key }}')" class="btn-xs btn-ghost text-error" />
                            </div>
                        @endforeach
                    </div>

                    <x-hr />

                    <div class="text-right text-xl font-bold text-primary mb-4">
                        ₦{{ number_format($cartTotal, 2) }}
                    </div>

                    <x-select label="Customer" wire:model.live="customer_id" :options="$customers" option-value="id" option-label="name" placeholder="Walk-in customer" />
                    <x-select label="Payment Method" wire:model="payment_method" :options="[
                        ['id' => 'cash', 'name' => 'Cash'],
                        ['id' => 'card', 'name' => 'Card'],
                        ['id' => 'transfer', 'name' => 'Transfer'],
                    ]" option-value="id" option-label="name" class="mt-3" />
                    <x-textarea label="Note" wire:model="note" placeholder="Optional" class="mt-3" rows="2" />

                    <x-button label="Complete Sale" wire:click="checkout" icon="o-check" class="btn-primary btn-block mt-4" wire:confirm="Confirm this sale?" />
                @else
                    <div class="text-center py-8 text-base-content/60">
                        <x-icon name="o-shopping-cart" class="w-12 h-12 mx-auto mb-2 opacity-30" />
                        <p>Cart is empty</p>
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</div>
