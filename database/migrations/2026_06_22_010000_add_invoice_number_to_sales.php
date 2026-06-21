<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('invoice_number')->unique()->nullable()->after('id');
            $table->foreignId('confirmed_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->after('status');
            $table->timestamp('confirmed_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'paid_at', 'confirmed_at']);
            $table->dropConstrainedForeignId('confirmed_by');
        });
    }
};
