<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware('auth')->group(function () {
    // Everyone can access
    Route::get('/dashboard', App\Livewire\Dashboard::class)->name('dashboard');
    Route::view('profile', 'profile')->name('profile');

    // Sales (admin, pharmacist, cashier)
    Route::middleware('role:admin,pharmacist,cashier')->group(function () {
        Route::get('/pos', App\Livewire\Pos\Index::class)->name('pos.index');
        Route::get('/sales', App\Livewire\Sales\Index::class)->name('sales.index');
        Route::get('/debt-book', App\Livewire\DebtBook\Index::class)->name('debt-book.index');
        Route::get('/invoice/{sale}', [App\Http\Controllers\InvoiceController::class, 'show'])->name('invoice.show');
        Route::get('/receipt/{sale}', [App\Http\Controllers\InvoiceController::class, 'receipt'])->name('receipt.show');
        Route::get('/customers', App\Livewire\Customers\Index::class)->name('customers.index');
        Route::get('/appointments', App\Livewire\Appointments\Index::class)->name('appointments.index');
    });

    // Inventory & Catalog (admin, pharmacist, inventory_manager)
    Route::middleware('role:admin,pharmacist,inventory_manager')->group(function () {
        Route::get('/categories', App\Livewire\Categories\Index::class)->name('categories.index');
        Route::get('/products', App\Livewire\Products\Index::class)->name('products.index');
        Route::get('/inventory', App\Livewire\Inventory\Index::class)->name('inventory.index');
        Route::get('/expiry-alerts', App\Livewire\ExpiryAlerts\Index::class)->name('expiry-alerts.index');
        Route::get('/locations', App\Livewire\Locations\Index::class)->name('locations.index');
        Route::get('/stock/transfers', App\Livewire\Stock\Transfers::class)->name('stock.transfers');
        Route::get('/stock/adjustments', App\Livewire\Stock\Adjustments::class)->name('stock.adjustments');
        Route::get('/stock/history', App\Livewire\Stock\History::class)->name('stock.history');
    });

    // Procurement (admin, pharmacist, inventory_manager)
    Route::middleware('role:admin,pharmacist,inventory_manager')->group(function () {
        Route::get('/suppliers', App\Livewire\Suppliers\Index::class)->name('suppliers.index');
        Route::get('/purchase-orders', App\Livewire\PurchaseOrders\Index::class)->name('purchase-orders.index');
    });

    // Admin & Pharmacist
    Route::middleware('role:admin,pharmacist')->group(function () {
        Route::get('/reports', App\Livewire\Reports\Index::class)->name('reports.index');
    });

    // Admin only
    Route::middleware('role:admin')->group(function () {
        Route::get('/staff', App\Livewire\Staff\Index::class)->name('staff.index');
        Route::get('/settings', App\Livewire\Settings\Index::class)->name('settings.index');
    });
});

require __DIR__.'/auth.php';
