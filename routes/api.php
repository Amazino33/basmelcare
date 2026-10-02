<?php

use App\Http\Controllers\Api\VoucherRedeemController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/voucher/redeem', [VoucherRedeemController::class, 'redeem']);
Route::match(['get', 'post'], '/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])->name('webhooks.whatsapp');

