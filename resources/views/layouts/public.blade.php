<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="pharmacy">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'BasmelCare Pharmacy' }}</title>
    <meta name="description" content="{{ $description ?? 'Your trusted pharmacy for quality healthcare products, prescriptions, and wellness services.' }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-base-100">

    <!-- Mobile top navbar -->
    <nav class="sticky top-0 z-50 bg-base-100 border-b border-base-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center justify-between h-14 md:h-16">
                <!-- Logo -->
                <a href="/" class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                        <x-icon name="o-heart" class="w-5 h-5 text-primary-content" />
                    </div>
                    <span class="font-bold text-lg text-primary">BasmelCare</span>
                </a>

                <!-- Desktop nav links -->
                <div class="hidden md:flex items-center gap-6">
                    <a href="/" class="text-sm font-medium hover:text-primary transition-colors">Home</a>
                    <a href="/shop" class="text-sm font-medium hover:text-primary transition-colors">Shop</a>
                    <a href="#services" class="text-sm font-medium hover:text-primary transition-colors">Services</a>
                    <a href="#contact" class="text-sm font-medium hover:text-primary transition-colors">Contact</a>
                </div>

                <!-- Desktop right actions -->
                <div class="hidden md:flex items-center gap-3">
                    <a href="/shop" class="btn btn-ghost btn-sm btn-circle relative">
                        <x-icon name="o-shopping-bag" class="w-5 h-5" />
                    </a>
                    <a href="/customer/login" class="btn btn-primary btn-sm">Sign In</a>
                </div>

                <!-- Mobile right actions -->
                <div class="md:hidden flex items-center gap-1">
                    <a href="/shop" class="btn btn-ghost btn-sm btn-circle relative">
                        <x-icon name="o-shopping-bag" class="w-5 h-5" />
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <main>
        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="bg-neutral text-neutral-content">
        <div class="max-w-7xl mx-auto px-4 py-10">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Brand -->
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                            <x-icon name="o-heart" class="w-5 h-5 text-primary-content" />
                        </div>
                        <span class="font-bold text-lg">BasmelCare</span>
                    </div>
                    <p class="text-sm opacity-70">Your trusted pharmacy for quality healthcare products, prescriptions, and wellness services.</p>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="font-semibold mb-3">Quick Links</h3>
                    <ul class="space-y-2 text-sm opacity-70">
                        <li><a href="/shop" class="hover:opacity-100 transition-opacity">Shop Products</a></li>
                        <li><a href="#services" class="hover:opacity-100 transition-opacity">Our Services</a></li>
                        <li><a href="#contact" class="hover:opacity-100 transition-opacity">Contact Us</a></li>
                        <li><a href="/customer/login" class="hover:opacity-100 transition-opacity">My Account</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div>
                    <h3 class="font-semibold mb-3">Contact Us</h3>
                    <ul class="space-y-2 text-sm opacity-70">
                        @php
                            $phone = \App\Models\AppSetting::get('pharmacy_phone', '');
                            $email = \App\Models\AppSetting::get('pharmacy_email', '');
                            $address = \App\Models\AppSetting::get('pharmacy_address', '');
                        @endphp
                        @if($phone)<li class="flex items-center gap-2"><x-icon name="o-phone" class="w-4 h-4" /> {{ $phone }}</li>@endif
                        @if($email)<li class="flex items-center gap-2"><x-icon name="o-envelope" class="w-4 h-4" /> {{ $email }}</li>@endif
                        @if($address)<li class="flex items-center gap-2"><x-icon name="o-map-pin" class="w-4 h-4" /> {{ $address }}</li>@endif
                    </ul>
                </div>
            </div>

            <div class="border-t border-neutral-content/10 mt-8 pt-6 text-center text-xs opacity-50">
                &copy; {{ date('Y') }} BasmelCare Pharmacy. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- Mobile bottom nav (visible only on mobile) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-base-100 border-t border-base-200 shadow-lg">
        <div class="flex">
            <a href="/" class="flex-1 flex flex-col items-center py-2 text-xs {{ request()->is('/') ? 'text-primary' : 'text-base-content/60' }}">
                <x-icon name="o-home" class="w-5 h-5" />
                <span class="mt-0.5">Home</span>
            </a>
            <a href="/shop" class="flex-1 flex flex-col items-center py-2 text-xs {{ request()->is('shop*') ? 'text-primary' : 'text-base-content/60' }}">
                <x-icon name="o-shopping-bag" class="w-5 h-5" />
                <span class="mt-0.5">Shop</span>
            </a>
            <a href="/cart" class="flex-1 flex flex-col items-center py-2 text-xs relative {{ request()->is('cart') ? 'text-primary' : 'text-base-content/60' }}">
                <x-icon name="o-shopping-cart" class="w-5 h-5" />
                <span class="mt-0.5">Cart</span>
            </a>
            <a href="/customer/login" class="flex-1 flex flex-col items-center py-2 text-xs {{ request()->is('account*') ? 'text-primary' : 'text-base-content/60' }}">
                <x-icon name="o-user" class="w-5 h-5" />
                <span class="mt-0.5">Account</span>
            </a>
        </div>
    </nav>

    <!-- Bottom nav spacer on mobile -->
    <div class="md:hidden h-14"></div>
</body>
</html>
