<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Debt extends Model
{
    protected $fillable = [
        'sale_id', 'customer_id', 'amount_owed', 'amount_paid', 'status', 'due_date', 'note',
    ];

    protected $casts = [
        'amount_owed' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(DebtPayment::class);
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->amount_owed - (float) $this->amount_paid;
    }
}
