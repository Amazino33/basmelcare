<div class="max-w-3xl mx-auto px-4 py-6">
    <h1 class="text-xl font-bold mb-4">Shopping Cart</h1>

    @if(count($items))
        <div class="space-y-3">
            @foreach($items as $key => $item)
                <div class="card bg-base-100 border border-base-200 p-3">
                    <div class="flex gap-3">
                        <!-- Image -->
                        <div class="w-16 h-16 bg-base-200 rounded-lg overflow-hidden shrink-0">
                            @if($item['image'])
                                <img src="{{ asset('storage/' . $item['image']) }}" alt="{{ $item['name'] }}" class="w-full h-full object-cover" />
                            @else
                                <div class="w-full h-full flex items-center justify-center">
                                    <x-icon name="o-cube" class="w-6 h-6 text-base-content/15" />
                                </div>
                            @endif
                        </div>

                        <!-- Details -->
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-semibold text-sm truncate">{{ $item['name'] }}</h3>
                                    <div class="text-primary font-bold text-sm mt-0.5">₦{{ number_format($item['price'], 2) }}</div>
                                    @if($item['requires_prescription'] ?? false)
                                        <span class="badge badge-error badge-xs mt-1">Rx Required</span>
                                    @endif
                                </div>
                                <button wire:click="removeItem({{ $item['product_id'] }})" class="btn btn-ghost btn-xs text-error shrink-0">
                                    <x-icon name="o-trash" class="w-4 h-4" />
                                </button>
                            </div>

                            <!-- Quantity + subtotal -->
                            <div class="flex items-center justify-between mt-2">
                                <div class="flex items-center border border-base-300 rounded-lg">
                                    <button wire:click="updateQuantity({{ $item['product_id'] }}, {{ $item['quantity'] - 1 }})" class="btn btn-ghost btn-xs btn-square">−</button>
                                    <span class="w-8 text-center text-sm font-bold">{{ $item['quantity'] }}</span>
                                    <button wire:click="updateQuantity({{ $item['product_id'] }}, {{ $item['quantity'] + 1 }})" class="btn btn-ghost btn-xs btn-square">+</button>
                                </div>
                                <span class="font-bold text-sm">₦{{ number_format($item['price'] * $item['quantity'], 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($requiresPrescription)
            <div class="bg-error/10 border border-error/20 rounded-lg p-3 mt-4">
                <div class="flex items-start gap-2">
                    <x-icon name="o-shield-exclamation" class="w-5 h-5 text-error shrink-0 mt-0.5" />
                    <div>
                        <div class="font-semibold text-sm text-error">Prescription Required</div>
                        <p class="text-xs text-base-content/60 mt-1">Some items in your cart require a prescription. You'll need to upload one during checkout.</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Summary -->
        <div class="card bg-base-100 border border-base-200 p-4 mt-4">
            <div class="flex justify-between text-sm mb-2">
                <span class="text-base-content/60">Subtotal ({{ $itemCount }} items)</span>
                <span class="font-bold">₦{{ number_format($subtotal, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm mb-3">
                <span class="text-base-content/60">Delivery</span>
                <span class="text-base-content/60">Calculated at checkout</span>
            </div>
            <div class="border-t border-base-200 pt-3 flex justify-between">
                <span class="font-bold">Total</span>
                <span class="font-bold text-lg text-primary">₦{{ number_format($subtotal, 2) }}</span>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-col gap-2 mt-4">
            <a href="/checkout" class="btn btn-primary btn-block">
                <x-icon name="o-lock-closed" class="w-4 h-4" /> Proceed to Checkout
            </a>
            <a href="/shop" class="btn btn-ghost btn-block btn-sm">Continue Shopping</a>
        </div>

        <button wire:click="clearCart" wire:confirm="Clear all items from cart?" class="btn btn-ghost btn-xs text-error mt-4">
            <x-icon name="o-trash" class="w-4 h-4" /> Clear Cart
        </button>
    @else
        <div class="text-center py-16">
            <x-icon name="o-shopping-cart" class="w-16 h-16 mx-auto mb-4 text-base-content/15" />
            <h2 class="text-lg font-semibold mb-1">Your cart is empty</h2>
            <p class="text-sm text-base-content/60 mb-4">Browse our products and add items to your cart.</p>
            <a href="/shop" class="btn btn-primary">
                <x-icon name="o-shopping-bag" class="w-5 h-5" /> Start Shopping
            </a>
        </div>
    @endif
</div>
