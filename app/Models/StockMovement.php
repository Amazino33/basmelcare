<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'batch_id', 'quantity', 'type', 'reference', 'note',
        'from_location_id', 'to_location_id', 'user_id',
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function fromLocation()
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation()
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
