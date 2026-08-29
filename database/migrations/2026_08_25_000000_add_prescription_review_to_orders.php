<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pharmacist review of prescriptions attached to online orders.
 *
 * The shop's copy of a migration the staff app already carries. Both apps run
 * against one database, so production has had these columns since the staff
 * app was migrated - but the shop writes prescription_status at checkout, and
 * without this its own migration set could not build a working database.
 *
 * Guarded column by column so it is safe to run against a database where the
 * staff app got there first.
 *
 * Null means no review is needed - the order contains nothing that requires a
 * prescription. That is deliberately different from 'pending', which means one
 * is required and has not happened yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'prescription_status')) {
                $table->string('prescription_status', 20)->nullable()->after('prescription_path');
            }

            if (! Schema::hasColumn('orders', 'prescription_reviewed_by')) {
                $table->foreignId('prescription_reviewed_by')->nullable()
                    ->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'prescription_reviewed_at')) {
                $table->timestamp('prescription_reviewed_at')->nullable();
            }

            if (! Schema::hasColumn('orders', 'prescription_note')) {
                // Why it was refused, so the customer can be told something
                // useful and the decision is not lost.
                $table->string('prescription_note', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'prescription_reviewed_by')) {
                $table->dropConstrainedForeignId('prescription_reviewed_by');
            }

            foreach (['prescription_status', 'prescription_reviewed_at', 'prescription_note'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
