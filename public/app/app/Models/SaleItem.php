<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'product_id', 'batch_id', 'quantity', 'unit_price', 'cost_price', 'subtotal',
        // Presentation of how the line was sold. Omitted from fillable these
        // would be silently dropped by the mass assignment above, and every
        // receipt would read as loose units.
        'is_pack', 'pack_size',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'subtotal'   => 'decimal:2',
        'is_pack'    => 'boolean',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
