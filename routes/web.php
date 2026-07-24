<?php

use Illuminate\Support\Facades\Route;

// Root redirects to desk
Route::get('/', fn() => redirect('/desk'))->name('home');

// Public pages
Route::get('/home', fn() => view('public.home'))->name('public.home');
Route::get('/shop', App\Livewire\Shop\Index::class)->name('shop.index');
Route::get('/shop/{product}', App\Livewire\Shop\Show::class)->name('shop.show');
Route::get('/cart', App\Livewire\Shop\Cart::class)->name('cart');
Route::get('/paystack/callback', [App\Http\Controllers\PaystackController::class, 'callback'])->name('paystack.callback');

// Customer auth
Route::middleware('guest:customer')->group(function () {
    Route::get('/customer/login', App\Livewire\Customer\Login::class)->name('customer.login');
    Route::get('/customer/register', App\Livewire\Customer\Register::class)->name('customer.register');
});

// Checkout (guest or logged in)
Route::get('/checkout', App\Livewire\Shop\Checkout::class)->name('checkout');
Route::get('/order/{order}/pay', [App\Http\Controllers\PaystackController::class, 'pay'])->name('order.pay');
Route::get('/order/{order}/confirmation', fn(App\Models\Order $order) => view('public.order-confirmation', ['order' => $order]))->name('order.confirmation');

// Customer portal (logged in only)
Route::middleware('auth:customer')->group(function () {
    Route::get('/account', App\Livewire\Customer\Account::class)->name('customer.account');
});

// Staff desk — all routes under /desk
Route::prefix('desk')->group(function () {
    Route::middleware('auth')->group(function () {
        // Everyone can access
        Route::get('/', App\Livewire\Dashboard::class)->name('dashboard');
        Route::view('profile', 'profile')->name('profile');

        // POS & Online Orders — sales person (admin, pharmacist, branch_manager, sales)
        Route::middleware('role:admin,pharmacist,branch_manager,sales')->group(function () {
            Route::get('pos', App\Livewire\Pos\Index::class)->name('pos.index');
            Route::get('online-orders', App\Livewire\OnlineOrders\Index::class)->name('online-orders.index');
        });

        // Cashier — processes payments and debt book (admin, pharmacist, branch_manager, cashier)
        Route::middleware('role:admin,pharmacist,branch_manager,cashier')->group(function () {
            Route::get('cashier', App\Livewire\Cashier\Index::class)->name('cashier.index');
            Route::get('debt-book', App\Livewire\DebtBook\Index::class)->name('debt-book.index');
            Route::get('debt-payment/{debtPayment}/receipt', [App\Http\Controllers\InvoiceController::class, 'debtReceipt'])->name('debt-payment.receipt');
        });

        // Shared sales pages (admin, pharmacist, branch_manager, sales, cashier)
        Route::middleware('role:admin,pharmacist,branch_manager,sales,cashier')->group(function () {
            Route::get('sales', App\Livewire\Sales\Index::class)->name('sales.index');
            Route::get('invoice/{sale}', [App\Http\Controllers\InvoiceController::class, 'show'])->name('invoice.show');
            Route::get('receipt/{sale}', [App\Http\Controllers\InvoiceController::class, 'receipt'])->name('receipt.show');
            Route::get('customers', App\Livewire\Customers\Index::class)->name('customers.index');
            Route::get('appointments', App\Livewire\Appointments\Index::class)->name('appointments.index');
        });

        // Inventory & Catalog (admin, pharmacist, branch_manager, inventory_manager)
        Route::middleware('role:admin,pharmacist,branch_manager,inventory_manager')->group(function () {
            Route::get('categories', App\Livewire\Categories\Index::class)->name('categories.index');
            Route::get('products', App\Livewire\Products\Index::class)->name('products.index');
            Route::get('inventory', App\Livewire\Inventory\Index::class)->name('inventory.index');
            Route::get('expiry-alerts', App\Livewire\ExpiryAlerts\Index::class)->name('expiry-alerts.index');
            Route::get('locations', App\Livewire\Locations\Index::class)->name('locations.index');
            Route::get('stock/transfers', App\Livewire\Stock\Transfers::class)->name('stock.transfers');
            Route::get('stock/adjustments', App\Livewire\Stock\Adjustments::class)->name('stock.adjustments');
            Route::get('stock/history', App\Livewire\Stock\History::class)->name('stock.history');
        });

        // Procurement (admin, pharmacist, branch_manager, inventory_manager)
        Route::middleware('role:admin,pharmacist,branch_manager,inventory_manager')->group(function () {
            Route::get('suppliers', App\Livewire\Suppliers\Index::class)->name('suppliers.index');
            Route::get('purchase-orders', App\Livewire\PurchaseOrders\Index::class)->name('purchase-orders.index');
        });

        // Reports (admin, pharmacist, branch_manager)
        Route::middleware('role:admin,pharmacist,branch_manager')->group(function () {
            Route::get('reports', App\Livewire\Reports\Index::class)->name('reports.index');
        });

        // Admin only
        Route::middleware('role:admin')->group(function () {
            Route::get('staff', App\Livewire\Staff\Index::class)->name('staff.index');
            Route::get('branches', App\Livewire\Branches\Index::class)->name('branches.index');
            Route::get('settings', App\Livewire\Settings\Index::class)->name('settings.index');
        });
    });
});

require __DIR__.'/auth.php';
