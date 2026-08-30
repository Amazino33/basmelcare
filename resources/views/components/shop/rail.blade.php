@props([
    'title',
    'subtitle' => null,
    'products',
    'link' => null,
    'muted' => false,
])

@if($products->isNotEmpty())
    {{--
        A horizontal rail on a phone and a grid on a desktop, from the same
        markup: the row scrolls by finger where the screen is narrow and simply
        wraps where it is not, so there is no carousel to go wrong.
    --}}
    <section class="{{ $muted ? 'bg-base-200/40' : '' }} border-y border-base-200/60">
        <div class="max-w-7xl mx-auto px-4 py-10 md:py-12">
            <div class="flex items-end justify-between gap-4 mb-5">
                <div class="min-w-0">
                    <h2 class="text-xl md:text-2xl font-bold tracking-tight text-balance">{{ $title }}</h2>
                    @if($subtitle)
                        <p class="text-sm text-base-content/60 mt-0.5">{{ $subtitle }}</p>
                    @endif
                </div>

                @if($link)
                    <a href="{{ $link }}" class="text-sm font-medium text-primary hover:underline whitespace-nowrap shrink-0">
                        See all <x-icon name="o-arrow-right" class="w-3.5 h-3.5 inline" />
                    </a>
                @endif
            </div>

            <div class="rail flex gap-3 overflow-x-auto pb-2 -mx-4 px-4
                        md:grid md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 md:gap-4 md:overflow-visible md:mx-0 md:px-0 md:pb-0">
                @foreach($products as $product)
                    <x-shop.product-card :product="$product" width="w-40 shrink-0 md:w-auto" />
                @endforeach
            </div>
        </div>
    </section>
@endif
