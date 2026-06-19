<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    protected $fillable = [
        'product_id', 'batch_number', 'expiry_date', 'cost_price', 'quantity', 'note',
    ];

    protected $casts = [
        'expiry_date'   => 'date',
        'cost_price'    => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }
}
