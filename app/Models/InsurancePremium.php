<?php

namespace App\Models;

use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A month bought.
 *
 * Kept as its own row rather than a date on the subscription because this is
 * the money coming in. Premiums against claims is the only figure that says
 * whether the scheme can go on being offered, and an auditor needs to see each
 * payment, who took it and how.
 */
class InsurancePremium extends Model
{
    use BelongsToBranch;

    /** Laravel pluralises this as "premia", which is not the table name. */
    protected $table = 'insurance_premiums';

    protected $fillable = [
        'insurance_subscription_id', 'amount', 'period_start', 'period_end',
        'method', 'reference', 'paid_at', 'recorded_by', 'branch_id',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'period_start' => 'date',
        'period_end'   => 'date',
        'paid_at'      => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(InsuranceSubscription::class, 'insurance_subscription_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopePaidBetween($query, $from, $to)
    {
        return $query->whereBetween('paid_at', [$from, $to]);
    }
}
