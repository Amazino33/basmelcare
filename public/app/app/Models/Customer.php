<?php

namespace App\Models;

use App\Models\Concerns\NormalisesName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use HasFactory, Notifiable;
    use NormalisesName;

    protected $fillable = [
        'name', 'type', 'phone', 'phone_normalised', 'email', 'password', 'address', 'notes',
        'otp', 'otp_expires_at', 'otp_attempts', 'otp_sent_at',
        'credit_balance', 'registered_by',
    ];

    protected $hidden = ['password', 'remember_token', 'otp'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'otp_expires_at'    => 'datetime',
        'otp_sent_at'       => 'datetime',
        'password'          => 'hashed',
        'credit_balance'    => 'decimal:2',
    ];

    /**
     * The comparable form of a phone number.
     *
     * Nigerian numbers arrive written every which way - 08031234567,
     * 0803 123 4567, +2348031234567, 234 803 123 4567 - and they are all the
     * same line. Reduced to its digits without the country code or the leading
     * zero, so any of those spellings finds the same person.
     *
     * Returns null for anything with no digits in it at all, which is not a
     * phone number and must not match every other blank one.
     */
    public static function normalisePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '234') && strlen($digits) > 10) {
            $digits = substr($digits, 3);
        }

        $digits = ltrim($digits, '0');

        return $digits === '' ? null : $digits;
    }

    /**
     * Find a customer by any spelling of their number.
     *
     * The single way to look someone up by phone. Booking a consultation used
     * an exact string match, so a number typed with spaces created a second
     * customer - and with it a second free consultation, a second purchase
     * history and a second debt.
     */
    public static function findByPhone(?string $phone): ?self
    {
        $normalised = static::normalisePhone($phone);

        return $normalised === null
            ? null
            : static::where('phone_normalised', $normalised)->first();
    }

    public function scopeWithPhone($query, ?string $phone)
    {
        return $query->where('phone_normalised', static::normalisePhone($phone));
    }

    /**
     * Keep the comparable form in step with whatever was typed.
     *
     * On the model rather than at each call site: a customer is created from
     * the till, the cashier's screen, the shop's booking form and its sign-up
     * page, and the one that forgot would quietly reintroduce the duplicates.
     */
    protected static function booted(): void
    {
        static::saving(function (self $customer) {
            if ($customer->isDirty('phone')) {
                $customer->phone_normalised = static::normalisePhone($customer->phone);
            }
        });
    }

    public function isWholesale(): bool
    {
        return $this->type === 'wholesale';
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function debts()
    {
        return $this->hasMany(Debt::class);
    }

    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function creditPayouts()
    {
        return $this->hasMany(CreditPayout::class);
    }

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function referralCommission()
    {
        return $this->hasOne(ReferralCommission::class);
    }

    public function getTotalDebtAttribute(): float
    {
        return $this->debts()->whereIn('status', ['unpaid', 'partial'])->sum('amount_owed')
             - $this->debts()->whereIn('status', ['unpaid', 'partial'])->sum('amount_paid');
    }

    /**
     * Canonical form for a Nigerian number so the same phone can't be entered
     * in two spellings to dodge the unique index. "+234 801 234 5678",
     * "0801-234-5678" and "8012345678" all become "08012345678".
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '234')) {
            $digits = '0' . substr($digits, 3);
        } elseif (!str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    public const OTP_MAX_ATTEMPTS = 5;

    public function generateOtp(): string
    {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->update([
            'otp'            => $otp,
            'otp_expires_at' => now()->addMinutes(10),
            'otp_attempts'   => 0,
            'otp_sent_at'    => now(),
        ]);
        return $otp;
    }

    public function otpAttemptsExhausted(): bool
    {
        return (int) $this->otp_attempts >= self::OTP_MAX_ATTEMPTS;
    }

    public function verifyOtp(string $otp): bool
    {
        if (!$this->otp || !$this->otp_expires_at || $this->otp_expires_at->isPast()) {
            return false;
        }

        if ($this->otpAttemptsExhausted()) {
            return false;
        }

        if (!hash_equals($this->otp, $otp)) {
            $this->increment('otp_attempts');
            return false;
        }

        return true;
    }

    public function clearOtp(): void
    {
        $this->update([
            'otp'            => null,
            'otp_expires_at' => null,
            'otp_attempts'   => 0,
        ]);
    }
}
