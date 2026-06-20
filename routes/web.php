<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', App\Livewire\Dashboard::class)->name('dashboard');
    Route::get('/categories', App\Livewire\Categories\Index::class)->name('categories.index');
    Route::get('/products', App\Livewire\Products\Index::class)->name('products.index');
    Route::get('/pos', App\Livewire\Pos\Index::class)->name('pos.index');
    Route::get('/sales', App\Livewire\Sales\Index::class)->name('sales.index');
    Route::get('/debt-book', App\Livewire\DebtBook\Index::class)->name('debt-book.index');
    Route::get('/inventory', App\Livewire\Inventory\Index::class)->name('inventory.index');
    Route::get('/expiry-alerts', App\Livewire\ExpiryAlerts\Index::class)->name('expiry-alerts.index');
    Route::get('/locations', App\Livewire\Locations\Index::class)->name('locations.index');
    Route::get('/stock/transfers', App\Livewire\Stock\Transfers::class)->name('stock.transfers');
    Route::get('/stock/adjustments', App\Livewire\Stock\Adjustments::class)->name('stock.adjustments');
    Route::get('/stock/history', App\Livewire\Stock\History::class)->name('stock.history');
    Route::get('/customers', App\Livewire\Customers\Index::class)->name('customers.index');
    Route::get('/suppliers', App\Livewire\Suppliers\Index::class)->name('suppliers.index');
    Route::get('/purchase-orders', App\Livewire\PurchaseOrders\Index::class)->name('purchase-orders.index');
    Route::get('/staff', App\Livewire\Staff\Index::class)->name('staff.index');
    Route::get('/appointments', App\Livewire\Appointments\Index::class)->name('appointments.index');
    Route::get('/invoice/{sale}', [App\Http\Controllers\InvoiceController::class, 'show'])->name('invoice.show');
    Route::get('/settings', App\Livewire\Settings\Index::class)->name('settings.index');

    Route::view('profile', 'profile')->name('profile');
});

require __DIR__.'/auth.php';
