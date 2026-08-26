<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'customer_id', 'user_id', 'title', 'description',
        'scheduled_at', 'duration_minutes', 'status', 'note',
        'mode', 'provider_type', 'price', 'was_free',
        'payment_status', 'payment_reference', 'paid_at', 'contact', 'booked_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'paid_at'      => 'datetime',
        'price'        => 'decimal:2',
        'was_free'     => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bookedBy()
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    /** Booked by the customer themselves rather than by staff. */
    public function isSelfBooked(): bool
    {
        return $this->booked_by === null;
    }

    /** Nothing left to collect: either paid, or given free. */
    public function isSettled(): bool
    {
        return $this->payment_status === 'paid' || $this->payment_status === 'free';
    }

    public function modeLabel(): string
    {
        return \App\Support\ConsultationPricing::MODES[$this->mode] ?? ucfirst((string) $this->mode);
    }

    public function providerLabel(): string
    {
        return \App\Support\ConsultationPricing::PROVIDERS[$this->provider_type] ?? ucfirst((string) $this->provider_type);
    }
}
