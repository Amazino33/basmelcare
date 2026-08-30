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
            {{-- The same card as the shop front, so a product looks the same
                 wherever it is met. --}}
            <x-shop.product-card :product="$product" />
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
