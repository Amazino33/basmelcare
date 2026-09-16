<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-out tracking for WhatsApp and SMS broadcast messaging.
 *
 * When a customer asks not to receive promotional announcements (e.g. replies STOP),
 * their opt-out timestamp is recorded here. The broadcast audience query excludes
 * anyone with broadcast_opt_out_at set, protecting the pharmacy from user spam reports
 * which are the leading cause of WhatsApp business number bans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'broadcast_opt_out_at')) {
                $table->timestamp('broadcast_opt_out_at')->nullable()->after('notes');
                $table->index('broadcast_opt_out_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'broadcast_opt_out_at')) {
                $table->dropIndex(['broadcast_opt_out_at']);
                $table->dropColumn('broadcast_opt_out_at');
            }
        });
    }
};
