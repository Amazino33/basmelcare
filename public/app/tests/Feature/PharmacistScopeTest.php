<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\MedicalRecord;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The pharmacist is a clinical role: patients, records, drug catalogue and stock
 * visibility. Money — selling, the till, refunds, procurement, reports — belongs
 * to admin and branch_manager.
 */
class PharmacistScopeTest extends TestCase
{
    use RefreshDatabase;

    private function url(string $path): string
    {
        return '/' . trim(config('app.desk_prefix'), '/') . '/' . ltrim($path, '/');
    }

    private function pharmacist(): User
    {
        return User::factory()->create(['role' => ['pharmacist'], 'status' => 'active']);
    }

    public static function forbiddenPaths(): array
    {
        return [
            'POS'             => ['pos'],
            'online orders'   => ['online-orders'],
            'stock take'      => ['stock/take'],
            'cashier till'    => ['cashier'],
            'change owed'     => ['credits'],
            'debt book'       => ['debt-book'],
            'sales history'   => ['sales'],
            'purchase orders' => ['purchase-orders'],
            'suppliers'       => ['suppliers'],
            'reports'         => ['reports'],
            'stock transfers' => ['stock/transfers'],
            'stock adjust'    => ['stock/adjustments'],
            'locations'       => ['locations'],
            'expenses'        => ['expenses'],
            'coupons'         => ['coupons'],
            'money trail'     => ['money-trail'],
        ];
    }

    public static function allowedPaths(): array
    {
        return [
            'customers'     => ['customers'],
            'appointments'  => ['appointments'],
            'products'      => ['products'],
            'categories'    => ['categories'],
            'stock levels'  => ['inventory'],
            'expiry alerts' => ['expiry-alerts'],
            'dashboard'     => ['dashboard'],
        ];
    }

    #[DataProvider('forbiddenPaths')]
    public function test_pharmacist_cannot_reach_money_pages(string $path): void
    {
        $this->actingAs($this->pharmacist())
            ->get($this->url($path))
            ->assertForbidden();
    }

    #[DataProvider('allowedPaths')]
    public function test_pharmacist_keeps_clinical_pages(string $path): void
    {
        $this->actingAs($this->pharmacist())
            ->get($this->url($path))
            ->assertOk();
    }

    public function test_branch_manager_still_reaches_everything_pharmacist_lost(): void
    {
        $manager = User::factory()->create(['role' => ['branch_manager'], 'status' => 'active']);

        foreach (['pos', 'cashier', 'sales', 'purchase-orders', 'reports', 'coupons', 'money-trail'] as $path) {
            $this->actingAs($manager)->get($this->url($path))
                ->assertOk("branch_manager lost access to {$path}");
        }
    }

    // ── Medical records: clinical only ───────────────────────────────

    private function customerWithRecord(): Customer
    {
        $customer = Customer::create([
            'name' => 'Aisha Bello', 'type' => 'retail', 'phone' => '08031112233',
        ]);

        MedicalRecord::create([
            'customer_id' => $customer->id,
            'recorded_by' => User::factory()->create(['role' => ['pharmacist']])->id,
            'title'       => 'Penicillin allergy',
            'type'        => 'allergy',
            'record_date' => today(),
        ]);

        return $customer;
    }

    public function test_pharmacist_can_write_medical_records(): void
    {
        $this->actingAs($this->pharmacist());
        $customer = $this->customerWithRecord();

        Livewire::test(\App\Livewire\Customers\Index::class)
            ->call('viewProfile', $customer->id)
            ->call('openMedicalRecord')
            ->set('mr_title', 'Blood pressure')
            ->set('mr_type', 'vitals')
            ->set('mr_date', today()->format('Y-m-d'))
            ->call('saveMedicalRecord');

        $this->assertSame(2, MedicalRecord::where('customer_id', $customer->id)->count());
    }

    public function test_cashier_and_sales_cannot_write_medical_records(): void
    {
        foreach ([['cashier'], ['sales']] as $roles) {
            $this->actingAs(User::factory()->create(['role' => $roles, 'status' => 'active']));
            $customer = Customer::create([
                'name' => 'Test ' . $roles[0], 'type' => 'retail',
                'phone' => '0803' . random_int(1000000, 9999999),
            ]);

            Livewire::test(\App\Livewire\Customers\Index::class)
                ->call('viewProfile', $customer->id)
                ->set('mr_title', 'Sneaky note')
                ->set('mr_type', 'diagnosis')
                ->set('mr_date', today()->format('Y-m-d'))
                ->call('saveMedicalRecord');

            $this->assertSame(0, MedicalRecord::where('customer_id', $customer->id)->count(),
                "{$roles[0]} was able to write a medical record.");
        }
    }

