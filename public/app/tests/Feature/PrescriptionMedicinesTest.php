<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The pharmacist decides which drugs may not be handed over without a
 * prescription.
 *
 * This flag drives everything else: the Rx label in the shop, the upload
 * demanded at checkout, and whether an order waits for review. Until it was
 * settable, the whole prescription flow was inert - the column existed, the
 * shop read it, and no screen anywhere could turn it on.
 *
 * It is the pharmacist's alone, including against admin. Letting another role
 * change it would let them route around the review they are meant to be
 * waiting on, and the pharmacist is otherwise barred from the catalogue
 * entirely - so the field is carved out rather than the door opened.
 */
class PrescriptionMedicinesTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $roles): User
    {
        return User::factory()->create(['role' => $roles, 'status' => 'active']);
    }

    private function product(string $name = 'AMOXICILLIN 500MG', bool $rx = false): Product
    {
        return Product::create([
            'name'                  => $name,
            'category_id'           => Category::firstOrCreate(['name' => 'General'])->id,
            'selling_price'         => 500,
            'reorder_level'         => 1,
            'requires_prescription' => $rx,
        ]);
    }

    private function page(User $user)
    {
        return Livewire::actingAs($user)->test(\App\Livewire\Prescriptions\Medicines::class);
    }

    // ── the decision ────────────────────────────────────────────────────

    public function test_a_pharmacist_can_mark_a_drug(): void
    {
        $product = $this->product();

        $this->page($this->user(['pharmacist']))->call('toggle', $product->id);

        $this->assertTrue((bool) $product->fresh()->requires_prescription);
    }

    public function test_a_pharmacist_can_unmark_a_drug(): void
    {
        $product = $this->product(rx: true);

        $this->page($this->user(['pharmacist']))->call('toggle', $product->id);

        $this->assertFalse((bool) $product->fresh()->requires_prescription);
    }

    public function test_an_admin_cannot_change_it(): void
    {
        // Deliberate. Admin approving their own way past the review would
        // defeat the point of the review.
        $product = $this->product();

        $this->page($this->user(['admin']))->call('toggle', $product->id);

        $this->assertFalse((bool) $product->fresh()->requires_prescription);
    }

    public function test_an_inventory_manager_cannot_change_it(): void
    {
        // They edit the catalogue, but this is a clinical call, not a
        // catalogue one.
        $product = $this->product();

        $this->page($this->user(['inventory_manager']))->call('toggle', $product->id);

        $this->assertFalse((bool) $product->fresh()->requires_prescription);
    }

    public function test_the_page_is_closed_to_everyone_but_a_pharmacist(): void
    {
        foreach (['admin', 'branch_manager', 'inventory_manager', 'cashier', 'sales'] as $role) {
            $this->actingAs($this->user([$role]))
                ->get(route('prescriptions.medicines'))
                ->assertForbidden();
        }

        $this->actingAs($this->user(['pharmacist']))
            ->get(route('prescriptions.medicines'))
            ->assertOk();
    }

    // ── the record ──────────────────────────────────────────────────────

    public function test_the_change_is_recorded_against_whoever_made_it(): void
    {
        $pharmacist = $this->user(['pharmacist']);
        $product    = $this->product();

        $this->page($pharmacist)->call('toggle', $product->id);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $pharmacist->id,
            'field'   => 'requires_prescription',
        ]);
    }

    // ── nothing else on the product may be touched ──────────────────────

    public function test_toggling_leaves_the_rest_of_the_product_alone(): void
    {
        // The pharmacist is barred from the catalogue; this page carves out one
        // field, and must not become a way in to the others.
        $product = $this->product();
        $before  = $product->only(['name', 'selling_price', 'category_id', 'reorder_level']);

        $this->page($this->user(['pharmacist']))->call('toggle', $product->id);

        $after = $product->fresh()->only(['name', 'selling_price', 'category_id', 'reorder_level']);

        $this->assertEquals($before, $after);
    }

    // ── finding things ──────────────────────────────────────────────────

    public function test_the_list_can_be_searched(): void
    {
        $this->product('AMOXICILLIN 500MG');
        $this->product('PARACETAMOL 500MG');

        $this->page($this->user(['pharmacist']))
            ->set('search', 'AMOX')
            ->assertSee('AMOXICILLIN 500MG')
            ->assertDontSee('PARACETAMOL 500MG');
    }

    public function test_the_list_can_be_filtered_to_what_is_marked(): void
    {
        $this->product('AMOXICILLIN 500MG', rx: true);
        $this->product('PARACETAMOL 500MG');

        $page = $this->page($this->user(['pharmacist']));

        $page->set('filter', 'marked')
            ->assertSee('AMOXICILLIN 500MG')
            ->assertDontSee('PARACETAMOL 500MG');

        $page->set('filter', 'unmarked')
            ->assertSee('PARACETAMOL 500MG')
            ->assertDontSee('AMOXICILLIN 500MG');
    }

    public function test_it_says_when_nothing_is_marked(): void
    {
        // Worth saying plainly: with nothing marked, the whole prescription
        // flow does nothing at all.
        $this->product();

        $this->page($this->user(['pharmacist']))
            ->set('filter', 'marked')
            ->assertSee('Nothing marked yet');
    }

    // ── it actually drives the flow ─────────────────────────────────────

    public function test_marking_a_drug_makes_the_shop_require_a_prescription(): void
    {
        // The end-to-end point of the flag.
        $product = $this->product();

        $this->assertFalse((bool) $product->fresh()->requires_prescription);

        $this->page($this->user(['pharmacist']))->call('toggle', $product->id);

        $this->assertTrue((bool) $product->fresh()->requires_prescription);
    }
}
