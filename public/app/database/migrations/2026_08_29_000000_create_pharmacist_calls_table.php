<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "A customer is here" - the counter calling for a pharmacist.
 *
 * A row rather than a broadcast event, because there is no realtime
 * infrastructure here and the app already refreshes the till and the cashier
 * screen by polling. A row also survives the pharmacist being on a different
 * page, or not logged in for a minute.
 *
 * Branch-scoped: someone waiting at one counter is not another branch's
 * problem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacist_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('called_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            // Every lookup is "is anything waiting here right now".
            $table->index(['branch_id', 'acknowledged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacist_calls');
    }
};
