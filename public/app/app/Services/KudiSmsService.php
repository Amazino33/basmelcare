<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KudiSmsService
{
    public function send(string $phone, string $message): bool
    {
        $enabled  = AppSetting::bool('kudisms_enabled', false);
        $token    = AppSetting::get('kudisms_token', '');
        $senderId = AppSetting::get('kudisms_sender_id', 'BasmelCare');

        if (!$enabled) {
            Log::info("[KudiSMS] Disabled (kudisms_enabled is off). Would send to {$phone}");
            return false;
        }

        if (empty($token)) {
            Log::info("[KudiSMS] No token set (kudisms_token is empty). Would send to {$phone}");
            return false;
        }

        $number = $this->normalizePhone($phone);

        if (!$number) {
            Log::warning("[KudiSMS] Invalid phone: {$phone}");
            return false;
        }

        try {
            $gateway  = (int) AppSetting::get('kudisms_gateway', 2);

            $response = Http::asJson()->post('https://my.kudisms.net/api/autocomposesms', [
                'token'   => $token,
                'gateway' => $gateway,
                'data'    => [[$senderId, $number, $message]],
            ]);

            if ($response->successful()) {
                Log::info("[KudiSMS] Sent to {$phone} → normalized: {$number}");
                return true;
            }

            Log::warning("[KudiSMS] Failed for {$phone} → normalized: {$number} — HTTP {$response->status()} — " . $response->body());
            return false;
        } catch (\Throwable $e) {
            Log::error("[KudiSMS] Exception for {$phone}: " . $e->getMessage());
            return false;
        }
    }

    private function normalizePhone(string $phone): ?string
    {
        $number = preg_replace('/\D/', '', $phone);

        // Already local format: 08012345678
        if (strlen($number) === 11 && str_starts_with($number, '0')) {
            return $number;
        }

        // International format: 2348012345678 → 08012345678
        if (strlen($number) === 13 && str_starts_with($number, '234')) {
            return '0' . substr($number, 3);
        }

        return null;
    }
}
