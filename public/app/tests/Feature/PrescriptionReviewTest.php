<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A prescription uploaded online has to be seen by a pharmacist.
 *
 * The customer already had to upload one at checkout, but nobody qualified
 * ever opened it: a sales user moved the order straight through to dispatch.
 * The file was collected as evidence and then not used as evidence.
 *
 * Approval is the pharmacist's alone - not admin's - so the record of who
 * authorised a dispensing means what it says. The cost is that orders wait
 * when no pharmacist is on duty, which is the intended trade.
 */
class PrescriptionReviewTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $roles): User
    {
        return User::factory()->create(['role' => $roles, 'status' => 'active']);
    }

    private function product(bool $rx = true): Product
    {
        $product = Product::create([
            'name'                  => $rx ? 'AMOXICILLIN 500MG' : 'VITAMIN C',
            'category_id'           => Category::firstOrCreate(['name' => 'General'])->id,
            'selling_price'         => 500,
            'reorder_level'         => 1,
            'requires_prescription' => $rx,
        ]);

        Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B-' . random_int(1000, 9999),
            'expiry_date'  => now()->addYear(),
            'cost_price'   => 300,
            'quantity'     => 100,
        ]);

        return $product;
    }

    private function order(?string $status = 'pending', bool $rx = true): Order
    {
        $order = Order::create([
            'order_number'        => 'ORD-' . random_int(100000, 999999),
            'guest_name'          => 'ADA OKAFOR',
            'guest_phone'         => '08031234567',
            'subtotal'            => 1000,
            'delivery_fee'        => 0,
            'total_amount'        => 1000,
            'fulfillment_type'    => 'pickup',
            'payment_method'      => 'cash',
            'payment_status'      => 'paid',
            'status'              => 'processing',
            'prescription_path'   => $rx ? 'prescriptions/scan.jpg' : null,
            'prescription_status' => $status,
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $this->product($rx)->id,
            'quantity'   => 2,
            'unit_price' => 500,
            'subtotal'   => 1000,
        ]);

        return $order->fresh();
    }

    private function queue(User $user)
    {
        return Livewire::actingAs($user)->test(\App\Livewire\Prescriptions\Index::class);
    }

    // ── who may decide ──────────────────────────────────────────────────

    public function test_a_pharmacist_can_approve(): void
    {
        $order = $this->order();

        $this->queue($this->user(['pharmacist']))->call('approve', $order->id);

        $order->refresh();

        $this->assertTrue($order->prescriptionApproved());
        $this->assertNotNull($order->prescription_reviewed_by);
        $this->assertNotNull($order->prescription_reviewed_at);
    }

    public function test_an_admin_cannot_approve(): void
    {
        // Deliberate: the record has to name someone qualified to have made
        // the decision, or it is not worth keeping.
        $order = $this->order();

        $this->queue($this->user(['admin']))->call('approve', $order->id);

        $this->assertTrue($order->fresh()->awaitingPrescriptionReview());
    }

    public function test_sales_staff_cannot_approve(): void
    {
        $order = $this->order();

        $this->queue($this->user(['sales']))->call('approve', $order->id);

        $this->assertTrue($order->fresh()->awaitingPrescriptionReview());
    }

    public function test_an_order_cannot_be_reviewed_twice(): void
    {
        $order = $this->order();
        $pharmacist = $this->user(['pharmacist']);

        $this->queue($pharmacist)->call('approve', $order->id);
        $first = $order->fresh()->prescription_reviewed_at;

        $this->queue($pharmacist)->call('reject', $order->id);

        $order->refresh();
        $this->assertTrue($order->prescriptionApproved(), 'A decided order was decided again.');
        $this->assertEquals($first, $order->prescription_reviewed_at);
    }

    // ── rejection ───────────────────────────────────────────────────────

    public function test_rejecting_requires_a_reason(): void
    {
        // A refusal with no reason cannot be explained to the customer or
        // acted on by the next person.
        $order = $this->order();

        $this->queue($this->user(['pharmacist']))
            ->set('rejectionNote', '')
            ->call('reject', $order->id)
            ->assertHasErrors('rejectionNote');

        $this->assertTrue($order->fresh()->awaitingPrescriptionReview());
    }

    public function test_a_rejection_records_the_reason(): void
    {
        $order = $this->order();

        $this->queue($this->user(['pharmacist']))
            ->set('rejectionNote', 'Prescription is expired.')
            ->call('reject', $order->id);

        $order->refresh();

        $this->assertTrue($order->prescriptionRejected());
        $this->assertSame('Prescription is expired.', $order->prescription_note);
    }

    // ── the gate on dispatch ────────────────────────────────────────────

    private function fulfilment(User $user)
    {
        return Livewire::actingAs($user)->test(\App\Livewire\OnlineOrders\Index::class);
    }

    public function test_an_unreviewed_order_cannot_be_marked_ready(): void
    {
        $staff = $this->user(['sales']);
        $order = $this->order();
        $order->update(['claimed_by' => $staff->id]);

        $this->fulfilment($staff)->call('markReady', $order->id);

        $this->assertSame('processing', $order->fresh()->status, 'Dispensed without a pharmacist seeing it.');
    }

    public function test_stock_is_not_touched_by_a_blocked_order(): void
    {
        $staff = $this->user(['sales']);
        $order = $this->order();
        $order->update(['claimed_by' => $staff->id]);

        $before = Batch::sum('quantity');

        $this->fulfilment($staff)->call('markReady', $order->id);

        $this->assertSame($before, Batch::sum('quantity'));
    }

    public function test_a_rejected_order_cannot_be_marked_ready(): void
    {
        $staff = $this->user(['sales']);
        $order = $this->order(status: 'rejected');
        $order->update(['claimed_by' => $staff->id]);

        $this->fulfilment($staff)->call('markReady', $order->id);

        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_an_approved_order_can_be_marked_ready(): void
    {
        $staff = $this->user(['sales']);
        $order = $this->order(status: 'approved');
        $order->update(['claimed_by' => $staff->id]);

        $this->fulfilment($staff)->call('markReady', $order->id);

        $this->assertSame('ready', $order->fresh()->status);
    }

    public function test_an_order_with_nothing_on_prescription_is_not_held_up(): void
    {
        // Null status means no review is needed, which is not the same as
        // pending. Ordinary orders must not queue behind a pharmacist.
        $staff = $this->user(['sales']);
        $order = $this->order(status: null, rx: false);
        $order->update(['claimed_by' => $staff->id]);

        $this->fulfilment($staff)->call('markReady', $order->id);

        $this->assertSame('ready', $order->fresh()->status);
    }

    // ── the file ────────────────────────────────────────────────────────

    private function storePrescription(Order $order): void
    {
        Storage::disk('public_site')->put($order->prescription_path, 'FAKE-IMAGE');
    }

    public function test_a_pharmacist_can_open_the_file(): void
    {
        Storage::fake('public_site');
        $order = $this->order();
        $this->storePrescription($order);

        $this->actingAs($this->user(['pharmacist']))
            ->get(route('prescriptions.file', $order->id))
            ->assertOk();
    }

    public function test_a_cashier_cannot_open_the_file(): void
    {
        Storage::fake('public_site');
        $order = $this->order();
        $this->storePrescription($order);

        $this->actingAs($this->user(['cashier']))
            ->get(route('prescriptions.file', $order->id))
            ->assertForbidden();
    }

    public function test_a_guest_cannot_open_the_file(): void
    {
        // The whole point of routing it: the address alone must not be enough.
        Storage::fake('public_site');
        $order = $this->order();
        $this->storePrescription($order);

        $this->get(route('prescriptions.file', $order->id))->assertRedirect();
    }

    public function test_a_missing_file_is_not_found_rather_than_an_error(): void
    {
        Storage::fake('public_site');
        $order = $this->order();

        $this->actingAs($this->user(['pharmacist']))
            ->get(route('prescriptions.file', $order->id))
            ->assertNotFound();
    }

    public function test_no_view_links_a_prescription_by_its_storage_url(): void
    {
        // A storage URL is unauthenticated: holding it is enough to read a
        // patient's prescription.
        foreach (glob(resource_path('views/livewire/**/*.blade.php')) as $view) {
            $contents = file_get_contents($view);

            if (str_contains($contents, 'prescription_path')) {
                $this->assertStringNotContainsString(
                    "url(\$viewOrder->prescription_path)",
                    $contents,
                    basename(dirname($view)) . '/' . basename($view) . ' links a prescription by storage URL.'
                );
            }
        }
    }

    // ── the queue ───────────────────────────────────────────────────────

    public function test_the_queue_shows_what_is_waiting(): void
    {
        $order = $this->order();

        $this->queue($this->user(['pharmacist']))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('AMOXICILLIN 500MG');
    }

    public function test_reviewed_orders_move_out_of_the_waiting_list(): void
    {
        $order = $this->order();
        $pharmacist = $this->user(['pharmacist']);

        $this->queue($pharmacist)->call('approve', $order->id);

        $this->queue($pharmacist)->assertDontSee($order->order_number);
        $this->queue($pharmacist)->set('filter', 'reviewed')->assertSee($order->order_number);
    }

    public function test_staff_who_cannot_decide_are_told_so(): void
    {
        $this->order();

        $this->queue($this->user(['sales']))
            ->assertSee('Only a pharmacist can approve');
    }
}
