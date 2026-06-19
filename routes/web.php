<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', App\Livewire\Dashboard::class)->name('dashboard');
    Route::get('/categories', App\Livewire\Categories\Index::class)->name('categories.index');
    Route::get('/products', App\Livewire\Products\Index::class)->name('products.index');
    Route::get('/pos', App\Livewire\Pos\Index::class)->name('pos.index');
    Route::get('/sales', App\Livewire\Sales\Index::class)->name('sales.index');
    Route::get('/inventory', App\Livewire\Inventory\Index::class)->name('inventory.index');
    Route::get('/expiry-alerts', App\Livewire\ExpiryAlerts\Index::class)->name('expiry-alerts.index');
    Route::get('/customers', App\Livewire\Customers\Index::class)->name('customers.index');
    Route::get('/suppliers', App\Livewire\Suppliers\Index::class)->name('suppliers.index');
    Route::get('/staff', App\Livewire\Staff\Index::class)->name('staff.index');
    Route::get('/settings', App\Livewire\Settings\Index::class)->name('settings.index');

    Route::view('profile', 'profile')->name('profile');
});

require __DIR__.'/auth.php';
