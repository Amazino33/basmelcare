<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds approval workflow fields to sale returns.
 *
 * The return process is triggered by a sales person, creating a pending
 * request. Approval by either an auditor or a branch manager finalizes the
 * return, restocking goods and disbursing refund/credit. Rejection cancels it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_returns', 'status')) {
                $table->string('status', 20)->default('pending')->after('refund_method');
            }
            if (! Schema::hasColumn('sale_returns', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('sale_returns', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('sale_returns', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('sale_returns', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            if (! Schema::hasColumn('sale_returns', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }
        });

        // Any return created prior to this migration was already processed and finalized.
        DB::table('sale_returns')
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '')
                    ->orWhere('status', 'pending');
            })
            ->whereNotNull('refunded_at')
            ->update([
                'status'      => 'approved',
                'approved_at' => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $drop = [];
            foreach (['rejection_reason', 'rejected_at', 'rejected_by', 'approved_at', 'approved_by', 'status'] as $col) {
                if (Schema::hasColumn('sale_returns', $col)) {
                    $drop[] = $col;
                }
            }
            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
