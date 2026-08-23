<?php

namespace App\Models;

use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

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

    /**
     * Run a sale-creating transaction, retrying if its invoice number or
     * Wi-Fi code collided with one another till claimed first.
     *
     * generateInvoiceNumber() reads the highest number for today and adds
     * one. Two tills pressing "create invoice" in the same moment read the
     * same value and compute the same successor; the unique index rejects
     * whichever insert lands second. Wrapping it in a transaction does not
     * help - the row being read does not exist yet, so there is nothing to
     * lock - and reserving numbers up front would leave gaps whenever a
     * reservation went unused, which the invoice sequence must not have.
     *
     * So let the database arbitrate. The loser's transaction has already
     * rolled back, stock included, which makes a fresh attempt safe: it
     * re-reads the sequence and takes the next free number.
     *
     * Only identity clashes are retried. Any other unique violation is a
     * real bug and must surface rather than be run five times over.
     */
    public static function transactWithRetry(callable $callback, int $attempts = 5)
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction($callback);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= $attempts || ! static::isIdentityClash($e)) {
                    throw $e;
                }

                // Jittered back-off: two tills that collided will otherwise
                // wake together and collide again on the same next number.
                usleep(random_int(10000, 60000));
            }
        }
    }

    /**
     * Driver-independent check that a unique violation was about a sale's
     * own identity. MySQL names the index ("sales_invoice_number_unique"),
     * SQLite names the column ("sales.invoice_number"); both mention it.
     */
    private static function isIdentityClash(UniqueConstraintViolationException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'invoice_number')
            || str_contains($message, 'wifi_code');
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
