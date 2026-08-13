<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Set when staff revoke the Wi-Fi access tied to this receipt.
            // Once set, the receipt can no longer (re)connect on HiFastLink.
            $table->timestamp('voucher_revoked_at')->nullable()->after('voucher_redeemed_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('voucher_revoked_at');
        });
    }
};
