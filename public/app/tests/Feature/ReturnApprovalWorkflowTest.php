<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReturnApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function seller(): User
    {
        return User::factory()->create(['role' => ['sales'], 'status' => 'active']);
    }

    private function auditor(): User
    {
        return User::factory()->create(['role' => ['auditor'], 'status' => 'active']);
    }

    private function branchManager(): User
    {
        return User::factory()->create(['role' => ['branch_manager'], 'status' => 'active']);
    }

    private function cashier(): User
    {
        return User::factory()->create(['role' => ['cashier'], 'status' => 'active']);
    }

    private function customer(): Customer
    {
        return Customer::create([
            'name'           => 'CHIDINMA EZE',
            'type'           => 'retail',
            'phone'          => '080' . random_int(10000000, 99999999),
            'credit_balance' => 0,
        ]);
    }

    private function sale(?Customer $customer = null, float $price = 5000, int $qty = 2, int $batchStock = 50): array
    {
        $product = Product::create([
            'name'          => 'AMOXICILLIN ' . random_int(100, 999),
            'category_id'   => Category::firstOrCreate(['name' => 'ANTIBIOTICS'])->id,
            'selling_price' => $price,
            'reorder_level' => 1,
        ]);

        $batch = Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B' . random_int(100, 999),
            'expiry_date'  => now()->addYear(),
            'cost_price'   => 3000,
            'quantity'     => $batchStock,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . random_int(1000, 9999),
            'user_id'        => $this->seller()->id,
            'customer_id'    => $customer?->id,
            'total_amount'   => $price * $qty,
            'payment_method' => 'cash',
            'status'         => 'paid',
            'paid_at'        => now(),
        ]);

        $item = SaleItem::create([
            'sale_id'    => $sale->id,
            'product_id' => $product->id,
            'batch_id'   => $batch->id,
            'quantity'   => $qty,
            'unit_price' => $price,
            'cost_price' => 3000,
            'subtotal'   => $price * $qty,
        ]);

        return [$sale->fresh(['saleItems']), $item, $batch, $product];
    }

    public function test_sales_person_can_trigger_a_return_request(): void
    {
        $customer = $this->customer();
        [$sale, $item, $batch] = $this->sale($customer, 4000, 2, 50);

        $seller = $this->seller();

        Livewire::actingAs($seller)
            ->test(\App\Livewire\Sales\Index::class)
            ->call('openReturn', $sale->id)
            ->set('refundMethod', SaleReturn::CREDIT)
            ->set('returnQtys.' . $item->id, 1)
            ->set('returnReason', 'Wrong prescription dose purchased')
            ->call('processReturn')
            ->assertHasNoErrors();

        $this->assertSame(1, SaleReturn::count());
        $return = SaleReturn::first();

        $this->assertSame(SaleReturn::STATUS_PENDING, $return->status);
        $this->assertSame($seller->id, $return->processed_by);
        $this->assertSame(4000.0, (float) $return->total_credit);
        $this->assertNull($return->approved_by);
        $this->assertNull($return->approved_at);
        $this->assertNull($return->refunded_at);

        // Stock and credit must NOT be changed while pending
        $this->assertSame(50, $batch->fresh()->quantity, 'Stock should NOT increase while pending.');
        $this->assertSame(0.0, (float) $customer->fresh()->credit_balance, 'Customer credit should NOT increase while pending.');
        $this->assertSame(0, StockMovement::where('type', 'return')->count(), 'No return movement should exist while pending.');
    }

    public function test_unauthorized_user_cannot_trigger_return(): void
    {
        [$sale, $item] = $this->sale(null, 4000, 2);

        $cashier = $this->cashier();

        $page = Livewire::actingAs($cashier)
            ->test(\App\Livewire\Sales\Index::class)
            ->call('openReturn', $sale->id);

        $page->assertSet('returnModal', false);
        $this->assertSame(0, SaleReturn::count());
    }

    public function test_auditor_can_approve_return(): void
    {
        $customer = $this->customer();
        [$sale, $item, $batch] = $this->sale($customer, 5000, 2, 40);

        // Sales person triggers return
        Livewire::actingAs($this->seller())
            ->test(\App\Livewire\Sales\Index::class)
            ->call('openReturn', $sale->id)
            ->set('refundMethod', SaleReturn::CREDIT)
            ->set('returnQtys.' . $item->id, 1)
            ->call('processReturn');

        $return = SaleReturn::first();
        $this->assertTrue($return->isPending());

        // Auditor approves
        $auditor = $this->auditor();
        Livewire::actingAs($auditor)
            ->test(\App\Livewire\Sales\Returns::class)
            ->call('approveReturn', $return->id)
            ->assertHasNoErrors();

        $return->refresh();
        $this->assertTrue($return->isApproved());
        $this->assertSame($auditor->id, $return->approved_by);
        $this->assertNotNull($return->approved_at);
        $this->assertNotNull($return->refunded_at);

        // Now stock and credit MUST be updated
        $this->assertSame(41, $batch->fresh()->quantity, 'Batch stock should be incremented upon approval.');
        $this->assertSame(5000.0, (float) $customer->fresh()->credit_balance, 'Store credit should be applied to customer.');
        $this->assertSame(1, StockMovement::where('type', 'return')->count(), 'Return stock movement should be recorded.');
    }

    public function test_branch_manager_can_approve_return(): void
    {
        [$sale, $item, $batch] = $this->sale(null, 3000, 1, 20); // walk-in cash return

        // Sales person triggers return
        Livewire::actingAs($this->seller())
            ->test(\App\Livewire\Sales\Index::class)
            ->call('openReturn', $sale->id)
            ->set('returnQtys.' . $item->id, 1)
            ->call('processReturn');

        $return = SaleReturn::first();
        $this->assertTrue($return->isPending());

        // Branch manager approves
        $manager = $this->branchManager();
        Livewire::actingAs($manager)
            ->test(\App\Livewire\Sales\Returns::class)
            ->call('approveReturn', $return->id)
            ->assertHasNoErrors();

        $return->refresh();
        $this->assertTrue($return->isApproved());
        $this->assertSame($manager->id, $return->approved_by);
        $this->assertSame(21, $batch->fresh()->quantity);
        $this->assertSame(1, StockMovement::where('type', 'return')->count());
    }

    public function test_sales_person_cannot_approve_return(): void
    {
        [$sale, $item] = $this->sale(null, 3000, 1);

        Livewire::actingAs($this->seller())
            ->test(\App\Livewire\Sales\Index::class)
            ->call('openReturn', $sale->id)
            ->set('returnQtys.' . $item->id, 1)
            ->call('processReturn');

        $return = SaleReturn::first();

        // Seller tries to approve their own or any return
        Livewire::actingAs($this->seller())
            ->test(\App\Livewire\Sales\Returns::class)
            ->call('approveReturn', $return->id);

        $this->assertTrue($return->fresh()->isPending());
    }

    public function test_auditor_or_branch_manager_can_reject_return(): void
    {
        $customer = $this->customer();
        [$sale, $item, $batch] = $this->sale($customer, 4500, 1, 10);

        Livewire::actingAs($this->seller())
            ->test(\App\Livewire\Sales\Index::class)
            ->call('openReturn', $sale->id)
            ->set('returnQtys.' . $item->id, 1)
            ->call('processReturn');

        $return = SaleReturn::first();

        $auditor = $this->auditor();
        Livewire::actingAs($auditor)
            ->test(\App\Livewire\Sales\Returns::class)
            ->call('openRejectModal', $return->id)
            ->set('rejectionReason', 'Item seal was broken and medicine cannot be resold.')
            ->call('rejectReturn')
            ->assertHasNoErrors();

        $return->refresh();
        $this->assertTrue($return->isRejected());
        $this->assertSame($auditor->id, $return->rejected_by);
        $this->assertSame('Item seal was broken and medicine cannot be resold.', $return->rejection_reason);

        // Stock and credit remain untouched
        $this->assertSame(10, $batch->fresh()->quantity);
        $this->assertSame(0.0, (float) $customer->fresh()->credit_balance);
        $this->assertSame(0, StockMovement::where('type', 'return')->count());
    }

    public function test_pending_return_reserves_returnable_quantity(): void
    {
        [$sale, $item] = $this->sale(null, 2000, 2);

        // Sales person requests returning 1 of 2 units
        Livewire::actingAs($this->seller())
            ->test(\App\Livewire\Sales\Index::class)
            ->call('openReturn', $sale->id)
            ->set('returnQtys.' . $item->id, 1)
            ->call('processReturn');

        // Open return again: only 1 unit should be returnable
        $page = Livewire::actingAs($this->seller())
            ->test(\App\Livewire\Sales\Index::class)
            ->call('openReturn', $sale->id);

        $this->assertSame(1, $page->get('returnableQtys.' . $item->id));

        // Request returning the second unit
        $page->set('returnQtys.' . $item->id, 1)->call('processReturn');
        $this->assertSame(2, SaleReturn::count());

        // Open return again: 0 units returnable
        $page2 = Livewire::actingAs($this->seller())
            ->test(\App\Livewire\Sales\Index::class)
            ->call('openReturn', $sale->id);

        $this->assertSame(0, $page2->get('returnableQtys.' . $item->id));
    }

    public function test_rejected_return_releases_returnable_quantity(): void
    {
        [$sale, $item] = $this->sale(null, 2000, 1);

        // Request return
        Livewire::actingAs($this->seller())
            ->test(\App\Livewire\Sales\Index::class)
            ->call('openReturn', $sale->id)
            ->set('returnQtys.' . $item->id, 1)
            ->call('processReturn');

        $return = SaleReturn::first();

        // Reject it
        Livewire::actingAs($this->branchManager())
            ->test(\App\Livewire\Sales\Returns::class)
            ->call('openRejectModal', $return->id)
            ->set('rejectionReason', 'Invalid receipt')
            ->call('rejectReturn');

        // Unit should now be available to return again
        $page = Livewire::actingAs($this->seller())
            ->test(\App\Livewire\Sales\Index::class)
            ->call('openReturn', $sale->id);

        $this->assertSame(1, $page->get('returnableQtys.' . $item->id));
    }

    public function test_finance_ignores_pending_and_rejected_returns(): void
    {
        [$sale, $item] = $this->sale(null, 10000, 1);

        // 1. Pending return
        Livewire::actingAs($this->seller())
            ->test(\App\Livewire\Sales\Index::class)
            ->call('openReturn', $sale->id)
            ->set('returnQtys.' . $item->id, 1)
            ->call('processReturn');

        $return = SaleReturn::first();

        // Finance should not deduct cash refunds while return is pending
        $finance = Livewire::actingAs($this->branchManager())
            ->test(\App\Livewire\Finance\Index::class)
            ->viewData('f');

        $this->assertSame(0.0, (float) $finance['cashRefunds'], 'Pending return must not deduct cash from takings.');

        // 2. Approve return
        Livewire::actingAs($this->auditor())
            ->test(\App\Livewire\Sales\Returns::class)
            ->call('approveReturn', $return->id);

        // Finance should now reflect the approved cash refund
        $financeApproved = Livewire::actingAs($this->branchManager())
            ->test(\App\Livewire\Finance\Index::class)
            ->viewData('f');

        $this->assertSame(10000.0, (float) $financeApproved['cashRefunds'], 'Approved return must deduct cash from takings.');
    }
}
