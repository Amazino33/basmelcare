<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'customer_id', 'user_id', 'title', 'description',
        'scheduled_at', 'duration_minutes', 'status', 'note',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
