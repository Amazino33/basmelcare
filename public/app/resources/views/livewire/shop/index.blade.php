<div class="max-w-7xl mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-4">
        <h1 class="text-xl md:text-2xl font-bold">Shop Products</h1>
        <p class="text-sm text-base-content/60">Browse our catalogue of healthcare products</p>
    </div>

    <!-- Search -->
    <div class="flex gap-2 mb-4">
        <div class="relative flex-1">
            <x-icon name="o-magnifying-glass" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40" />
            <input wire:model.live.debounce.300ms="search" type="text" class="input input-bordered w-full pl-10 input-sm md:input-md" placeholder="Search medicines, vitamins, health products..." />
        </div>
        <select wire:model.live="sort" class="select select-bordered select-sm md:select-md w-28 md:w-36">
            <option value="latest">Latest</option>
            <option value="price_low">Price: Low</option>
            <option value="price_high">Price: High</option>
            <option value="name">Name</option>
        </select>
    </div>

    <!-- Category pills (horizontal scroll) -->
    <div class="flex overflow-x-auto gap-2 pb-3 mb-4 scrollbar-hide">
        <button wire:click="setCategory(null)" @class([
            'btn btn-sm whitespace-nowrap',
            'btn-primary' => !$category,
            'btn-ghost border border-base-300' => $category,
        ])>All</button>
        @foreach($categories as $cat)
            @if($cat->products_count > 0)
                <button wire:click="setCategory({{ $cat->id }})" @class([
                    'btn btn-sm whitespace-nowrap',
                    'btn-primary' => $category === $cat->id,
                    'btn-ghost border border-base-300' => $category !== $cat->id,
                ])>{{ $cat->name }} <span class="opacity-60">({{ $cat->products_count }})</span></button>
            @endif
        @endforeach
    </div>

    <!-- Products Grid -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-4">
        @forelse($products as $product)
            @php $stock = $product->batches->sum('quantity'); @endphp
            <a href="{{ route('shop.show', $product->id) }}" class="card bg-base-100 border border-base-200 hover:shadow-md transition-all active:scale-[0.98]">
                <!-- Image -->
                <figure class="bg-base-200 h-32 md:h-44 relative overflow-hidden">
                    @if($product->image)
                        <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover" loading="lazy" />
                    @else
                        <div class="w-full h-full flex items-center justify-center">
                            <x-icon name="o-cube" class="w-10 h-10 text-base-content/15" />
                        </div>
                    @endif

                    <!-- Badges -->
                    <div class="absolute top-2 left-2 flex flex-col gap-1">
                        @if($product->requires_prescription)
                            <span class="badge badge-error badge-xs">Rx</span>
                        @endif
                        @if($stock == 0)
                            <span class="badge badge-neutral badge-xs">Out of Stock</span>
                        @endif
                    </div>
                </figure>

                <div class="p-2.5">
                    <!-- Category -->
                    <span class="text-xs text-primary font-medium">{{ $product->category?->name }}</span>

                    <!-- Name -->
                    <h3 class="text-sm font-semibold leading-tight line-clamp-2 mt-0.5">{{ $product->name }}</h3>

                    <!-- Price -->
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-primary font-bold text-sm md:text-base">₦{{ number_format($product->selling_price, 0) }}</span>
                        @if($stock > 0)
                            <span class="text-xs text-success">In Stock</span>
                        @else
                            <span class="text-xs text-error">Sold Out</span>
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <div class="col-span-full text-center py-12">
                <x-icon name="o-magnifying-glass" class="w-12 h-12 mx-auto mb-3 text-base-content/20" />
                <p class="text-base-content/60">No products found</p>
                @if($search || $category)
                    <button wire:click="$set('search', ''); $set('category', null)" class="btn btn-ghost btn-sm mt-2">Clear Filters</button>
                @endif
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $products->links() }}
    </div>
</div>
