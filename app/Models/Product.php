<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name', 'sku', 'category_id', 'selling_price', 'wholesale_price',
        'wholesale_min_qty', 'reorder_level', 'description', 'image', 'barcode',
        'requires_prescription', 'is_featured', 'show_in_shop',
        'has_pack', 'pack_size', 'pack_price',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'pack_price' => 'decimal:2',
        'requires_prescription' => 'boolean',
        'is_featured' => 'boolean',
        'show_in_shop' => 'boolean',
        'has_pack' => 'boolean',
    ];

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
