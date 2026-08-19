<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function actor(array $roles = ['admin']): User
    {
        $user = User::factory()->create(['role' => $roles, 'status' => 'active']);
        $this->actingAs($user);

        return $user;
    }

    private function makeProduct(): Product
    {
        return Product::create([
            'name'          => 'Paracetamol',
            'category_id'   => Category::create(['name' => 'Painkillers'])->id,
            'selling_price' => 850,
            'reorder_level' => 5,
        ]);
    }

    public function test_selling_price_change_records_who_and_the_values(): void
    {
        $user    = $this->actor();
        $product = $this->makeProduct();

        $product->update(['selling_price' => 1200]);

        $log = AuditLog::where('field', 'selling_price')->latest('id')->first();

        $this->assertNotNull($log, 'No audit entry written for a price change.');
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(Product::class, $log->auditable_type);
        // Product::getNameAttribute() uppercases on read, so that is what gets stored.
        $this->assertSame('PARACETAMOL', $log->auditable_label);
        $this->assertSame('updated', $log->event);
        $this->assertEquals(850, (float) $log->old_value);
        $this->assertEquals(1200, (float) $log->new_value);
    }

    public function test_untracked_field_change_is_not_logged(): void
    {
        $this->actor();
        $product = $this->makeProduct();
        AuditLog::query()->delete();

        $product->update(['reorder_level' => 99]);

        $this->assertSame(0, AuditLog::where('event', 'updated')->count());
    }

    public function test_batch_cost_and_quantity_are_tracked(): void
    {
        $this->actor();
        $product = $this->makeProduct();

        $batch = Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B-001',
            'expiry_date'  => now()->addYear(),
            'cost_price'   => 400,
            'quantity'     => 50,
        ]);

        AuditLog::query()->delete();
        $batch->update(['cost_price' => 500, 'quantity' => 40]);

        $fields = AuditLog::pluck('field')->all();
        $this->assertContains('cost_price', $fields);
        $this->assertContains('quantity', $fields);
        $this->assertSame('B-001', AuditLog::first()->auditable_label);
    }

    public function test_coupon_creation_and_discount_change_are_tracked(): void
    {
        $this->actor();

        $coupon = Coupon::create([
            'code' => 'SAVE50', 'type' => 'percent', 'value' => 50,
            'is_active' => true, 'auto_apply' => true, 'customer_type' => 'all',
        ]);

        $this->assertSame(1, AuditLog::where('event', 'created')
            ->where('auditable_type', Coupon::class)->count());

        AuditLog::query()->delete();
        $coupon->update(['value' => 75]);

        $log = AuditLog::where('field', 'value')->first();
        $this->assertNotNull($log);
        $this->assertSame('SAVE50', $log->auditable_label);
        $this->assertEquals(50, (float) $log->old_value);
        $this->assertEquals(75, (float) $log->new_value);
    }

    public function test_commission_rate_change_is_tracked_through_the_settings_helper(): void
    {
        $user = $this->actor();
        AppSetting::set('commission_amount', 100);
        AuditLog::query()->delete();

        AppSetting::set('commission_amount', 500);

        $log = AuditLog::where('auditable_type', AppSetting::class)->latest('id')->first();

        $this->assertNotNull($log, 'Settings changed via AppSetting::set() were not audited.');
        $this->assertSame('commission_amount', $log->auditable_label);
        $this->assertEquals(100, (float) $log->old_value);
        $this->assertEquals(500, (float) $log->new_value);
        $this->assertSame($user->id, $log->user_id);
    }

    public function test_deleting_a_coupon_is_recorded(): void
    {
        $this->actor();
        $coupon = Coupon::create([
            'code' => 'GONE', 'type' => 'fixed', 'value' => 100,
            'is_active' => true, 'customer_type' => 'all',
        ]);
        AuditLog::query()->delete();

        $coupon->delete();

        $log = AuditLog::where('event', 'deleted')->first();
        $this->assertNotNull($log);
        // Label survives the deletion so the entry stays readable.
        $this->assertSame('GONE', $log->auditable_label);
    }

    public function test_system_changes_without_a_user_are_still_recorded(): void
    {
        auth()->logout();
        $product = $this->makeProduct();
        AuditLog::query()->delete();

        $product->update(['selling_price' => 999]);

        $log = AuditLog::where('field', 'selling_price')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->user_id);
    }

    public function test_page_is_restricted_to_admin_and_branch_manager(): void
    {
        $url = '/' . trim(config('app.desk_prefix'), '/') . '/money-trail';

        foreach ([['admin'], ['branch_manager']] as $roles) {
            $this->actingAs(User::factory()->create(['role' => $roles, 'status' => 'active']))
                ->get($url)->assertOk();
        }

        foreach ([['pharmacist'], ['cashier'], ['sales'], ['inventory_manager'], ['promoter']] as $roles) {
            $this->actingAs(User::factory()->create(['role' => $roles, 'status' => 'active']))
                ->get($url)->assertForbidden();
        }
    }

    public function test_page_shows_a_recorded_change(): void
    {
        $user = $this->actor(['branch_manager']);
        $this->makeProduct()->update(['selling_price' => 1200]);

        $response = $this->get('/' . trim(config('app.desk_prefix'), '/') . '/money-trail');

        $response->assertOk();
        $response->assertSee('PARACETAMOL');
        $response->assertSee($user->name);
        $response->assertSee('850');   // was
        $response->assertSee('1200');  // now
        $this->assertStringNotContainsString('Undefined variable', $response->getContent());
    }
}
