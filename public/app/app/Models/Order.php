<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'customer_id', 'guest_name', 'guest_email', 'guest_phone',
        'subtotal', 'delivery_fee', 'total_amount',
        'insurance_covered', 'insurance_subscription_id',
        'fulfillment_type', 'payment_method', 'payment_status', 'payment_reference',
        'status', 'claimed_by', 'claimed_at', 'delivery_address', 'delivery_phone', 'note',
        'prescription_path', 'paid_at',
        'prescription_status', 'prescription_reviewed_by', 'prescription_reviewed_at', 'prescription_note',
        'cashier_verified_at', 'verified_by',
        'delivery_person_name', 'delivery_person_phone', 'delivery_user_id', 'dispatched_at',
    ];

    protected $casts = [
        'subtotal'             => 'decimal:2',
        'insurance_covered'    => 'decimal:2',
        'delivery_fee'         => 'decimal:2',
        'total_amount'         => 'decimal:2',
        'paid_at'              => 'datetime',
        'claimed_at'           => 'datetime',
        'cashier_verified_at'  => 'datetime',
        'dispatched_at'        => 'datetime',
        'prescription_reviewed_at' => 'datetime',
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

    /**
     * Waiting on a pharmacist.
     *
     * Null status means the order contains nothing needing a prescription, so
     * there is nothing to review - deliberately different from 'pending',
     * which means a review is required and has not happened.
     */
    public function awaitingPrescriptionReview(): bool
    {
        return $this->prescription_status === 'pending';
    }

    public function prescriptionApproved(): bool
    {
        return $this->prescription_status === 'approved';
    }

    public function prescriptionRejected(): bool
    {
        return $this->prescription_status === 'rejected';
    }

    /** The pharmacist who looked at it. */
    public function prescriptionReviewer()
    {
        return $this->belongsTo(User::class, 'prescription_reviewed_by');
    }
}
