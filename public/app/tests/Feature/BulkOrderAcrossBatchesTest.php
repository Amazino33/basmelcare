<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A wholesale order routinely exceeds one delivery.
 *
 * A cart line is tied to a single batch, so asking for 200 tablets when the
 * batch expiring soonest holds 50 used to be refused outright - with 500 more
 * sitting on the shelf behind it. The till now fills across batches, earliest
 * expiry first, so old stock moves before new.
 *
 * Two things have to stay true while it does:
 *
 *   - each line keeps ITS OWN batch cost, because profit is subtotal minus
 *     cost_price times quantity, per line
 *   - whether wholesale applies is judged on the WHOLE order, not on what
 *     landed on one line, or splitting an order would lose the discount
 */
class BulkOrderAcrossBatchesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::set('wholesale_markup_percent', 5);
        Product::forgetDefaultMarkup();
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name'          => 'PARACETAMOL 500MG',
            'category_id'   => Category::firstOrCreate(['name' => 'General'])->id,
            'selling_price' => 20,
            'reorder_level' => 1,
        ], $attributes));
    }

    private function batch(Product $product, string $number, float $cost, int $qty, int $monthsToExpiry): Batch
    {
        return Batch::create([
            'product_id'   => $product->id,
            'batch_number' => $number,
            'expiry_date'  => now()->addMonths($monthsToExpiry),
            'cost_price'   => $cost,
            'quantity'     => $qty,
        ]);
    }

    /** 50 units expiring soon at cost 8, then 500 later at cost 10. */
    private function stockedProduct(array $attributes = []): Product
    {
        $product = $this->product($attributes);
        $this->batch($product, 'SOON', 8, 50, 1);
        $this->batch($product, 'LATER', 10, 500, 24);

        return $product->fresh();
    }

    private function wholesaler(): Customer
    {
        return Customer::create([
            'name'  => 'MUSA WHOLESALE',
            'type'  => 'wholesale',
            'phone' => '080' . random_int(10000000, 99999999),
        ]);
    }

    private function till(?Customer $customer = null)
    {
        $component = Livewire::actingAs(User::factory()->create(['role' => ['cashier'], 'status' => 'active']))
            ->test(\App\Livewire\Pos\Index::class);

        return $customer ? $component->call('selectCustomer', $customer->id) : $component;
    }

    private function unitsIn(array $cart): int
    {
        return array_sum(array_column($cart, 'units'));
    }

    // ── filling across batches ──────────────────────────────────────────

    public function test_an_order_larger_than_one_batch_is_filled_from_the_next(): void
    {
        $product = $this->stockedProduct();

        $component = $this->till($this->wholesaler())->call('addToCart', $product->id);
        $key = array_key_first($component->get('cart'));

        $cart = $component->call('updateQty', $key, 200)->get('cart');

        $this->assertCount(2, $cart, 'The order was not spread across batches.');
        $this->assertSame(200, $this->unitsIn($cart));
    }

    public function test_the_batch_expiring_soonest_is_used_first(): void
    {
        $product = $this->stockedProduct();

        $component = $this->till($this->wholesaler())->call('addToCart', $product->id);
        $key = array_key_first($component->get('cart'));

        $cart = collect($component->call('updateQty', $key, 200)->get('cart'))->keyBy('batch_number');

        $this->assertSame(50, $cart['SOON']['units'], 'Old stock should go out first.');
        $this->assertSame(150, $cart['LATER']['units']);
    }

    public function test_each_line_keeps_its_own_batch_cost(): void
    {
        // Copying the first batch's cost across would misstate the margin on
        // everything that came from a different delivery.
        $product = $this->stockedProduct();

        $component = $this->till($this->wholesaler())->call('addToCart', $product->id);
        $key = array_key_first($component->get('cart'));

        $cart = collect($component->call('updateQty', $key, 200)->get('cart'))->keyBy('batch_number');

        $this->assertEquals(8, $cart['SOON']['cost_price']);
        $this->assertEquals(10, $cart['LATER']['cost_price']);
    }

    public function test_raising_the_request_again_grows_the_batches_already_in_use(): void
    {
        // The first attempt only opened untouched batches, so a second, larger
        // request could not grow past what was already allocated.
        $product = $this->stockedProduct();

        $component = $this->till($this->wholesaler())->call('addToCart', $product->id);
        $key = array_key_first($component->get('cart'));

        $component->call('updateQty', $key, 200);
        $cart = $component->call('updateQty', $key, 400)->get('cart');

        $this->assertSame(400, $this->unitsIn($cart));
    }

    public function test_asking_for_more_than_exists_takes_everything_and_says_so(): void
    {
        $product = $this->stockedProduct();   // 550 in total

        $component = $this->till($this->wholesaler())->call('addToCart', $product->id);
        $key = array_key_first($component->get('cart'));

        $cart = $component->call('updateQty', $key, 900)->get('cart');

        $this->assertSame(550, $this->unitsIn($cart), 'Should sell everything on the shelf.');
    }

    public function test_tapping_the_product_past_a_full_batch_moves_to_the_next(): void
    {
        $product = $this->product();
        $this->batch($product, 'TINY', 8, 1, 1);
        $this->batch($product, 'NEXT', 10, 5, 24);

        $component = $this->till()->call('addToCart', $product->id);
        $cart = $component->call('addToCart', $product->id)->get('cart');

        $this->assertCount(2, $cart, 'The till stopped at the first batch.');
        $this->assertSame(2, $this->unitsIn($cart));
    }

    // ── pricing across the split ────────────────────────────────────────

    public function test_a_wholesaler_pays_one_price_across_every_batch(): void
    {
        $product = $this->stockedProduct();

        $component = $this->till($this->wholesaler())->call('addToCart', $product->id);
        $key = array_key_first($component->get('cart'));

        $cart = $component->call('updateQty', $key, 200)->get('cart');

        foreach ($cart as $line) {
            $this->assertSame(10.50, $line['unit_price'],
                'The customer was charged different prices for the same drug.');
        }

        $this->assertSame(2100.0, array_sum(array_column($cart, 'subtotal')));
    }

    public function test_splitting_an_order_does_not_lose_a_bulk_discount(): void
    {
        // A retail buyer qualifies at 100 units. Asking for 200 splits into
        // 50 and 150 - and judging each line alone would put the first back
        // to retail price purely because stock arrived in two deliveries.
        $product = $this->stockedProduct(['wholesale_min_qty' => 100]);

        $component = $this->till()->call('addToCart', $product->id);
        $key = array_key_first($component->get('cart'));

        $cart = $component->call('updateQty', $key, 200)->get('cart');

        foreach ($cart as $line) {
            $this->assertSame(10.50, $line['unit_price'],
                'A split order was priced per line instead of on the whole order.');
        }
    }

    // ── what reaches the invoice ────────────────────────────────────────

    public function test_the_invoice_records_a_line_per_batch_with_correct_profit(): void
    {
        $product = $this->stockedProduct();

        $component = $this->till($this->wholesaler())->call('addToCart', $product->id);
        $key = array_key_first($component->get('cart'));

        $component->call('updateQty', $key, 200)->call('createInvoice');

        $items = SaleItem::orderBy('cost_price')->get();

        $this->assertCount(2, $items);

        // 50 x 10.50 = 525, cost 50 x 8  = 400  -> 125
        $this->assertSame(125.0, (float) $items[0]->subtotal - (float) $items[0]->cost_price * $items[0]->quantity);
        // 150 x 10.50 = 1575, cost 150 x 10 = 1500 -> 75
        $this->assertSame(75.0, (float) $items[1]->subtotal - (float) $items[1]->cost_price * $items[1]->quantity);
    }

    public function test_stock_comes_off_the_right_batches(): void
    {
        $product = $this->stockedProduct();

        $component = $this->till($this->wholesaler())->call('addToCart', $product->id);
        $key = array_key_first($component->get('cart'));

        $component->call('updateQty', $key, 200)->call('createInvoice');

        $this->assertSame(0, (int) Batch::where('batch_number', 'SOON')->value('quantity'));
        $this->assertSame(350, (int) Batch::where('batch_number', 'LATER')->value('quantity'));
    }

    // ── packs across batches ────────────────────────────────────────────

    public function test_packs_are_only_made_up_from_batches_that_hold_a_whole_one(): void
    {
        // A pack is a sealed card; it cannot be assembled from two deliveries.
        $product = $this->product(['has_pack' => true, 'pack_size' => 10, 'pack_price' => 150]);
        $this->batch($product, 'PART', 8, 4, 1);     // not even one pack
        $this->batch($product, 'FULL', 10, 100, 24); // ten packs

        $component = $this->till()->call('addToCart', $product->id);
        $key = array_key_first($component->get('cart'));

        $cart = $component->call('togglePack', $key)->get('cart');

        foreach ($cart as $line) {
            if (! $line['is_pack']) {
                continue;   // sold loose, which is always allowed
            }

            $this->assertGreaterThanOrEqual(
                $line['pack_size'],
                (int) Batch::find($line['batch_id'])->quantity + $line['units'],
                'Sold a pack from a batch that cannot make one up.'
            );
        }
    }
}
