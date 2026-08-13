<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTake extends Model
{
    protected $fillable = [
        'started_by', 'approved_by', 'status', 'notes', 'submitted_at', 'approved_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
    ];

    public function starter()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(StockTakeItem::class);
    }
}
