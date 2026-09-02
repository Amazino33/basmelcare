@props(['product', 'width' => null])

@php
    $stock = $product->relationLoaded('batches')
        ? $product->batches->sum('quantity')
        : $product->batches()->sum('quantity');

    $low = $stock > 0 && $stock <= max((int) $product->reorder_level, 1);
@endphp

{{--
    One card, used by the home rails and the shop grid alike, so a product
    looks the same wherever it is met.

    No star rating. The design showed one, but this app records no reviews -
    there is nothing behind a rating, and inventing one on a pharmacy is a lie
    about a medicine. The space goes to what is true instead: the category, and
    whether it is actually on the shelf.
--}}
<div {{ $attributes->merge(['class' => 'group relative bg-base-100 border border-base-200 rounded-xl overflow-hidden hover:border-primary/40 hover:shadow-sm transition-all flex flex-col ' . ($width ?? '')]) }}>
    <a href="{{ route('shop.show', $product) }}" class="block relative aspect-square bg-base-200/40">
        @if($product->imageUrl('card'))
            <img src="{{ $product->imageUrl('card') }}" alt="{{ $product->name }}" loading="lazy"
                 class="w-full h-full object-contain p-3" />
        @else
            <span class="w-full h-full flex items-center justify-center text-base-content/15">
                <x-icon name="o-beaker" class="w-12 h-12" />
            </span>
        @endif

        <span class="absolute top-2 left-2 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide
            @if($stock == 0) bg-base-300 text-base-content/60
            @elseif($low)    bg-warning text-warning-content
            @else            bg-success text-success-content @endif">
            {{ $stock == 0 ? 'Out of stock' : ($low ? 'Low stock' : 'In stock') }}
        </span>
    </a>

    <div class="p-3 flex flex-col gap-1 flex-1">
        <span class="text-[11px] text-base-content/50 truncate">{{ Str::title(Str::lower($product->category?->name ?? 'General')) }}</span>

        <a href="{{ route('shop.show', $product) }}" class="text-sm font-medium leading-snug line-clamp-2 hover:text-primary">
            {{ Str::title(Str::lower($product->name)) }}
        </a>

        <div class="flex items-end justify-between gap-2 mt-auto pt-2">
            <span class="font-bold text-primary tabular-nums">
                @if($product->hasWholesaleDiscount())
                    <span class="block text-[11px] font-normal text-base-content/40 line-through">
                        &#8358;{{ number_format($product->selling_price, 0) }}
                    </span>
                @endif
                &#8358;{{ number_format($product->shopPrice(), 0) }}
                {{-- ₦50 beside a picture of a box reads as the price of the
                     box. Said plainly wherever a price appears. --}}
                @if($product->priceUnitLabel())
                    <span class="block text-[11px] font-normal text-base-content/60 tracking-normal">
                        {{ $product->priceUnitLabel() }}
                    </span>
                @endif
            </span>

            @if($stock > 0)
                <button type="button"
                        wire:click="addToCart({{ $product->id }})"
                        class="w-8 h-8 rounded-full bg-primary text-primary-content flex items-center justify-center
                               hover:opacity-90 shrink-0"
                        aria-label="Add {{ $product->name }} to basket">
                    <x-icon name="o-shopping-cart" class="w-4 h-4" />
                </button>
            @else
                {{-- No button rather than a disabled one: there is nothing to
                     do, and a greyed control invites a click that does nothing. --}}
                <span class="text-[11px] text-base-content/40">Ask at the counter</span>
            @endif
        </div>
    </div>
</div>
