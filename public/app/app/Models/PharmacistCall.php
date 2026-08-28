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

    protected $fillable = ['called_by', 'branch_id', 'acknowledged_by', 'acknowledged_at'];

    protected $casts = ['acknowledged_at' => 'datetime'];

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
}
