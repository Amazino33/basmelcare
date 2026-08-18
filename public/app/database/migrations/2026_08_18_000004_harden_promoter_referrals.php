<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Blank phones must be NULL, not '', or the unique index below would
        // reject every customer added without a phone after the first.
        DB::table('customers')->where('phone', '')->update(['phone' => null]);

        // Canonicalise existing numbers so the unique index actually catches
        // "0801 234 5678" and "+234 801 234 5678" as the same number.
        foreach (DB::table('customers')->whereNotNull('phone')->get(['id', 'phone']) as $row) {
            $normalized = \App\Models\Customer::normalizePhone($row->phone);
            if ($normalized !== $row->phone) {
                DB::table('customers')->where('id', $row->id)->update(['phone' => $normalized]);
            }
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->unique('phone');
            $table->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_expires_at');
            $table->timestamp('otp_sent_at')->nullable()->after('otp_attempts');
        });

        // One commission per promoter per customer.
        Schema::table('referral_commissions', function (Blueprint $table) {
            $table->unique(['user_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn(['otp_attempts', 'otp_sent_at']);
        });

        Schema::table('referral_commissions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'customer_id']);
        });
    }
};
