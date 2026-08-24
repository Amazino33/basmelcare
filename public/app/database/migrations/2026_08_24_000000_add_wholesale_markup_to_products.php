<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product override for the wholesale markup.
 *
 * The default lives in Settings and covers most of the catalogue; this is for
 * the few lines where a flat percentage is wrong - a controlled drug carrying
 * more handling cost, or a supplement competing on price.
 *
 * Null means "use the global default", which is different from zero: zero is a
 * deliberate choice to sell at cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('wholesale_markup_percent', 5, 2)->nullable()->after('wholesale_min_qty');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('wholesale_markup_percent');
        });
    }
};
