<?php

namespace App\Services;

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Customer;
use App\Models\Sale;

/**
 * Sends a broadcast, a few at a time.
 *
 * Everything goes out one message per person. Nobody is put in a group, so no
 * customer ever sees another's number - which for a pharmacy would be telling
 * people who else buys medicine here.
 *
 * Sending is deliberately batched. Several hundred messages in one web request
 * times out, exactly as the Cloudinary upload did, and hammering an unofficial
 * WhatsApp gateway with hundreds of calls back to back is the pattern that
 * gets a business number banned. The same number sends your receipts.
 */
class BroadcastSender
{
    /** Messages per run. 5 messages with anti-ban delays fits safely within web requests. */
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
     * Resolves dynamic template variables ({name}, {first_name}, {pharmacy})
     * so each message has unique content and hash signatures, preventing bulk-spam detection.
     */
    public function personalizeMessage(string $template, ?Customer $customer): string
    {
        $name = trim((string) ($customer?->name ?? ''));
        if ($name !== '') {
            // Customer names are stored in UPPERCASE by NormalisesName;
            // format into Title Case so WhatsApp greetings don't appear in all-caps shouting.
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

        return str_ireplace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Who a broadcast goes to.
     *
     * A customer with no phone number cannot be messaged, so they are left out.
     * Customers who have opted out (broadcast_opt_out_at) are excluded to prevent spam reports.
     */
    public function audience(string $audience)
    {
        $query = Customer::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNull('broadcast_opt_out_at');

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
     * Send the next batch with optional anti-ban jitter delays.
     *
     * @return array{sent: int, whatsapp: int, sms: int, failed: int, remaining: int}
     */
    public function sendBatch(Broadcast $broadcast, int $limit = self::BATCH): array
    {
        if (! $broadcast->started_at) {
            $broadcast->forceFill(['started_at' => now()])->save();
        }

        $imageUrl = $broadcast->imageUrl();

        $pending = $broadcast->recipients()
            ->with('customer')
            ->where('status', 'pending')
            ->limit($limit)
            ->get();

        $tally = ['sent' => 0, 'whatsapp' => 0, 'sms' => 0, 'failed' => 0];

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
                // Both SMS outcomes are "it went by SMS" as far as the record
                // is concerned; the difference matters for commission, not here.
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
