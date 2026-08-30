@php
    // A category has no icon of its own in this app, so one is chosen by what
    // the shelf is called. Presentation, not invented data - and anything
    // unrecognised gets the neutral one rather than a wrong guess.
    $iconFor = function (string $name) {
        $name = strtolower($name);
        return match (true) {
            str_contains($name, 'malaria')                                   => 'o-bug-ant',
            str_contains($name, 'antibiotic')                                => 'o-shield-check',
            str_contains($name, 'pain'), str_contains($name, 'analgesic')    => 'o-bolt',
            str_contains($name, 'vitamin'), str_contains($name, 'supplement')=> 'o-sparkles',
            str_contains($name, 'cough'), str_contains($name, 'cold')        => 'o-cloud',
            str_contains($name, 'skin'), str_contains($name, 'cream')        => 'o-sun',
            str_contains($name, 'baby'), str_contains($name, 'child')        => 'o-face-smile',
            str_contains($name, 'diabet')                                    => 'o-beaker',
            str_contains($name, 'pressure'), str_contains($name, 'heart')    => 'o-heart',
            str_contains($name, 'first aid'), str_contains($name, 'wound')   => 'o-plus-circle',
            default                                                          => 'o-squares-2x2',
        };
    };
@endphp

<div>
    {{-- Hero. The pharmacy's own promise, over its own colour - no stock
         photograph standing in for a shop that exists. --}}
    <section class="relative overflow-hidden" style="background: var(--pub-deep)">
        @if($heroImage)
            {{-- object-cover, so whatever shape is uploaded fills the banner
                 rather than stretching. A dark wash sits over it: the writing
                 is white, and a bright photograph would swallow it. --}}
            <img src="{{ asset('storage/' . $heroImage) }}" alt=""
                 class="absolute inset-0 w-full h-full object-cover" />
            <div aria-hidden="true" class="absolute inset-0" style="background: oklch(38% 0.062 178 / 0.72)"></div>
        @else
            <div aria-hidden="true"
                 class="absolute inset-0 opacity-[0.07]"
                 style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;"></div>
        @endif

        <div class="relative max-w-7xl mx-auto px-4 py-14 md:py-20">
            <div class="max-w-xl">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 text-white/90 text-xs font-medium">
                    <x-icon name="o-map-pin" class="w-3.5 h-3.5" />
                    Trusted in Uyo
                </span>

                <h1 class="text-white font-extrabold tracking-tight mt-4 text-3xl md:text-5xl leading-[1.1] text-balance">
                    Your Health, Our Priority.
                </h1>

                <p class="text-white/75 mt-4 text-base md:text-lg leading-relaxed max-w-[46ch]">
                    Quality medicines and wellness products, dispensed by pharmacists and
                    delivered across Uyo. Expert care, anytime.
                </p>

                <div class="flex flex-wrap gap-3 mt-7">
                    <a href="{{ route('shop.index') }}"
                       class="inline-flex items-center gap-2 h-11 px-6 rounded-full font-semibold text-sm shadow-sm hover:opacity-90"
                       style="background: var(--pub-cta); color: var(--pub-cta-ink)">
                        Shop Now
                        <x-icon name="o-arrow-right" class="w-4 h-4" />
                    </a>
                    <a href="{{ route('consultation.book') }}"
                       class="inline-flex items-center gap-2 h-11 px-6 rounded-full font-semibold text-sm
                              border border-white/30 text-white hover:bg-white/10">
                        <x-icon name="o-chat-bubble-left-right" class="w-4 h-4" />
                        Talk to a Pharmacist
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Shop by category --}}
    @if($categories->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 py-10 md:py-12">
            <div class="flex items-end justify-between gap-4 mb-5">
                <div>
                    <h2 class="text-xl md:text-2xl font-bold tracking-tight">Shop by Category</h2>
                    <p class="text-sm text-base-content/60 mt-0.5">Find what you need quickly and easily.</p>
                </div>
                @if($categoryCount > $categories->count())
                    <a href="{{ route('shop.index') }}" class="text-sm font-medium text-primary hover:underline whitespace-nowrap">
                        View all <x-icon name="o-arrow-right" class="w-3.5 h-3.5 inline" />
                    </a>
                @endif
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                @foreach($categories as $category)
                    <a href="{{ route('shop.index', ['category' => $category->id]) }}"
                       class="group bg-base-100 border border-base-200 rounded-xl p-4 flex flex-col items-center gap-2 text-center
                              hover:border-primary/40 hover:shadow-sm transition-all">
                        <span class="w-12 h-12 rounded-full overflow-hidden bg-primary/10 flex items-center justify-center group-hover:bg-primary/15 transition-colors">
                            @if($category->image)
                                <img src="{{ asset('storage/' . $category->image) }}" alt=""
                                     class="w-full h-full object-cover" loading="lazy" />
                            @else
                                {{-- No picture uploaded. A symbol chosen from the
                                     shelf's name, so the row still looks finished. --}}
                                <x-icon name="{{ $iconFor($category->name) }}" class="w-5 h-5 text-primary" />
                            @endif
                        </span>
                        <span class="text-sm font-medium leading-snug">{{ Str::title(Str::lower($category->name)) }}</span>
                        <span class="text-[11px] text-base-content/50">
                            {{ $category->products_count }} {{ Str::plural('product', $category->products_count) }}
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Best sellers --}}
    <x-shop.rail
        title="Top Selling Healthcare Products"
        subtitle="What customers here buy most."
        :products="$bestSellers" />

    {{-- Newest in --}}
    <x-shop.rail
        title="New Arrivals"
        subtitle="Just landed on the shelf."
        :products="$newArrivals"
        muted />

    {{-- One rail per well-stocked shelf, named after the shelf itself. --}}
    @foreach($rails as $rail)
        <x-shop.rail
            :title="Str::title(Str::lower($rail['category']->name))"
            :subtitle="$rail['category']->products_count . ' ' . Str::plural('product', $rail['category']->products_count) . ' in this category.'"
            :products="$rail['products']"
            :link="route('shop.index', ['category' => $rail['category']->id])" />
    @endforeach

    @if($bestSellers->isEmpty() && $newArrivals->isEmpty())
        <section class="max-w-7xl mx-auto px-4 py-16 text-center">
            <x-icon name="o-shopping-bag" class="w-12 h-12 mx-auto text-base-content/15" />
            <h2 class="text-lg font-semibold mt-3">Nothing is listed online yet</h2>
            <p class="text-sm text-base-content/60 mt-1 max-w-[42ch] mx-auto">
                The shelves are stocked, but nothing has been published to the shop.
                Call us and we will help you directly.
            </p>
            @if($pharmacyPhone ?? false)
                <a href="tel:{{ $pharmacyPhone }}" class="btn btn-primary btn-sm mt-4">Call the pharmacy</a>
            @endif
        </section>
    @endif
</div>
