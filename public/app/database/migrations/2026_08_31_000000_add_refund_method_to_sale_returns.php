<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How a return is actually paid back.
 *
 * Until now there was only one answer, and it was never written down: the
 * amount went onto the customer's store credit. That silently made a
 * registered customer a requirement, because store credit needs an account to
 * sit on — and it is why walk-in returns were blocked rather than solved.
 *
 * A walk-in has no account, so the only honest refund is cash out of the till.
 * Recording which one happened matters beyond the receipt: cash leaving the
 * drawer has to reduce the day's takings, and store credit must not, because
 * credit costs nothing until it is drawn and is already counted when it is
 * paid out.
 *
 * Existing rows were all store credit, which is what the backfill says.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_returns', 'refund_method')) {
                $table->string('refund_method', 10)->default('credit')->after('total_credit');
            }

            if (! Schema::hasColumn('sale_returns', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('refund_method');
            }
        });

        // Every return that already exists was store credit. Saying so
        // explicitly keeps the cash figures right for past periods rather than
        // letting a new default rewrite history.
        DB::table('sale_returns')->whereNull('refunded_at')->update([
            'refund_method' => 'credit',
        ]);
    }

    public function down(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            foreach (['refund_method', 'refunded_at'] as $column) {
                if (Schema::hasColumn('sale_returns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
