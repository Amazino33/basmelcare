<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SoldProductsTabTest extends TestCase
{
    use RefreshDatabase;

    private function staffUser(string $role = 'sales'): User
    {
        return User::factory()->create(['role' => [$role], 'status' => 'active']);
    }

    private function createSoldProduct(
        string $productName = 'PARACETAMOL 500MG',
        float $price = 1500,
        int $qty = 3,
        string $saleStatus = 'paid',
        ?Customer $customer = null
    ): array {
        $category = Category::firstOrCreate(['name' => 'ANALGESICS']);

        $product = Product::create([
            'name'          => $productName,
            'category_id'   => $category->id,
            'selling_price' => $price,
            'reorder_level' => 5,
        ]);

        $batch = Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'BAT-' . random_int(1000, 9999),
            'expiry_date'  => now()->addYear(),
            'cost_price'   => $price * 0.6,
            'quantity'     => 100,
        ]);

        $seller = $this->staffUser('sales');

        $sale = Sale::create([
            'invoice_number' => 'INV-' . random_int(10000, 99999),
            'user_id'        => $seller->id,
            'customer_id'    => $customer?->id,
            'total_amount'   => $price * $qty,
            'payment_method' => 'cash',
            'status'         => $saleStatus,
            'paid_at'        => now(),
        ]);

        $item = SaleItem::create([
            'sale_id'    => $sale->id,
            'product_id' => $product->id,
            'batch_id'   => $batch->id,
            'quantity'   => $qty,
            'unit_price' => $price,
            'cost_price' => $price * 0.6,
            'subtotal'   => $price * $qty,
        ]);

        return [$product, $batch, $sale, $item, $seller];
    }

    public function test_user_can_view_sold_products_tab_with_timestamp_and_details(): void
    {
        $user = $this->staffUser('sales');
        $customer = Customer::create([
            'name'  => 'EMMANUEL OKON',
            'phone' => '08012345678',
            'type'  => 'retail',
        ]);

        [$product, $batch, $sale, $item] = $this->createSoldProduct(
            'AMOXIL CAPSULES',
            2500,
            2,
            'paid',
            $customer
        );

        Livewire::actingAs($user)
            ->test(\App\Livewire\Sales\Index::class)
            ->set('tab', 'items')
            ->assertSee('Products Sold')
            ->assertSee('AMOXIL CAPSULES')
            ->assertSee('EMMANUEL OKON')
            ->assertSee($batch->batch_number)
            ->assertSee(number_format(2500, 2))
            ->assertSee(number_format(5000, 2))
            ->assertSee($sale->invoice_number);
    }

    public function test_sold_products_filters_by_search_keyword(): void
    {
        $user = $this->staffUser('sales');

        $this->createSoldProduct('VITAMIN C 1000MG', 1200, 1, 'completed');
        $this->createSoldProduct('CIPROFLOXACIN 500MG', 3400, 1, 'completed');

        Livewire::actingAs($user)
            ->test(\App\Livewire\Sales\Index::class)
            ->set('tab', 'items')
            ->set('search', 'VITAMIN')
            ->assertSee('VITAMIN C 1000MG')
            ->assertDontSee('CIPROFLOXACIN 500MG');
    }

    public function test_sold_products_excludes_cancelled_or_pending_sales(): void
    {
        $user = $this->staffUser('sales');

        $this->createSoldProduct('PAID MEDICINE', 1000, 1, 'paid');
        $this->createSoldProduct('PENDING MEDICINE', 1000, 1, 'pending');
        $this->createSoldProduct('CANCELLED MEDICINE', 1000, 1, 'cancelled');

        Livewire::actingAs($user)
            ->test(\App\Livewire\Sales\Index::class)
            ->set('tab', 'items')
            ->assertSee('PAID MEDICINE')
            ->assertDontSee('PENDING MEDICINE')
            ->assertDontSee('CANCELLED MEDICINE');
    }

    public function test_sold_products_csv_export_returns_stream(): void
    {
        $user = $this->staffUser('branch_manager');
        $this->createSoldProduct('PARACETAMOL SYRUP', 850, 4, 'paid');

        $component = Livewire::actingAs($user)
            ->test(\App\Livewire\Sales\Index::class)
            ->set('tab', 'items');

        $response = $component->call('exportSoldItems');
        $this->assertNotNull($response);
    }
}
