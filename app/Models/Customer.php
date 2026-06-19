<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sale;

class Customer extends Model
{
    protected $fillable = [
         'name', 'type', 'phone', 'email', 'address', 'notes',
    ];

    public function isWholesale(): bool
    {
        return $this->type === 'wholesale';
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
