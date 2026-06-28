<?php

namespace App\Models;

use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use BelongsToBranch;

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
        $prefix = 'INV-' . now()->format('Ymd') . '-';
        $last = static::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        if ($last) {
            $lastNum = (int) substr($last, strlen($prefix));
            $next = $lastNum + 1;
        } else {
            $next = 1;
        }

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
