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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('has_pack')->default(false)->after('wholesale_min_qty');
            $table->unsignedInteger('pack_size')->nullable()->after('has_pack');
            $table->decimal('pack_price', 10, 2)->nullable()->after('pack_size');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['has_pack', 'pack_size', 'pack_price']);
        });
    }
};
