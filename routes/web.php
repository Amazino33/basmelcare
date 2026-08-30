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
Route::get('/order/{order}/pay', [App\Http\Controllers\PaystackController::class, 'pay'])->name('order.pay');
Route::get('/order/{order}/confirmation', fn(App\Models\Order $order) => view('public.order-confirmation', ['order' => $order]))->name('order.confirmation');

// Customer portal
Route::middleware('auth:customer')->group(function () {
    Route::get('/account', App\Livewire\Customer\Account::class)->name('customer.account');
});
