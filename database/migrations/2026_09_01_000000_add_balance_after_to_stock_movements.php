<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the batch held after the movement.
 *
 * Movement History could say a sale took one unit and a return put one back,
 * but not what the shelf actually held either side of it - so when somebody
 * says "the stock did not change", there was no way to answer from the log.
 *
 * Only the closing balance is stored. The opening one is that minus the
 * movement's own quantity, so the two can never drift apart or contradict each
 * other. Every writer updates the batch before recording the movement, which
 * is what makes the figure trustworthy; a model hook reads it there rather
 * than each call site remembering to pass it.
 *
 * Nullable, and left null for everything already recorded. A balance invented
 * for past rows by replaying the log would be a guess: batch quantities can
 * also be corrected directly, and those corrections are not movements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'balance_after')) {
                $table->integer('balance_after')->nullable()->after('quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'balance_after')) {
                $table->dropColumn('balance_after');
            }
        });
    }
};
