<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records how the customer was reached. A customer who could only be reached
     * by SMS has no smart device, so no Wi-Fi code is issued to them and the
     * promoter is paid at registration instead of on connection — this column is
     * the evidence for why that commission exists.
     */
    public function up(): void
    {
        Schema::table('promoter_codes', function (Blueprint $table) {
            $table->string('delivered_via', 20)->nullable()->after('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('promoter_codes', function (Blueprint $table) {
            $table->dropColumn('delivered_via');
        });
    }
};
