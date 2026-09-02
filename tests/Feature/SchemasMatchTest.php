<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The two apps have to agree about the shape of the tables they share.
 *
 * They run against one database in production but keep separate migration
 * sets, so a column added on one side has to be copied to the other. Three
 * were missing when this was written - prescription review, pack selling and
 * the wholesale markup - and each was found the same way: something worked in
 * production and blew up in the shop's own tests, because production had the
 * column and the shop's migrations could not create it.
 *
 * That gap hides real faults. The shop cannot be tested against the schema it
 * actually runs on, so nothing about those columns is ever exercised until a
 * customer meets it.
 *
 * This reads the columns the shop's own migrations produce and checks the ones
 * its code depends on are there. It is deliberately about what this app uses
 * rather than a column-by-column comparison with the staff app: the staff app
 * has plenty the shop has no business knowing about.
 */
class SchemasMatchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Columns the shop reads or writes, by table.
     *
     * Add to this whenever the shop starts using a column the staff app owns -
     * that is the moment the migration needs copying across.
     */
    private const REQUIRED = [
        'products' => [
            'name', 'unit', 'sku', 'category_id', 'selling_price',
            'wholesale_price', 'wholesale_min_qty', 'wholesale_markup_percent',
            'has_pack', 'pack_size', 'pack_price',
            'reorder_level', 'description', 'image', 'barcode',
            'requires_prescription', 'is_featured', 'show_in_shop',
        ],
        'customers' => [
            'name', 'type', 'phone', 'phone_normalised', 'email', 'password',
            'credit_balance', 'registered_by',
        ],
        'orders' => [
            'order_number', 'customer_id', 'subtotal', 'delivery_fee', 'total_amount',
            'insurance_covered', 'insurance_subscription_id',
            'prescription_path', 'prescription_status',
            'fulfillment_type', 'payment_method', 'payment_status', 'status',
        ],
        'sale_returns' => [
            'sale_id', 'processed_by', 'reason', 'total_credit',
            'refund_method', 'refunded_at',
        ],
        'stock_movements' => [
            'batch_id', 'quantity', 'balance_after', 'type', 'reference', 'user_id',
        ],
        'categories' => ['name', 'description', 'image'],
    ];

    public function test_every_column_this_app_uses_can_be_built_from_its_own_migrations(): void
    {
        $missing = [];

        foreach (self::REQUIRED as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $missing[] = $table . ' (the whole table)';
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $missing[] = $table . '.' . $column;
                }
            }
        }

        $this->assertSame([], $missing, implode("\n", array_merge(
            ['The shop uses these columns but its own migrations do not create them.'],
            ['Production has them because the staff app migrated them into the shared database,'],
            ['so this only shows up as tests that cannot run. Copy the migration across:'],
            $missing,
        )));
    }

    public function test_the_check_would_notice_a_missing_column(): void
    {
        // The guard above only earns its place if it can fail, and an empty
        // result should mean "checked" rather than "never looked".
        $this->assertFalse(Schema::hasColumn('products', 'a_column_that_does_not_exist'));
        $this->assertTrue(Schema::hasColumn('products', 'selling_price'));
    }
}
