<?php

namespace App\Models;

use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'invoice_number', 'wifi_code', 'user_id', 'cashier_id', 'confirmed_by', 'customer_id',
        'total_amount', 'payment_method', 'payment_details',
        'status', 'paid_at', 'confirmed_at', 'note',
        'voucher_redeemed_at', 'voucher_revoked_at',
        // Were missing, so processPayment()'s mass update silently dropped them
        // and every discount given at the till was lost from the sale record.
        'coupon_id', 'coupon_discount',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'payment_details' => 'array',
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'voucher_redeemed_at' => 'datetime',
        'voucher_revoked_at' => 'datetime',
    ];

    /**
     * When the Wi-Fi access tied to this receipt expires — measured from the
     * first redemption, NOT recomputed on reconnect, so the 24h window is a
     * true wall-clock and cannot be extended by reconnecting.
     */
    public function wifiExpiresAt(): ?\Illuminate\Support\Carbon
    {
        if (! $this->voucher_redeemed_at) {
            return null;
        }

        $hours = (int) AppSetting::get('voucher_validity_hours', 24);

        return $this->voucher_redeemed_at->copy()->addHours($hours);
    }

    /** True while the receipt's Wi-Fi pass is live (redeemed, not revoked, not expired). */
    public function wifiActive(): bool
    {
        return $this->voucher_redeemed_at
            && ! $this->voucher_revoked_at
            && $this->wifiExpiresAt()?->isFuture();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function debt()
    {
        return $this->hasOne(Debt::class);
    }

    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ymd') . '-';
        $last = static::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $lastNum = $last ? (int) substr($last, strlen($prefix)) : 0;

        return $prefix . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
    }

    /** Cryptographically-random 6-char Wi-Fi code, unique across all sales. */
    public static function generateWifiCode(): string
    {
        do {
            $code = static::randomCode();
        } while (static::where('wifi_code', $code)->exists());

        return $code;
    }

    private static function randomCode(int $length = 6): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $maxIndex = strlen($alphabet) - 1;
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $maxIndex)];
        }
        return $code;
    }
}
