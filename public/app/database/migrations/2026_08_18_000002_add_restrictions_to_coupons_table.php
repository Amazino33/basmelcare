<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->string('customer_type', 10)->default('all')->after('max_discount'); // all, new, returning
            $table->decimal('min_order_amount', 10, 2)->nullable()->after('customer_type');
            $table->decimal('max_order_amount', 10, 2)->nullable()->after('min_order_amount');
            $table->json('restricted_categories')->nullable()->after('max_order_amount');
            $table->json('restricted_products')->nullable()->after('restricted_categories');
            $table->unsignedInteger('min_item_count')->nullable()->after('restricted_products');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn([
                'customer_type', 'min_order_amount', 'max_order_amount',
                'restricted_categories', 'restricted_products', 'min_item_count',
            ]);
        });
    }
};
