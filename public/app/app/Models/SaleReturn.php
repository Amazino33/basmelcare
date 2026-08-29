<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturn extends Model
{
    public const CREDIT = 'credit';
    public const CASH   = 'cash';

    protected $fillable = [
        'sale_id', 'processed_by', 'reason', 'total_credit',
        'refund_method', 'refunded_at',
    ];

    protected $casts = [
        'total_credit' => 'decimal:2',
        'refunded_at'  => 'datetime',
    ];

    /** Money that actually left the drawer, as opposed to credit promised. */
    public function isCash(): bool
    {
        return $this->refund_method === self::CASH;
    }

    /** How to describe it on a receipt or a report. */
    public function refundLabel(): string
    {
        return $this->isCash() ? 'Cash refunded' : 'Credit added to account';
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function items()
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
