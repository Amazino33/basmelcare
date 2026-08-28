<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /** Delivered over WhatsApp — the customer has a smart device. */
    public const VIA_WHATSAPP = 'whatsapp';

    /** WhatsApp was working but this number is not on it — feature phone. */
    public const VIA_SMS = 'sms';

    /**
     * WhatsApp itself was unavailable (not configured, API down, timeout), so
     * we fell back to SMS without learning anything about the customer.
     * Callers must NOT read this as "no smart device".
     */
    public const VIA_SMS_DEGRADED = 'sms_degraded';

    public const FAILED = 'failed';

    public function __construct(protected KudiSmsService $sms) {}

    /**
     * Kept for the many callers that only care whether the message got out.
     */
    public function send(string $phone, string $message): bool
    {
        return $this->deliver($phone, $message) !== self::FAILED;
    }

    /**
     * Send and report HOW it was delivered.
     *
     * The distinction matters: a genuine per-number WhatsApp rejection tells us
     * the customer has no smart device, but a WhatsApp outage tells us nothing.
     * Treating the second as the first would hand out commission for customers
     * who could have connected perfectly well.
     */
    public function deliver(string $phone, string $message): string
    {
        $result = $this->attemptWhatsApp($phone, $message);

        if ($result === self::VIA_WHATSAPP) {
            return self::VIA_WHATSAPP;
        }

        if (! $this->sms->send($phone, $message)) {
            return self::FAILED;
        }

        // $result is either 'no whatsapp for this number' or 'whatsapp unusable'.
        return $result;
    }

    /**
     * @return string VIA_WHATSAPP on success, VIA_SMS when the number is not on
     *                WhatsApp, VIA_SMS_DEGRADED when WhatsApp itself failed us.
     */
    private function attemptWhatsApp(string $phone, string $message): string
    {
        $enabled    = AppSetting::bool('wawp_enabled', false);
        $instanceId = AppSetting::get('wawp_instance_id', '');
        $token      = AppSetting::get('wawp_access_token', '');

        if (!$enabled || empty($instanceId) || empty($token)) {
            Log::info("[WhatsApp] Not configured. Would send to {$phone}: {$message}");
            return self::VIA_SMS_DEGRADED;
        }

        try {
            $response = Http::timeout(15)->get('https://api.wawp.net/v2/send/text', [
                'instance_id'  => $instanceId,
                'access_token' => $token,
                'chatId'       => $this->chatId($phone),
                'message'      => $message,
            ]);

            if (! $response->successful()) {
                // Transport/service problem, not a fact about the customer.
                Log::warning("[WhatsApp] HTTP {$response->status()} for {$phone}: " . $response->body());
                return self::VIA_SMS_DEGRADED;
            }

            $body   = $response->json();
            $status = strtolower((string) ($body['status'] ?? 'success'));

            if ($status === 'success' || $status === 'ok') {
                Log::info("[WhatsApp] Sent to {$phone}");
                return self::VIA_WHATSAPP;
            }

            // A clean HTTP response that declined this specific number — the
            // usual reason being that the number has no WhatsApp account.
            Log::info("[WhatsApp] Declined for {$phone}: " . $response->body());
            return self::VIA_SMS;
        } catch (\Throwable $e) {
            Log::error("[WhatsApp] Exception sending to {$phone}: " . $e->getMessage());
            return self::VIA_SMS_DEGRADED;
        }
    }

    /**
     * Send with a picture, falling back to the words alone.
     *
     * The gateway fetches the image from a URL rather than receiving an
     * upload, so it has to be publicly reachable.
     *
     * SMS cannot carry a picture. A customer on a feature phone therefore gets
     * the message and not the image, which is why the caller is told whether
     * the image actually went - and why the words have to stand on their own.
     *
     * @return array{via: string, image_sent: bool}
     */
    public function deliverWithImage(string $phone, string $message, ?string $imageUrl): array
    {
        if (! $imageUrl) {
            return ['via' => $this->deliver($phone, $message), 'image_sent' => false];
        }

        if ($this->attemptWhatsAppMedia($phone, $message, $imageUrl) === self::VIA_WHATSAPP) {
            return ['via' => self::VIA_WHATSAPP, 'image_sent' => true];
        }

        // The picture could not go. Send the words the ordinary way rather
        // than dropping the message entirely.
        return ['via' => $this->deliver($phone, $message), 'image_sent' => false];
    }

    private function attemptWhatsAppMedia(string $phone, string $message, string $imageUrl): string
    {
        $enabled    = AppSetting::bool('wawp_enabled', false);
        $instanceId = AppSetting::get('wawp_instance_id', '');
        $token      = AppSetting::get('wawp_access_token', '');

        if (! $enabled || empty($instanceId) || empty($token)) {
            Log::info("[WhatsApp] Not configured. Would send image to {$phone}");

            return self::VIA_SMS_DEGRADED;
        }

        try {
            $response = Http::timeout(30)->get('https://api.wawp.net/v2/send/media', [
                'instance_id'  => $instanceId,
                'access_token' => $token,
                'chatId'       => $this->chatId($phone),
                'media_url'    => $imageUrl,
                'caption'      => $message,
                'type'         => 'image',
            ]);

            if (! $response->successful()) {
                Log::warning("[WhatsApp] media HTTP {$response->status()} for {$phone}: " . $response->body());

                return self::VIA_SMS_DEGRADED;
            }

            $status = strtolower((string) ($response->json()['status'] ?? 'success'));

            if ($status === 'success' || $status === 'ok') {
                return self::VIA_WHATSAPP;
            }

            Log::info("[WhatsApp] media declined for {$phone}: " . $response->body());

            return self::VIA_SMS;
        } catch (\Throwable $e) {
            Log::error("[WhatsApp] media exception for {$phone}: " . $e->getMessage());

            return self::VIA_SMS_DEGRADED;
        }
    }

    /** Nigerian numbers arrive as 0803..., the gateway wants 234803...@c.us. */
    private function chatId(string $phone): string
    {
        $number = preg_replace('/\D/', '', $phone);

        if (strlen($number) === 11 && str_starts_with($number, '0')) {
            $number = '234' . substr($number, 1);
        }

        return $number . '@c.us';
    }
}
