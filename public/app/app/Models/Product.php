<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use RecordsAudit;

    /** Prices drive revenue and margin, so changes must be attributable. */
    protected array $audited = ['selling_price', 'wholesale_price', 'cost_price_hint'];

    protected $fillable = [
        'name', 'sku', 'category_id', 'selling_price', 'wholesale_price',
        'wholesale_min_qty', 'reorder_level', 'description', 'image', 'barcode',
        'requires_prescription', 'is_featured', 'show_in_shop',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'requires_prescription' => 'boolean',
        'is_featured' => 'boolean',
        'show_in_shop' => 'boolean',
    ];

    /**
     * Product names are stored uppercase.
     *
     * This used to be an accessor, which meant the UI looked consistent while
     * the stored value kept whatever casing was typed — so a case-sensitive
     * query or an export behaved differently from the screen. Normalising on
     * write covers every path (form, quick add, bulk edit, import) instead of
     * relying on each caller to remember.
     */
    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value === null ? null : strtoupper(trim($value));
    }

    public function getPriceFor(?Customer $customer, int $qty = 1): float
    {
        if ($this->wholesale_price) {
            if ($customer && $customer->type === 'wholesale') {
                return (float) $this->wholesale_price;
            }

            if ($this->wholesale_min_qty && $qty >= $this->wholesale_min_qty) {
                return (float) $this->wholesale_price;
            }
        }

        return (float) $this->selling_price;
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }
}
