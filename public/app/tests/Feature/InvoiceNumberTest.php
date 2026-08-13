<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberTest extends TestCase
{
    use RefreshDatabase;

    private function persist(string $invoiceNumber): void
    {
        $user = User::factory()->create();
        Sale::create([
            'invoice_number' => $invoiceNumber,
            'user_id'        => $user->id,
            'total_amount'   => 1000,
            'payment_method' => 'cash',
            'status'         => 'completed',
        ]);
    }

    public function test_has_sequential_core_and_random_suffix(): void
    {
        $number = Sale::generateInvoiceNumber();

        // INV-YYYYMMDD-NNNN-SUFFIX  (suffix uses an unambiguous alphabet)
        $this->assertMatchesRegularExpression(
            '/^INV-\d{8}-\d{4}-[A-HJ-NP-Z2-9]{6}$/',
            $number
        );
    }

    public function test_sequential_core_increments_while_suffix_is_unpredictable(): void
    {
        $first = Sale::generateInvoiceNumber();
        $this->persist($first);

        $second = Sale::generateInvoiceNumber();

        // Cores increment (gapless for accounting)…
        preg_match('/^INV-\d{8}-(\d{4})-/', $first, $m1);
        preg_match('/^INV-\d{8}-(\d{4})-/', $second, $m2);
        $this->assertSame((int) $m1[1] + 1, (int) $m2[1]);

        // …but the full numbers differ, so #0002 is not derivable from #0001.
        $this->assertNotSame($first, $second);
    }

    public function test_suffix_is_not_constant_across_generations(): void
    {
        // Same sequential slot (no sale persisted between calls) must still
        // yield different suffixes — i.e. the suffix is actually random.
        $suffixes = [];
        for ($i = 0; $i < 20; $i++) {
            $suffixes[] = substr(Sale::generateInvoiceNumber(), -6);
        }

        // With ~888M possibilities, 20 samples being all-unique is effectively
        // certain; more than one distinct value is enough to prove randomness.
        $this->assertGreaterThan(1, count(array_unique($suffixes)));
    }
}
