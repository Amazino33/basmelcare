<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the phones were rung for a call nobody answered on screen.
 *
 * Recorded so it happens once. The check runs on the counter's own polling,
 * which fires every five seconds - without a mark it would message the
 * pharmacists twelve times a minute until somebody came.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacist_calls', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable()->after('acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('pharmacist_calls', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};
