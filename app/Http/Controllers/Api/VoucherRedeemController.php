<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoucherRedeemController extends Controller
{
    public function redeem(Request $request): JsonResponse
    {
        $expectedKey = AppSetting::get('hifastlink_api_key', '');

        if (! $expectedKey || $request->header('X-API-Key') !== $expectedKey) {
            return response()->json(['valid' => false, 'message' => 'Unauthorized.'], 401);
        }

        $request->validate(['invoice_number' => 'required|string']);

        $sale = Sale::with('customer')
            ->where('invoice_number', $request->invoice_number)
            ->first();

        if (! $sale) {
            return response()->json(['valid' => false, 'message' => 'Invoice not found.'], 404);
        }

        if (! in_array($sale->status, ['paid', 'completed'])) {
            return response()->json(['valid' => false, 'message' => 'This invoice has not been paid yet.'], 422);
        }

        if ($sale->voucher_redeemed_at) {
            return response()->json(['valid' => false, 'message' => 'This receipt has already been redeemed.'], 422);
        }

        $hours = (int) AppSetting::get('voucher_validity_hours', 24);
        $expiresAt = now()->addHours($hours);

        $sale->update(['voucher_redeemed_at' => now()]);

        return response()->json([
            'valid'          => true,
            'expires_at'     => $expiresAt->toDateTimeString(),
            'validity_hours' => $hours,
            'customer'       => $sale->customer?->name,
            'invoice_number' => $sale->invoice_number,
        ]);
    }
}
