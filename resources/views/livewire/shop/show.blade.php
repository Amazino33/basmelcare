<div class="max-w-4xl mx-auto px-4 py-6">
    <!-- Back link -->
    <a href="{{ route('shop.index') }}" class="btn btn-ghost btn-sm mb-4">
        <x-icon name="o-arrow-left" class="w-4 h-4" /> Back to Shop
    </a>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Image -->
        <div class="{{ $product->image ? 'bg-white border border-base-200' : 'bg-base-200' }} rounded-xl aspect-square flex items-center justify-center overflow-hidden">
            @if($product->image)
                <img src="{{ $product->imageUrl('zoom') }}" alt="{{ $product->name }}" class="w-full h-full object-contain p-4" />
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
                <span class="text-2xl md:text-3xl font-bold text-primary">
                    @if($product->hasWholesaleDiscount())<span class="text-base font-normal text-base-content/40 line-through mr-2">&#8358;{{ number_format($product->selling_price, 2) }}</span>@endif&#8358;{{ number_format($product->shopPrice(), 2) }}
                </span>
                @if($product->priceUnitLabel())
                    <span class="text-base font-normal text-base-content/60">{{ $product->priceUnitLabel() }}</span>
                @endif

                @if($product->hasWholesaleDiscount())
                    <div class="text-xs text-success mt-1">Wholesale price, applied to your account.</div>
                @endif

                {{-- Where the same thing is also sold sealed, say so and say
                     what it costs. Somebody wanting a full course should not
                     have to work out that ten of these is a packet. --}}
                @if($product->packLabel() && $product->pack_price)
                    <div class="mt-2 inline-flex items-center gap-2 rounded-lg border border-base-300 bg-base-200/50 px-3 py-1.5 text-sm">
                        <x-icon name="o-squares-2x2" class="w-4 h-4 text-base-content/50" />
                        <span>{{ $product->packLabel() }} &mdash;
                            <strong class="text-primary">&#8358;{{ number_format($product->pack_price, 2) }}</strong>
                        </span>
                        <span class="text-xs text-base-content/50">ask at the counter</span>
                    </div>
                @endif
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
                        <button wire:click="addToCart" class="btn btn-primary flex-1">
                            <x-icon name="o-shopping-cart" class="w-5 h-5" /> Add to Cart
                        </button>
                        <button wire:click="buyNow" class="btn btn-accent flex-1">
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
                        <figure class="{{ $related->image ? 'bg-white' : 'bg-base-200' }} aspect-square relative overflow-hidden">
                            @if($related->image)
                                <img src="{{ $related->imageUrl('card') }}" alt="{{ $related->name }}" class="w-full h-full object-contain p-2" loading="lazy" />
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
                            <span class="text-primary font-bold text-sm">@if($related->hasWholesaleDiscount())<span class="text-xs text-base-content/40 line-through mr-1">&#8358;{{ number_format($related->selling_price, 0) }}</span>@endif&#8358;{{ number_format($related->shopPrice(), 0) }}@if($related->priceUnitLabel())<span class="block text-[11px] font-normal text-base-content/60">{{ $related->priceUnitLabel() }}</span>@endif</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