    public function test_cashier_cannot_delete_a_medical_record(): void
    {
        $this->actingAs($this->pharmacist());
        $customer = $this->customerWithRecord();
        $record   = MedicalRecord::where('customer_id', $customer->id)->first();

        $this->actingAs(User::factory()->create(['role' => ['cashier'], 'status' => 'active']));

        Livewire::test(\App\Livewire\Customers\Index::class)
            ->call('deleteMedicalRecord', $record->id);

        $this->assertDatabaseHas('medical_records', ['id' => $record->id]);
    }

    public function test_records_are_not_even_loaded_for_staff_without_clinical_access(): void
    {
        $this->actingAs($this->pharmacist());
        $customer = $this->customerWithRecord();

        $this->actingAs(User::factory()->create(['role' => ['cashier'], 'status' => 'active']));

        $data = Livewire::test(\App\Livewire\Customers\Index::class)
            ->call('viewProfile', $customer->id)
            ->viewData('viewCustomer');

        $this->assertFalse($data->relationLoaded('medicalRecords'),
            'Medical records were loaded for a role that may not see them.');
    }

    public function test_branch_manager_may_read_but_not_write_records(): void
    {
        $this->actingAs($this->pharmacist());
        $customer = $this->customerWithRecord();

        $this->actingAs(User::factory()->create(['role' => ['branch_manager'], 'status' => 'active']));

        $component = Livewire::test(\App\Livewire\Customers\Index::class)
            ->call('viewProfile', $customer->id);

        $this->assertTrue($component->viewData('canViewRecords'));
        $this->assertFalse($component->viewData('canEditRecords'));
    }

    // ── Refunds ──────────────────────────────────────────────────────

    public function test_refund_actions_are_limited_to_admin_and_branch_manager(): void
    {
        $product = Product::create([
            'name' => 'Paracetamol', 'selling_price' => 850, 'reorder_level' => 1,
            'category_id' => Category::create(['name' => 'Painkillers'])->id,
        ]);

        foreach ([['admin', true], ['branch_manager', true], ['cashier', false], ['sales', false]] as [$role, $allowed]) {
            $user = User::factory()->create(['role' => [$role], 'status' => 'active']);
            $sale = Sale::create([
                'invoice_number' => 'INV-' . random_int(10000, 99999),
                'user_id' => $user->id, 'total_amount' => 850, 'status' => 'paid',
            ]);
            $this->actingAs($user);

            $component = Livewire::test(\App\Livewire\Sales\Index::class)
                ->call('completeHandover', $sale->id);

            $this->assertSame(
                $allowed ? 'completed' : 'paid',
                $sale->fresh()->status,
                "{$role} handover behaved unexpectedly."
            );
        }
    }

    // ── Dashboard ────────────────────────────────────────────────────

    public function test_pharmacist_dashboard_is_clinical_with_no_money(): void
    {
        $component = Livewire::actingAs($this->pharmacist())
            ->test(\App\Livewire\Dashboard::class);

        $this->assertSame(['pharmacist'], $component->viewData('panels'));

        // viewData() throws when a key is absent, which is exactly the guarantee
        // we want: none of these are passed to the pharmacist's view at all.
        foreach (['totalSalesToday', 'todayProfit', 'recentSales', 'potentialProfit', 'invStockValue'] as $money) {
            try {
                $component->viewData($money);
                $this->fail("Pharmacist dashboard exposed {$money}.");
            } catch (\ErrorException|\PHPUnit\Framework\AssertionFailedError $e) {
                if ($e instanceof \PHPUnit\Framework\AssertionFailedError) {
                    throw $e;
                }
                $this->addToAssertionCount(1);
            }
        }

        $this->assertNotNull($component->viewData('phExpiringSoon'));
        $this->assertNotNull($component->viewData('phTodayAppointments'));
    }

    public function test_pharmacist_dashboard_renders(): void
    {
        $response = $this->actingAs($this->pharmacist())->get($this->url('dashboard'));

        $response->assertOk();
        $this->assertStringNotContainsString('Undefined variable', $response->getContent());
        $response->assertSee('Patients and drug safety');
    }
}
