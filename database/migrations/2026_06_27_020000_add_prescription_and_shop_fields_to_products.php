<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('requires_prescription')->default(false)->after('barcode');
            $table->boolean('is_featured')->default(false)->after('requires_prescription');
            $table->boolean('show_in_shop')->default(true)->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['requires_prescription', 'is_featured', 'show_in_shop']);
        });
    }
};
