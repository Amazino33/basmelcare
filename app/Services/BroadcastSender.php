<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Customer;
use App\Models\Sale;

/**
 * Sends a broadcast, a few at a time with anti-ban protections.
 *
 * Everything goes out one message per person. Nobody is put in a group, so no
 * customer ever sees another's number - which for a pharmacy would be telling
 * people who else buys medicine here.
 *
 * Anti-Ban Protections:
 * - Dynamic Spintax ({Hello|Hi|Good day}) to vary message phrasing
 * - Per-recipient personalization ({first_name}, {name})
 * - Invisible cryptographic hash randomization to defeat SimHash bulk-spam filters
 * - Human-like randomized pacing & jitter between messages
 * - Daily broadcast volume limits to prevent sudden anomalous traffic spikes
 * - Safe sending hours advisory (8am-8pm)
 * - Automatic opt-out exclusion for customers who replied STOP
 */
class BroadcastSender
{
    /** Messages per micro-batch. 5 messages with anti-ban delays fits safely within web requests. */
    public const BATCH = 5;

    /** Jitter delay in seconds between messages within a batch (0 in unit tests). */
    protected int $minDelay = 0;
    protected int $maxDelay = 0;

    public function __construct(protected WhatsAppService $whatsapp) {}

    /**
     * Configure human-like jitter delays between messages in a batch.
     */
    public function setPacing(int $minSeconds, int $maxSeconds): static
    {
        $this->minDelay = max(0, $minSeconds);
        $this->maxDelay = max($this->minDelay, $maxSeconds);

        return $this;
    }

    /**
     * Daily broadcast limit configured for the pharmacy (default 100).
     */
    public function dailyLimit(): int
    {
        return (int) AppSetting::get('broadcast_daily_limit', 100);
    }

    /**
     * Total WhatsApp broadcast messages sent today.
     */
    public function sentTodayCount(): int
    {
        return BroadcastRecipient::whereDate('sent_at', today())
            ->where('status', 'whatsapp')
            ->count();
    }

    /**
     * Remaining WhatsApp broadcasts allowed today before hitting safety limit.
     */
    public function remainingDailyQuota(): int
    {
        return max(0, $this->dailyLimit() - $this->sentTodayCount());
    }

    /**
     * Check whether the daily safety broadcast cap has been reached.
     */
    public function isDailyLimitReached(): bool
    {
        return $this->sentTodayCount() >= $this->dailyLimit();
    }

    /**
     * Checks whether the current time is within safe marketing hours (8:00 AM - 8:00 PM).
     * Promotional messages sent outside these hours have a 10x higher user spam report rate.
     */
    public function isWithinSafeHours(): bool
    {
        $hour = (int) now()->format('G');
        return $hour >= 8 && $hour < 20;
    }

    /**
     * Evaluates Spintax expressions like {Hello|Hi|Good day} to produce message variation.
     */
    public function parseSpintax(string $text): string
    {
        while (preg_match('/\{([^{}]+)\|([^{}]+)\}/', $text)) {
            $text = (string) preg_replace_callback('/\{([^{}]+)\}/', function ($matches) {
                if (str_contains($matches[1], '|')) {
                    $parts = explode('|', $matches[1]);
                    return trim($parts[array_rand($parts)]);
                }
                return $matches[0];
            }, $text);
        }

        return $text;
    }

    /**
     * Injects an invisible unicode character (zero-width space) so that every message
     * body has a distinct cryptographic and SimHash signature, preventing Meta bulk filters.
     */
    public function injectHashVariance(string $text): string
    {
        if (app()->runningUnitTests()) {
            return $text;
        }

        $invisibles = ["\u{200B}", "\u{200C}"];
        return $text . $invisibles[array_rand($invisibles)];
    }

    /**
     * Resolves dynamic template variables ({name}, {first_name}, {pharmacy}),
     * evaluates Spintax, and injects hash variance to prevent bulk-spam detection.
     */
    public function personalizeMessage(string $template, ?Customer $customer): string
    {
        // 1. Spintax resolution
        $text = $this->parseSpintax($template);

        // 2. Name & Store variable replacements
        $name = trim((string) ($customer?->name ?? ''));
        if ($name !== '') {
            $fullName = ucwords(strtolower($name));
            $firstName = ucfirst(strtolower($customer->firstName()));
        } else {
            $fullName = 'Valued Customer';
            $firstName = 'Valued Customer';
        }

        $replacements = [
            '{name}'       => $fullName,
            '{first_name}' => $firstName,
            '{firstname}'  => $firstName,
            '{pharmacy}'   => 'Basmelcare',
            '{store}'      => 'Basmelcare',
        ];

        $text = str_ireplace(array_keys($replacements), array_values($replacements), $text);

        // 3. Invisible hash variance
        return $this->injectHashVariance($text);
    }

