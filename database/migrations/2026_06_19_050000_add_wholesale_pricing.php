<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('wholesale_price', 10, 2)->nullable()->after('selling_price');
            $table->integer('wholesale_min_qty')->nullable()->after('wholesale_price');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('type')->default('retail')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['wholesale_price', 'wholesale_min_qty']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
