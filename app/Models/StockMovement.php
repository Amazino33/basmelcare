<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'batch_id', 'quantity', 'type', 'reference', 'note'
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
