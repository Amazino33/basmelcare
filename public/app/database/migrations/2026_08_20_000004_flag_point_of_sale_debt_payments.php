<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks the debt payment created at the moment of sale.
     *
     * When a customer part-pays, the money is recorded in two places: the
     * sale's payment_details (what the till actually took) and a DebtPayment
     * against the debt that part-payment created. Both are correct in isolation,
     * but anything summing sale payments AND debt payments counts that money
     * twice — which is why reported cash exceeded the cash in the drawer.
     *
     * Later repayments are genuinely separate money and must still count.
     */
    public function up(): void
    {
        Schema::table('debt_payments', function (Blueprint $table) {
            $table->boolean('at_point_of_sale')->default(false)->after('payment_method');
        });

        // Backfill: the opening payment is the first one against a debt and is
        // written in the same transaction, so it lands within seconds of it.
        //
        // Compared in PHP rather than SQL: date arithmetic is dialect-specific
        // (DATE_ADD is MySQL-only) and this migration must also run on SQLite,
        // which is what the test suite uses.
        $opening = [];

        DB::table('debts')
            ->select('id', 'created_at')
            ->orderBy('id')
            ->chunk(200, function ($debts) use (&$opening) {
                foreach ($debts as $debt) {
                    $first = DB::table('debt_payments')
                        ->where('debt_id', $debt->id)
                        ->orderBy('id')
                        ->first(['id', 'created_at']);

                    if (! $first || ! $debt->created_at || ! $first->created_at) {
                        continue;
                    }

                    $gap = abs(strtotime((string) $first->created_at) - strtotime((string) $debt->created_at));

                    if ($gap <= 120) {
                        $opening[] = $first->id;
                    }
                }
            });

        foreach (array_chunk($opening, 500) as $batch) {
            DB::table('debt_payments')->whereIn('id', $batch)->update(['at_point_of_sale' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('debt_payments', function (Blueprint $table) {
            $table->dropColumn('at_point_of_sale');
        });
    }
};
