<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prescriptions attached to a customer's online orders, on the customer page.
 *
 * Deliberately narrow. A customer has two quite different things called a
 * prescription: the file they uploaded to buy something, and whatever a
 * pharmacist recorded about them clinically. Sales staff already handle the
 * orders, so gathering those files onto the customer page exposes nothing
 * new. Medical records stay with the pharmacist.
 */
class CustomerPrescriptionsTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $roles): User
    {
        return User::factory()->create(['role' => $roles, 'status' => 'active']);
    }

    private function customerWithOrder(?string $prescription = 'prescriptions/scan.jpg', ?string $status = 'pending'): Customer
    {
        $customer = Customer::create([
            'name'  => 'ADA OKAFOR',
            'type'  => 'retail',
            'phone' => '080' . random_int(10000000, 99999999),
        ]);

        Order::create([
            'order_number'        => 'ORD-' . random_int(100000, 999999),
            'customer_id'         => $customer->id,
            'subtotal'            => 1000,
            'delivery_fee'        => 0,
            'total_amount'        => 1000,
            'fulfillment_type'    => 'pickup',
            'payment_method'      => 'cash',
            'payment_status'      => 'paid',
            'status'              => 'processing',
            'prescription_path'   => $prescription,
            'prescription_status' => $prescription ? $status : null,
        ]);

        return $customer;
    }

    private function openCustomer(User $user, Customer $customer)
    {
        return Livewire::actingAs($user)
            ->test(\App\Livewire\Customers\Index::class)
            ->call('viewProfile', $customer->id);
    }

    public function test_sales_staff_see_the_prescription_on_the_order(): void
    {
        $customer = $this->customerWithOrder();

        $this->openCustomer($this->user(['sales']), $customer)
            ->assertSee('Prescription')
            ->assertSee(route('prescriptions.file', Order::first()->id), false);
    }

    public function test_the_review_state_is_shown_beside_it(): void
    {
        // Useful at the counter: a rejected prescription should not be
        // dispensed against, and the sales desk should be able to see that.
        $customer = $this->customerWithOrder(status: 'rejected');

        $this->openCustomer($this->user(['sales']), $customer)->assertSee('Rejected');
    }

    public function test_an_order_without_a_prescription_shows_no_link(): void
    {
        $customer = $this->customerWithOrder(prescription: null);

        $this->openCustomer($this->user(['sales']), $customer)->assertDontSee('Awaiting pharmacist');
    }

    public function test_sales_staff_still_cannot_see_medical_records(): void
    {
        // The boundary this change had to respect: clinical records stay with
        // the pharmacist. Only the order document was opened up.
        $customer = $this->customerWithOrder();

        $component = Livewire::actingAs($this->user(['sales']))
            ->test(\App\Livewire\Customers\Index::class);

        $this->assertFalse($component->instance()->canViewMedicalRecords());
    }

    public function test_a_pharmacist_still_sees_medical_records(): void
    {
        $component = Livewire::actingAs($this->user(['pharmacist']))
            ->test(\App\Livewire\Customers\Index::class);

        $this->assertTrue($component->instance()->canViewMedicalRecords());
    }

    public function test_sales_staff_can_actually_open_the_file(): void
    {
        // A link they cannot follow would be worse than no link.
        Storage::fake('public_site');
        $customer = $this->customerWithOrder();
        $order    = Order::first();

        Storage::disk('public_site')->put($order->prescription_path, 'FAKE');

        $this->actingAs($this->user(['sales']))
            ->get(route('prescriptions.file', $order->id))
            ->assertOk();
    }

    public function test_a_cashier_cannot_open_the_file(): void
    {
        Storage::fake('public_site');
        $customer = $this->customerWithOrder();
        $order    = Order::first();

        Storage::disk('public_site')->put($order->prescription_path, 'FAKE');

        $this->actingAs($this->user(['cashier']))
            ->get(route('prescriptions.file', $order->id))
            ->assertForbidden();
    }
}
