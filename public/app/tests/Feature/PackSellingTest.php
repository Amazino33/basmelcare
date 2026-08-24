<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Selling by the pack as well as by the tablet.
 *
 * sale_items.quantity is ALWAYS in units. A pack is recorded by is_pack and
 * pack_size alongside it, purely so a receipt can say "2 packs of 10".
 *
 * That is the whole design, and it exists because of how this failed before.
 * A previous attempt (a01dcb4, reverted in c9aa47b) redefined quantity to mean
 * packs while leaving cost_price per tablet. Profit is computed everywhere as
 * subtotal minus cost_price times quantity, so a pack of ten reported roughly
 * ten times too little cost - and nearly three times the profit it made.
 */
class PackSellingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::set('wholesale_markup_percent', 5);
        Product::forgetDefaultMarkup();
    }

    /** Tablets cost 10, sell singly at 20, and by the card of 10 at 150. */
    private function product(array $attributes = [], float $cost = 10, int $stock = 500): Product
    {
        $product = Product::create(array_merge([
            'name'          => 'PARACETAMOL 500MG',
            'category_id'   => Category::firstOrCreate(['name' => 'General'])->id,
            'selling_price' => 20,
            'reorder_level' => 1,
            'has_pack'      => true,
            'pack_size'     => 10,
            'pack_price'    => 150,
        ], $attributes));

        Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B-' . random_int(1000, 9999),
            'expiry_date'  => now()->addYear(),
            'cost_price'   => $cost,
            'quantity'     => $stock,
        ]);

        return $product->fresh();
    }

    private function customer(string $type): Customer
    {
        return Customer::create([
            'name'  => strtoupper($type),
            'type'  => $type,
            'phone' => '080' . random_int(10000000, 99999999),
        ]);
    }

    private function till()
    {
        return Livewire::actingAs(User::factory()->create(['role' => ['cashier'], 'status' => 'active']))
            ->test(\App\Livewire\Pos\Index::class);
    }

    private function line(array $cart): array
    {
        return reset($cart);
    }

    // ── pricing ─────────────────────────────────────────────────────────

    public function test_a_retail_pack_costs_less_than_the_same_tablets_loose(): void
    {
        $product = $this->product();
        $retail  = $this->customer('retail');

        $this->assertSame(150.0, $product->packPriceFor($retail));
        $this->assertSame(200.0, $product->getPriceFor($retail, 10) * 10);
    }

    public function test_a_wholesaler_pays_the_same_whether_they_buy_packs_or_loose(): void
    {
        // Wholesale is derived per unit and scales on its own, so a pack is
        // only a faster way of entering ten tablets - never a worse deal.
        $product   = $this->product();
        $wholesale = $this->customer('wholesale');

        $this->assertSame(105.0, $product->packPriceFor($wholesale));
        $this->assertSame(105.0, $product->getPriceFor($wholesale, 10) * 10);
    }

    public function test_a_pack_priced_below_cost_does_not_leak_into_wholesale(): void
    {
        // 10 tablets cost 100; this pack is priced at 80. A retail buyer gets
        // that mistake, but wholesale must stay above cost.
        $product = $this->product(['pack_price' => 80]);

        $this->assertSame(80.0, $product->packPriceFor($this->customer('retail')));
        $this->assertSame(105.0, $product->packPriceFor($this->customer('wholesale')));
    }

    public function test_a_product_without_pack_pricing_has_no_pack_price(): void
    {
        $product = $this->product(['has_pack' => false]);

        $this->assertFalse($product->sellsInPacks());
        $this->assertNull($product->packPriceFor($this->customer('retail')));
    }

    public function test_a_pack_of_one_is_not_a_pack(): void
    {
        $this->assertFalse($this->product(['pack_size' => 1])->sellsInPacks());
    }

    // ── the till ────────────────────────────────────────────────────────

    public function test_a_line_starts_as_loose_units(): void
    {
        $product = $this->product();

        $cart = $this->till()->call('addToCart', $product->id)->get('cart');
        $line = $this->line($cart);

        $this->assertFalse($line['is_pack']);
        $this->assertSame(1, $line['units']);
        $this->assertSame(20.0, $line['subtotal']);
    }

    public function test_switching_to_packs_reprices_and_recounts(): void
    {
        $product = $this->product();

        $component = $this->till()->call('addToCart', $product->id);
        $key = array_key_first($component->get('cart'));

        $line = $this->line($component->call('togglePack', $key)->get('cart'));

        $this->assertTrue($line['is_pack']);
        $this->assertSame(1, $line['qty'], 'Quantity should reset, not carry across.');
        $this->assertSame(10, $line['units']);
        $this->assertSame(150.0, $line['subtotal']);
    }

    public function test_quantity_resets_rather_than_converting(): void
    {
        // "3" meaning three tablets and "3" meaning three packs are different
        // enough orders that carrying the number across sells thirty by
        // accident.
        $product = $this->product();

        $component = $this->till()->call('addToCart', $product->id);
        $key = array_key_first($component->get('cart'));

        $line = $this->line(
            $component->call('updateQty', $key, 3)->call('togglePack', $key)->get('cart')
        );

        $this->assertSame(1, $line['qty']);
    }

    public function test_the_quantity_limit_follows_the_unit_being_sold(): void
    {
        $product = $this->product(stock: 35);

        $component = $this->till()->call('addToCart', $product->id);
        $key = array_key_first($component->get('cart'));

        $this->assertSame(35, $this->line($component->get('cart'))['max_qty']);

        // 35 tablets is three whole packs, not three and a half.
        $line = $this->line($component->call('togglePack', $key)->get('cart'));
        $this->assertSame(3, $line['max_qty']);
    }

    public function test_packs_are_refused_when_there_is_not_a_full_one(): void
    {
        $product = $this->product(stock: 4);

        $component = $this->till()->call('addToCart', $product->id);
        $key = array_key_first($component->get('cart'));

        $line = $this->line($component->call('togglePack', $key)->get('cart'));

        $this->assertFalse($line['is_pack'], 'Sold a pack that cannot be made up from stock.');
    }

    public function test_toggling_back_returns_to_loose_pricing(): void
    {
        $product = $this->product();

        $component = $this->till()->call('addToCart', $product->id);
        $key = array_key_first($component->get('cart'));

        $line = $this->line(
            $component->call('togglePack', $key)->call('togglePack', $key)->get('cart')
        );

        $this->assertFalse($line['is_pack']);
        $this->assertSame(1, $line['units']);
        $this->assertSame(20.0, $line['subtotal']);
    }

    // ── what gets recorded, which is the part that broke last time ──────

    private function sellOnePack(Product $product): SaleItem
    {
        $component = $this->till()->call('addToCart', $product->id);
        $key = array_key_first($component->get('cart'));

        $component->call('togglePack', $key)->call('createInvoice');

        return SaleItem::firstOrFail();
    }

    public function test_quantity_is_recorded_in_units_not_packs(): void
    {
        $item = $this->sellOnePack($this->product());

        $this->assertSame(10, (int) $item->quantity, 'quantity must be units, or every profit figure breaks.');
        $this->assertTrue($item->is_pack);
        $this->assertSame(10, (int) $item->pack_size);
    }

    public function test_profit_on_a_pack_sale_is_correct(): void
    {
        // The defect that sank the previous attempt. Pack sells for 150,
        // ten tablets cost 10 each, so the profit is 50 - not 140.
        $item = $this->sellOnePack($this->product());

        $profit = (float) $item->subtotal - ((float) $item->cost_price * (int) $item->quantity);

        $this->assertSame(150.0, (float) $item->subtotal);
        $this->assertSame(50.0, $profit);
    }

    public function test_the_dashboard_reports_pack_profit_correctly(): void
    {
        // Same arithmetic, but through the query the dashboard actually runs.
        $this->sellOnePack($this->product());
        Sale::query()->update(['status' => 'completed']);

        $row = DB::table('sale_items')
            ->selectRaw('SUM(subtotal) AS revenue, SUM(subtotal - cost_price * quantity) AS profit')
            ->first();

        $this->assertSame(150.0, (float) $row->revenue);
        $this->assertSame(50.0, (float) $row->profit);
    }

    public function test_stock_is_deducted_in_units(): void
    {
        $product = $this->product(stock: 100);

        $this->sellOnePack($product);

        $this->assertSame(90, (int) Batch::where('product_id', $product->id)->value('quantity'));
    }

    public function test_a_stock_movement_records_the_units(): void
    {
        $product = $this->product(stock: 100);
        $this->sellOnePack($product);

        $this->assertDatabaseHas('stock_movements', ['quantity' => -10, 'type' => 'sale']);
    }

    public function test_cancelling_restores_the_units(): void
    {
        $product = $this->product(stock: 100);
        $item    = $this->sellOnePack($product);

        Livewire::actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']))
            ->test(\App\Livewire\Pos\Index::class)
            ->call('cancelInvoice', $item->sale_id);

        $this->assertSame(100, (int) Batch::where('product_id', $product->id)->value('quantity'));
    }

    public function test_the_receipt_describes_the_pack(): void
    {
        $item = $this->sellOnePack($this->product());

        $this->actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']))
            ->get(route('invoice.show', $item->sale_id))
            ->assertOk()
            ->assertSee('pack of 10');
    }

    // ── money does not drift ────────────────────────────────────────────

    public function test_a_pack_price_that_does_not_divide_evenly_still_totals_exactly(): void
    {
        // 150 over 7 is 21.428... Charging quantity times a rounded unit price
        // would bill 149.94 or 150.01 instead of the 150 that was quoted.
        $product = $this->product(['pack_size' => 7, 'pack_price' => 150]);

        $item = $this->sellOnePack($product);

        $this->assertSame(150.0, (float) $item->subtotal);
        $this->assertSame(7, (int) $item->quantity);
    }
}
