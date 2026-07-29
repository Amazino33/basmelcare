<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'customer_id', 'guest_name', 'guest_email', 'guest_phone',
        'subtotal', 'delivery_fee', 'total_amount',
        'fulfillment_type', 'payment_method', 'payment_status', 'payment_reference',
        'status', 'claimed_by', 'claimed_at', 'delivery_address', 'delivery_phone', 'note',
        'prescription_path', 'paid_at',
        'cashier_verified_at', 'verified_by',
        'delivery_person_name', 'delivery_person_phone', 'delivery_user_id', 'dispatched_at',
    ];

    protected $casts = [
        'subtotal'             => 'decimal:2',
        'delivery_fee'         => 'decimal:2',
        'total_amount'         => 'decimal:2',
        'paid_at'              => 'datetime',
        'claimed_at'           => 'datetime',
        'cashier_verified_at'  => 'datetime',
        'dispatched_at'        => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function claimedByUser()
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function deliveryUser()
    {
        return $this->belongsTo(User::class, 'delivery_user_id');
    }

    public function isCod(): bool
    {
        return $this->payment_status === 'pending';
    }

    public function isVerified(): bool
    {
        return $this->cashier_verified_at !== null;
    }

    public static function generateOrderNumber(): string
    {
        $last = static::latest('id')->first();
        $next = $last ? $last->id + 1 : 1;
        return 'ORD-' . now()->format('Ym') . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
