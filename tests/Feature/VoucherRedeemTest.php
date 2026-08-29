<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherRedeemTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey = 'test-api-key-abc123';

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::set('hifastlink_api_key', $this->apiKey);
        AppSetting::set('voucher_validity_hours', '24');
    }

    private function makeSale(array $overrides = []): Sale
    {
        $user = User::factory()->create();
        return Sale::create(array_merge([
            'invoice_number' => 'INV-20260706-0001',
            'user_id'        => $user->id,
            'total_amount'   => 5000,
            'payment_method' => 'cash',
            'status'         => 'paid',
            'paid_at'        => now(),
        ], $overrides));
    }

    // ── Auth ─────────────────────────────────────────────────────

    public function test_rejects_request_with_no_api_key(): void
    {
        $response = $this->postJson('/api/voucher/redeem', [
            'invoice_number' => 'INV-20260706-0001',
        ]);

        $response->assertStatus(401)
                 ->assertJson(['valid' => false]);
    }

    public function test_rejects_request_with_wrong_api_key(): void
    {
        $response = $this->postJson('/api/voucher/redeem',
            ['invoice_number' => 'INV-20260706-0001'],
            ['X-API-Key' => 'wrong-key']
        );

        $response->assertStatus(401)
                 ->assertJson(['valid' => false]);
    }

    // ── Validation ───────────────────────────────────────────────

    public function test_rejects_missing_invoice_number(): void
    {
        $response = $this->postJson('/api/voucher/redeem', [], [
            'X-API-Key' => $this->apiKey,
        ]);

        $response->assertStatus(422);
    }

    // ── Invoice state ────────────────────────────────────────────

    public function test_rejects_unknown_invoice(): void
    {
        $response = $this->postJson('/api/voucher/redeem',
            ['invoice_number' => 'INV-DOESNT-EXIST'],
            ['X-API-Key' => $this->apiKey]
        );

        $response->assertStatus(404)
                 ->assertJson(['valid' => false]);
    }

    public function test_rejects_unpaid_invoice(): void
    {
        $this->makeSale(['status' => 'pending', 'paid_at' => null]);

        $response = $this->postJson('/api/voucher/redeem',
            ['invoice_number' => 'INV-20260706-0001'],
            ['X-API-Key' => $this->apiKey]
        );

        $response->assertStatus(422)
                 ->assertJson(['valid' => false]);
    }

    public function test_rejects_revoked_invoice(): void
    {
        $this->makeSale([
            'voucher_redeemed_at' => now()->subHour(),
            'voucher_revoked_at'  => now(),
        ]);

        $response = $this->postJson('/api/voucher/redeem',
            ['invoice_number' => 'INV-20260706-0001'],
            ['X-API-Key' => $this->apiKey]
        );

        $response->assertStatus(422)
                 ->assertJson(['valid' => false]);
    }

    public function test_rejects_redeemed_but_expired_invoice(): void
    {
        // Redeemed 25h ago with a 24h window → window has closed.
        $this->makeSale(['voucher_redeemed_at' => now()->subHours(25)]);

        $response = $this->postJson('/api/voucher/redeem',
            ['invoice_number' => 'INV-20260706-0001'],
            ['X-API-Key' => $this->apiKey]
        );

        $response->assertStatus(422)
                 ->assertJson(['valid' => false]);
    }

    public function test_matches_invoice_case_insensitively(): void
    {
        $this->makeSale(); // stored uppercase

        $response = $this->postJson('/api/voucher/redeem',
            ['invoice_number' => 'inv-20260706-0001'],
            ['X-API-Key' => $this->apiKey]
        );

        $response->assertStatus(200)
                 ->assertJson(['valid' => true]);
    }

    // ── Happy path ───────────────────────────────────────────────

    public function test_redeems_paid_invoice_and_returns_expires_at(): void
    {
        $sale = $this->makeSale();

        $response = $this->postJson('/api/voucher/redeem',
            ['invoice_number' => 'INV-20260706-0001'],
            ['X-API-Key' => $this->apiKey]
        );

        $response->assertStatus(200)
                 ->assertJson([
                     'valid'          => true,
                     'invoice_number' => 'INV-20260706-0001',
                     'validity_hours' => 24,
                 ]);

        $this->assertNotNull($response->json('expires_at'));

        $sale->refresh();
        $this->assertNotNull($sale->voucher_redeemed_at);
    }

    public function test_redeems_completed_status_invoice(): void
    {
        $this->makeSale(['status' => 'completed']);

        $response = $this->postJson('/api/voucher/redeem',
            ['invoice_number' => 'INV-20260706-0001'],
            ['X-API-Key' => $this->apiKey]
        );

        $response->assertStatus(200)
                 ->assertJson(['valid' => true]);
    }

    public function test_expires_at_reflects_configured_validity_hours(): void
    {
        AppSetting::set('voucher_validity_hours', '6');
        $this->makeSale();

        $before = now()->addHours(6)->subSeconds(5);
        $after  = now()->addHours(6)->addSeconds(5);

        $response = $this->postJson('/api/voucher/redeem',
            ['invoice_number' => 'INV-20260706-0001'],
            ['X-API-Key' => $this->apiKey]
        );

        $expiresAt = Carbon::parse($response->json('expires_at'));

        $this->assertTrue(
            $expiresAt->between($before, $after),
            "expires_at should be ~6 hours from now, got {$expiresAt}"
        );
    }

    public function test_allows_reconnect_within_window(): void
    {
        // Already redeemed an hour ago; still inside the 24h window.
        $this->makeSale(['voucher_redeemed_at' => now()->subHour()]);

        $response = $this->postJson('/api/voucher/redeem',
            ['invoice_number' => 'INV-20260706-0001'],
            ['X-API-Key' => $this->apiKey]
        );

        $response->assertStatus(200)
                 ->assertJson(['valid' => true]);
    }

    public function test_reconnect_does_not_extend_the_window(): void
    {
        $this->makeSale();

        $first = $this->postJson('/api/voucher/redeem',
            ['invoice_number' => 'INV-20260706-0001'],
            ['X-API-Key' => $this->apiKey]
        );
        $firstExpiry = Carbon::parse($first->json('expires_at'));

        // Two hours later the customer reconnects.
        $this->travel(2)->hours();

        $second = $this->postJson('/api/voucher/redeem',
            ['invoice_number' => 'INV-20260706-0001'],
            ['X-API-Key' => $this->apiKey]
        );
        $second->assertStatus(200)->assertJson(['valid' => true]);
        $secondExpiry = Carbon::parse($second->json('expires_at'));

        // Expiry must be anchored to first redemption, not pushed forward.
        $this->assertTrue(
            $secondExpiry->between($firstExpiry->copy()->subMinute(), $firstExpiry->copy()->addMinute()),
            "Reconnect extended the window: {$firstExpiry} → {$secondExpiry}"
        );
    }

    // ── how a receipt is identified ──────────────────────────────

    private function redeem(string $code)
    {
        return $this->postJson('/api/voucher/redeem',
            ['invoice_number' => $code],
            ['X-API-Key' => $this->apiKey]
        );
    }

    public function test_a_receipt_is_claimed_by_its_wifi_code(): void
    {
        $this->makeSale(['wifi_code' => 'K7MQ2X']);

        $this->redeem('K7MQ2X')->assertStatus(200)->assertJson(['valid' => true]);
    }

    public function test_a_guessed_four_digit_number_no_longer_opens_a_receipt(): void
    {
        // The lookup used to fall back to invoice_number LIKE '%-0001'. Numbers
        // count up from 0001 every morning, so all nine thousand of a day's
        // possible receipts were reachable by typing four digits - which is the
        // whole reason wifi_code exists.
        $this->makeSale(['wifi_code' => 'K7MQ2X']);

        $this->redeem('0001')->assertStatus(404)->assertJson(['valid' => false]);
    }

    public function test_a_current_receipt_cannot_be_opened_with_its_invoice_number(): void
    {
        // Guessing the whole number is barely harder: the date is today's and
        // the counter is small. If the sale has a wifi_code, that is the key.
        $this->makeSale(['wifi_code' => 'K7MQ2X']);

        $this->redeem('INV-20260706-0001')->assertStatus(404)->assertJson(['valid' => false]);
    }

    public function test_a_receipt_printed_before_wifi_codes_existed_still_works(): void
    {
        // Those receipts have nothing else on them. Honouring them is the only
        // reason the invoice-number path survives at all.
        $this->makeSale(['wifi_code' => null]);

        $this->redeem('INV-20260706-0001')->assertStatus(200)->assertJson(['valid' => true]);
    }

    public function test_one_customer_s_code_does_not_open_another_s_receipt(): void
    {
        $this->makeSale(['invoice_number' => 'INV-20260706-0001', 'wifi_code' => 'K7MQ2X']);
        $this->makeSale(['invoice_number' => 'INV-20260706-0002', 'wifi_code' => 'P3JD9R']);

        $this->assertSame(
            'INV-20260706-0002',
            $this->redeem('P3JD9R')->json('invoice_number')
        );
    }
}
