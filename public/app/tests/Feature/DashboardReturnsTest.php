<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardReturnsTest extends TestCase
{
    use RefreshDatabase;

    private function manager(?Branch $branch = null): User
    {
        return User::factory()->create([
            'role'      => ['branch_manager'],
            'status'    => 'active',
            'branch_id' => $branch?->id,
        ]);
    }

    private function productAndBatch(float $sellingPrice = 10000, float $costPrice = 6000): array
    {
        $category = Category::firstOrCreate(['name' => 'General']);
        $product  = Product::create([
            'name'          => 'Paracetamol 500mg',
            'category_id'   => $category->id,
            'selling_price' => $sellingPrice,
            'reorder_level' => 5,
        ]);
        $batch = Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'BATCH-001',
            'quantity'     => 100,
            'cost_price'   => $costPrice,
            'expiry_date'  => now()->addYear(),
        ]);

        return [$product, $batch];
    }

    private function createSaleWithItem(User $user, ?Branch $branch = null, int $qty = 1, float $price = 10000, float $cost = 6000): array
    {
        [$product, $batch] = $this->productAndBatch($price, $cost);

        $sale = Sale::create([
            'invoice_number' => 'INV-' . uniqid(),
            'user_id'        => $user->id,
            'branch_id'      => $branch?->id ?? $user->branch_id,
            'total_amount'   => $price * $qty,
            'payment_method' => 'cash',
            'status'         => 'paid',
            'paid_at'        => now(),
            'payment_details' => ['cash' => $price * $qty],
        ]);

        $item = SaleItem::create([
            'sale_id'     => $sale->id,
            'product_id'  => $product->id,
            'batch_id'    => $batch->id,
            'quantity'    => $qty,
            'unit_price'  => $price,
            'cost_price'  => $cost,
            'subtotal'    => $price * $qty,
        ]);

        return [$sale, $item, $product, $batch];
    }

    public function test_cash_refund_deducts_from_dashboard_cash_collected_and_profit(): void
    {
        $branch  = Branch::create(['name' => 'MAIN BRANCH', 'is_main' => true]);
        $manager = $this->manager($branch);

        [$sale, $item, $product, $batch] = $this->createSaleWithItem($manager, $branch, 2, 5000, 3000); // 10,000 total, 6,000 cost

        // Create approved return of 1 item (5000 refund, 3000 cost restocked)
        $return = SaleReturn::create([
            'sale_id'       => $sale->id,
            'processed_by'  => $manager->id,
            'total_credit'  => 5000,
            'refund_method' => SaleReturn::CASH,
            'status'        => SaleReturn::STATUS_APPROVED,
            'approved_by'   => $manager->id,
            'approved_at'   => now(),
            'refunded_at'   => now(),
        ]);

        SaleReturnItem::create([
            'sale_return_id'    => $return->id,
            'sale_item_id'      => $item->id,
            'product_id'        => $product->id,
            'batch_id'          => $batch->id,
            'quantity_returned' => 1,
            'unit_price'        => 5000,
            'subtotal'          => 5000,
        ]);

        $page = Livewire::actingAs($manager)->test(\App\Livewire\Dashboard::class);

        // Before return: collected was 10,000. Now cash refund of 5,000 must reduce collected to 5,000.
        $page->assertViewHas('cashCollectedToday', 5000.0);
        $page->assertViewHas('returnsCountToday', 1);
        $page->assertViewHas('returnsTotalToday', 5000.0);
        $page->assertViewHas('cashRefundedToday', 5000.0);

        // Profit: (10,000 revenue - 5,000 return) - (6,000 cost - 3,000 returned cost) = 5,000 - 3,000 = 2,000
        $page->assertViewHas('todayProfit', 2000.0);

        // View assertions
        $page->assertSeeHtml('RT-' . str_pad($return->id, 5, '0', STR_PAD_LEFT));
        $page->assertSeeHtml('cash refunded');
    }

    public function test_credit_refund_does_not_deduct_from_drawer_cash(): void
    {
        $branch  = Branch::create(['name' => 'MAIN BRANCH', 'is_main' => true]);
        $manager = $this->manager($branch);

        [$sale, $item, $product, $batch] = $this->createSaleWithItem($manager, $branch, 1, 10000, 6000);

        // Create approved store-credit return
        $return = SaleReturn::create([
            'sale_id'       => $sale->id,
            'processed_by'  => $manager->id,
            'total_credit'  => 10000,
            'refund_method' => SaleReturn::CREDIT,
            'status'        => SaleReturn::STATUS_APPROVED,
            'approved_by'   => $manager->id,
            'approved_at'   => now(),
            'refunded_at'   => now(),
        ]);

        SaleReturnItem::create([
            'sale_return_id'    => $return->id,
            'sale_item_id'      => $item->id,
            'product_id'        => $product->id,
            'batch_id'          => $batch->id,
            'quantity_returned' => 1,
            'unit_price'        => 10000,
            'subtotal'          => 10000,
        ]);

        $page = Livewire::actingAs($manager)->test(\App\Livewire\Dashboard::class);

        // Cash remains 10,000 in the till (store credit liability only)
        $page->assertViewHas('cashCollectedToday', 10000.0);
        $page->assertViewHas('cashRefundedToday', 0.0);
        $page->assertViewHas('creditRefundedToday', 10000.0);
        $page->assertViewHas('returnsTotalToday', 10000.0);

        // Profit is zeroed out: (10000 - 10000) - (6000 - 6000) = 0
        $page->assertViewHas('todayProfit', 0.0);
    }

    public function test_pending_returns_alert_is_shown_to_branch_manager(): void
    {
        $branch  = Branch::create(['name' => 'MAIN BRANCH', 'is_main' => true]);
        $manager = $this->manager($branch);

        [$sale, $item, $product, $batch] = $this->createSaleWithItem($manager, $branch, 1, 8000, 4000);

        // Pending return awaiting review
        SaleReturn::create([
            'sale_id'       => $sale->id,
            'processed_by'  => $manager->id,
            'total_credit'  => 8000,
            'refund_method' => SaleReturn::CASH,
            'status'        => SaleReturn::STATUS_PENDING,
        ]);

        $page = Livewire::actingAs($manager)->test(\App\Livewire\Dashboard::class);

        $page->assertViewHas('pendingReturnsCount', 1);
        $page->assertViewHas('pendingReturnsTotal', 8000.0);
        $page->assertSeeHtml('1 return request awaiting approval');
        $page->assertSeeHtml('Review Returns');
    }

    public function test_branch_manager_only_sees_returns_scoped_to_their_branch(): void
    {
        $branch1 = Branch::create(['name' => 'BRANCH 1', 'is_main' => true]);
        $branch2 = Branch::create(['name' => 'BRANCH 2', 'is_main' => false]);

        $manager1 = $this->manager($branch1);
        $manager2 = $this->manager($branch2);

        [$sale1, $item1, $prod1, $batch1] = $this->createSaleWithItem($manager1, $branch1, 1, 5000, 2000);
        [$sale2, $item2, $prod2, $batch2] = $this->createSaleWithItem($manager2, $branch2, 1, 7000, 3000);

        // Return for Branch 1
        SaleReturn::create([
            'sale_id'       => $sale1->id,
            'processed_by'  => $manager1->id,
            'total_credit'  => 5000,
            'refund_method' => SaleReturn::CASH,
            'status'        => SaleReturn::STATUS_APPROVED,
            'approved_by'   => $manager1->id,
            'approved_at'   => now(),
        ]);

        // Return for Branch 2
        SaleReturn::create([
            'sale_id'       => $sale2->id,
            'processed_by'  => $manager2->id,
            'total_credit'  => 7000,
            'refund_method' => SaleReturn::CASH,
            'status'        => SaleReturn::STATUS_APPROVED,
            'approved_by'   => $manager2->id,
            'approved_at'   => now(),
        ]);

        // Manager 1 dashboard should ONLY see Branch 1's return
        $page1 = Livewire::actingAs($manager1)->test(\App\Livewire\Dashboard::class);
        $page1->assertViewHas('returnsCountToday', 1);
        $page1->assertViewHas('returnsTotalToday', 5000.0);

        // Manager 2 dashboard should ONLY see Branch 2's return
        $page2 = Livewire::actingAs($manager2)->test(\App\Livewire\Dashboard::class);
        $page2->assertViewHas('returnsCountToday', 1);
        $page2->assertViewHas('returnsTotalToday', 7000.0);
    }
}

