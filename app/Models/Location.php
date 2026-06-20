<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = ['name', 'type', 'address', 'is_default'];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }
}
