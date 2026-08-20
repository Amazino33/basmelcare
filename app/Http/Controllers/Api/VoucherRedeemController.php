<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * LEGACY COPY — the staff app (public/app) owns voucher redemption.
 *
 * Both applications share one database and both expose this route, so a
 * misconfigured `basmelcare_api_url` in HiFastLink silently lands here. This
 * copy only understands sale receipts: a promoter Wi-Fi code looked like an
 * unknown invoice and came back "Invoice not found", which is indistinguishable
 * from a genuinely bad code and cost real debugging time.
 *
 * It now detects that case and says so explicitly instead.
 *
 * Point HiFastLink at the staff app's /api/voucher/redeem.
 */
class VoucherRedeemController extends Controller
{
    /**
     * Queried directly rather than through a model: this app has no PromoterCode
     * model, and deliberately so — the logic lives in the staff app. All this
     * needs to know is that the code exists, so it can say where to send it.
     */
    private function isPromoterCode(string $code): bool
    {
        if (! Schema::hasTable('promoter_codes')) {
            return false;
        }

        return DB::table('promoter_codes')->where('code', $code)->exists();
    }

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

        // New sales: look up by dedicated wifi_code column.
        // Old sales: fall back to suffix embedded in invoice_number.
        $sale = Sale::with('customer')->where('wifi_code', $invoiceNumber)->first()
            ?? (str_contains($invoiceNumber, '-')
                ? Sale::with('customer')->whereRaw('UPPER(invoice_number) = ?', [$invoiceNumber])->first()
                : Sale::with('customer')->whereRaw('UPPER(invoice_number) LIKE ?', ['%-' . $invoiceNumber])->first());

        if (! $sale) {
            // Is this actually a promoter code that reached the wrong app?
            if ($this->isPromoterCode($invoiceNumber)) {
                Log::error('[VoucherRedeem] Promoter code sent to the main site endpoint.', [
                    'code' => $invoiceNumber,
                    'fix'  => 'Point HiFastLink basmelcare_api_url at the staff app (public/app).',
                ]);

                return response()->json([
                    'valid'   => false,
                    'message' => 'Wi-Fi service is misconfigured. Please tell the pharmacy staff.',
                    'error'   => 'wrong_endpoint',
                    'detail'  => 'Promoter codes are handled by the staff app. '
                               . 'Update basmelcare_api_url in HiFastLink to the staff app URL.',
                ], 421);   // 421 Misdirected Request
            }

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
            'wifi_code'      => $sale->wifi_code,
        ]);
    }
}
