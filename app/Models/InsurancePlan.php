<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a plan promises, and what it refuses.
 *
 * Every figure here is a commitment the pharmacy has to honour out of stock it
 * has already paid for, so all of them are audited.
 */
class InsurancePlan extends Model
{
    /**
     * No audit trait here. The shop only ever reads a plan - it is the staff
     * app that changes them, and that is where the money trail is kept.
     */
    protected $fillable = [
        'name', 'code', 'description',
        'monthly_premium', 'monthly_cover', 'copay_percent',
        'waiting_days', 'grace_days', 'excluded_categories', 'is_active',
    ];

    protected $casts = [
        'monthly_premium'     => 'decimal:2',
        'monthly_cover'       => 'decimal:2',
        'copay_percent'       => 'integer',
        'waiting_days'        => 'integer',
        'grace_days'          => 'integer',
        'excluded_categories' => 'array',
        'is_active'           => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(InsuranceSubscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Will this plan pay for this product at all? */
    public function covers(Product $product): bool
    {
        $excluded = $this->excluded_categories ?? [];

        return ! in_array((int) $product->category_id, array_map('intval', $excluded), true);
    }

    /**
     * How much of a covered line the subscriber still pays.
     *
     * Zero co-pay means genuinely free at the counter. Anything above it is
     * the subscriber's share, and it is taken off the top rather than out of
     * the cover, so a co-pay stretches the pool rather than draining it.
     */
    public function copayOn(float $amount): float
    {
        return round($amount * ($this->copay_percent / 100), 2);
    }

    /**
     * The most this plan can cost the pharmacy in a month, if every subscriber
     * claimed their whole cover. The number worth looking at before agreeing a
     * premium.
     */
    public function monthlyExposure(): float
    {
        return round($this->subscriptions()->whereIn('status', ['active', 'waiting'])->count()
            * (float) $this->monthly_cover, 2);
    }

    /** "₦5,000/month, covers ₦10,000" - how it reads to a customer. */
    public function summary(): string
    {
        $line = '₦' . number_format((float) $this->monthly_premium, 2)
            . '/month, covers ₦' . number_format((float) $this->monthly_cover, 2);

        if ($this->copay_percent > 0) {
            $line .= ', ' . $this->copay_percent . '% co-pay';
        }

        return $line;
    }
}
