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
}
