<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTakeItem extends Model
{
    protected $fillable = ['stock_take_id', 'product_id', 'system_qty', 'physical_qty'];

    protected $casts = [
        'system_qty'   => 'integer',
        'physical_qty' => 'integer',
    ];

    public function stockTake()
    {
        return $this->belongsTo(StockTake::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
