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

    /**
     * Finalize the return upon approval by an auditor or branch manager.
     * Restocks the batch, logs stock movements, issues store credit or marks cash refund,
     * and sends WhatsApp notification if applicable.
     */
    public function finalize(User $approver): void
    {
        if ($this->isApproved()) {
            return;
        }

        DB::transaction(function () use ($approver) {
            $this->loadMissing(['sale.customer', 'items.batch', 'items.product']);

            foreach ($this->items as $item) {
                if (! $item->batch) {
                    throw new \RuntimeException(
                        'The batch "' . ($item->product->name ?? 'item') . '" was sold from no longer exists, '
                        . 'so it cannot be put back. Add the stock by hand and record the refund separately.'
                    );
                }

                $item->batch->increment('quantity', $item->quantity_returned);

                StockMovement::create([
                    'batch_id'  => $item->batch_id,
                    'quantity'  => $item->quantity_returned,
                    'type'      => 'return',
                    'reference' => "Return from Sale #{$this->sale_id}",
                    'user_id'   => $approver->id,
                ]);
            }

            // Only a credit refund touches the account. Cash leaves the drawer when the customer is paid.
            if ($this->refund_method === self::CREDIT && $this->sale?->customer_id && (float) $this->total_credit > 0) {
                $this->sale->customer->increment('credit_balance', (float) $this->total_credit);
            }

            $this->update([
                'status'      => self::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'refunded_at' => now(),
            ]);
        });

        // Dispatch WhatsApp notification if credit was added to account
        if ($this->refund_method === self::CREDIT && $this->sale?->customer?->phone && (float) $this->total_credit > 0) {
            try {
                $customer = $this->sale->customer;
                $pharmacyName = AppSetting::get('pharmacy_name', 'BasmelCare');
                $newBalance = $customer->fresh()->credit_balance;
                $message = "Hi {$customer->name}, a return of \u{20A6}" . number_format($this->total_credit, 2)
                    . " has been credited to your {$pharmacyName} account."
                    . " Your new credit balance is \u{20A6}" . number_format($newBalance, 2)
                    . ". Ref: RT-" . str_pad($this->id, 5, '0', STR_PAD_LEFT) . ".";

                app(\App\Services\WhatsAppService::class)->send($customer->phone, $message);
            } catch (\Throwable $e) {
                Log::error('[WhatsApp Return] ' . $e->getMessage());
            }
        }
    }

    /**
     * Reject the return request.
     */
    public function reject(User $rejector, ?string $reason = null): void
    {
        if ($this->isApproved()) {
            throw new \RuntimeException('An approved return cannot be rejected.');
        }

        $this->update([
            'status'           => self::STATUS_REJECTED,
            'rejected_by'      => $rejector->id,
            'rejected_at'      => now(),
            'rejection_reason' => $reason,
        ]);
    }
}
