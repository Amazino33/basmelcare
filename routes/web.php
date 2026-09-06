<?php

use Illuminate\Support\Facades\Route;

// Public pages
Route::get('/', App\Livewire\Shop\Home::class)->name('home');
Route::get('/shop', App\Livewire\Shop\Index::class)->name('shop.index');
Route::get('/shop/{product}', App\Livewire\Shop\Show::class)->name('shop.show');
Route::get('/cart', App\Livewire\Shop\Cart::class)->name('cart');
Route::get('/paystack/callback', [App\Http\Controllers\PaystackController::class, 'callback'])->name('paystack.callback');

// Consultations. Anyone can book: identity comes from the phone number, which
// is matched to a customer record or creates one, because the free allowance
// is per customer and an unattached booking could claim it repeatedly.
Route::get('/consultation', App\Livewire\Consultations\Book::class)->name('consultation.book');
Route::get('/consultation/{appointment}/pay', [App\Http\Controllers\ConsultationPaymentController::class, 'pay'])->name('consultation.pay');
Route::get('/consultation/callback', [App\Http\Controllers\ConsultationPaymentController::class, 'callback'])->name('consultation.callback');
Route::get('/consultation/{appointment}/confirmed', [App\Http\Controllers\ConsultationPaymentController::class, 'confirmation'])->name('consultation.confirmation');

// Customer auth
Route::middleware('guest:customer')->group(function () {
    Route::get('/login', App\Livewire\Customer\Login::class)->name('customer.login');
    Route::get('/register', App\Livewire\Customer\Register::class)->name('customer.register');
});

// Checkout (guest or logged in)
Route::get('/checkout', App\Livewire\Shop\Checkout::class)->name('checkout');
// Bound on the token, not the id. These pages have no login in front of
// them - a guest has no account to log into - so the link itself is what
// stands between one customer's order and the next. Addressed by id, as they
// were, anyone could count upwards and read a stranger's order: what they
// bought, what they paid, and the street it was going to.
//
// The binding is set here rather than by overriding getRouteKeyName on the
// model, which would also reach the staff app's invoice, receipt and
// prescription-file routes - all of which are bound by id.
Route::get('/order/{order:public_token}/pay', [App\Http\Controllers\PaystackController::class, 'pay'])->name('order.pay');
Route::get('/order/{order:public_token}', App\Livewire\Shop\OrderStatus::class)->name('order.status');

// Getting back to an order whose link has been lost. Kept off the /order
// prefix on purpose: anything under there would have to be excluded from the
// token pattern, and one day somebody would forget.
Route::get('/find-order', App\Livewire\Shop\FindOrder::class)->name('order.find');

// Customer portal
Route::middleware('auth:customer')->group(function () {
    Route::get('/account', App\Livewire\Customer\Account::class)->name('customer.account');
});
