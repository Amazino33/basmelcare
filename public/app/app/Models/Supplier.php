<?php

namespace App\Models;

use App\Models\Concerns\NormalisesName;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use NormalisesName;

    protected $fillable = [
        'name', 'phone', 'email', 'address', 'contact_person',
    ];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
