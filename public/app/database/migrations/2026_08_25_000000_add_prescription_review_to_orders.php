<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pharmacist review of prescriptions attached to online orders.
 *
 * Until now the customer uploaded a prescription at checkout and nobody
 * qualified ever opened it: a sales user moved the order straight through to
 * dispatch. The file was collected as evidence and then not used as evidence.
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
            $table->string('prescription_status', 20)->nullable()->after('prescription_path');
            $table->foreignId('prescription_reviewed_by')->nullable()->after('prescription_status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('prescription_reviewed_at')->nullable()->after('prescription_reviewed_by');
            // Why it was refused, so the customer can be told something useful
            // and the decision is not lost.
            $table->string('prescription_note', 500)->nullable()->after('prescription_reviewed_at');
        });

        // Orders already in flight: anything with a prescription on file that
        // has not shipped still needs looking at. Ones already completed are
        // left alone - the goods are gone and marking them pending would put
        // work in the queue that cannot be acted on.
        Schema::hasTable('order_items') && \Illuminate\Support\Facades\DB::table('orders')
            ->whereNotNull('prescription_path')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->update(['prescription_status' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prescription_reviewed_by');
            $table->dropColumn(['prescription_status', 'prescription_reviewed_at', 'prescription_note']);
        });
    }
};
