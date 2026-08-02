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

        if (!$enabled || empty($token)) {
            Log::info("[KudiSMS] Not configured. Would send to {$phone}");
            return false;
        }

        $number = $this->normalizePhone($phone);

        if (!$number) {
            Log::warning("[KudiSMS] Invalid phone: {$phone}");
            return false;
        }

        try {
            $response = Http::post('https://my.kudisms.net/api/personalisedsms', [
                'token'      => $token,
                'senderID'   => $senderId,
                'message'    => $message,
                'csvHeaders' => ['phone_number'],
                'recipients' => [['phone_number' => $number]],
            ]);

            $body = $response->json();

            if ($response->successful() && ($body['error_code'] ?? '') === '000') {
                Log::info("[KudiSMS] Sent to {$phone}");
                return true;
            }

            Log::warning("[KudiSMS] Failed for {$phone}: " . json_encode($body));
            return false;
        } catch (\Throwable $e) {
            Log::error("[KudiSMS] Exception for {$phone}: " . $e->getMessage());
            return false;
        }
    }

    private function normalizePhone(string $phone): ?string
    {
        $number = preg_replace('/\D/', '', $phone);

        if (strlen($number) === 11 && str_starts_with($number, '0')) {
            return '234' . substr($number, 1);
        }

        if (strlen($number) === 13 && str_starts_with($number, '234')) {
            return $number;
        }

        return null;
    }
}
