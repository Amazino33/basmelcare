<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoterCode extends Model
{
    protected $fillable = [
        'code', 'user_id', 'customer_id', 'delivered_via',
        'valid_until', 'redeemed_at', 'revoked_at',
    ];

    /** Reached only by SMS, so the customer has no device that can use Wi-Fi. */
    public function isSmsOnly(): bool
    {
        return $this->delivered_via === \App\Services\WhatsAppService::VIA_SMS;
    }

    protected $casts = [
        'valid_until' => 'date',
        'redeemed_at' => 'datetime',
        'revoked_at'  => 'datetime',
    ];

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Codes share a redemption endpoint with sale Wi-Fi codes, so they must be
     * unique across BOTH tables — a collision would hand the wrong person access.
     */
    public static function generateCode(): string
    {
        do {
            $code = static::randomCode();
        } while (
            static::where('code', $code)->exists()
            || Sale::where('wifi_code', $code)->exists()
        );

        return $code;
    }

    /** Same alphabet as sale Wi-Fi codes — no I, L, O, 0 or 1 to misread aloud. */
    private static function randomCode(int $length = 6): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $max      = strlen($alphabet) - 1;
        $code     = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    /** Why this code cannot be redeemed right now, or null when it is good. */
    public function redemptionError(): ?string
    {
        if ($this->revoked_at) {
            return 'This code is no longer valid for internet access.';
        }

        // valid_until is a date, so it casts to midnight. The code is good for
        // the WHOLE of that day — compare against its end, not its start.
        if ($this->valid_until && $this->valid_until->endOfDay()->isPast()) {
            return 'This code has expired.';
        }

        return null;
    }

    public function expiresAt(): ?\Illuminate\Support\Carbon
    {
        if (! $this->redeemed_at) {
            return null;
        }

        return $this->redeemed_at->copy()
            ->addHours((int) AppSetting::get('voucher_validity_hours', 24));
    }
}
