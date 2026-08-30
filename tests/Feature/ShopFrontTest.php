<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The shop front.
 *
 * Every rail on it is driven by what the pharmacy actually has and has
 * actually sold. A hand-written list of featured products would go stale in a
 * week and nobody would notice; these cannot, because there is nothing to
 * maintain.
 */
class ShopFrontTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name, array $overrides = []): Product
    {
        $product = Product::create(array_merge([
            'name'          => $name,
            'category_id'   => Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => 1000,
            'reorder_level' => 2,
            'show_in_shop'  => true,
        ], $overrides));

        Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B' . random_int(1000, 9999),
            'expiry_date'  => now()->addYear(),
            'cost_price'   => 600,
            'quantity'     => $overrides['stock'] ?? 20,
        ]);

        return $product->fresh();
    }

    /** Record that a product sold this many units. */
    private function sold(Product $product, int $units): void
    {
        $sale = Sale::create([
            'invoice_number' => 'INV-' . random_int(100000, 999999),
            'user_id'        => User::factory()->create()->id,
            'total_amount'   => $units * 1000,
            'payment_method' => 'cash',
            'status'         => 'paid',
            'paid_at'        => now(),
        ]);

        SaleItem::create([
            'sale_id'    => $sale->id,
            'product_id' => $product->id,
            'batch_id'   => $product->batches->first()->id,
            'quantity'   => $units,
            'unit_price' => 1000,
            'cost_price' => 600,
            'subtotal'   => $units * 1000,
        ]);
    }

    private function home()
    {
        return Livewire::test(\App\Livewire\Shop\Home::class);
    }

    // ── the rails are real ──────────────────────────────────────────────

    public function test_best_sellers_are_ordered_by_what_actually_sold(): void
    {
        $quiet   = $this->product('QUIET PRODUCT');
        $popular = $this->product('POPULAR PRODUCT');

        $this->sold($quiet, 2);
        $this->sold($popular, 40);

        $rail = $this->home()->viewData('bestSellers');

        $this->assertSame($popular->id, $rail->first()->id);
    }

    public function test_best_sellers_count_units_not_orders(): void
    {
        // A box of thirty must not rank below a single tube bought three times.
        $box   = $this->product('BOX OF THIRTY');
        $tube  = $this->product('SINGLE TUBE');

        $this->sold($box, 30);
        $this->sold($tube, 1);
        $this->sold($tube, 1);
        $this->sold($tube, 1);

        $this->assertSame($box->id, $this->home()->viewData('bestSellers')->first()->id);
    }

    public function test_a_shop_that_has_sold_nothing_shows_its_newest_stock(): void
    {
        // Opening week. An empty rail would say the pharmacy has nothing.
        $this->product('BRAND NEW');

        $this->assertCount(1, $this->home()->viewData('bestSellers'));
    }

    public function test_products_hidden_from_the_shop_stay_hidden(): void
    {
        $this->product('PUBLIC PRODUCT');
        $hidden = $this->product('HIDDEN PRODUCT', ['show_in_shop' => false]);

        $this->sold($hidden, 99);   // best seller at the counter, not online

        $page = $this->home();

        $page->assertDontSee('Hidden Product');
        $this->assertFalse($page->viewData('bestSellers')->contains('id', $hidden->id));
    }

    public function test_categories_with_nothing_on_sale_are_not_offered(): void
    {
        // A tab leading to an empty shelf is a dead end.
        $this->product('IN STOCK PRODUCT');
        Category::create(['name' => 'EMPTY SHELF']);

        $names = $this->home()->viewData('categories')->pluck('name');

        $this->assertFalse($names->contains('EMPTY SHELF'));
    }

    public function test_category_rails_are_named_after_real_categories(): void
    {
        $vitamins = Category::create(['name' => 'VITAMINS & SUPPLEMENTS']);
        $this->product('VITAMIN C', ['category_id' => $vitamins->id]);

        $rails = $this->home()->viewData('rails');

        $this->assertTrue(
            collect($rails)->contains(fn ($rail) => $rail['category']->id === $vitamins->id)
        );
    }

    // ── nothing invented ────────────────────────────────────────────────

    public function test_no_star_ratings_are_shown(): void
    {
        // The design had them; this app records no reviews. A rating with
        // nothing behind it is a claim about a medicine that nobody made.
        $this->product('PARACETAMOL');

        $html = $this->home()->html();

        foreach (['★', 'o-star', 'rating-'] as $fabricated) {
            $this->assertStringNotContainsString($fabricated, $html,
                'A star rating was rendered with no review data behind it.');
        }
    }

    // ── the basket button ───────────────────────────────────────────────

    public function test_a_product_can_be_added_straight_from_the_front(): void
    {
        $product = $this->product('PARACETAMOL');

        $this->home()->call('addToCart', $product->id);

        $this->assertSame(1, (new CartService)->count());
    }

    public function test_it_can_be_added_from_the_shop_grid_too(): void
    {
        $product = $this->product('PARACETAMOL');

        Livewire::test(\App\Livewire\Shop\Index::class)->call('addToCart', $product->id);

        $this->assertSame(1, (new CartService)->count());
    }

    public function test_something_out_of_stock_cannot_be_added(): void
    {
        // The button is not drawn for it, but the action is what decides.
        $product = $this->product('SOLD OUT', ['stock' => 0]);

        $this->home()->call('addToCart', $product->id);

        $this->assertSame(0, (new CartService)->count());
    }

    public function test_a_product_hidden_from_the_shop_cannot_be_added(): void
    {
        $hidden = $this->product('HIDDEN PRODUCT', ['show_in_shop' => false]);

        $this->home()->call('addToCart', $hidden->id);

        $this->assertSame(0, (new CartService)->count());
    }

    // ── the shell every page wears ──────────────────────────────────────

    public function test_the_header_search_goes_to_the_shop(): void
    {
        $this->product('PARACETAMOL');
        $this->product('IBUPROFEN');

        $this->get(route('shop.index', ['search' => 'PARACETAMOL']))
            ->assertOk()
            ->assertSee('Paracetamol')
            ->assertDontSee('Ibuprofen');
    }

    public function test_every_public_page_wears_the_same_shell(): void
    {
        // One layout, two entry points - a Livewire page and a plain Blade
        // view. They used to be separate copies and had already drifted.
        $this->product('PARACETAMOL');

        foreach ([route('home'), route('shop.index'), route('consultation.book'), route('customer.login')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('Explore All', $html, "No category strip on {$url}");
            $this->assertStringContainsString('Prescription Dispensing', $html, "No service band on {$url}");
        }
    }

    public function test_the_consultation_link_is_reachable_from_every_page(): void
    {
        // It existed in one copy of the layout and not the other, so half the
        // site had no way to reach it.
        $this->get(route('customer.login'))
            ->assertOk()
            ->assertSee(route('consultation.book'));
    }
}
