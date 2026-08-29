<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What cover paid towards an online order.
 *
 * Recorded on the order rather than worked out from the claim, because the
 * order is what the customer was charged against and has to stay explicable on
 * its own - including after the cover has moved on to a new month.
 *
 * The cover is spent when the order is placed, not when it is paid for. Two
 * orders placed minutes apart would otherwise each be promised the same
 * allowance, and the second customer would be undercharged with nothing left
 * to draw on. Cancelling an order gives the cover back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'insurance_covered')) {
                $table->decimal('insurance_covered', 12, 2)->default(0)->after('subtotal');
            }

            if (! Schema::hasColumn('orders', 'insurance_subscription_id')) {
                $table->unsignedBigInteger('insurance_subscription_id')->nullable()->after('insurance_covered');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['insurance_covered', 'insurance_subscription_id'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
