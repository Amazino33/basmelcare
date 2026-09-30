<?php

use App\Http\Controllers\ConsultationPaymentController;
use App\Http\Controllers\PaystackController;
use App\Livewire\Consultations\Book;
use App\Livewire\Customer\Account;
use App\Livewire\Customer\Login;
use App\Livewire\Customer\Register;
use App\Livewire\Shop\Cart;
use App\Livewire\Shop\Checkout;
use App\Livewire\Shop\FindOrder;
use App\Livewire\Shop\Home;
use App\Livewire\Shop\Index;
use App\Livewire\Shop\OrderStatus;
use App\Livewire\Shop\Show;
use Illuminate\Support\Facades\Route;

// Public pages
Route::get('/', Home::class)->name('home');
Route::get('/shop', Index::class)->name('shop.index');
Route::get('/shop/{product}', Show::class)->name('shop.show');
Route::get('/cart', Cart::class)->name('cart');
Route::get('/paystack/callback', [PaystackController::class, 'callback'])->name('paystack.callback');

// Consultations. Anyone can book: identity comes from the phone number, which
// is matched to a customer record or creates one, because the free allowance
// is per customer and an unattached booking could claim it repeatedly.
Route::get('/consultation', Book::class)->name('consultation.book');
Route::get('/consultation/{appointment}/pay', [ConsultationPaymentController::class, 'pay'])->name('consultation.pay');
Route::get('/consultation/callback', [ConsultationPaymentController::class, 'callback'])->name('consultation.callback');
Route::get('/consultation/{appointment}/confirmed', [ConsultationPaymentController::class, 'confirmation'])->name('consultation.confirmation');

// Customer auth
Route::middleware('guest:customer')->group(function () {
    Route::get('/login', Login::class)->name('customer.login');
    Route::get('/register', Register::class)->name('customer.register');
});
Route::redirect('/customer/login', '/login');
Route::redirect('/customer/register', '/register');

// Checkout (guest or logged in)
Route::get('/checkout', Checkout::class)->name('checkout');
// Bound on the token, not the id. These pages have no login in front of
// them - a guest has no account to log into - so the link itself is what
// stands between one customer's order and the next. Addressed by id, as they
// were, anyone could count upwards and read a stranger's order: what they
// bought, what they paid, and the street it was going to.
//
// The binding is set here rather than by overriding getRouteKeyName on the
// model, which would also reach the staff app's invoice, receipt and
// prescription-file routes - all of which are bound by id.
Route::get('/order/{order:public_token}/pay', [PaystackController::class, 'pay'])->name('order.pay');
Route::get('/order/{order:public_token}', OrderStatus::class)->name('order.status');

// Getting back to an order whose link has been lost. Kept off the /order
// prefix on purpose: anything under there would have to be excluded from the
// token pattern, and one day somebody would forget.
Route::get('/find-order', FindOrder::class)->name('order.find');

// Customer portal
Route::middleware('auth:customer')->group(function () {
    Route::get('/account', Account::class)->name('customer.account');
});
