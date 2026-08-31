<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Typing a quantity into the till.
 *
 * The box is not a form field somebody fills in once; it is edited, cleared,
 * retyped and over-typed while a customer waits. Each of those has to leave the
 * cart in a state the operator expects.
 */
class PosQuantityInputTest extends TestCase
{
    use RefreshDatabase;

    private function seller(): User
    {
        return User::factory()->create(['role' => ['sales'], 'status' => 'active']);
    }

    private function product(array $batches = [50], array $overrides = []): Product
    {
        $product = Product::create(array_merge([
            'name'          => 'PARACETAMOL ' . random_int(100, 999),
            'category_id'   => Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => 100,
            'reorder_level' => 1,
        ], $overrides));

        foreach ($batches as $i => $qty) {
            Batch::create([
                'product_id'   => $product->id,
                'batch_number' => 'B' . $i . random_int(100, 999),
                'expiry_date'  => now()->addMonths(6 + $i),
                'cost_price'   => 60,
                'quantity'     => $qty,
            ]);
        }

        return $product->fresh();
    }

    private function till(Product $product)
    {
        return Livewire::actingAs($this->seller())
            ->test(\App\Livewire\Pos\Index::class)
            ->call('addToCart', $product->id);
    }

    private function firstKey($page): string
    {
        return array_key_first($page->get('cart'));
    }

    // ── the ways a person edits the box ─────────────────────────────────

    public function test_a_quantity_can_be_typed(): void
    {
        $product = $this->product();
        $page    = $this->till($product);

        $page->call('updateQty', $this->firstKey($page), '12');

        $this->assertSame(12, collect($page->get('cart'))->sum('qty'));
    }

    public function test_clearing_the_box_does_not_throw_the_item_away(): void
    {
        // People clear the field to retype it. Losing the line at that moment
        // is the single most annoying thing a till can do, and the operator has
        // to find the product and add it again.
        $product = $this->product();
        $page    = $this->till($product);

        $page->call('updateQty', $this->firstKey($page), '');

        $this->assertNotEmpty($page->get('cart'), 'Clearing the box removed the product from the cart.');
        $this->assertSame(1, collect($page->get('cart'))->sum('qty'));
    }

    public function test_a_half_typed_zero_does_not_throw_it_away_either(): void
    {
        // Typing "10" over a selected "1" can momentarily read 0 on some
        // keyboards, and the change event can fire on it.
        $product = $this->product();
        $page    = $this->till($product);

        $page->call('updateQty', $this->firstKey($page), '0');

        $this->assertNotEmpty($page->get('cart'));
    }

    public function test_the_x_button_is_still_how_you_remove_a_line(): void
    {
        // Removing must stay deliberate, which is the other half of the rule
        // above.
        $product = $this->product();
        $page    = $this->till($product);

        $page->call('removeFromCart', $this->firstKey($page));

        $this->assertEmpty($page->get('cart'));
    }

    public function test_typing_more_than_there_is_gives_what_there_is(): void
    {
        $product = $this->product([20]);
        $page    = $this->till($product);

        $page->call('updateQty', $this->firstKey($page), '999');

        $this->assertSame(20, collect($page->get('cart'))->sum('qty'),
            'The cart took a quantity the shelf cannot cover.');
    }

    public function test_a_quantity_spanning_two_batches_is_spread_across_them(): void
    {
        $product = $this->product([15, 30]);
        $page    = $this->till($product);

        $page->call('updateQty', $this->firstKey($page), '40');

        $cart = collect($page->get('cart'));

        $this->assertSame(40, $cart->sum('qty'));
        $this->assertCount(2, $cart, 'A quantity larger than one batch stayed on one line.');
    }

    public function test_reducing_it_again_collapses_back_to_one_batch(): void
    {
        $product = $this->product([15, 30]);
        $page    = $this->till($product);

        $page->call('updateQty', $this->firstKey($page), '40');
        $page->call('updateQty', $this->firstKey($page), '5');

        $cart = collect($page->get('cart'));

        $this->assertSame(5, $cart->sum('qty'));
        $this->assertCount(1, $cart);
    }

    public function test_a_stray_character_does_not_wipe_the_line(): void
    {
        // type="number" mostly prevents this, but a paste or a phone keyboard
        // can still deliver something that is not a number.
        $product = $this->product();
        $page    = $this->till($product);

        $page->call('updateQty', $this->firstKey($page), 'abc');

        $this->assertNotEmpty($page->get('cart'));
        $this->assertSame(1, collect($page->get('cart'))->sum('qty'));
    }

    public function test_a_negative_number_is_not_treated_as_a_removal(): void
    {
        $product = $this->product();
        $page    = $this->till($product);

        $page->call('updateQty', $this->firstKey($page), '-3');

        $this->assertNotEmpty($page->get('cart'));
    }

    // ── packs ───────────────────────────────────────────────────────────

    public function test_a_pack_line_counts_packs_not_units(): void
    {
        $product = $this->product([50], [
            'has_pack' => true, 'pack_size' => 10, 'pack_price' => 900,
        ]);

        $page = $this->till($product);
        $key  = $this->firstKey($page);

        $page->call('togglePack', $key)
            ->call('updateQty', $this->firstKey($page), '3');

        $cart = collect($page->get('cart'));

        $this->assertSame(3, $cart->sum('qty'), 'The box should count packs on a pack line.');
        $this->assertSame(30, $cart->sum('units'), 'Three packs of ten is thirty units.');
    }

    public function test_clearing_a_pack_line_does_not_throw_it_away(): void
    {
        $product = $this->product([50], [
            'has_pack' => true, 'pack_size' => 10, 'pack_price' => 900,
        ]);

        $page = $this->till($product);
        $page->call('togglePack', $this->firstKey($page))
            ->call('updateQty', $this->firstKey($page), '');

        $this->assertNotEmpty($page->get('cart'));
    }

    // ── the box has to show what the cart holds ─────────────────────────

    public function test_the_box_is_redrawn_when_the_server_settles_on_another_figure(): void
    {
        // Livewire will not overwrite an input somebody has typed into, so the
        // element carries the quantity in its key: a different figure makes it
        // a different element, and the browser draws the truth. Without it the
        // cart said 20 while the box still read 999.
        $product = $this->product([20]);
        $page    = $this->till($product);

        $page->call('updateQty', $this->firstKey($page), '999');

        $key = $this->firstKey($page);

        $page->assertSee('wire:key="qty-' . $key . '-20"', false);
    }

    public function test_the_operator_is_told_what_was_short(): void
    {
        // The quantity quietly becoming smaller than what was typed would be
        // worse than refusing it.
        $product = $this->product([20]);
        $page    = $this->till($product);

        // The toast is pushed to the browser as JavaScript. What a test can
        // read is the call Livewire queued for it.
        $page->call('updateQty', $this->firstKey($page), '999');

        $queued = json_encode($page->effects['js'] ?? $page->effects ?? []);

        $this->assertStringContainsString('Short by', $queued,
            'Nothing told the operator the quantity had been cut down.');
    }
}
