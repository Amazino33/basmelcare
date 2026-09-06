<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An area the pharmacy delivers to, and what it charges to get there.
 *
 * See the create_delivery_zones migration for why this exists rather than a
 * number in the checkout.
 */
class DeliveryZone extends Model
{
    protected $fillable = ['name', 'fee', 'note', 'is_active', 'sort_order'];

    protected $casts = [
        'fee'       => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The zones a customer may choose from, cheapest-looking order first.
     *
     * Sorted by the pharmacy's own ordering before fee, so it can put the
     * areas most people live in at the top of the list.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('fee');
    }

    /** Whether the shop is taking delivery orders at all. */
    public static function deliveryOffered(): bool
    {
        return AppSetting::bool('delivery_enabled', true)
            && static::active()->exists();
    }

    /**
     * The order value at which delivery stops being charged for.
     *
     * Zero means never - the fee always applies.
     */
    public static function freeOver(): float
    {
        return (float) AppSetting::get('delivery_free_over', 0);
    }

    /**
     * What this zone costs on a basket of this size.
     *
     * The threshold is read against the goods, not the total: adding the
     * delivery fee to the figure that decides whether delivery is free would
     * let a basket buy its own free delivery.
     */
    public function feeFor(float $subtotal): float
    {
        $freeOver = static::freeOver();

        if ($freeOver > 0 && $subtotal >= $freeOver) {
            return 0.0;
        }

        return (float) $this->fee;
    }
}
