<div class="max-w-4xl mx-auto px-4 py-6">
    <!-- Back link -->
    <a href="{{ route('shop.index') }}" class="btn btn-ghost btn-sm mb-4">
        <x-icon name="o-arrow-left" class="w-4 h-4" /> Back to Shop
    </a>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Image -->
        <div class="bg-base-200 rounded-xl h-64 md:h-80 flex items-center justify-center overflow-hidden">
            @if($product->image)
                <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover" />
            @else
                <x-icon name="o-cube" class="w-20 h-20 text-base-content/15" />
            @endif
        </div>

        <!-- Details -->
        <div>
            <span class="text-sm text-primary font-medium">{{ $product->category?->name }}</span>
            <h1 class="text-xl md:text-2xl font-bold mt-1">{{ $product->name }}</h1>

            <!-- Badges -->
            <div class="flex gap-2 mt-2">
                @if($product->requires_prescription)
                    <span class="badge badge-error badge-sm">Prescription Required</span>
                @endif
                @if($stock > 0)
                    <span class="badge badge-success badge-sm">In Stock ({{ $stock }})</span>
                @else
                    <span class="badge badge-neutral badge-sm">Out of Stock</span>
                @endif
            </div>

            <!-- Price -->
            <div class="mt-4">
                <span class="text-2xl md:text-3xl font-bold text-primary">₦{{ number_format($product->selling_price, 2) }}</span>
            </div>

            @if($product->description)
                <p class="text-sm text-base-content/60 mt-4">{{ $product->description }}</p>
            @endif

            <!-- Prescription notice -->
            @if($product->requires_prescription)
                <div class="bg-error/10 border border-error/20 rounded-lg p-3 mt-4">
                    <div class="flex items-start gap-2">
                        <x-icon name="o-shield-exclamation" class="w-5 h-5 text-error shrink-0 mt-0.5" />
                        <div>
                            <div class="font-semibold text-sm text-error">Prescription Required</div>
                            <p class="text-xs text-base-content/60 mt-1">You'll need to upload a valid prescription during checkout to purchase this product.</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Quantity & Add to Cart -->
            @if($stock > 0)
                <div class="mt-6">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="text-sm font-semibold">Quantity:</span>
                        <div class="flex items-center border border-base-300 rounded-lg">
                            <button wire:click="decrement" class="btn btn-ghost btn-sm btn-square">−</button>
                            <span class="w-10 text-center font-bold">{{ $quantity }}</span>
                            <button wire:click="increment" class="btn btn-ghost btn-sm btn-square">+</button>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-2">
                        <button class="btn btn-primary flex-1" onclick="alert('Cart coming soon!')">
                            <x-icon name="o-shopping-cart" class="w-5 h-5" /> Add to Cart
                        </button>
                        <button class="btn btn-accent flex-1" onclick="alert('Cart coming soon!')">
                            Buy Now
                        </button>
                    </div>
                </div>
            @else
                <div class="mt-6">
                    <button class="btn btn-disabled btn-block">Out of Stock</button>
                </div>
            @endif

            <!-- Product info -->
            @if($product->sku || $product->barcode)
                <div class="mt-6 pt-4 border-t border-base-200 text-xs text-base-content/40 space-y-1">
                    @if($product->sku)<div>SKU: {{ $product->sku }}</div>@endif
                    @if($product->barcode)<div>Barcode: {{ $product->barcode }}</div>@endif
                </div>
            @endif
        </div>
    </div>

    <!-- Related Products -->
    @if($relatedProducts->count())
        <div class="mt-10">
            <h2 class="text-lg font-bold mb-4">Related Products</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach($relatedProducts as $related)
                    @php $relStock = $related->batches->sum('quantity'); @endphp
                    <a href="{{ route('shop.show', $related->id) }}" class="card bg-base-100 border border-base-200 hover:shadow-md transition-all">
                        <figure class="bg-base-200 h-28 md:h-36 relative overflow-hidden">
                            @if($related->image)
                                <img src="{{ asset('storage/' . $related->image) }}" alt="{{ $related->name }}" class="w-full h-full object-cover" loading="lazy" />
                            @else
                                <div class="w-full h-full flex items-center justify-center">
                                    <x-icon name="o-cube" class="w-8 h-8 text-base-content/15" />
                                </div>
                            @endif
                            @if($related->requires_prescription)
                                <span class="badge badge-error badge-xs absolute top-2 left-2">Rx</span>
                            @endif
                        </figure>
                        <div class="p-2">
                            <h3 class="text-xs font-semibold line-clamp-2">{{ $related->name }}</h3>
                            <span class="text-primary font-bold text-sm">₦{{ number_format($related->selling_price, 0) }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
