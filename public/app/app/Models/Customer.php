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
        'name', 'type', 'phone', 'email', 'password', 'address', 'notes',
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
