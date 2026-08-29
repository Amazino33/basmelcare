<?php

namespace App\Models;

use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Medicine handed over against cover rather than paid for.
 *
 * cost_amount is what that medicine cost the pharmacy, captured when the claim
 * was made. Batch costs change and stock gets sold, so the true cost of a
 * claim cannot be worked out again afterwards - and premiums minus cost is the
 * only honest answer to "is this scheme losing us money".
 */
class InsuranceClaim extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'insurance_subscription_id', 'sale_id', 'order_id',
        'amount', 'period_start', 'cost_amount', 'branch_id',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'cost_amount'  => 'decimal:2',
        'period_start' => 'date',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(InsuranceSubscription::class, 'insurance_subscription_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Where the medicine went, for a report that has to name it. */
    public function reference(): string
    {
        return $this->sale?->invoice_number
            ?? ($this->order_id ? 'Online order #' . $this->order_id : '—');
    }

    public function scopeMadeBetween($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
