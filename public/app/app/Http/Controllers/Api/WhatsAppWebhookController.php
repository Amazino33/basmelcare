<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Handles incoming WhatsApp webhook events (WAWP / WhatsApp Web / Cloud API).
 *
 * Automatically detects opt-out ("STOP", "UNSUBSCRIBE") and opt-in ("START")
 * requests from customers. When a customer replies STOP, they are instantly
 * marked as opted out and an automated confirmation is returned.
 *
 * This prevents the #1 trigger for Meta number bans: customers reporting
 * numbers as spam when they cannot unsubscribe.
 */
class WhatsAppWebhookController extends Controller
{
    protected const OPT_OUT_KEYWORDS = [
        'stop', 'unsubscribe', 'opt out', 'optout', 'cancel', 'quit', 'end', 'no more', 'stop promo',
    ];

    protected const OPT_IN_KEYWORDS = [
        'start', 'subscribe', 'unstop', 'opt in', 'optin', 'resume',
    ];

    public function handle(Request $request, WhatsAppService $whatsapp)
    {
        // 1. Meta / Webhook challenge handshake
        if ($request->has('hub_challenge')) {
            return response($request->query('hub_challenge'), 200)
                ->header('Content-Type', 'text/plain');
        }

        // 2. Ignore messages sent by our own instance
        if ($request->input('data.fromMe') === true || $request->input('fromMe') === true) {
            return response()->json(['status' => 'ignored_self']);
        }

        // 3. Extract sender phone
        $rawSender = $request->input('data.from')
            ?? $request->input('data.chatId')
            ?? $request->input('chatId')
            ?? $request->input('from')
            ?? $request->input('phone')
            ?? $request->input('entry.0.changes.0.value.messages.0.from');

        if (! $rawSender) {
            return response()->json(['status' => 'no_sender']);
        }

        // Strip @c.us, @s.whatsapp.net and non-digits
        $phone = preg_replace('/\D+/', '', explode('@', (string) $rawSender)[0]);

        // 4. Extract incoming message text
        $messageText = $request->input('data.body')
            ?? $request->input('data.message')
            ?? $request->input('data.text')
            ?? $request->input('message')
            ?? $request->input('body')
            ?? $request->input('text')
            ?? $request->input('entry.0.changes.0.value.messages.0.text.body')
            ?? '';

        $cleanText = strtolower(trim((string) $messageText));

        if ($cleanText === '') {
            return response()->json(['status' => 'empty_message']);
        }

        $customer = Customer::findByPhone($phone);

        // 5. Handle Opt-Out request
        if (in_array($cleanText, self::OPT_OUT_KEYWORDS, true)) {
            if ($customer) {
                $customer->update(['broadcast_opt_out_at' => now()]);
                Log::info("[WhatsApp Webhook] Customer #{$customer->id} ({$customer->phone}) opted out via message '{$messageText}'.");
                $name = $customer->firstName() ? ' ' . $customer->firstName() : '';
            } else {
                Log::info("[WhatsApp Webhook] Unregistered number {$phone} opted out via message '{$messageText}'.");
                $name = '';
            }

            try {
                $reply = "Hi{$name}, you have been unsubscribed from Basmelcare broadcast messages. You will no longer receive promotional updates (receipt slips for purchases will continue to be delivered). Reply START at any time to re-subscribe.";
                $whatsapp->send($phone, $reply);
            } catch (\Throwable $e) {
                Log::warning("[WhatsApp Webhook] Failed sending opt-out auto-reply to {$phone}: " . $e->getMessage());
            }

            return response()->json(['status' => 'opted_out']);
        }

        // 6. Handle Opt-In / Re-subscribe request
        if (in_array($cleanText, self::OPT_IN_KEYWORDS, true)) {
            if ($customer) {
                $customer->update(['broadcast_opt_out_at' => null]);
                Log::info("[WhatsApp Webhook] Customer #{$customer->id} ({$customer->phone}) opted back in via message '{$messageText}'.");
                $name = $customer->firstName() ? ' ' . $customer->firstName() : '';
            } else {
                Log::info("[WhatsApp Webhook] Unregistered number {$phone} opted back in via message '{$messageText}'.");
                $name = '';
            }

            try {
                $reply = "Hi{$name}, welcome back! You have been re-subscribed to Basmelcare updates. Reply STOP to opt out at any time.";
                $whatsapp->send($phone, $reply);
            } catch (\Throwable $e) {
                Log::warning("[WhatsApp Webhook] Failed sending opt-in auto-reply to {$phone}: " . $e->getMessage());
            }

            return response()->json(['status' => 'opted_in']);
        }

        return response()->json(['status' => 'received']);
    }
}
