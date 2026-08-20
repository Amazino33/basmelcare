<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The auditor answers three questions — what came in, what went out, what we
 * actually made — from the underlying sales rather than a figure handed over by
 * a cashier. They must be able to see everything financial and change nothing.
 */
class AuditorFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function url(string $path): string
    {
        return '/' . trim(config('app.desk_prefix'), '/') . '/' . ltrim($path, '/');
    }

    private function auditor(): User
    {
        return User::factory()->create(['role' => ['auditor'], 'status' => 'active']);
    }

    /** A settled sale of $qty units bought at $cost and sold at $price. */
    private function sell(float $price, float $cost, int $qty = 1, float $discount = 0): Sale
    {
        $product = Product::create([
            'name' => 'Item ' . random_int(1000, 9999),
            'category_id' => Category::firstOrCreate(['name' => 'General'])->id,
            'selling_price' => $price, 'reorder_level' => 1,
        ]);

        // sale_items.batch_id is NOT NULL — stock always comes from a batch.
        $batch = Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B-' . random_int(1000, 9999),
            'expiry_date'  => now()->addYear(),
            'cost_price'   => $cost,
            'quantity'     => max($qty, 1),
        ]);

        $sale = Sale::create([
            'invoice_number'  => 'INV-' . random_int(10000, 99999),
            'user_id'         => User::factory()->create(['role' => ['cashier']])->id,
            'total_amount'    => $price * $qty,
            // Column is NOT NULL with a default of 0 — never null.
            'coupon_discount' => $discount,
            'status'          => 'completed',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id, 'batch_id' => $batch->id,
            'quantity' => $qty, 'unit_price' => $price,
            'cost_price' => $cost, 'subtotal' => $price * $qty,
        ]);

        return $sale;
    }

    private function figures(): array
    {
        return Livewire::actingAs($this->auditor())
            ->test(\App\Livewire\Finance\Index::class)
            ->viewData('f');
    }

    // ── The arithmetic ───────────────────────────────────────────────

    public function test_gross_profit_is_revenue_minus_cost_of_goods_sold(): void
    {
        $this->sell(price: 1000, cost: 600, qty: 2);   // 2000 revenue, 1200 cost

        $f = $this->figures();

        $this->assertEquals(2000, $f['revenue']);
        $this->assertEquals(1200, $f['cogs']);
        $this->assertEquals(800, $f['gross']);
        $this->assertEqualsWithDelta(40.0, $f['grossMargin'], 0.01);
    }

    public function test_coupon_discount_is_deducted_from_revenue(): void
    {
        // total_amount holds the pre-discount figure; the coupon is a separate
        // column subtracted at payment. Ignoring it overstates every discounted sale.
        $this->sell(price: 1000, cost: 600, qty: 1, discount: 150);

        $f = $this->figures();

        $this->assertEquals(850, $f['revenue'], 'Coupon discount was not deducted from revenue.');
        $this->assertEquals(250, $f['gross']);
    }

    public function test_expenses_reduce_net_profit_but_not_gross(): void
    {
        $this->sell(price: 1000, cost: 600);
        Expense::create([
            'user_id' => User::factory()->create()->id,
            'category' => 'Rent', 'description' => 'Shop rent',
            'amount' => 250, 'expense_date' => today(),
        ]);

        $f = $this->figures();

        $this->assertEquals(400, $f['gross'], 'Expenses must not affect gross profit.');
        $this->assertEquals(250, $f['expenses']);
        $this->assertEquals(150, $f['netProfit']);
    }

    public function test_unsettled_sales_are_not_counted_as_income(): void
    {
        $sale = $this->sell(price: 1000, cost: 600);
        $sale->update(['status' => 'pending']);

        $f = $this->figures();

        $this->assertEquals(0, $f['revenue'], 'A pending invoice is not income.');
        $this->assertEquals(0, $f['saleCount']);
    }

    public function test_cancelled_sales_are_excluded(): void
    {
        $sale = $this->sell(price: 1000, cost: 600);
        $sale->update(['status' => 'cancelled']);

        $this->assertEquals(0, $this->figures()['revenue']);
    }

    public function test_trading_and_cash_are_reported_separately(): void
    {
        $this->sell(price: 1000, cost: 600);

        $f = $this->figures();

        // Same sale, two different questions — they must not be the same number
        // once stock purchases enter the picture.
        $this->assertArrayHasKey('gross', $f);
        $this->assertArrayHasKey('netCash', $f);
        $this->assertArrayHasKey('stockPurchases', $f);
    }

    public function test_sales_outside_the_period_are_excluded(): void
    {
        $old = $this->sell(price: 5000, cost: 3000);
        // created_at is not fillable, so update() would silently drop it.
        $old->forceFill(['created_at' => today()->subMonths(3)])->saveQuietly();

        $component = Livewire::actingAs($this->auditor())
            ->test(\App\Livewire\Finance\Index::class)
            ->call('applyPreset', 'today');

        $this->assertEquals(0, $component->viewData('f')['revenue']);
    }

    public function test_every_sale_is_listed_with_its_own_margin(): void
    {
        $this->sell(price: 1000, cost: 600);

        // The client asked to see the actual sales, not just a total.
        $html = $this->actingAs($this->auditor())->get($this->url('finance'))->getContent();

        $this->assertStringContainsString('Every sale in this period', $html);
        $this->assertStringContainsString('Cost', $html);
        $this->assertStringContainsString('Margin', $html);
    }

    // ── Access ───────────────────────────────────────────────────────

    public static function readablePaths(): array
    {
        return [
            'financial records' => ['finance'],
            'money trail'       => ['money-trail'],
            'sales history'     => ['sales'],
            'expenses'          => ['expenses'],
            'purchase orders'   => ['purchase-orders'],
            'reports'           => ['reports'],
            'debt book'         => ['debt-book'],
            'change owed'       => ['credits'],
            'dashboard'         => ['dashboard'],
        ];
    }

    #[DataProvider('readablePaths')]
    public function test_auditor_can_see_the_financial_pages(string $path): void
    {
        $this->actingAs($this->auditor())->get($this->url($path))->assertOk();
    }

    public static function forbiddenPaths(): array
    {
        return [
            'the till'    => ['cashier'],
            'POS'         => ['pos'],
            'coupons'     => ['coupons'],
            'settings'    => ['settings'],
            'staff'       => ['staff'],
            'stock adjust'=> ['stock/adjustments'],
            'customers'   => ['customers'],
            'products'    => ['products'],
        ];
    }

    #[DataProvider('forbiddenPaths')]
    public function test_auditor_cannot_reach_operational_pages(string $path): void
    {
        $this->actingAs($this->auditor())->get($this->url($path))->assertForbidden();
    }

    // ── Read-only ────────────────────────────────────────────────────

    public function test_auditor_cannot_record_an_expense(): void
    {
        $this->actingAs($this->auditor());

        Livewire::test(\App\Livewire\Expenses\Index::class)
            ->set('category', 'Rent')
            ->set('description', 'Sneaky')
            ->set('amount', 5000)
            ->set('expense_date', today()->format('Y-m-d'))
            ->call('save');

        $this->assertSame(0, Expense::count(), 'An auditor created an expense.');
    }

    public function test_auditor_cannot_delete_an_expense(): void
    {
        $expense = Expense::create([
            'user_id' => User::factory()->create()->id,
            'category' => 'Rent', 'description' => 'Real', 'amount' => 100,
            'expense_date' => today(),
        ]);

        $this->actingAs($this->auditor());
        Livewire::test(\App\Livewire\Expenses\Index::class)->call('delete', $expense->id);

        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }

    public function test_an_auditor_who_is_also_a_manager_keeps_manager_powers(): void
    {
        $both = User::factory()->create(['role' => ['auditor', 'branch_manager'], 'status' => 'active']);
        $this->actingAs($both);

        Livewire::test(\App\Livewire\Expenses\Index::class)
            ->set('category', 'Rent')
            ->set('description', 'Legitimate')
            ->set('amount', 5000)
            ->set('expense_date', today()->format('Y-m-d'))
            ->call('save');

        $this->assertSame(1, Expense::count(), 'Holding an operational role must override auditor read-only.');
    }

    // ── Dashboard ────────────────────────────────────────────────────

    public function test_auditor_dashboard_shows_the_money(): void
    {
        $this->sell(price: 1000, cost: 600);

        $component = Livewire::actingAs($this->auditor())->test(\App\Livewire\Dashboard::class);

        $this->assertSame(['auditor'], $component->viewData('panels'));
        $this->assertEquals(1000, $component->viewData('audRevenue'));
        $this->assertEquals(400, $component->viewData('audGross'));
    }

    public function test_auditor_dashboard_renders(): void
    {
        $response = $this->actingAs($this->auditor())->get($this->url('dashboard'));

        $response->assertOk();
        $this->assertStringNotContainsString('Undefined variable', $response->getContent());
        $response->assertSee('Money in, money out, profit');
    }
}
