<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What one of something is.
 *
 * The shop showed a bare price. On a product sold loose that reads as the
 * price of the whole packet - ₦50 next to a picture of a box of paracetamol
 * looks like a mistake, or a bargain, and neither brings anybody to the
 * counter in a good mood.
 *
 * Null means "each", which is right for most things: a bottle of syrup, a
 * thermometer, a pack of plasters. It only needs setting on the things that
 * are broken out of their packet and sold one at a time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'unit')) {
                $table->string('unit', 20)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'unit')) {
                $table->dropColumn('unit');
            }
        });
    }
};
