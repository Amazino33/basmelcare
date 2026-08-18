<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'max_discount',
        'customer_type', 'min_order_amount', 'max_order_amount',
        'restricted_categories', 'restricted_products', 'min_item_count',
        'max_uses', 'used_count', 'expires_at', 'is_active', 'auto_apply',
    ];

    protected $casts = [
        'expires_at'             => 'datetime',
        'is_active'              => 'boolean',
        'auto_apply'             => 'boolean',
        'value'                  => 'float',
        'restricted_categories'  => 'array',
        'restricted_products'    => 'array',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Calculate the discount amount. When category or product restrictions are set,
     * the discount applies only to eligible line items (Option B / filter mode).
     * Pass $saleItems as an array of ['product_id', 'category_id', 'subtotal'] maps.
     */
    public function calculateDiscount(float $cartTotal, array $saleItems = []): float
    {
        $baseAmount = $cartTotal;

        $hasItemRestriction = !empty($this->restricted_categories) || !empty($this->restricted_products);

        if ($hasItemRestriction && !empty($saleItems)) {
            $restrictedCats  = array_map('intval', (array) ($this->restricted_categories ?? []));
            $restrictedProds = array_map('intval', (array) ($this->restricted_products ?? []));
            $baseAmount = 0;

            foreach ($saleItems as $item) {
                $catMatch  = !empty($restrictedCats)  && in_array((int) ($item['category_id'] ?? 0), $restrictedCats, true);
                $prodMatch = !empty($restrictedProds) && in_array((int) ($item['product_id'] ?? 0), $restrictedProds, true);
                if ($catMatch || $prodMatch) {
                    $baseAmount += (float) ($item['subtotal'] ?? 0);
                }
            }
        }

        if ($baseAmount <= 0) {
            return 0.0;
        }

        if ($this->type === 'percent') {
            $discount = round($baseAmount * ($this->value / 100), 2);
            if ($this->max_discount !== null) {
                $discount = min($discount, (float) $this->max_discount);
            }
            return $discount;
        }

        return min((float) $this->value, $baseAmount);
    }

    public function getValidationError(
        float $cartTotal = 0,
        ?object $customer = null,
        array $categoryIds = [],
        array $productIds = [],
        int $itemCount = 0
    ): ?string {
        if (!$this->is_active) {
            return 'This coupon is inactive.';
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'This coupon has expired.';
        }
        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return 'This coupon has reached its usage limit.';
        }

        $customerType = $this->customer_type ?? 'all';
        if ($customerType !== 'all') {
            // A prior purchase is any sale already settled — 'paid' (money taken)
            // or 'completed' (handed over). 'pending' and 'cancelled' don't count.
            $hasPriorPurchase = $customer
                && $customer->sales()->whereIn('status', ['paid', 'completed'])->exists();

            if ($customerType === 'new' && $hasPriorPurchase) {
                return 'This coupon is for new customers only.';
            }
            if ($customerType === 'returning' && !$hasPriorPurchase) {
                return 'This coupon is for returning customers only.';
            }
        }

        if ($this->min_order_amount !== null && $cartTotal < (float) $this->min_order_amount) {
            return 'Minimum order of ₦' . number_format($this->min_order_amount, 2) . ' required for this coupon.';
        }
        if ($this->max_order_amount !== null && $cartTotal > (float) $this->max_order_amount) {
            return 'This coupon is only valid for orders up to ₦' . number_format($this->max_order_amount, 2) . '.';
        }

        if ($this->min_item_count !== null && $itemCount < (int) $this->min_item_count) {
            return "This coupon requires at least {$this->min_item_count} item(s) in your order.";
        }

        // Ensure the cart contains at least one eligible item when item restrictions are set
        if (!empty($this->restricted_categories) || !empty($this->restricted_products)) {
            $restrictedCats  = array_map('intval', (array) ($this->restricted_categories ?? []));
            $restrictedProds = array_map('intval', (array) ($this->restricted_products ?? []));
            $catMatch  = !empty($restrictedCats)  && !empty(array_intersect($categoryIds, $restrictedCats));
            $prodMatch = !empty($restrictedProds) && !empty(array_intersect($productIds, $restrictedProds));

            if (!$catMatch && !$prodMatch) {
                return 'No eligible items in your cart for this coupon.';
            }
        }

        return null;
    }
}
