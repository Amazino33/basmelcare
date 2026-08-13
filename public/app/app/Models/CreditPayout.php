<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditPayout extends Model
{
    protected $fillable = ['customer_id', 'amount', 'balance_before', 'balance_after', 'cashier_id', 'note'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
