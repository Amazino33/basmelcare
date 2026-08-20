<?php

namespace App\Models;

use App\Models\Concerns\NormalisesName;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use NormalisesName;

    protected $fillable = ['name', 'address', 'phone', 'is_main'];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function locations()
    {
        return $this->hasMany(Location::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
