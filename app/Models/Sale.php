<?php

namespace App\Models;

use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'invoice_number', 'user_id', 'confirmed_by', 'customer_id',
        'total_amount', 'payment_method', 'payment_details',
        'status', 'paid_at', 'confirmed_at', 'note',
        'voucher_redeemed_at', 'voucher_revoked_at',
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

        if ($last) {
            // The sequential core sits immediately after the prefix. (int) stops
            // at the first non-digit, so the random suffix below never interferes
            // with reading back the last number.
            $lastNum = (int) substr($last, strlen($prefix));
            $next = $lastNum + 1;
        } else {
            $next = 1;
        }

        // Sequential core — stays gapless for accounting/audit.
        $sequential = $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);

        // Random suffix — the receipt doubles as a HiFastLink Wi-Fi access code,
        // so it must not be guessable from a neighbouring invoice. The core above
        // keeps the books sequential; this suffix carries the unpredictability.
        do {
            $candidate = $sequential . '-' . static::randomInvoiceSuffix();
        } while (static::where('invoice_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * A short, cryptographically-random suffix using an unambiguous alphabet
     * (no 0/O/1/I/L) so it is easy to read off a printed receipt.
     */
    protected static function randomInvoiceSuffix(int $length = 6): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $maxIndex = strlen($alphabet) - 1;

        $suffix = '';
        for ($i = 0; $i < $length; $i++) {
            $suffix .= $alphabet[random_int(0, $maxIndex)];
        }

        return $suffix;
    }
}
