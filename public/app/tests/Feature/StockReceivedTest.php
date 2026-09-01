<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * What came into stock, grouped as it actually happened.
 *
 * The auditor asked to see one delivery - some products new to the catalogue,
 * some already stocked. A delivery is not a row in this system: it is however
 * many lines one person entered in one sitting, so lines are grouped by day
 * and by who entered them.
 *
 * Two of the four intake paths used to record neither the person nor anything
 * identifying: Quick Add and Add Batch both wrote the constant "Initial
 * stock" with no user_id, so a delivery entered that way could not be
 * attributed to anybody.
 */
class StockReceivedTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $roles): User
    {
        return User::factory()->create(['role' => $roles, 'status' => 'active']);
    }

    private function received(string $product, int $qty, float $cost, ?User $by, string $reference = 'Stock intake', ?string $on = null): StockMovement
    {
        $model = Product::firstOrCreate(
            ['name' => $product],
            [
                'category_id'   => Category::firstOrCreate(['name' => 'General'])->id,
                'selling_price' => $cost * 2,
                'reorder_level' => 1,
            ]
        );

        $batch = Batch::create([
            'product_id'   => $model->id,
            'batch_number' => 'B-' . random_int(1000, 9999),
            'expiry_date'  => now()->addYear(),
            'cost_price'   => $cost,
            'quantity'     => $qty,
        ]);

        $movement = StockMovement::create([
            'batch_id'  => $batch->id,
            'quantity'  => $qty,
            'type'      => 'purchase',
            'reference' => $reference,
            'user_id'   => $by?->id,
        ]);

        if ($on) {
            $movement->forceFill(['created_at' => $on])->save();
        }

        return $movement->fresh();
    }

    private function page(User $user)
    {
        return Livewire::actingAs($user)->test(\App\Livewire\Stock\Received::class);
    }

    // ── the grouping ────────────────────────────────────────────────────

    public function test_one_persons_entries_on_one_day_are_one_intake(): void
    {
        $ibrahim = $this->user(['inventory_manager']);

        $this->received('PARACETAMOL 500MG', 100, 85, $ibrahim);
        $this->received('AMOXIL 500MG', 50, 260, $ibrahim);
        $this->received('ARBITEL 80H', 20, 1100, $ibrahim);

        $intakes = $this->page($ibrahim)->viewData('intakes');

        $this->assertCount(1, $intakes, 'Three lines from one person on one day are one delivery.');
        $this->assertCount(3, $intakes[0]['lines']);
    }

    public function test_two_people_on_the_same_day_are_two_intakes(): void
    {
        $ibrahim = $this->user(['inventory_manager']);
        $amina   = $this->user(['inventory_manager']);

        $this->received('PARACETAMOL 500MG', 100, 85, $ibrahim);
        $this->received('AMOXIL 500MG', 50, 260, $amina);

        $this->assertCount(2, $this->page($ibrahim)->viewData('intakes'));
    }

    public function test_the_same_person_on_different_days_is_two_intakes(): void
    {
        $ibrahim = $this->user(['inventory_manager']);

        $this->received('PARACETAMOL 500MG', 100, 85, $ibrahim);
        $this->received('AMOXIL 500MG', 50, 260, $ibrahim, on: now()->subDays(3)->toDateTimeString());

        // The window is set rather than left at its default. The page opens on
        // this month, so on the first of a month "three days ago" is in the
        // last one - and the test failed for three days in every thirty while
        // the grouping it is about was perfectly correct.
        $page = $this->page($ibrahim)->set('dateFrom', now()->subWeek()->format('Y-m-d'));

        $this->assertCount(2, $page->viewData('intakes'));
    }

    // ── what the auditor asked for ──────────────────────────────────────

    public function test_new_products_are_distinguished_from_top_ups(): void
    {
        // The whole shape of the question: "some new products, some already
        // existing".
        $ibrahim = $this->user(['inventory_manager']);

        $this->received('ARBITEL 80H', 20, 1100, $ibrahim, reference: 'Opening stock');
        $this->received('ASTYFER CAPS', 30, 420, $ibrahim, reference: 'Opening stock');
        $this->received('PARACETAMOL 500MG', 100, 85, $ibrahim);

        $intake = $this->page($ibrahim)->viewData('intakes')[0];

        $this->assertSame(2, $intake['newCount']);
        $this->assertCount(3, $intake['lines']);
    }

    public function test_it_totals_the_units_and_the_cost(): void
    {
        $ibrahim = $this->user(['inventory_manager']);

        $this->received('PARACETAMOL 500MG', 100, 85, $ibrahim);   // 8,500
        $this->received('ARBITEL 80H', 20, 1100, $ibrahim);        // 22,000

        $page = $this->page($ibrahim);

        $this->assertSame(120, $page->viewData('totalUnits'));
        $this->assertEquals(30500, $page->viewData('totalValue'));
    }

    public function test_who_entered_it_is_shown(): void
    {
        $ibrahim = User::factory()->create(['role' => ['inventory_manager'], 'status' => 'active', 'name' => 'IBRAHIM']);

        $this->received('PARACETAMOL 500MG', 100, 85, $ibrahim);

        $this->page($ibrahim)->assertSee('IBRAHIM');
    }

    public function test_unattributed_stock_is_surfaced_not_hidden(): void
    {
        // Stock entered before the user was recorded. The gap is itself
        // something the auditor should be told about.
        $auditor = $this->user(['auditor']);

        $this->received('PARACETAMOL 500MG', 100, 85, null);

        $page = $this->page($auditor);

        $this->assertSame(1, $page->viewData('unattributed'));
        $page->assertSee('no one recorded against it');
    }

    // ── scope ───────────────────────────────────────────────────────────

    public function test_sales_are_not_shown_as_stock_received(): void
    {
        $ibrahim = $this->user(['inventory_manager']);

        $movement = $this->received('PARACETAMOL 500MG', 100, 85, $ibrahim);
        $movement->update(['type' => 'sale', 'quantity' => -5]);

        $this->assertCount(0, $this->page($ibrahim)->viewData('intakes'));
    }

    public function test_it_can_be_narrowed_to_a_date_range(): void
    {
        $ibrahim = $this->user(['inventory_manager']);

        $this->received('PARACETAMOL 500MG', 100, 85, $ibrahim);
        $this->received('AMOXIL 500MG', 50, 260, $ibrahim, on: now()->subMonths(3)->toDateTimeString());

        $this->assertCount(1, $this->page($ibrahim)->viewData('intakes'), 'Default range is this month.');
    }

    public function test_it_can_be_searched_by_product(): void
    {
        $ibrahim = $this->user(['inventory_manager']);

        $this->received('PARACETAMOL 500MG', 100, 85, $ibrahim);
        $this->received('AMOXIL 500MG', 50, 260, $ibrahim);

        $page = $this->page($ibrahim)->set('search', 'AMOXIL');

        $this->assertCount(1, $page->viewData('intakes')[0]['lines']);
    }

    // ---- opening stock ----

    public function test_it_shows_the_first_time_each_product_was_stocked(): void
    {
        $ibrahim = $this->user(['inventory_manager']);

        // The startup load, then a top-up months later.
        $this->received('PARACETAMOL 500MG', 100, 85, $ibrahim, on: now()->subMonths(6)->toDateTimeString());
        $this->received('PARACETAMOL 500MG', 50, 90, $ibrahim);

        $opening = $this->page($ibrahim)->set('view', 'opening')->viewData('opening')->flatten();

        $this->assertCount(1, $opening, 'A product should appear once, at its first stocking.');
        $this->assertSame(100, (int) $opening->first()->quantity);
    }

    public function test_the_opening_quantity_is_what_went_in_not_what_is_left(): void
    {
        // batches.quantity is eaten into by sales; the movement that created
        // the batch still carries the number that went in.
        $ibrahim  = $this->user(['inventory_manager']);
        $movement = $this->received('PARACETAMOL 500MG', 100, 85, $ibrahim);

        $movement->batch->update(['quantity' => 12]);   // 88 sold since

        $opening = $this->page($ibrahim)->set('view', 'opening')->viewData('opening')->flatten();

        $this->assertSame(100, (int) $opening->first()->quantity);
        $this->assertSame(12, (int) $opening->first()->batch->quantity);
    }

    public function test_it_reaches_past_the_date_filter(): void
    {
        // The question is what the pharmacy started with, which is older than
        // whatever range someone happens to be looking at.
        $ibrahim = $this->user(['inventory_manager']);

        $this->received('PARACETAMOL 500MG', 100, 85, $ibrahim, on: now()->subYear()->toDateTimeString());

        $page = $this->page($ibrahim)->set('view', 'opening');

        $this->assertSame(1, $page->viewData('openingCount'));
        $this->assertSame(100, $page->viewData('openingUnits'));
    }

    public function test_it_totals_the_startup_load_at_cost(): void
    {
        $ibrahim = $this->user(['inventory_manager']);

        $this->received('PARACETAMOL 500MG', 100, 85, $ibrahim);    // 8,500
        $this->received('AMOXIL 500MG', 50, 260, $ibrahim);         // 13,000

        $page = $this->page($ibrahim)->set('view', 'opening');

        $this->assertSame(2, $page->viewData('openingCount'));
        $this->assertEquals(21500, $page->viewData('openingValue'));
    }

    public function test_products_first_stocked_later_appear_under_their_own_date(): void
    {
        // So the original startup load reads as one block.
        $ibrahim = $this->user(['inventory_manager']);

        $this->received('PARACETAMOL 500MG', 100, 85, $ibrahim, on: now()->subMonths(6)->toDateTimeString());
        $this->received('ARBITEL 80H', 20, 1100, $ibrahim);

        $this->assertCount(2, $this->page($ibrahim)->set('view', 'opening')->viewData('opening'));
    }

    public function test_sales_are_never_treated_as_opening_stock(): void
    {
        $ibrahim  = $this->user(['inventory_manager']);
        $movement = $this->received('PARACETAMOL 500MG', 100, 85, $ibrahim);
        $movement->update(['type' => 'sale', 'quantity' => -5]);

        $this->assertSame(0, $this->page($ibrahim)->set('view', 'opening')->viewData('openingCount'));
    }

    public function test_the_auditor_can_see_opening_stock(): void
    {
        $this->received('PARACETAMOL 500MG', 100, 85, $this->user(['inventory_manager']));

        $this->page($this->user(['auditor']))
            ->set('view', 'opening')
            ->assertOk()
            ->assertSee('PARACETAMOL 500MG')
            ->assertSee('Opening qty');
    }

    // ── who may look ────────────────────────────────────────────────────

    public function test_the_auditor_can_open_it(): void
    {
        $this->actingAs($this->user(['auditor']))->get(route('stock.received'))->assertOk();
    }

    public function test_a_cashier_cannot(): void
    {
        $this->actingAs($this->user(['cashier']))->get(route('stock.received'))->assertForbidden();
    }

    // ── the recording that makes it possible ────────────────────────────

    public function test_both_products_intake_paths_record_who_did_it(): void
    {
        // They wrote the constant "Initial stock" with no user_id, so a
        // delivery entered through the Products page was untraceable.
        $source = file_get_contents(app_path('Livewire/Products/Index.php'));

        $this->assertStringNotContainsString("'Initial stock'", $source);
        $this->assertStringContainsString("'reference' => 'Opening stock'", $source);
        $this->assertStringContainsString("'reference' => 'Stock intake'", $source);
    }
}
