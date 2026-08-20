<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The name backfills rewrite live rows and cannot be reversed, so the original
 * casing is written to the audit trail first.
 *
 * These tests seed MIXED-CASE rows deliberately: on the development database
 * every row was already uppercase, so the migrations were a no-op there and
 * proved nothing about what they do to real data.
 */
class NameBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(string $file): void
    {
        (require database_path("migrations/{$file}.php"))->up();
    }

    private function insertRaw(string $table, array $row): int
    {
        DB::table($table)->insert($row + ['created_at' => now(), 'updated_at' => now()]);

        return DB::table($table)->where('name', $row['name'])->value('id');
    }

    public function test_product_backfill_uppercases_and_keeps_the_original(): void
    {
        $categoryId = Category::create(['name' => 'General'])->id;

        $id = $this->insertRaw('products', [
            'name' => 'Paracetamol Extra Strength',
            'category_id' => $categoryId,
            'selling_price' => 500,
            'reorder_level' => 1,
        ]);
        AuditLog::query()->delete();

        $this->runMigration('2026_08_19_000003_normalise_product_name_casing');

        $this->assertSame(
            'PARACETAMOL EXTRA STRENGTH',
            DB::table('products')->where('id', $id)->value('name')
        );

        $log = AuditLog::where('auditable_type', Product::class)
            ->where('auditable_id', (string) $id)->first();

        $this->assertNotNull($log, 'Original product name was lost with no record.');
        $this->assertSame('Paracetamol Extra Strength', $log->old_value);
        $this->assertSame('PARACETAMOL EXTRA STRENGTH', $log->new_value);
        $this->assertNull($log->user_id, 'A migration should not be attributed to a person.');
    }

    public function test_customer_backfill_keeps_the_original_name_recoverable(): void
    {
        $id = $this->insertRaw('customers', [
            'name'  => 'Aisha Bello',
            'type'  => 'retail',
            'phone' => '08031112233',
        ]);
        AuditLog::query()->delete();

        $this->runMigration('2026_08_20_000001_normalise_name_casing_across_models');

        $this->assertSame('AISHA BELLO', DB::table('customers')->where('id', $id)->value('name'));

        $log = AuditLog::where('auditable_type', Customer::class)
            ->where('auditable_id', (string) $id)->first();

        $this->assertNotNull($log, 'Customer name changed on a receipt-facing field with no record.');
        $this->assertSame('Aisha Bello', $log->old_value);
    }

    public function test_rows_already_uppercase_are_left_alone_and_not_logged(): void
    {
        $id = $this->insertRaw('customers', [
            'name'  => 'MUSA DANJUMA',
            'type'  => 'retail',
            'phone' => '08034445566',
        ]);
        AuditLog::query()->delete();

        $this->runMigration('2026_08_20_000001_normalise_name_casing_across_models');

        $this->assertSame('MUSA DANJUMA', DB::table('customers')->where('id', $id)->value('name'));
        $this->assertSame(0, AuditLog::count(), 'Unchanged rows should not create noise in the trail.');
    }

    public function test_backfill_is_safe_to_run_twice(): void
    {
        $id = $this->insertRaw('customers', [
            'name'  => 'Ngozi Okeke',
            'type'  => 'retail',
            'phone' => '08036667788',
        ]);
        AuditLog::query()->delete();

        $this->runMigration('2026_08_20_000001_normalise_name_casing_across_models');
        $this->runMigration('2026_08_20_000001_normalise_name_casing_across_models');

        $this->assertSame('NGOZI OKEKE', DB::table('customers')->where('id', $id)->value('name'));
        $this->assertSame(1, AuditLog::count(), 'Second run should be a no-op.');
    }

    public function test_original_casing_can_actually_be_reconstructed(): void
    {
        $names = ['Aisha Bello', 'Musa Danjuma', 'Ngozi Okeke'];

        foreach ($names as $i => $name) {
            $this->insertRaw('customers', [
                'name' => $name, 'type' => 'retail', 'phone' => '0803000' . (1000 + $i),
            ]);
        }
        AuditLog::query()->delete();

        $this->runMigration('2026_08_20_000001_normalise_name_casing_across_models');

        // This is the restore path if uppercase reads badly on receipts.
        $restored = AuditLog::where('auditable_type', Customer::class)
            ->where('field', 'name')
            ->pluck('old_value')
            ->all();

        sort($names);
        sort($restored);
        $this->assertSame($names, $restored);
    }
}
