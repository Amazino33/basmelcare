<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="pharmacy">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'BasmelCare Pharmacy' }}</title>
    <meta name="description" content="{{ $description ?? 'Your trusted pharmacy for quality healthcare products, prescriptions, and wellness services.' }}">
    @include('partials.head-icons')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /*
         * The shop's own palette, kept here rather than in the DaisyUI theme:
         * the staff app shares that theme, and the counter should not change
         * colour because the shop did.
         */
        :root {
            --pub-deep:      oklch(38% 0.062 178);   /* the band and the hero */
            --pub-deep-soft: oklch(46% 0.058 178);
            --pub-cta:       oklch(70% 0.175 48);    /* one warm accent, used sparingly */
            --pub-cta-ink:   oklch(28% 0.09 48);
        }
        body { font-family: 'Inter', sans-serif; }

        /* Rails scroll by finger on a phone and by the arrows on a desktop. */
        .rail { scrollbar-width: none; }
        .rail::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="min-h-screen bg-base-100">

    {{-- Utility bar. Real contact details, not decoration: this is how most
         customers here start a conversation. --}}
    @php
        $pharmacyPhone   = \App\Models\AppSetting::get('pharmacy_phone', '');
        $pharmacyAddress = \App\Models\AppSetting::get('pharmacy_address', '');
        $pharmacyEmail   = \App\Models\AppSetting::get('pharmacy_email', '');
        $cartCount       = (new \App\Services\CartService)->count();
        $navCategories   = \App\Models\Category::query()
            ->whereHas('products', fn ($q) => $q->where('show_in_shop', true))
            ->orderBy('name')
            ->limit(8)
            ->get();
    @endphp

    <div class="text-white text-xs" style="background: var(--pub-deep)">
        <div class="max-w-7xl mx-auto px-4 h-9 flex items-center justify-between gap-4">
            <div class="flex items-center gap-4 min-w-0">
                @if($pharmacyPhone)
                    <a href="tel:{{ $pharmacyPhone }}" class="flex items-center gap-1.5 hover:opacity-80 shrink-0">
                        <x-icon name="o-phone" class="w-3.5 h-3.5" />
                        <span>{{ $pharmacyPhone }}</span>
                    </a>
                @endif
                @if($pharmacyAddress)
                    <span class="hidden sm:flex items-center gap-1.5 opacity-80 truncate">
                        <x-icon name="o-map-pin" class="w-3.5 h-3.5 shrink-0" />
                        <span class="truncate">{{ $pharmacyAddress }}</span>
                    </span>
                @endif
            </div>

            @if($pharmacyPhone)
                <a href="https://wa.me/{{ preg_replace('/\D/', '', \Illuminate\Support\Str::start(ltrim($pharmacyPhone, '0'), '234')) }}"
                   target="_blank" rel="noopener"
                   class="flex items-center gap-1.5 hover:opacity-80 shrink-0">
                    <x-icon name="o-chat-bubble-left-right" class="w-3.5 h-3.5" />
                    <span class="hidden xs:inline">Chat on WhatsApp</span>
                    <span class="xs:hidden">WhatsApp</span>
                </a>
            @endif
        </div>
    </div>

    {{-- Header --}}
    <header class="sticky top-0 z-50 bg-base-100 border-b border-base-200">
        <div class="max-w-7xl mx-auto px-4">
            <div class="h-16 flex items-center gap-3 md:gap-6">
                <a href="/" class="flex items-center gap-2 shrink-0">
                    <img src="/logo.png" class="h-9 w-auto object-contain" alt="BasmelCare Pharmacy">
                    <span class="font-extrabold text-lg tracking-tight hidden sm:inline">BasmelCare</span>
                </a>

                {{-- Straight to the shop's own search, which already filters
                     the catalogue - no second implementation of it. --}}
                <form action="{{ route('shop.index') }}" method="GET" class="flex-1 hidden md:block">
                    <label class="relative block">
                        <x-icon name="o-magnifying-glass" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40" />
                        <input type="search" name="search" value="{{ request('search') }}"
                               placeholder="Search medicines, vitamins, supplies…"
                               class="w-full h-10 pl-9 pr-3 rounded-full bg-base-200 border border-transparent
                                      focus:border-primary focus:bg-base-100 focus:outline-none text-sm" />
                    </label>
                </form>

                <div class="flex items-center gap-1 md:gap-2 ml-auto shrink-0">
                    <a href="{{ route('shop.index') }}"
                       class="hidden md:inline-flex items-center gap-1.5 h-10 px-4 rounded-full bg-primary text-primary-content text-sm font-semibold hover:opacity-90">
                        <x-icon name="o-document-arrow-up" class="w-4 h-4" />
                        Upload Prescription
                    </a>

                    <a href="{{ auth('customer')->check() ? route('customer.account') : route('customer.login') }}"
                       class="btn btn-ghost btn-sm btn-circle" aria-label="Account">
                        <x-icon name="o-user" class="w-5 h-5" />
                    </a>

                    <a href="/cart" class="btn btn-ghost btn-sm btn-circle relative" aria-label="Cart">
                        <x-icon name="o-shopping-cart" class="w-5 h-5" />
                        @if($cartCount > 0)
                            <span class="absolute -top-0.5 -right-0.5 min-w-4 h-4 px-1 rounded-full bg-error text-error-content
                                         text-[10px] font-bold flex items-center justify-center">{{ $cartCount }}</span>
                        @endif
                    </a>
                </div>
            </div>

            {{-- Search on a phone, where it does not fit beside the logo. --}}
            <form action="{{ route('shop.index') }}" method="GET" class="md:hidden pb-3">
                <label class="relative block">
                    <x-icon name="o-magnifying-glass" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40" />
                    <input type="search" name="search" value="{{ request('search') }}"
                           placeholder="Search medicines…"
                           class="w-full h-10 pl-9 pr-3 rounded-full bg-base-200 border border-transparent
                                  focus:border-primary focus:bg-base-100 focus:outline-none text-sm" />
                </label>
            </form>
        </div>

        {{-- Category strip, from the categories that actually have something
             on sale. A tab for an empty shelf is a dead end. --}}
        @if($navCategories->isNotEmpty())
            <nav class="border-t border-base-200 bg-base-100">
                <div class="max-w-7xl mx-auto px-4">
                    <div class="rail flex items-center gap-5 overflow-x-auto h-11 text-sm">
                        <a href="{{ route('shop.index') }}"
                           class="whitespace-nowrap font-semibold {{ request()->routeIs('shop.index') && ! request('category') ? 'text-primary border-b-2 border-primary' : 'text-base-content/70 hover:text-primary' }} h-11 flex items-center">
                            Explore All
                        </a>
                        @foreach($navCategories as $navCategory)
                            <a href="{{ route('shop.index', ['category' => $navCategory->id]) }}"
                               class="whitespace-nowrap {{ (int) request('category') === $navCategory->id ? 'text-primary font-semibold border-b-2 border-primary' : 'text-base-content/70 hover:text-primary' }} h-11 flex items-center">
                                {{ Str::title(Str::lower($navCategory->name)) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </nav>
        @endif
    </header>

    <main>
        {{ $slot }}
    </main>

    {{-- What the pharmacy actually offers. Every one of these is a real
         service the shop performs, not a stock illustration. --}}
    <section class="bg-base-200/60 border-t border-base-200">
        <div class="max-w-7xl mx-auto px-4 py-10">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 text-center">
                @foreach([
                    ['o-clipboard-document-check', 'Prescription Dispensing', 'Your prescription is reviewed by a pharmacist before anything is dispensed.'],
                    ['o-heart', 'Health Monitoring', 'Blood pressure and blood sugar checks when you visit the shop.'],
                    ['o-truck', 'Home Delivery', 'Delivered across Uyo, or collect from the counter.'],
                    ['o-shield-check', 'Genuine Products', 'Sourced from verified distributors and tracked by batch and expiry.'],
                ] as [$icon, $heading, $blurb])
                    <div class="flex flex-col items-center gap-2">
                        <span class="w-11 h-11 rounded-full bg-base-100 flex items-center justify-center shadow-sm">
                            <x-icon name="{{ $icon }}" class="w-5 h-5 text-primary" />
                        </span>
                        <span class="font-semibold text-sm">{{ $heading }}</span>
                        <span class="text-xs text-base-content/60 leading-relaxed max-w-[22ch]">{{ $blurb }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <footer class="text-white" style="background: var(--pub-deep)">
        <div class="max-w-7xl mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="md:col-span-2 lg:col-span-1">
                    <div class="flex items-center gap-2 mb-3">
                        <img src="/logo.png" class="h-9 w-auto object-contain bg-white rounded p-1" alt="BasmelCare">
                        <span class="font-extrabold text-lg">BasmelCare</span>
                    </div>
                    <p class="text-sm text-white/70 leading-relaxed max-w-[38ch]">
                        Your trusted community pharmacy, dedicated to clinical excellence and patient-centred care.
                    </p>
                </div>

                <div>
                    <h3 class="font-semibold mb-3 text-sm uppercase tracking-wide text-white/90">Shop</h3>
                    <ul class="space-y-2 text-sm text-white/70">
                        <li><a href="{{ route('shop.index') }}" class="hover:text-white">All products</a></li>
                        <li><a href="/cart" class="hover:text-white">Your basket</a></li>
                        <li><a href="{{ route('consultation.book') }}" class="hover:text-white">Book a consultation</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-semibold mb-3 text-sm uppercase tracking-wide text-white/90">Your account</h3>
                    <ul class="space-y-2 text-sm text-white/70">
                        @if(auth('customer')->check())
                            <li><a href="{{ route('customer.account') }}" class="hover:text-white">Orders &amp; history</a></li>
                            <li><a href="{{ route('customer.account') }}" class="hover:text-white">Wallet balance</a></li>
                        @else
                            <li><a href="{{ route('customer.login') }}" class="hover:text-white">Sign in</a></li>
                            <li><a href="{{ route('customer.register') }}" class="hover:text-white">Create an account</a></li>
                        @endif
                    </ul>
                </div>

                <div>
                    <h3 class="font-semibold mb-3 text-sm uppercase tracking-wide text-white/90">Contact</h3>
                    <ul class="space-y-2 text-sm text-white/70">
                        @if($pharmacyAddress)
                            <li class="flex gap-2"><x-icon name="o-map-pin" class="w-4 h-4 shrink-0 mt-0.5" /> <span>{{ $pharmacyAddress }}</span></li>
                        @endif
                        @if($pharmacyPhone)
                            <li class="flex gap-2"><x-icon name="o-phone" class="w-4 h-4 shrink-0 mt-0.5" /> <a href="tel:{{ $pharmacyPhone }}" class="hover:text-white">{{ $pharmacyPhone }}</a></li>
                        @endif
                        @if($pharmacyEmail)
                            <li class="flex gap-2"><x-icon name="o-envelope" class="w-4 h-4 shrink-0 mt-0.5" /> <a href="mailto:{{ $pharmacyEmail }}" class="hover:text-white">{{ $pharmacyEmail }}</a></li>
                        @endif
                    </ul>

                    <div class="mt-4">
                        <div class="text-xs text-white/50 mb-1.5">Secure payments by</div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-1 rounded bg-white/10 text-[10px] font-semibold tracking-wide">Paystack</span>
                            <span class="px-2 py-1 rounded bg-white/10 text-[10px] font-semibold tracking-wide">Mastercard</span>
                            <span class="px-2 py-1 rounded bg-white/10 text-[10px] font-semibold tracking-wide">Visa</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-white/10 mt-10 pt-6 text-xs text-white/50">
                &copy; {{ date('Y') }} {{ $pharmacyName ?? 'BasmelCare Pharmacy' }}. Clinical excellence and patient care.
            </div>
        </div>
    </footer>

    {{-- Mobile bottom bar. Kept: most customers here are on a phone. --}}
    <nav class="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-base-100 border-t border-base-200 shadow-lg">
        <div class="flex">
            @foreach([
                ['/', 'o-home', 'Home', request()->is('/')],
                [route('shop.index'), 'o-shopping-bag', 'Shop', request()->is('shop*')],
                ['/cart', 'o-shopping-cart', 'Cart', request()->is('cart')],
                [auth('customer')->check() ? route('customer.account') : route('customer.login'), 'o-user', 'Account', request()->is('account*')],
            ] as [$href, $icon, $label, $active])
                <a href="{{ $href }}" class="flex-1 flex flex-col items-center py-2 text-xs {{ $active ? 'text-primary' : 'text-base-content/60' }}">
                    <span class="relative">
                        <x-icon name="{{ $icon }}" class="w-5 h-5" />
                        @if($label === 'Cart' && $cartCount > 0)
                            <span class="absolute -top-1.5 -right-2.5 min-w-4 h-4 px-1 rounded-full bg-error text-error-content
                                         text-[10px] font-bold flex items-center justify-center">{{ $cartCount }}</span>
                        @endif
                    </span>
                    <span class="mt-0.5">{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    <div class="md:hidden h-14"></div>
</body>
</html>
