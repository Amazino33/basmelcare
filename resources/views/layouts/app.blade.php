<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-200/50 dark:bg-base-200">

    <x-main full-width>
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">
            <x-menu activate-by-route>
                <x-menu-item title="BasmelCare" icon="o-heart" class="text-primary font-bold" link="{{ route('dashboard') }}" />

                <x-menu-separator />
                <x-menu-item title="Dashboard" icon="o-chart-bar-square" link="{{ route('dashboard') }}" />

                <x-menu-separator />
                <x-menu-sub title="Catalog" icon="o-cube">
                    <x-menu-item title="Categories" icon="o-tag" link="{{ route('categories.index') }}" />
                    <x-menu-item title="Products" icon="o-cube" link="{{ route('products.index') }}" />
                </x-menu-sub>

                <x-menu-sub title="Sales" icon="o-shopping-cart">
                    <x-menu-item title="POS" icon="o-shopping-cart" link="{{ route('pos.index') }}" />
                    <x-menu-item title="Sales History" icon="o-clipboard-document-list" link="{{ route('sales.index') }}" />
                </x-menu-sub>

                <x-menu-sub title="Inventory" icon="o-archive-box">
                    <x-menu-item title="Stock Levels" icon="o-archive-box" link="{{ route('inventory.index') }}" />
                    <x-menu-item title="Transfers" icon="o-arrows-right-left" link="{{ route('stock.transfers') }}" />
                    <x-menu-item title="Adjustments" icon="o-adjustments-horizontal" link="{{ route('stock.adjustments') }}" />
                    <x-menu-item title="Movement History" icon="o-clock" link="{{ route('stock.history') }}" />
                    <x-menu-item title="Expiry Alerts" icon="o-exclamation-triangle" link="{{ route('expiry-alerts.index') }}" />
                    <x-menu-item title="Locations" icon="o-map-pin" link="{{ route('locations.index') }}" />
                </x-menu-sub>

                <x-menu-sub title="People" icon="o-users">
                    <x-menu-item title="Staff" icon="o-identification" link="{{ route('staff.index') }}" />
                    <x-menu-item title="Customers" icon="o-users" link="{{ route('customers.index') }}" />
                    <x-menu-item title="Suppliers" icon="o-truck" link="{{ route('suppliers.index') }}" />
                </x-menu-sub>

                <x-menu-separator />
                <x-menu-item title="Settings" icon="o-cog-6-tooth" link="{{ route('settings.index') }}" />
            </x-menu>

            <x-slot:actions>
                <x-menu activate-by-route>
                    <x-menu-separator />
                    <x-menu-item title="Theme" icon="o-sun">
                        <x-slot:actions>
                            <x-theme-toggle />
                        </x-slot:actions>
                    </x-menu-item>
                    <x-menu-item title="{{ auth()->user()->name }}" icon="o-user" link="{{ route('profile') }}" />
                    <x-menu-item title="Logout" icon="o-power" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" />
                    <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
                </x-menu>
            </x-slot:actions>
        </x-slot:sidebar>

        <x-slot:content>
            <div class="p-5">
                {{ $slot }}
            </div>
        </x-slot:content>
    </x-main>

    <x-toast />
</body>
</html>
