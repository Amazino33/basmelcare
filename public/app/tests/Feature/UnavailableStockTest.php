<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\FailedSearch;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * One buying list for everything the pharmacy cannot sell.
 *
 * Two kinds, kept apart because they need different action: a drug a customer
 * asked for that is not in the catalogue at all, and one that is but has run
 * out. Until now the first sort accumulated on a dashboard panel with no way
 * to clear them.
 *
 * "Got it" only clears the entry and records who. It does not create products
 * or add stock: a button that half-did either would leave the catalogue in a
 * state nobody chose.
 *
 * The mark expires by itself, which is the part worth protecting. A shortage
 * that comes back must not stay hidden behind an old tick.
 */
class UnavailableStockTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $roles): User
    {
        return User::factory()->create(['role' => $roles, 'status' => 'active']);
    }

    private function buyer(): User
    {
        return $this->user(['inventory_manager']);
    }

    private function product(string $name = 'AMOXIL 500MG', int $stock = 0): Product
    {
        $product = Product::create([
            'name'          => $name,
            'category_id'   => Category::firstOrCreate(['name' => 'General'])->id,
            'selling_price' => 500,
            'reorder_level' => 1,
        ]);

        if ($stock > 0) {
            Batch::create([
                'product_id'   => $product->id,
                'batch_number' => 'B-' . random_int(1000, 9999),
                'expiry_date'  => now()->addYear(),
                'cost_price'   => 300,
                'quantity'     => $stock,
            ]);
        }

        return $product->fresh();
    }

    private function page(?User $as = null)
    {
        return Livewire::actingAs($as ?? $this->buyer())
            ->test(\App\Livewire\Stock\Unavailable::class);
    }

    // ── what appears ────────────────────────────────────────────────────

    public function test_a_drug_asked_for_but_never_stocked_appears(): void
    {
        $this->actingAs($this->buyer());
        FailedSearch::record('INSULIN GLARGINE');

        $this->page()->assertSee('INSULIN GLARGINE');
    }

    public function test_a_product_with_no_stock_appears(): void
    {
        $this->product('AMOXIL 500MG', stock: 0);

        $this->page()->assertSee('AMOXIL 500MG');
    }

    public function test_a_product_with_stock_does_not(): void
    {
        $this->product('PARACETAMOL 500MG', stock: 40);

        $this->assertCount(0, $this->page()->viewData('outOfStock'));
    }

    public function test_the_two_kinds_are_counted_separately(): void
    {
        $this->actingAs($this->buyer());
        FailedSearch::record('INSULIN GLARGINE');
        $this->product('AMOXIL 500MG', stock: 0);

        $page = $this->page();

        $this->assertSame(1, $page->viewData('askedCount'));
        $this->assertSame(1, $page->viewData('stockCount'));
    }

    public function test_the_most_asked_for_comes_first(): void
    {
        // The strongest buying signal: somebody stood at the counter wanting it.
        $this->actingAs($this->buyer());

        FailedSearch::record('VENTOLIN INHALER');
        foreach (range(1, 4) as $i) {
            FailedSearch::record('INSULIN GLARGINE');
        }

        $this->assertSame('INSULIN GLARGINE', $this->page()->viewData('asked')->first()->term);
    }

    // ── marking it sourced ──────────────────────────────────────────────

    public function test_marking_a_request_clears_it_from_the_list(): void
    {
        $this->actingAs($this->buyer());
        FailedSearch::record('INSULIN GLARGINE');
        $miss = FailedSearch::withoutGlobalScopes()->first();

        $this->page()->call('markSearchSourced', $miss->id);

        $this->assertNotNull($miss->fresh()->sourced_at);
        $this->assertSame(0, FailedSearch::outstanding()->count());
    }

    public function test_it_records_who_marked_it(): void
    {
        $buyer = $this->buyer();
        $this->actingAs($buyer);
        FailedSearch::record('INSULIN GLARGINE');
        $miss = FailedSearch::withoutGlobalScopes()->first();

        $this->page($buyer)->call('markSearchSourced', $miss->id);

        $this->assertSame($buyer->id, $miss->fresh()->sourced_by);
    }

    public function test_marking_a_product_clears_it_from_the_list(): void
    {
        $product = $this->product('AMOXIL 500MG', stock: 0);

        $this->page()->call('markProductSourced', $product->id);

        $this->assertSame(0, Product::unsellable()->count());
    }

    public function test_marking_creates_nothing(): void
    {
        // Deliberately inert: the catalogue only changes on the pages built
        // for it, so a tick here cannot leave a half-made product behind.
        $this->actingAs($this->buyer());
        FailedSearch::record('INSULIN GLARGINE');
        $miss = FailedSearch::withoutGlobalScopes()->first();

        $before = Product::count();

        $this->page()->call('markSearchSourced', $miss->id);

        $this->assertSame($before, Product::count());
    }

    // ── the mark expires on its own ─────────────────────────────────────

    public function test_a_product_returns_to_the_list_when_it_runs_out_again(): void
    {
        // Sourced, stock arrived, sold through. It must come back, or the next
        // shortage hides behind a tick from months ago.
        $product = $this->product('AMOXIL 500MG', stock: 0);

        $this->page()->call('markProductSourced', $product->id);
        $this->assertSame(0, Product::unsellable()->count());

        // Stock arrives: the sourcing is finished.
        $batch = Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B-1',
            'expiry_date'  => now()->addYear(),
            'cost_price'   => 300,
            'quantity'     => 10,
        ]);

        $this->assertNull($product->fresh()->sourced_at, 'The mark should clear when stock arrives.');

        $batch->update(['quantity' => 0]);

        $this->assertSame(1, Product::unsellable()->count(), 'It did not come back after running out.');
    }

    public function test_a_request_returns_if_someone_asks_again(): void
    {
        $this->actingAs($this->buyer());
        FailedSearch::record('INSULIN GLARGINE');
        $miss = FailedSearch::withoutGlobalScopes()->first();

        $this->page()->call('markSearchSourced', $miss->id);
        $this->assertSame(0, FailedSearch::outstanding()->count());

        // Asked for again and still not found: whatever was sourced did not
        // settle it.
        FailedSearch::record('INSULIN GLARGINE');

        $this->assertSame(1, FailedSearch::outstanding()->count());
    }

    public function test_a_mark_can_be_undone(): void
    {
        $this->actingAs($this->buyer());
        FailedSearch::record('INSULIN GLARGINE');
        $miss = FailedSearch::withoutGlobalScopes()->first();

        $page = $this->page();
        $page->call('markSearchSourced', $miss->id);
        $page->call('undoSearch', $miss->id);

        $this->assertNull($miss->fresh()->sourced_at);
    }

    // ── who may act ─────────────────────────────────────────────────────

    public function test_sales_staff_can_see_it_but_not_mark_it(): void
    {
        // They hear the requests, so seeing the list is useful; buying is not
        // their decision.
        $this->actingAs($this->buyer());
        FailedSearch::record('INSULIN GLARGINE');
        $miss = FailedSearch::withoutGlobalScopes()->first();

        $page = $this->page($this->user(['sales']));
        $page->assertSee('INSULIN GLARGINE');
        $page->call('markSearchSourced', $miss->id);

        $this->assertNull($miss->fresh()->sourced_at);
    }

    public function test_a_cashier_cannot_reach_the_page(): void
    {
        $this->actingAs($this->user(['cashier']))
            ->get(route('stock.unavailable'))
            ->assertForbidden();
    }

    public function test_a_buyer_can_reach_the_page(): void
    {
        $this->actingAs($this->buyer())
            ->get(route('stock.unavailable'))
            ->assertOk();
    }
}
