<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * This app and the staff app both expose /api/voucher/redeem against one shared
 * database, but only the staff app understands promoter Wi-Fi codes. A
 * misconfigured basmelcare_api_url in HiFastLink lands here, where a valid
 * promoter code previously came back "Invoice not found" — identical to a
 * genuinely bad code, and impossible to diagnose from the captive portal.
 */
class WrongEndpointTest extends TestCase
{
    use RefreshDatabase;

    private string $key = 'TESTKEY123';

    protected function setUp(): void
    {
        parent::setUp();

        AppSetting::set('hifastlink_api_key', $this->key);

        // The staff app owns this table; this app only reads it to give a
        // useful error, so create it here for the test.
        if (! Schema::hasTable('promoter_codes')) {
            Schema::create('promoter_codes', function ($table) {
                $table->id();
                $table->string('code', 12)->unique();
                $table->timestamps();
            });
        }
    }

    private function redeem(string $code)
    {
        return $this->withHeaders(['X-API-Key' => $this->key])
            ->postJson('/api/voucher/redeem', ['invoice_number' => $code]);
    }

    public function test_a_promoter_code_reports_the_misconfiguration(): void
    {
        DB::table('promoter_codes')->insert([
            'code' => 'ABC123', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->redeem('ABC123');

        $response->assertStatus(421);   // Misdirected Request
        $response->assertJsonPath('error', 'wrong_endpoint');
        $this->assertStringContainsString('staff app', $response->json('detail'));
    }

    public function test_a_genuinely_unknown_code_still_reads_as_not_found(): void
    {
        // Must NOT be reported as a misconfiguration, or a real bad code
        // sends staff chasing a settings problem that does not exist.
        $response = $this->redeem('ZZZZZZ');

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Invoice not found.');
        $response->assertJsonMissingPath('error');
    }

    public function test_the_api_key_is_still_required(): void
    {
        DB::table('promoter_codes')->insert([
            'code' => 'ABC123', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withHeaders(['X-API-Key' => 'wrong'])
            ->postJson('/api/voucher/redeem', ['invoice_number' => 'ABC123'])
            ->assertStatus(401);
    }

    public function test_it_survives_the_table_being_absent(): void
    {
        Schema::dropIfExists('promoter_codes');

        // Before the staff app's migrations run, this must degrade to the
        // ordinary not-found response rather than erroring.
        $this->redeem('ABC123')->assertStatus(404);
    }
}
