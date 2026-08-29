<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'batch_id', 'quantity', 'balance_after', 'type', 'reference', 'note',
        'from_location_id', 'to_location_id', 'user_id',
    ];

    /**
     * Capture what the batch held once this movement had been applied.
     *
     * Every writer updates the batch first and records the movement after, so
     * reading the batch here gives the closing balance. Done in one hook rather
     * than at nine call sites, because the tenth would be the one that forgot.
     */
    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement) {
            if ($movement->balance_after === null && $movement->batch_id) {
                $movement->balance_after = Batch::whereKey($movement->batch_id)->value('quantity');
            }
        });
    }

    /**
     * What the shelf held before it. Derived rather than stored, so the two
     * figures cannot drift apart.
     *
     * Null for movements recorded before balances were kept - an opening figure
     * invented for those would be a guess, since batch quantities can also be
     * corrected directly and those corrections are not movements.
     */
    public function balanceBefore(): ?int
    {
        return $this->balance_after === null
            ? null
            : (int) $this->balance_after - (int) $this->quantity;
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function fromLocation()
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation()
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
