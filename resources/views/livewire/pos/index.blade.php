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
                    <x-select label="Payment Method" wire:model.live="payment_method" :options="[
                        ['id' => 'cash', 'name' => 'Cash'],
                        ['id' => 'card', 'name' => 'Card'],
                        ['id' => 'transfer', 'name' => 'Transfer'],
                        ['id' => 'split', 'name' => 'Split Payment'],
                        ['id' => 'credit', 'name' => 'Credit (Debt)'],
                    ]" option-value="id" option-label="name" class="mt-3" />

                    @if($payment_method === 'split')
                        <div class="bg-base-200 rounded-lg p-3 mt-3 space-y-2">
                            <div class="text-xs font-semibold text-base-content/60 uppercase">Split Amount (Total: ₦{{ number_format($cartTotal, 2) }})</div>
                            <x-input wire:model.blur="split_cash" prefix="₦" type="number" step="0.01" placeholder="0.00" label="Cash" />
                            <x-input wire:model.blur="split_transfer" prefix="₦" type="number" step="0.01" placeholder="0.00" label="Transfer" />
                            <x-input wire:model.blur="split_card" prefix="₦" type="number" step="0.01" placeholder="0.00" label="Card" />
                            @php $splitSum = (float)($split_cash ?: 0) + (float)($split_transfer ?: 0) + (float)($split_card ?: 0); @endphp
                            <div class="flex justify-between text-sm pt-1 border-t border-base-300">
                                <span>Split Total:</span>
                                <span @class(['font-bold', 'text-success' => abs($splitSum - $cartTotal) < 0.01, 'text-error' => abs($splitSum - $cartTotal) >= 0.01])>
                                    ₦{{ number_format($splitSum, 2) }}
                                </span>
                            </div>
                            @if(abs($splitSum - $cartTotal) >= 0.01 && $splitSum > 0)
                                <div class="text-xs text-error">Remaining: ₦{{ number_format($cartTotal - $splitSum, 2) }}</div>
                            @endif
                        </div>
                    @endif

                    @if($payment_method === 'credit')
                        <x-alert title="Credit Sale" description="This will create a debt record for the customer. A customer must be selected." icon="o-exclamation-triangle" class="alert-warning mt-3" />
                    @endif
                    <x-textarea label="Note" wire:model="note" placeholder="Optional" class="mt-3" rows="2" />

                    <x-button label="Complete Sale" wire:click="checkout" icon="o-check" class="btn-primary btn-block mt-4" wire:confirm="Confirm this sale?" />
                @else
                    @if($lastSaleId)
                        <div class="text-center py-6">
                            <x-icon name="o-check-circle" class="w-12 h-12 mx-auto mb-2 text-success" />
                            <p class="font-semibold mb-4">Sale #{{ $lastSaleId }} completed!</p>
                            <div class="flex gap-2 justify-center">
                                <x-button label="Receipt" link="{{ route('receipt.show', $lastSaleId) }}" icon="o-printer" class="btn-sm btn-primary" external />
                                <x-button label="Invoice" link="{{ route('invoice.show', $lastSaleId) }}" icon="o-document-text" class="btn-sm btn-ghost" external />
                            </div>
                            <x-button label="New Sale" wire:click="$set('lastSaleId', null)" class="btn-sm btn-ghost mt-3" icon="o-plus" />
                        </div>
                    @else
                        <div class="text-center py-8 text-base-content/60">
                            <x-icon name="o-shopping-cart" class="w-12 h-12 mx-auto mb-2 opacity-30" />
                            <p>Cart is empty</p>
                        </div>
                    @endif
                @endif
            </x-card>
        </div>
    </div>
</div>
