<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CreditPayout;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreditsCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create([
            'role'   => ['branch_manager'],
            'status' => 'active',
            'name'   => 'Branch Manager Test',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role'   => ['admin'],
            'status' => 'active',
            'name'   => 'Admin Test',
        ]);
    }

    private function cashier(): User
    {
        return User::factory()->create([
            'role'   => ['cashier'],
            'status' => 'active',
            'name'   => 'Cashier Test',
        ]);
    }

    private function auditor(): User
    {
        return User::factory()->create([
            'role'   => ['auditor'],
            'status' => 'active',
            'name'   => 'Auditor Test',
        ]);
    }

    public function test_branch_manager_can_correct_mistakenly_entered_credit_balance(): void
    {
        $manager = $this->manager();
        $customer = Customer::create([
            'name'           => 'CHIDINMA AGBO',
            'type'           => 'retail',
            'phone'          => '08012345678',
            'credit_balance' => 400.00,
        ]);

        Livewire::actingAs($manager)
            ->test(\App\Livewire\Credits\Index::class)
            ->call('openAdjustment', $customer->id)
            ->assertSet('adjustCustomerId', $customer->id)
            ->assertSet('new_balance', '400.00')
            ->set('new_balance', '0.00')
            ->set('adjust_reason', 'Mistakenly entered ₦400 change at till')
            ->call('saveAdjustment')
            ->assertHasNoErrors()
            ->assertSet('adjustModal', false);

        $this->assertEquals(0.00, (float) $customer->fresh()->credit_balance);

        // Verify audit trail in credit_payouts with amount = 0 so drawer cash calculation is untouched
        $payoutAudit = CreditPayout::where('customer_id', $customer->id)->latest()->first();
        $this->assertNotNull($payoutAudit);
        $this->assertEquals(0.00, (float) $payoutAudit->amount);
        $this->assertEquals(400.00, (float) $payoutAudit->balance_before);
        $this->assertEquals(0.00, (float) $payoutAudit->balance_after);
        $this->assertEquals($manager->id, $payoutAudit->cashier_id);
        $this->assertStringContainsString('Mistakenly entered ₦400 change at till', $payoutAudit->note);
    }

    public function test_cashier_cannot_adjust_credit_balance(): void
    {
        $cashier = $this->cashier();
        $customer = Customer::create([
            'name'           => 'CHIDINMA AGBO',
            'type'           => 'retail',
            'phone'          => '08012345678',
            'credit_balance' => 400.00,
        ]);

        Livewire::actingAs($cashier)
            ->test(\App\Livewire\Credits\Index::class)
            ->call('openAdjustment', $customer->id)
            ->set('new_balance', '0.00')
            ->set('adjust_reason', 'Unauthorized attempt')
            ->call('saveAdjustment');

        $this->assertEquals(400.00, (float) $customer->fresh()->credit_balance);
        $this->assertDatabaseMissing('credit_payouts', [
            'customer_id' => $customer->id,
            'note'        => 'Unauthorized attempt',
        ]);
    }

    public function test_auditor_cannot_adjust_credit_balance(): void
    {
        $auditor = $this->auditor();
        $customer = Customer::create([
            'name'           => 'CHIDINMA AGBO',
            'type'           => 'retail',
            'phone'          => '08012345678',
            'credit_balance' => 400.00,
        ]);

        Livewire::actingAs($auditor)
            ->test(\App\Livewire\Credits\Index::class)
            ->set('adjustCustomerId', $customer->id)
            ->set('new_balance', '0.00')
            ->set('adjust_reason', 'Auditor edit attempt')
            ->call('saveAdjustment');

        $this->assertEquals(400.00, (float) $customer->fresh()->credit_balance);
    }

    public function test_branch_manager_can_void_mistaken_payout(): void
    {
        $manager = $this->manager();
        $customer = Customer::create([
            'name'           => 'BASHIR BELLO',
            'type'           => 'retail',
            'phone'          => '08099887766',
            'credit_balance' => 0.00,
        ]);

        $payout = CreditPayout::create([
            'customer_id'    => $customer->id,
            'amount'         => 400.00,
            'balance_before' => 400.00,
            'balance_after'  => 0.00,
            'cashier_id'     => $manager->id,
            'note'           => 'Cash paid out',
        ]);

        Livewire::actingAs($manager)
            ->test(\App\Livewire\Credits\Index::class)
            ->call('voidPayout', $payout->id)
            ->assertHasNoErrors()
            ->assertSee('Voided');

        // Customer's 400 balance restored
        $this->assertEquals(400.00, (float) $customer->fresh()->credit_balance);

        // Payout amount zeroed out so drawer reconciliation restores cash
        $this->assertEquals(0.00, (float) $payout->fresh()->amount);
        $this->assertStringStartsWith('[VOIDED by', $payout->fresh()->note);
    }

    public function test_branch_manager_can_adjust_credit_from_customer_profile_drawer(): void
    {
        $manager = $this->manager();
        $customer = Customer::create([
            'name'           => 'NGOZI TEST',
            'type'           => 'retail',
            'phone'          => '08077665544',
            'credit_balance' => 400.00,
        ]);

        Livewire::actingAs($manager)
            ->test(\App\Livewire\Customers\Index::class)
            ->call('openCreditAdjustment', $customer->id)
            ->assertSet('creditAdjustModal', true)
            ->set('new_credit_balance', '0.00')
            ->set('credit_adjust_reason', 'Correcting ₦400 mistake from cashier')
            ->call('saveCreditAdjustment')
            ->assertHasNoErrors()
            ->assertSet('creditAdjustModal', false);

        $this->assertEquals(0.00, (float) $customer->fresh()->credit_balance);
        $this->assertDatabaseHas('credit_payouts', [
            'customer_id' => $customer->id,
            'amount'      => 0.00,
        ]);
    }

    public function test_branch_manager_can_correct_sale_payment_details_and_stored_credit(): void
    {
        $manager = $this->manager();
        $customer = Customer::create([
            'name'           => 'EMMANUEL EZE',
            'type'           => 'retail',
            'phone'          => '08044332211',
            'credit_balance' => 400.00,
        ]);

        $sale = Sale::create([
            'invoice_number'  => 'INV-20261002-001',
            'user_id'         => $manager->id,
            'customer_id'     => $customer->id,
            'total_amount'    => 5000.00,
            'payment_method'  => 'cash',
            'payment_details' => [
                'cash'          => 5400.00,
                'stored_credit' => 400.00,
            ],
            'status'          => 'paid',
            'paid_at'         => now(),
            'note'            => 'Initial checkout note',
        ]);

        Livewire::actingAs($manager)
            ->test(\App\Livewire\Sales\Index::class)
            ->call('openEditSale', $sale->id)
            ->assertSet('editSaleModal', true)
            ->assertSet('editStoredCredit', '400')
            ->set('editStoredCredit', '0.00')
            ->set('editCash', '5000.00')
            ->set('editReason', 'Mistakenly entered ₦400 change stored as credit')
            ->call('saveEditSale')
            ->assertHasNoErrors();

        // Customer credit balance decreased from 400 to 0
        $this->assertEquals(0.00, (float) $customer->fresh()->credit_balance);

        $freshSale = $sale->fresh();
        $this->assertArrayNotHasKey('stored_credit', $freshSale->payment_details ?? []);
        $this->assertEquals(5000.00, $freshSale->payment_details['cash']);
        $this->assertStringContainsString('Mistakenly entered ₦400 change stored as credit', $freshSale->note);
    }

    public function test_branch_manager_can_adjust_debt_and_void_payment(): void
    {
        $manager = $this->manager();
        $customer = Customer::create([
            'name'           => 'DEBTOR TEST',
            'type'           => 'retail',
            'phone'          => '08033221100',
            'credit_balance' => 0.00,
        ]);

        $sale = Sale::create([
            'invoice_number'  => 'INV-20261002-002',
            'user_id'         => $manager->id,
            'customer_id'     => $customer->id,
            'total_amount'    => 5000.00,
            'payment_method'  => 'cash',
            'status'          => 'paid',
        ]);

        $debt = Debt::create([
            'sale_id'     => $sale->id,
            'customer_id' => $customer->id,
            'amount_owed' => 5000.00,
            'amount_paid' => 2000.00,
            'status'      => 'partial',
        ]);

        $payment = DebtPayment::create([
            'debt_id'        => $debt->id,
            'amount'         => 2000.00,
            'payment_method' => 'cash',
            'received_by'    => $manager->id,
        ]);

        // Branch manager adjusts amount owed
        Livewire::actingAs($manager)
            ->test(\App\Livewire\DebtBook\Index::class)
            ->call('openAdjustDebt', $debt->id)
            ->set('new_amount_owed', '4000.00')
            ->set('debt_adjust_reason', 'Negotiated settlement discount')
            ->call('saveAdjustDebt')
            ->assertHasNoErrors();

        $this->assertEquals(4000.00, (float) $debt->fresh()->amount_owed);

        // Branch manager voids payment
        Livewire::actingAs($manager)
            ->test(\App\Livewire\DebtBook\Index::class)
            ->call('voidDebtPayment', $payment->id)
            ->assertHasNoErrors();

        $this->assertEquals(0.00, (float) $debt->fresh()->amount_paid);
        $this->assertEquals(0.00, (float) $payment->fresh()->amount);
        $this->assertStringStartsWith('[VOIDED by', $payment->fresh()->note);
    }
}
