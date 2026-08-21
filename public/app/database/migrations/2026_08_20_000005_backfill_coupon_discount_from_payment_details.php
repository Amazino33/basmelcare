<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Recovers discounts that were applied but never stored.
     *
     * coupon_id and coupon_discount were missing from Sale::$fillable, so the
     * mass update in processPayment() silently dropped them and the column
     * stayed 0 — while payment_details, written as a plain array, kept the real
     * figure. Every affected sale therefore reports revenue higher than the
     * customer was actually charged.
     *
     * payment_details is the surviving record, so it is the source of truth here.
     */
    public function up(): void
    {
        DB::table('sales')
            ->whereNotNull('payment_details')
            ->select('id', 'coupon_discount', 'payment_details')
            ->orderBy('id')
            ->chunk(200, function ($sales) {
                foreach ($sales as $sale) {
                    $details = $sale->payment_details;
                    $details = is_string($details) ? json_decode($details, true) : $details;

                    if (! is_array($details)) {
                        continue;
                    }

                    $discount = (float) ($details['coupon_discount'] ?? 0);

                    // Only fill genuine gaps; never overwrite a stored value.
                    if ($discount > 0 && (float) $sale->coupon_discount === 0.0) {
                        DB::table('sales')
                            ->where('id', $sale->id)
                            ->update(['coupon_discount' => $discount]);
                    }
                }
            });
    }

    public function down(): void
    {
        // The recovered values are the correct ones; nothing to undo.
    }
};
