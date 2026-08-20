<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// Staff desk
Route::prefix(config('app.desk_prefix'))->group(function () {
    Route::middleware('auth')->group(function () {
        // Everyone can access
        Route::get('/dashboard', App\Livewire\Dashboard::class)->name('dashboard');
        Route::view('profile', 'profile')->name('profile');

        // NOTE: pharmacist is deliberately absent from every money-handling group
        // below. That role is clinical only — patients, records, drug catalogue
        // and stock visibility. Finance belongs to admin and branch_manager.

        // Selling: POS, online orders, stock take
        Route::middleware('role:admin,branch_manager,sales')->group(function () {
            Route::get('pos', App\Livewire\Pos\Index::class)->name('pos.index');
            Route::get('online-orders', App\Livewire\OnlineOrders\Index::class)->name('online-orders.index');
            Route::get('stock/take', App\Livewire\StockTake\Index::class)->name('stock-take.index');
            Route::get('stock/take/{stockTake}', App\Livewire\StockTake\Show::class)->name('stock-take.show');
        });

        // The till itself — takes money, so never the auditor.
        Route::middleware('role:admin,branch_manager,cashier')->group(function () {
            Route::get('cashier', App\Livewire\Cashier\Index::class)->name('cashier.index');
        });

        // Money owed and change held — records the auditor must be able to read.
        Route::middleware('role:admin,branch_manager,cashier,auditor')->group(function () {
            Route::get('credits', App\Livewire\Credits\Index::class)->name('credits.index');
            Route::get('credit-payout/{creditPayout}/receipt', [App\Http\Controllers\InvoiceController::class, 'creditPayoutReceipt'])->name('credit-payout.receipt');
            Route::get('debt-book', App\Livewire\DebtBook\Index::class)->name('debt-book.index');
            Route::get('debt-payment/{debtPayment}/receipt', [App\Http\Controllers\InvoiceController::class, 'debtReceipt'])->name('debt-payment.receipt');
        });

        // Shared sales pages
        Route::middleware('role:admin,branch_manager,sales,cashier,auditor')->group(function () {
            Route::get('sales', App\Livewire\Sales\Index::class)->name('sales.index');
            Route::get('invoice/{sale}', [App\Http\Controllers\InvoiceController::class, 'show'])->name('invoice.show');
            Route::get('receipt/{sale}', [App\Http\Controllers\InvoiceController::class, 'receipt'])->name('receipt.show');
            Route::get('return/{saleReturn}/receipt', [App\Http\Controllers\InvoiceController::class, 'returnReceipt'])->name('return.receipt');
            Route::get('order-invoice/{order}', [App\Http\Controllers\InvoiceController::class, 'orderInvoice'])->name('order.invoice');
            Route::get('order-receipt/{order}', [App\Http\Controllers\InvoiceController::class, 'orderReceipt'])->name('order.receipt');
        });

        // Customers & appointments: sales roles + promoters
        Route::middleware('role:admin,pharmacist,branch_manager,sales,cashier,promoter')->group(function () {
            Route::get('customers', App\Livewire\Customers\Index::class)->name('customers.index');
            Route::get('appointments', App\Livewire\Appointments\Index::class)->name('appointments.index');
        });

        // Commissions: managers see all; commission-eligible staff see own
        Route::middleware('role:admin,branch_manager,promoter,cashier,sales')->group(function () {
            Route::get('commissions', App\Livewire\Commissions\Index::class)->name('commissions.index');
        });

        // Drug catalogue and stock visibility — the pharmacist needs to know what
        // is stocked and what is expiring, so this stays open to them.
        Route::middleware('role:admin,pharmacist,branch_manager,inventory_manager')->group(function () {
            Route::get('categories', App\Livewire\Categories\Index::class)->name('categories.index');
            Route::get('products', App\Livewire\Products\Index::class)->name('products.index');
            Route::get('inventory', App\Livewire\Inventory\Index::class)->name('inventory.index');
            Route::get('inventory/print', App\Http\Controllers\InventoryPrintController::class)->name('inventory.print');
            Route::get('expiry-alerts', App\Livewire\ExpiryAlerts\Index::class)->name('expiry-alerts.index');
        });

        // Stock operations — these move stock, so not the auditor.
        Route::middleware('role:admin,branch_manager,inventory_manager')->group(function () {
            Route::get('products/import-template', App\Http\Controllers\ProductImportTemplateController::class)->name('products.import-template');
            Route::get('locations', App\Livewire\Locations\Index::class)->name('locations.index');
            Route::get('stock/transfers', App\Livewire\Stock\Transfers::class)->name('stock.transfers');
            Route::get('stock/adjustments', App\Livewire\Stock\Adjustments::class)->name('stock.adjustments');
            Route::get('stock/history', App\Livewire\Stock\History::class)->name('stock.history');
        });

        // Procurement — commits money to suppliers
        Route::middleware('role:admin,branch_manager,inventory_manager,auditor')->group(function () {
            Route::get('suppliers', App\Livewire\Suppliers\Index::class)->name('suppliers.index');
            Route::get('purchase-orders', App\Livewire\PurchaseOrders\Index::class)->name('purchase-orders.index');
        });

        // Reports — revenue, profit and CSV exports
        Route::middleware('role:admin,branch_manager,auditor')->group(function () {
            Route::get('reports', App\Livewire\Reports\Index::class)->name('reports.index');
        });

        // Expenses (admin, branch_manager, cashier)
        Route::middleware('role:admin,branch_manager,cashier,auditor')->group(function () {
            Route::get('expenses', App\Livewire\Expenses\Index::class)->name('expenses.index');
        });

        // Coupons (admin, branch_manager)
        Route::middleware('role:admin,branch_manager')->group(function () {
            Route::get('coupons', App\Livewire\Coupons\Index::class)->name('coupons.index');
        });

        // Financial oversight — the auditor sees all of this but changes none of it.
        Route::middleware('role:admin,branch_manager,auditor')->group(function () {
            Route::get('finance', App\Livewire\Finance\Index::class)->name('finance.index');
            Route::get('money-trail', App\Livewire\AuditTrail\Index::class)->name('audit-trail.index');
        });

        // Content / image uploaders (admin, content)
        Route::middleware('role:admin,content')->group(function () {
            Route::get('product-images', App\Livewire\Media\Index::class)->name('media.index');
        });

        // Admin only
        Route::middleware('role:admin')->group(function () {
            Route::get('staff', App\Livewire\Staff\Index::class)->name('staff.index');
            Route::get('branches', App\Livewire\Branches\Index::class)->name('branches.index');
            Route::get('settings', App\Livewire\Settings\Index::class)->name('settings.index');
            Route::get('logs', App\Livewire\Logs\Index::class)->name('logs.index');
        });
    });
});

require __DIR__.'/auth.php';
