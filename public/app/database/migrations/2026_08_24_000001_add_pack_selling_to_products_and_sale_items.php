<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Selling by the pack as well as by the tablet.
 *
 * These columns existed once before: a pack feature was added in a01dcb4 and
 * reverted the next day in c9aa47b, which deleted the migration files but not
 * the columns from databases that had already run them. So every statement
 * here is guarded - some installations have these columns and some do not, and
 * the migration has to be correct on both.
 *
 * On sale_items the columns are for PRESENTATION only. quantity stays in
 * units, so "2 packs of 10" is recorded as quantity 20 with is_pack set. That
 * is the important difference from the reverted version, which redefined
 * quantity to mean packs while leaving cost_price per tablet - profit is
 * computed everywhere as subtotal minus cost_price times quantity, so a pack
 * sale reported roughly three times the profit it actually made.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'has_pack')) {
                $table->boolean('has_pack')->default(false)->after('wholesale_markup_percent');
            }

            if (! Schema::hasColumn('products', 'pack_size')) {
                $table->unsignedInteger('pack_size')->nullable()->after('has_pack');
            }

            // Retail only. A wholesale customer pays the wholesale unit price
            // multiplied by the pack size, because wholesale pricing already
            // scales per unit - so a pack needs no separate wholesale figure.
            if (! Schema::hasColumn('products', 'pack_price')) {
                $table->decimal('pack_price', 10, 2)->nullable()->after('pack_size');
            }
        });

        Schema::table('sale_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_items', 'is_pack')) {
                $table->boolean('is_pack')->default(false)->after('subtotal');
            }

            // Recorded on the line rather than read from the product, so a
            // receipt reprinted next year still says what was actually sold
            // even if the pack size has changed since.
            if (! Schema::hasColumn('sale_items', 'pack_size')) {
                $table->unsignedInteger('pack_size')->nullable()->after('is_pack');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['has_pack', 'pack_size', 'pack_price']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['is_pack', 'pack_size']);
        });
    }
};
