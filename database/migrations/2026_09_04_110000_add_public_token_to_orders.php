<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A link to an order that only the person who placed it can hold.
 *
 * The order pages were addressed by id - /order/5/confirmation and
 * /order/5/pay - with no login and no ownership check on either. Anyone could
 * count upwards through the numbers and read a stranger's order: what they
 * bought, what they paid, the phone number and the street it was going to.
 *
 * A guest has no account to log into, so the answer cannot be a login. It has
 * to be a link that cannot be guessed, which is what this column is: a random
 * token, generated when the order is created, that stands in for the id in
 * every public URL.
 *
 * Existing orders are given one here rather than left null, so that a customer
 * looking at their own history does not find half of it unreachable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'public_token')) {
                $table->string('public_token', 40)->nullable()->unique()->after('order_number');
            }
        });

        // In chunks: this runs against a live orders table, and a token has
        // to be generated per row rather than set to one value.
        //
        // chunkById rather than chunk, because the loop writes the very
        // column the filter reads. Paging by offset would renumber the
        // remaining rows under itself and skip every other page; paging by
        // id > last cannot. No orderBy of our own - chunkById sets its own,
        // and a second one only confuses the matter.
        DB::table('orders')->whereNull('public_token')
            ->chunkById(200, function ($orders) {
                foreach ($orders as $order) {
                    DB::table('orders')->where('id', $order->id)
                        ->update(['public_token' => Str::random(32)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'public_token')) {
                $table->dropColumn('public_token');
            }
        });
    }
};