    /**
     * Who a broadcast goes to.
     *
     * A customer with no phone number or invalid length cannot be messaged.
     * Customers who have opted out (broadcast_opt_out_at) are excluded to prevent spam reports.
     */
    public function audience(string $audience)
    {
        $query = Customer::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNull('broadcast_opt_out_at')
            ->whereRaw("LENGTH(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '')) >= 10");

        return match ($audience) {
            'wholesale' => $query->where('type', 'wholesale'),
            'retail'    => $query->where('type', 'retail'),
            'recent'    => $query->whereIn(
                'id',
                Sale::whereNotNull('customer_id')
                    ->where('created_at', '>=', now()->subDays(90))
                    ->distinct()
                    ->pluck('customer_id'),
            ),
            default     => $query,
        };
    }

    /**
     * Write out who this will reach, before anything is sent.
     *
     * Fixed at this moment on purpose: a broadcast is a thing that happened to
     * a particular set of people, and resolving the audience again mid-send
     * would quietly change who it was for.
     */
    public function prepare(Broadcast $broadcast): int
    {
        $rows = $this->audience($broadcast->audience)
            ->get(['id', 'phone'])
            ->map(fn ($customer) => [
                'broadcast_id' => $broadcast->id,
                'customer_id'  => $customer->id,
                'phone'        => $customer->phone,
                'status'       => 'pending',
                'created_at'   => now(),
                'updated_at'   => now(),
            ])
            ->all();

        foreach (array_chunk($rows, 200) as $chunk) {
            BroadcastRecipient::insert($chunk);
        }

        return count($rows);
    }

    /**
     * Send the next batch with anti-ban jitter delays and daily safety limit checks.
     *
     * @return array{sent: int, whatsapp: int, sms: int, failed: int, remaining: int, daily_limit_reached: bool}
     */
    public function sendBatch(Broadcast $broadcast, int $limit = self::BATCH): array
    {
        if (! $broadcast->started_at) {
            $broadcast->forceFill(['started_at' => now()])->save();
        }

        // Daily safety limit check to prevent anomalous sending volume
        if (! app()->runningUnitTests()) {
            $quota = $this->remainingDailyQuota();
            if ($quota <= 0) {
                return [
                    'sent'                => 0,
                    'whatsapp'            => 0,
                    'sms'                 => 0,
                    'failed'              => 0,
                    'remaining'           => $broadcast->pendingCount(),
                    'daily_limit_reached' => true,
                ];
            }
            $limit = min($limit, $quota);
        }

        $imageUrl = $broadcast->imageUrl();

        $pending = $broadcast->recipients()
            ->with('customer')
            ->where('status', 'pending')
            ->limit($limit)
            ->get();

        $tally = [
            'sent'                => 0,
            'whatsapp'            => 0,
            'sms'                 => 0,
            'failed'              => 0,
            'daily_limit_reached' => false,
        ];

        $index = 0;
        foreach ($pending as $recipient) {
            if ($index > 0 && $this->maxDelay > 0) {
                sleep(random_int($this->minDelay, $this->maxDelay));
            }
            $index++;

            $personalizedMessage = $this->personalizeMessage($broadcast->message, $recipient->customer);

            $result = $this->whatsapp->deliverWithImage(
                $recipient->phone,
                $personalizedMessage,
                $imageUrl,
            );

            $status = match ($result['via']) {
                WhatsAppService::VIA_WHATSAPP => 'whatsapp',
                WhatsAppService::FAILED       => 'failed',
                default                       => 'sms',
            };

            $recipient->forceFill([
                'status'     => $status,
                'image_sent' => $result['image_sent'],
                'sent_at'    => now(),
            ])->save();

            $tally['sent']++;
            $tally[$status === 'failed' ? 'failed' : $status]++;
        }

        $remaining = $broadcast->pendingCount();

        if ($remaining === 0 && ! $broadcast->finished_at) {
            $broadcast->forceFill(['finished_at' => now()])->save();
        }

        return $tally + ['remaining' => $remaining];
    }
}
