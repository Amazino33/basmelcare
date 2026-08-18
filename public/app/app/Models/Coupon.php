<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'max_discount', 'max_uses', 'used_count', 'expires_at', 'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active'  => 'boolean',
        'value'      => 'float',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function calculateDiscount(float $cartTotal): float
    {
        if ($this->type === 'percent') {
            $discount = round($cartTotal * ($this->value / 100), 2);
            if ($this->max_discount !== null) {
                $discount = min($discount, (float) $this->max_discount);
            }
            return $discount;
        }

        return min((float) $this->value, $cartTotal);
    }

    public function getValidationError(): ?string
    {
        if (!$this->is_active) {
            return 'This coupon is inactive.';
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'This coupon has expired.';
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return 'This coupon has reached its usage limit.';
        }

        return null;
    }
}
