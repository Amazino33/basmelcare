<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleReturn extends Model
{
    public const CREDIT = 'credit';
    public const CASH   = 'cash';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'sale_id', 'processed_by', 'reason', 'total_credit',
        'refund_method', 'refunded_at',
        'status', 'approved_by', 'approved_at',
        'rejected_by', 'rejected_at', 'rejection_reason',
    ];

    protected $casts = [
        'total_credit' => 'decimal:2',
        'refunded_at'  => 'datetime',
        'approved_at'  => 'datetime',
        'rejected_at'  => 'datetime',
    ];

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

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

    public function requester()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function items()
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
