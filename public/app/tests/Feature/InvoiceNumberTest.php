<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shape of an invoice number: INV-YYYYMMDD-NNNN.
 *
 * Gapless within the day, because that is what an auditor counts. It is
 * therefore also guessable, which is fine - the number is printed on the
 * customer's receipt and grants nothing on its own. The unguessable part of a
 * receipt is the Wi-Fi code, which is generated separately for exactly that
 * reason.
 *
 * Who the number is compared against is tested in InvoiceNumberBranchScopeTest,
 * and what happens when two tills ask at once in InvoiceNumberRaceTest.
 */
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

    public function test_it_is_the_date_and_a_four_digit_counter(): void
    {
        $this->assertMatchesRegularExpression(
            '/^INV-\d{8}-\d{4}$/',
            Sale::generateInvoiceNumber()
        );
    }

    public function test_it_carries_today_s_date(): void
    {
        $this->assertStringStartsWith('INV-' . now()->format('Ymd') . '-', Sale::generateInvoiceNumber());
    }

    public function test_the_first_sale_of_the_day_is_0001(): void
    {
        $this->assertStringEndsWith('-0001', Sale::generateInvoiceNumber());
    }

    public function test_it_counts_up_by_one(): void
    {
        // Gapless: an auditor reading the day's book should find every number
        // between the first and the last, or know a sale is missing.
        $first = Sale::generateInvoiceNumber();
        $this->persist($first);

        $second = Sale::generateInvoiceNumber();

        $this->assertSame((int) substr($first, -4) + 1, (int) substr($second, -4));
    }

    public function test_asking_twice_without_selling_offers_the_same_number(): void
    {
        // It is not a reservation - nothing is taken until a sale is written.
        // Two tills asking together is a real race, handled by the retry in
        // transactWithRetry rather than by making the number unpredictable.
        $this->assertSame(Sale::generateInvoiceNumber(), Sale::generateInvoiceNumber());
    }

    public function test_a_gap_in_the_book_is_not_filled_back_in(): void
    {
        // Reusing a freed number would make two different sales share one
        // invoice across a reprint or a refund.
        $this->persist('INV-' . now()->format('Ymd') . '-0007');

        $this->assertStringEndsWith('-0008', Sale::generateInvoiceNumber());
    }
}
