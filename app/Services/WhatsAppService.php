<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function send(string $phone, string $message): bool
    {
        $enabled = AppSetting::bool('wawp_enabled', false);
        $instanceId = AppSetting::get('wawp_instance_id', '');
        $token = AppSetting::get('wawp_access_token', '');

        if (!$enabled || empty($instanceId) || empty($token)) {
            Log::info("[WhatsApp] Not configured. Would send to {$phone}: {$message}");
            return false;
        }

        $number = preg_replace('/\D/', '', $phone);

        if (strlen($number) === 11 && str_starts_with($number, '0')) {
            $number = '234' . substr($number, 1);
        }

        try {
            $response = Http::get('https://api.wawp.net/v2/send/text', [
                'instance_id' => $instanceId,
                'access_token' => $token,
                'chatId' => $number . '@c.us',
                'message' => $message,
            ]);

            if ($response->successful()) {
                Log::info("[WhatsApp] Sent to {$phone}");
                return true;
            }

            Log::warning("[WhatsApp] HTTP {$response->status()} for {$phone}: " . $response->body());
            return false;
        } catch (\Throwable $e) {
            Log::error("[WhatsApp] Exception sending to {$phone}: " . $e->getMessage());
            return false;
        }
    }
}
