<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promoter_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();      // promoter who issued it
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();  // customer it was issued to
            $table->date('valid_until')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'redeemed_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Per-promoter target; null falls back to the global default setting.
            $table->unsignedInteger('referral_target')->nullable()->after('salary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promoter_codes');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('referral_target');
        });
    }
};
