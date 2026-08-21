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

/**
 * Clicking an invoice opens its full detail, so the auditor can check where a
 * figure came from without leaving the page - line by line, with cost and
 * margin, and how the money was taken.
 */
class InvoiceDetailDrawerTest extends TestCase
{
    use RefreshDatabase;

    private function auditor(): User
    {
        return User::factory()->create(['role' => ['auditor'], 'status' => 'active']);
    }

    private function sell(
        float $price = 1000,
        float $cost = 600,
        int $qty = 1,
        ?array $paymentDetails = ['cash' => 1000],
        string $status = 'completed',
        float $discount = 0,
    ): Sale {
        $product = Product::create([
            'name' => 'PARACETAMOL 500MG',
            'category_id' => Category::firstOrCreate(['name' => 'General'])->id,
            'selling_price' => $price, 'reorder_level' => 1,
        ]);

        $batch = Batch::create([
            'product_id' => $product->id, 'batch_number' => 'B-' . random_int(1000, 9999),
            'expiry_date' => now()->addYear(), 'cost_price' => $cost, 'quantity' => 50,
        ]);

        $seller  = User::factory()->create(['name' => 'Idara Sales', 'role' => ['sales']]);
        $cashier = User::factory()->create(['name' => 'Bola Cashier', 'role' => ['cashier']]);

        $sale = Sale::create([
            'invoice_number'  => 'INV-20260820-0001-ABC',
            'user_id'         => $seller->id,
            'cashier_id'      => $cashier->id,
            'customer_id'     => Customer::create([
                'name' => 'Aisha Bello', 'type' => 'retail', 'phone' => '0803' . random_int(1000000, 9999999),
            ])->id,
            'total_amount'    => $price * $qty,
            'coupon_discount' => $discount,
            'status'          => $status,
            'payment_details' => $paymentDetails,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id, 'batch_id' => $batch->id,
            'quantity' => $qty, 'unit_price' => $price,
            'cost_price' => $cost, 'subtotal' => $price * $qty,
        ]);

        return $sale;
    }

    private function open(Sale $sale)
    {
        return Livewire::actingAs($this->auditor())
            ->test(\App\Livewire\Finance\Index::class)
            ->call('viewSale', $sale->id);
    }

    public function test_clicking_an_invoice_opens_it(): void
    {
        $sale = $this->sell();

        $this->open($sale)
            ->assertSet('saleDrawer', true)
            ->assertSet('viewSaleId', $sale->id);
    }

    public function test_the_rows_are_clickable(): void
    {
        $sale = $this->sell();

        $html = Livewire::actingAs($this->auditor())
            ->test(\App\Livewire\Finance\Index::class)->html();

        $this->assertStringContainsString('viewSale(' . $sale->id . ')', $html);
    }

    public function test_it_shows_each_item_with_its_cost_and_profit(): void
    {
        $sale = $this->sell(price: 1000, cost: 600);

        $html = $this->open($sale)->html();

        $this->assertStringContainsString('PARACETAMOL 500MG', $html);
        $this->assertStringContainsString('600.00', $html);   // cost per unit
        $this->assertStringContainsString('400.00', $html);   // profit on the line
    }

    public function test_it_shows_who_sold_it_and_who_took_the_money(): void
    {
        $sale = $this->sell();

        $html = $this->open($sale)->html();

        $this->assertStringContainsString('Idara Sales', $html);
        $this->assertStringContainsString('Bola Cashier', $html);
        $this->assertStringContainsString('AISHA BELLO', $html);
    }

    public function test_it_shows_the_coupon_discount_in_the_total(): void
    {
        $sale = $this->sell(price: 1000, cost: 600, discount: 150);

        $html = $this->open($sale)->html();

        $this->assertStringContainsString('Coupon discount', $html);
        $this->assertStringContainsString('150.00', $html);
        // 1000 - 150 = 850 paid, minus 600 cost = 250 profit.
        $this->assertStringContainsString('850.00', $html);
        $this->assertStringContainsString('250.00', $html);
    }

    public function test_it_shows_how_the_money_was_taken(): void
    {
        $sale = $this->sell(paymentDetails: ['cash' => 400, 'transfer' => 600]);

        $html = $this->open($sale)->html();

        $this->assertStringContainsString('Cash', $html);
        $this->assertStringContainsString('Transfer', $html);
        $this->assertStringContainsString('400.00', $html);
        $this->assertStringContainsString('600.00', $html);
    }

    public function test_store_credit_is_labelled_as_not_new_money(): void
    {
        $sale = $this->sell(paymentDetails: ['cash' => 800, 'credit' => 200]);

        $html = $this->open($sale)->html();

        $this->assertStringContainsString('Store credit used', $html);
        $this->assertStringContainsString('not new money', $html);
    }

    public function test_a_sale_with_no_breakdown_says_so(): void
    {
        $sale = $this->sell(paymentDetails: null);

        $html = $this->open($sale)->html();

        $this->assertStringContainsString('No payment breakdown was recorded', $html);
        $this->assertStringContainsString('Method not recorded', $html);
    }

    public function test_a_cancelled_invoice_says_it_does_not_count(): void
    {
        $sale = $this->sell(status: 'cancelled');

        $html = $this->open($sale)->html();

        $this->assertStringContainsString('none of it counts towards the totals', $html);
        $this->assertStringContainsString('This invoice is cancelled', $html);
    }

    public function test_closing_clears_the_drawer(): void
    {
        $sale = $this->sell();

        $this->open($sale)
            ->call('closeSale')
            ->assertSet('saleDrawer', false)
            ->assertSet('viewSaleId', null);
    }

    public function test_the_drawer_is_read_only(): void
    {
        $sale = $this->sell();

        $html = $this->open($sale)->html();

        // Nothing in here may change the sale.
        foreach (['openReturn', 'processReturn', 'revokeWifi', 'completeHandover'] as $writeAction) {
            $this->assertStringNotContainsString($writeAction, $html,
                "The auditor's invoice drawer offered {$writeAction}.");
        }
    }
}
