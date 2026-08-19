<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'role', 'position',
        'employment_date', 'salary', 'address',
        'emergency_contact_name', 'emergency_contact_phone', 'status', 'branch_id',
        'referral_target',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'employment_date' => 'date',
            'salary' => 'decimal:2',
            'role' => 'array',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function referralCommissions()
    {
        return $this->hasMany(ReferralCommission::class);
    }

    public function promoterCodes()
    {
        return $this->hasMany(PromoterCode::class);
    }

    /** This promoter's own target, or the global default when unset. */
    public function effectiveReferralTarget(): int
    {
        return (int) ($this->referral_target ?? AppSetting::get('promoter_target_default', 20));
    }

    /**
     * Codes handed out and actually connected on a given day — the pair the
     * promoter is judged on.
     */
    public function promoterProgressOn(\Carbon\Carbon|string $date): array
    {
        $day = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);

        $issued   = $this->promoterCodes()->whereDate('created_at', $day)->count();
        $redeemed = $this->promoterCodes()->whereDate('created_at', $day)->whereNotNull('redeemed_at')->count();
        $target   = $this->effectiveReferralTarget();

        return [
            'issued'   => $issued,
            'redeemed' => $redeemed,
            'target'   => $target,
            'percent'  => $target > 0 ? min(100, (int) round(($redeemed / $target) * 100)) : 0,
            'stalled'  => $issued - $redeemed,
        ];
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->role ?? []);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isBranchManager(): bool
    {
        return $this->hasRole('branch_manager');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
