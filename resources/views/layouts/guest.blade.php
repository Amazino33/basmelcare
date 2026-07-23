<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    @include('partials.head-icons')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function(){
            try {
                var t = JSON.parse(localStorage.getItem('mary-theme'));
                if (t) { document.documentElement.setAttribute('data-theme', t); document.documentElement.setAttribute('class', t); }
            } catch(e){}
        })();
    </script>
</head>
<body class="min-h-screen font-sans antialiased bg-base-200">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div class="text-3xl font-bold text-primary">BasmelCare</div>
                <div class="text-sm text-base-content/60">Pharmacy Management System</div>
            </div>

            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    {{ $slot }}
                </div>
            </div>

            <div class="text-center mt-6 text-xs text-base-content/40">
                BasmelCare Pharmacy &copy; {{ date('Y') }}
            </div>
        </div>
    </div>
</body>
</html>
