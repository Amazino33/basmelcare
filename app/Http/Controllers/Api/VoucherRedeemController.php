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

        // Receipts are stored uppercase; normalise so casing off the printed
        // receipt never causes a false "not found".
        $invoiceNumber = strtoupper(trim($request->invoice_number));

        $sale = Sale::with('customer')
            ->whereRaw('UPPER(invoice_number) = ?', [$invoiceNumber])
            ->first();

        if (! $sale) {
            return response()->json(['valid' => false, 'message' => 'Invoice not found.'], 404);
        }

        if (! in_array($sale->status, ['paid', 'completed'])) {
            return response()->json(['valid' => false, 'message' => 'This invoice has not been paid yet.'], 422);
        }

        // Staff have pulled this receipt's access.
        if ($sale->voucher_revoked_at) {
            return response()->json(['valid' => false, 'message' => 'This receipt is no longer valid for internet access.'], 422);
        }

        $hours = (int) AppSetting::get('voucher_validity_hours', 24);

        // First redemption — start the clock now.
        if (! $sale->voucher_redeemed_at) {
            $sale->update(['voucher_redeemed_at' => now()]);
        }

        // Expiry is measured from the FIRST redemption and never extended, so a
        // customer reconnecting mid-window keeps the same 24h wall-clock. This
        // also makes the endpoint idempotent — reconnecting simply re-validates.
        $expiresAt = $sale->wifiExpiresAt();

        if (! $expiresAt || $expiresAt->isPast()) {
            return response()->json(['valid' => false, 'message' => 'This receipt\'s free internet window has expired.'], 422);
        }

        return response()->json([
            'valid'          => true,
            'expires_at'     => $expiresAt->toDateTimeString(),
            'validity_hours' => $hours,
            'customer'       => $sale->customer?->name,
            'invoice_number' => $sale->invoice_number,
        ]);
    }
}
