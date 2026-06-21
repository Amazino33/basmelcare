<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'invoice_number', 'user_id', 'confirmed_by', 'customer_id',
        'total_amount', 'payment_method', 'payment_details',
        'status', 'paid_at', 'confirmed_at', 'note',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'payment_details' => 'array',
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function debt()
    {
        return $this->hasOne(Debt::class);
    }

    public static function generateInvoiceNumber(): string
    {
        $last = static::whereNotNull('invoice_number')->latest('id')->first();
        $next = $last ? ((int) str_replace('INV-', '', $last->invoice_number)) + 1 : 1;
        return 'INV-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
