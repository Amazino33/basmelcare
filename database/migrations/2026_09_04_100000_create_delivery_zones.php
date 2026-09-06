<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What it costs to deliver, by where the customer lives.
 *
 * Delivery was a flat 1500 written into the checkout, charged the same to a
 * street behind the pharmacy and to the other end of the state. That is a
 * price the pharmacy never chose and cannot change without a developer, and
 * it is wrong in both directions: it loses money on the far runs and drives
 * away the near ones.
 *
 * A zone is an area the pharmacy is willing to deliver to and the fee for
 * getting there. The customer picks theirs at checkout, so they see the fee
 * before they commit rather than discovering it at the end.
 *
 *   fee        What that run costs. Zero is allowed and means free.
 *   note       "Same day if ordered before 4pm" - the sort of thing that
 *              belongs next to the choice, not in a policy page nobody reads.
 *   is_active  A zone the pharmacy has stopped serving. Kept rather than
 *              deleted, because orders point at it.
 *
 * Orders keep their own copy of the fee and the area name. A zone renamed or
 * repriced next month must not silently rewrite what a customer was charged
 * last month - the order is the record of what was agreed.
 *
 * The existing flat fee is carried in as one zone covering everywhere, so
 * nothing about checkout changes until the pharmacy sets its own areas up.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_zones')) {
            Schema::create('delivery_zones', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->decimal('fee', 10, 2)->default(0);
                $table->string('note')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });

            // Whatever the shop was charging, it keeps charging, to the same
            // people, until somebody decides otherwise.
            DB::table('delivery_zones')->insert([
                'name'       => 'Standard delivery',
                'fee'        => 1500,
                'note'       => null,
                'is_active'  => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'delivery_zone_id')) {
                $table->foreignId('delivery_zone_id')->nullable()->after('delivery_fee')
                    ->constrained('delivery_zones')->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'delivery_area')) {
                $table->string('delivery_area')->nullable()->after('delivery_zone_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'delivery_zone_id')) {
                $table->dropConstrainedForeignId('delivery_zone_id');
            }

            if (Schema::hasColumn('orders', 'delivery_area')) {
                $table->dropColumn('delivery_area');
            }
        });

        Schema::dropIfExists('delivery_zones');
    }
};
