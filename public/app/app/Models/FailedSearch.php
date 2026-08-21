<?php

namespace App\Models;

use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A search at the till that found nothing — demand you could not meet.
 */
class FailedSearch extends Model
{
    use BelongsToBranch;

    protected $fillable = ['term', 'times', 'last_user_id', 'branch_id', 'last_searched_at'];

    protected $casts = ['last_searched_at' => 'datetime'];

    public function lastUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_user_id');
    }

    /**
     * Record one miss.
     *
     * The POS searches as the user types, so "para", "parac", "paracet" would
     * each register while they are still mid-word. Only terms of a sensible
     * length are kept, and the counter is incremented rather than a new row
     * added, so a busy day does not flood the table.
     */
    public static function record(string $term, ?int $userId = null): void
    {
        $term = strtoupper(trim(preg_replace('/\s+/', ' ', $term)));

        // Too short to mean anything; almost certainly mid-typing.
        if (mb_strlen($term) < 3) {
            return;
        }

        $branchId = auth()->check() ? auth()->user()->branch_id : null;

        $row = static::withoutGlobalScopes()
            ->where('term', $term)
            ->where('branch_id', $branchId)
            ->first();

        if ($row) {
            $row->increment('times');
            $row->forceFill([
                'last_user_id'     => $userId,
                'last_searched_at' => now(),
            ])->save();

            return;
        }

        static::create([
            'term'             => $term,
            'times'            => 1,
            'last_user_id'     => $userId,
            'branch_id'        => $branchId,
            'last_searched_at' => now(),
        ]);
    }
}
