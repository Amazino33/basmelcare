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
    <script>
        function applyTheme() {
            try {
                var t = JSON.parse(localStorage.getItem('mary-theme'));
                if (t) { document.documentElement.setAttribute('data-theme', t); document.documentElement.setAttribute('class', t); }
            } catch(e){}
        }
        applyTheme();
        document.addEventListener('livewire:navigated', applyTheme);
    </script>
</head>
<body class="min-h-screen font-sans antialiased bg-base-200/50 dark:bg-base-200">

    @php $role = auth()->user()->role; @endphp

    <x-main full-width>
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">
            <x-menu activate-by-route>
                <x-menu-item title="BasmelCare" icon="o-heart" class="text-primary font-bold" link="{{ route('dashboard') }}" />

                @if(auth()->user()->branch)
                    <div class="px-5 mb-2">
                        <x-badge value="{{ auth()->user()->branch->name }}" class="badge-ghost badge-sm" />
                    </div>
                @endif

                <x-menu-separator />
                <x-menu-item title="Dashboard" icon="o-chart-bar-square" link="{{ route('dashboard') }}" />

                @if(in_array($role, ['admin', 'pharmacist', 'branch_manager', 'inventory_manager']))
                    <x-menu-separator />
                    <x-menu-sub title="Catalog" icon="o-cube">
                        <x-menu-item title="Categories" icon="o-tag" link="{{ route('categories.index') }}" />
                        <x-menu-item title="Products" icon="o-cube" link="{{ route('products.index') }}" />
                    </x-menu-sub>
                @endif

                @if(in_array($role, ['admin', 'pharmacist', 'branch_manager', 'sales', 'cashier']))
                    @php $onlineOrderCount = \App\Models\Order::whereNull('claimed_by')->whereIn('status', ['pending', 'processing'])->count(); @endphp
                    <x-menu-sub title="Sales" icon="o-shopping-cart">
                        @if(in_array($role, ['admin', 'pharmacist', 'branch_manager', 'sales']))
                            <x-menu-item title="POS" icon="o-shopping-cart" link="{{ route('pos.index') }}" />
                            <x-menu-item title="Online Orders" icon="o-globe-alt" link="{{ route('online-orders.index') }}" badge="{{ $onlineOrderCount ?: '' }}" badge-classes="badge-error badge-xs" />
                        @endif
                        @if(in_array($role, ['admin', 'pharmacist', 'branch_manager', 'cashier']))
                            <x-menu-item title="Cashier" icon="o-banknotes" link="{{ route('cashier.index') }}" />
                        @endif
                        <x-menu-item title="Sales History" icon="o-clipboard-document-list" link="{{ route('sales.index') }}" />
                        <x-menu-item title="Debt Book" icon="o-book-open" link="{{ route('debt-book.index') }}" />
                    </x-menu-sub>
                @endif

                @if(in_array($role, ['admin', 'pharmacist', 'branch_manager', 'inventory_manager']))
                    <x-menu-sub title="Inventory" icon="o-archive-box">
                        <x-menu-item title="Stock Levels" icon="o-archive-box" link="{{ route('inventory.index') }}" />
                        <x-menu-item title="Transfers" icon="o-arrows-right-left" link="{{ route('stock.transfers') }}" />
                        <x-menu-item title="Adjustments" icon="o-adjustments-horizontal" link="{{ route('stock.adjustments') }}" />
                        <x-menu-item title="Movement History" icon="o-clock" link="{{ route('stock.history') }}" />
                        <x-menu-item title="Expiry Alerts" icon="o-exclamation-triangle" link="{{ route('expiry-alerts.index') }}" />
                        <x-menu-item title="Locations" icon="o-map-pin" link="{{ route('locations.index') }}" />
                    </x-menu-sub>

                    <x-menu-sub title="Procurement" icon="o-truck">
                        <x-menu-item title="Purchase Orders" icon="o-clipboard-document" link="{{ route('purchase-orders.index') }}" />
                        <x-menu-item title="Suppliers" icon="o-truck" link="{{ route('suppliers.index') }}" />
                    </x-menu-sub>
                @endif

                <x-menu-sub title="People" icon="o-users">
                    @if($role === 'admin')
                        <x-menu-item title="Staff" icon="o-identification" link="{{ route('staff.index') }}" />
                    @endif
                    @if(in_array($role, ['admin', 'pharmacist', 'branch_manager', 'sales', 'cashier']))
                        <x-menu-item title="Customers" icon="o-users" link="{{ route('customers.index') }}" />
                        <x-menu-item title="Appointments" icon="o-calendar" link="{{ route('appointments.index') }}" />
                    @endif
                </x-menu-sub>

                @if(in_array($role, ['admin', 'pharmacist', 'branch_manager']))
                    <x-menu-separator />
                    <x-menu-item title="Reports" icon="o-document-chart-bar" link="{{ route('reports.index') }}" />
                @endif

                @if($role === 'admin')
                    <x-menu-item title="Branches" icon="o-building-storefront" link="{{ route('branches.index') }}" />
                    <x-menu-item title="Settings" icon="o-cog-6-tooth" link="{{ route('settings.index') }}" />
                @endif

                <x-menu-separator />
                <x-menu-item title="My Profile" icon="o-user-circle" link="{{ route('profile') }}" />
                <x-menu-item title="Toggle Theme" icon="o-moon"
                    x-data
                    @click.prevent="
                        let current = document.documentElement.getAttribute('data-theme');
                        let next = current === 'dark' ? 'light' : 'dark';
                        document.documentElement.setAttribute('data-theme', next);
                        document.documentElement.setAttribute('class', next);
                        localStorage.setItem('mary-theme', JSON.stringify(next));
                        localStorage.setItem('mary-class', JSON.stringify(next));
                    "
                />
                <x-menu-item title="Logout" icon="o-arrow-right-start-on-rectangle" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" />
                <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
            </x-menu>
        </x-slot:sidebar>

        <x-slot:content>
            <!-- Mobile top bar -->
            <div class="lg:hidden flex items-center justify-between p-3 bg-base-100 border-b border-base-200">
                <label for="main-drawer" class="btn btn-ghost btn-sm">
                    <x-icon name="o-bars-3" class="w-6 h-6" />
                </label>
                <span class="font-bold text-primary">BasmelCare</span>
                <a href="{{ route('profile') }}" class="btn btn-ghost btn-sm btn-circle">
                    <x-icon name="o-user-circle" class="w-6 h-6" />
                </a>
            </div>

            <div class="p-5">
                {{ $slot }}
            </div>
        </x-slot:content>
    </x-main>

    <x-toast />
</body>
</html>
