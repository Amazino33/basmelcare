<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadcastRecipient extends Model
{
    protected $fillable = [
        'broadcast_id', 'customer_id', 'phone', 'status', 'image_sent', 'sent_at',
    ];

    protected $casts = [
        'image_sent' => 'boolean',
        'sent_at'    => 'datetime',
    ];

    public function broadcast()
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
