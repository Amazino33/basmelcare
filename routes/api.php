<?php

use App\Http\Controllers\Api\VoucherRedeemController;
use Illuminate\Support\Facades\Route;

Route::post('/voucher/redeem', [VoucherRedeemController::class, 'redeem']);
