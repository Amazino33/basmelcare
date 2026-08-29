<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\PromoterCode;
use App\Models\ReferralCommission;
use App\Models\Sale;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        // A receipt is claimed by its wifi_code, which is random and unique.
        $sale = Sale::with('customer')->where('wifi_code', $invoiceNumber)->first()

            // Receipts printed before wifi_code existed carry only the invoice
            // number, so those are still honoured - but only those. Invoice
            // numbers count up from 0001 each morning, so anything that accepts
            // one accepts a guess; restricting this to rows with no wifi_code
            // means no sale made since then can be reached by guessing.
            ?? Sale::with('customer')
                ->whereNull('wifi_code')
                ->whereRaw('UPPER(invoice_number) = ?', [$invoiceNumber])
                ->first();

        // Not a receipt — it may be a promoter-issued Wi-Fi code.
        if (! $sale) {
            return $this->redeemPromoterCode($invoiceNumber);
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

    /**
     * A code handed out by a promoter at registration. First redemption is what
     * pays them — the customer actually getting online is the thing we reward.
     */
    private function redeemPromoterCode(string $code): JsonResponse
    {
        $promoterCode = PromoterCode::with('customer')->where('code', $code)->first();

        if (! $promoterCode) {
            return response()->json(['valid' => false, 'message' => 'Invoice not found.'], 404);
        }

        if ($error = $promoterCode->redemptionError()) {
            return response()->json(['valid' => false, 'message' => $error], 422);
        }

        $hours = (int) AppSetting::get('voucher_validity_hours', 24);

        // First redemption starts the clock and earns the promoter their commission.
        if (! $promoterCode->redeemed_at) {
            DB::transaction(function () use ($promoterCode) {
                $promoterCode->update(['redeemed_at' => now()]);

                try {
                    ReferralCommission::create([
                        'user_id'     => $promoterCode->user_id,
                        'customer_id' => $promoterCode->customer_id,
                        'amount'      => (float) AppSetting::get('commission_amount', 100),
                    ]);
                } catch (QueryException $e) {
                    // Unique (user_id, customer_id): already paid for this customer,
                    // e.g. manually credited earlier. Redemption still succeeds.
                    Log::info('[PromoterCode] commission already recorded', [
                        'code' => $promoterCode->code,
                    ]);
                }
            });

            $promoterCode->refresh();
        }

        $expiresAt = $promoterCode->expiresAt();

        if (! $expiresAt || $expiresAt->isPast()) {
            return response()->json(['valid' => false, 'message' => 'This code\'s free internet window has expired.'], 422);
        }

        return response()->json([
            'valid'          => true,
            'expires_at'     => $expiresAt->toDateTimeString(),
            'validity_hours' => $hours,
            'customer'       => $promoterCode->customer?->name,
            'invoice_number' => null,
            'wifi_code'      => $promoterCode->code,
        ]);
    }
}
