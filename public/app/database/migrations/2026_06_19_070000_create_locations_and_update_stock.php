<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('shop');
            $table->string('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('from_location_id')->nullable()->after('type')->constrained('locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->after('from_location_id')->constrained('locations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->after('to_location_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_location_id');
            $table->dropConstrainedForeignId('to_location_id');
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::dropIfExists('locations');
    }
};
