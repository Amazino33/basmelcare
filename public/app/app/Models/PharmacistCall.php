<?php

namespace App\Models;

use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/**
 * The counter asking for a pharmacist.
 *
 * One tap, no form: at a busy counter the point is speed, and a pharmacist
 * walking over finds out what is needed by asking.
 */
class PharmacistCall extends Model
{
    use BelongsToBranch;

    protected $fillable = ['called_by', 'branch_id', 'acknowledged_by', 'acknowledged_at', 'notified_at'];

    protected $casts = ['acknowledged_at' => 'datetime', 'notified_at' => 'datetime'];

    /**
     * How long an unanswered call keeps showing.
     *
     * A call nobody answered should stop ringing eventually. Leaving it up
     * forever would train people to ignore the banner, which is worse than
     * losing the occasional call.
     */
    public const EXPIRES_AFTER_MINUTES = 15;

    public function caller()
    {
        return $this->belongsTo(User::class, 'called_by');
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /** Still waiting: nobody has answered, and it has not gone stale. */
    public function scopeWaiting($query)
    {
        return $query->whereNull('acknowledged_at')
            ->where('created_at', '>=', now()->subMinutes(self::EXPIRES_AFTER_MINUTES));
    }

    /**
     * Ring for a pharmacist.
     *
     * Tapping again while one is already waiting returns the same call rather
     * than stacking them - the pharmacist should see one customer waiting, not
     * five, because somebody pressed the button repeatedly.
     */
    public static function ring(User $caller): self
    {
        $existing = static::waiting()->where('branch_id', $caller->branch_id)->first();

        if ($existing) {
            return $existing;
        }

        return static::create([
            'called_by' => $caller->id,
            'branch_id' => $caller->branch_id,
        ]);
    }

    /**
     * Unanswered on screen for long enough that phones should ring.
     *
     * The delay matters: if a pharmacist is at a screen they see the banner in
     * five seconds, and messaging them as well would be noise through the same
     * gateway that sends receipts.
     */
    public function shouldNotify(): bool
    {
        if (! AppSetting::bool('pharmacist_call_alert_enabled', false)) {
            return false;
        }

        if ($this->acknowledged_at || $this->notified_at) {
            return false;
        }

        $after = max(15, (int) AppSetting::get('pharmacist_call_alert_after_seconds', 60));

        return $this->created_at?->lt(now()->subSeconds($after)) ?? false;
    }

    /**
     * The people to ring: active pharmacists at that counter who have a
     * number on file.
     *
     * There is no rota in the system, so "who is on duty" cannot be answered -
     * everyone who could answer is told, which for a pharmacy is one or two
     * people.
     */
    public function notifiable()
    {
        return User::query()
            ->where('status', 'active')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->when($this->branch_id, fn ($q) => $q->where(fn ($w) => $w
                ->where('branch_id', $this->branch_id)
                ->orWhereNull('branch_id')))
            ->get()
            ->filter(fn ($user) => in_array('pharmacist', $user->role ?? [], true));
    }
}
