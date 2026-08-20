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
 * POS search must find a product however staff phrase it, while never letting a
 * typo-tolerant near-miss outrank or hide a real match on a dispensing screen.
 */
class PosSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']));

        $category = Category::create(['name' => 'General']);

        foreach ([
            'Amoxicillin 500mg',
            'Paracetamol 500mg',
            'Vitamin C 1000mg',
            'Cough Syrup (Benylin)',
            'Ibuprofen 400mg',
        ] as $name) {
            $product = Product::create([
                'name' => $name, 'category_id' => $category->id,
                'selling_price' => 500, 'reorder_level' => 1,
            ]);

            Batch::create([
                'product_id' => $product->id, 'batch_number' => 'B1',
                'expiry_date' => now()->addYear(), 'cost_price' => 200, 'quantity' => 10,
            ]);
        }
    }

    private function search(string $term)
    {
        return Livewire::test(\App\Livewire\Pos\Index::class)->set('search', $term);
    }

    private function names(string $term): array
    {
        return collect($this->search($term)->viewData('products'))
            ->pluck('name')->all();
    }

    public function test_word_order_no_longer_matters(): void
    {
        // This returned nothing before the change.
        $this->assertContains('AMOXICILLIN 500MG', $this->names('500mg amoxicillin'));
        $this->assertContains('AMOXICILLIN 500MG', $this->names('amoxicillin 500mg'));
    }

    public function test_partial_words_in_any_order_match(): void
    {
        $this->assertContains('AMOXICILLIN 500MG', $this->names('500 amox'));
        $this->assertContains('AMOXICILLIN 500MG', $this->names('amox 500'));
        $this->assertContains('VITAMIN C 1000MG', $this->names('vit c'));
    }

    public function test_a_word_from_inside_the_name_matches(): void
    {
        $this->assertContains('COUGH SYRUP (BENYLIN)', $this->names('benylin'));
        $this->assertContains('COUGH SYRUP (BENYLIN)', $this->names('benylin cough'));
    }

    public function test_every_word_must_match_so_unrelated_terms_return_nothing(): void
    {
        $this->assertSame([], $this->names('amoxicillin ibuprofen'));
    }

    public function test_exact_match_is_ranked_first(): void
    {
        $names = $this->names('paracetamol');

        $this->assertNotEmpty($names);
        $this->assertSame('PARACETAMOL 500MG', $names[0]);
    }

    // ── Typo tolerance ───────────────────────────────────────────────

    public function test_a_typo_still_finds_the_product(): void
    {
        $component = $this->search('amoxicilin');   // one letter missing

        $this->assertContains('AMOXICILLIN 500MG',
            collect($component->viewData('products'))->pluck('name')->all());
    }

    public function test_typo_results_are_flagged_so_staff_look_twice(): void
    {
        $this->assertTrue($this->search('amoxicilin')->viewData('searchWasFuzzy'));
    }

    public function test_exact_results_are_never_flagged_as_fuzzy(): void
    {
        $this->assertFalse($this->search('amoxicillin')->viewData('searchWasFuzzy'));
        $this->assertFalse($this->search('500 amox')->viewData('searchWasFuzzy'));
    }

    public function test_fuzzy_never_runs_when_an_exact_match_exists(): void
    {
        // "cough" matches exactly; fuzzy must not add unrelated near-misses.
        $names = $this->names('cough');

        $this->assertSame(['COUGH SYRUP (BENYLIN)'], $names);
        $this->assertFalse($this->search('cough')->viewData('searchWasFuzzy'));
    }

    public function test_nonsense_returns_nothing_rather_than_a_wrong_drug(): void
    {
        $this->assertSame([], $this->names('zzzzzzzz'));
        $this->assertFalse($this->search('zzzzzzzz')->viewData('searchWasFuzzy'));
    }

    public function test_short_words_get_no_typo_leeway(): void
    {
        // 3 letters: a single wrong character must not silently match something.
        $this->assertSame([], $this->names('xyz'));
    }

    public function test_empty_search_lists_products(): void
    {
        $this->assertNotEmpty($this->names(''));
        $this->assertFalse($this->search('')->viewData('searchWasFuzzy'));
    }

    public function test_sku_and_barcode_are_searchable(): void
    {
        $product = Product::first();
        $product->update(['sku' => 'AMX-500', 'barcode' => '5901234123457']);

        $this->assertContains($product->fresh()->name, $this->names('AMX-500'));
        $this->assertContains($product->fresh()->name, $this->names('5901234123457'));
    }
}
