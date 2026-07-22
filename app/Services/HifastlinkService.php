<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HifastlinkService
{
    /**
     * Tell HiFastLink to revoke the Wi-Fi access tied to a receipt so the
     * device can no longer (re)connect. Passive revocation: HiFastLink deletes
     * the RADIUS credentials, so the next authentication attempt is rejected.
     *
     * Returns true when HiFastLink confirms the revoke (or has nothing to
     * revoke). Returns false on a transport/config error — the caller has
     * already flagged the receipt locally, so access is denied on our side
     * regardless; this only reports whether the push reached HiFastLink.
     */
    public static function revoke(string $invoiceNumber): bool
    {
        $baseUrl = rtrim((string) AppSetting::get('hifastlink_url', ''), '/');
        $apiKey  = (string) AppSetting::get('hifastlink_api_key', '');

        if (! $baseUrl || ! $apiKey) {
            Log::warning('[Hifastlink] revoke skipped — integration URL/key not configured.', [
                'invoice' => $invoiceNumber,
            ]);

            return false;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['X-API-Key' => $apiKey])
                ->post($baseUrl . '/api/pharmacy/revoke', [
                    'invoice_number' => $invoiceNumber,
                ]);

            if (! $response->successful()) {
                Log::error('[Hifastlink] revoke rejected by HiFastLink.', [
                    'invoice' => $invoiceNumber,
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('[Hifastlink] revoke call failed: ' . $e->getMessage(), [
                'invoice' => $invoiceNumber,
            ]);

            return false;
        }
    }
}
