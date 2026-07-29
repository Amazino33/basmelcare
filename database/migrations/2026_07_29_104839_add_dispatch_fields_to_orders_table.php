<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('cashier_verified_at')->nullable()->after('paid_at');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete()->after('cashier_verified_at');
            $table->string('delivery_person_name')->nullable()->after('verified_by');
            $table->string('delivery_person_phone')->nullable()->after('delivery_person_name');
            $table->foreignId('delivery_user_id')->nullable()->constrained('users')->nullOnDelete()->after('delivery_person_phone');
            $table->timestamp('dispatched_at')->nullable()->after('delivery_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropForeign(['delivery_user_id']);
            $table->dropColumn(['cashier_verified_at', 'verified_by', 'delivery_person_name', 'delivery_person_phone', 'delivery_user_id', 'dispatched_at']);
        });
    }
};
