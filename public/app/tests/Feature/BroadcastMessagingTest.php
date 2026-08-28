<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\BroadcastSender;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * One message to many customers, each on their own.
 *
 * Nobody is put in a group. For a pharmacy a group would show every member
 * who else buys medicine here, which is a fact about named people's health
 * that none of them agreed to share.
 *
 * Sending is batched. Several hundred messages in one web request times out,
 * as the Cloudinary upload did, and firing that many calls at an unofficial
 * gateway back to back is what gets a business number banned - the same
 * number that sends the receipts.
 */
class BroadcastMessagingTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $roles): User
    {
        return User::factory()->create(['role' => $roles, 'status' => 'active']);
    }

    private function customer(string $type = 'retail', ?string $phone = null): Customer
    {
        return Customer::create([
            'name'  => 'CUSTOMER ' . random_int(1000, 9999),
            'type'  => $type,
            'phone' => $phone ?? ('080' . random_int(10000000, 99999999)),
        ]);
    }

    private function page(?User $as = null)
    {
        return Livewire::actingAs($as ?? $this->user(['admin']))
            ->test(\App\Livewire\Messages\Index::class);
    }

    /** Pretend the gateway answered a certain way. */
    private function fakeWhatsApp(string $via, bool $imageSent = false): void
    {
        $this->mock(WhatsAppService::class, function ($mock) use ($via, $imageSent) {
            $mock->shouldReceive('deliverWithImage')
                ->andReturn(['via' => $via, 'image_sent' => $imageSent]);
        });
    }

    // ── who it reaches ──────────────────────────────────────────────────

    public function test_it_reaches_every_customer_with_a_number(): void
    {
        $this->customer();
        $this->customer();

        $this->assertSame(2, app(BroadcastSender::class)->audience('all')->count());
    }

    public function test_a_customer_with_no_number_is_left_out(): void
    {
        // Counting them and then failing silently would be worse than omitting
        // them: the report would claim a reach that never existed.
        $this->customer();
        Customer::create(['name' => 'NO PHONE', 'type' => 'retail', 'phone' => null]);

        $this->assertSame(1, app(BroadcastSender::class)->audience('all')->count());
    }

    public function test_the_wholesale_audience_excludes_retail(): void
    {
        $this->customer('wholesale');
        $this->customer('retail');

        $this->assertSame(1, app(BroadcastSender::class)->audience('wholesale')->count());
    }

    public function test_the_recent_audience_is_people_who_actually_bought(): void
    {
        $bought = $this->customer();
        $this->customer();   // never bought

        $product = Product::create([
            'name' => 'X', 'category_id' => Category::firstOrCreate(['name' => 'General'])->id,
            'selling_price' => 100, 'reorder_level' => 1,
        ]);
        $batch = Batch::create([
            'product_id' => $product->id, 'batch_number' => 'B',
            'expiry_date' => now()->addYear(), 'cost_price' => 50, 'quantity' => 10,
        ]);
        $sale = Sale::create([
            'invoice_number' => 'INV-1', 'customer_id' => $bought->id,
            'user_id' => $this->user(['cashier'])->id, 'total_amount' => 100, 'status' => 'completed',
        ]);
        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id, 'batch_id' => $batch->id,
            'quantity' => 1, 'unit_price' => 100, 'cost_price' => 50, 'subtotal' => 100,
        ]);

        $recent = app(BroadcastSender::class)->audience('recent')->pluck('id');

        $this->assertTrue($recent->contains($bought->id));
        $this->assertCount(1, $recent);
    }

    // ── preparing ───────────────────────────────────────────────────────

    public function test_recipients_are_written_out_before_anything_is_sent(): void
    {
        // Fixed at that moment on purpose: a broadcast happened to a particular
        // set of people, and re-resolving mid-send would change who it was for.
        $this->customer();
        $this->customer();

        $this->page()->set('message', 'New stock in')->call('create');

        $this->assertSame(2, BroadcastRecipient::count());
        $this->assertSame(2, BroadcastRecipient::where('status', 'pending')->count());
    }

    public function test_adding_a_customer_afterwards_does_not_join_a_running_send(): void
    {
        $this->customer();

        $this->page()->set('message', 'New stock in')->call('create');

        $this->customer();   // arrives after it was prepared

        $this->assertSame(1, BroadcastRecipient::count());
    }

    public function test_it_refuses_when_nobody_can_be_reached(): void
    {
        $this->page()->set('message', 'Hello')->call('create');

        $this->assertSame(0, Broadcast::count(), 'An empty broadcast was left behind.');
    }

    public function test_a_message_is_required(): void
    {
        $this->customer();

        $this->page()->set('message', '')->call('create')->assertHasErrors('message');
    }

    // ── sending, in batches ─────────────────────────────────────────────

    private function prepared(int $customers = 3): Broadcast
    {
        foreach (range(1, $customers) as $i) {
            $this->customer();
        }

        $this->page()->set('message', 'New stock in')->call('create');

        return Broadcast::firstOrFail();
    }

    public function test_a_batch_sends_only_its_share(): void
    {
        $this->fakeWhatsApp(WhatsAppService::VIA_WHATSAPP);

        $broadcast = $this->prepared(5);

        app(BroadcastSender::class)->sendBatch($broadcast, limit: 2);

        $this->assertSame(2, $broadcast->recipients()->where('status', 'whatsapp')->count());
        $this->assertSame(3, $broadcast->pendingCount());
    }

    public function test_nobody_is_messaged_twice(): void
    {
        // Stopping and coming back has to be safe, or a broadcast that timed
        // out would double-message everyone already reached.
        $this->fakeWhatsApp(WhatsAppService::VIA_WHATSAPP);

        $broadcast = $this->prepared(3);
        $sender    = app(BroadcastSender::class);

        $sender->sendBatch($broadcast, limit: 2);
        $second = $sender->sendBatch($broadcast, limit: 2);

        $this->assertSame(1, $second['sent'], 'It sent to somebody already reached.');
        $this->assertSame(0, $broadcast->pendingCount());
    }

    public function test_it_marks_itself_finished(): void
    {
        $this->fakeWhatsApp(WhatsAppService::VIA_WHATSAPP);

        $broadcast = $this->prepared(2);

        app(BroadcastSender::class)->sendBatch($broadcast, limit: 10);

        $this->assertNotNull($broadcast->fresh()->finished_at);
        $this->assertTrue($broadcast->fresh()->isFinished());
    }

    // ── the picture, and who does not get it ────────────────────────────

    public function test_a_customer_on_sms_gets_the_words_without_the_picture(): void
    {
        // SMS cannot carry an image. They must still receive the message, which
        // is why the text has to stand on its own.
        $this->fakeWhatsApp(WhatsAppService::VIA_SMS, imageSent: false);

        $broadcast = $this->prepared(1);
        $broadcast->update(['image_path' => 'broadcasts/promo.jpg']);

        app(BroadcastSender::class)->sendBatch($broadcast);

        $recipient = $broadcast->recipients()->first();

        $this->assertSame('sms', $recipient->status);
        $this->assertFalse($recipient->image_sent);
    }

    public function test_whether_the_picture_arrived_is_recorded(): void
    {
        $this->fakeWhatsApp(WhatsAppService::VIA_WHATSAPP, imageSent: true);

        $broadcast = $this->prepared(1);
        $broadcast->update(['image_path' => 'broadcasts/promo.jpg']);

        app(BroadcastSender::class)->sendBatch($broadcast);

        $this->assertTrue($broadcast->recipients()->first()->image_sent);
    }

    public function test_the_image_is_stored_where_the_gateway_can_fetch_it(): void
    {
        // WhatsApp media is sent as a URL, so it has to be publicly reachable -
        // which is exactly why patient documents never go through here.
        $source = file_get_contents(app_path('Livewire/Messages/Index.php'));

        $this->assertStringContainsString("store('broadcasts', 'public_site')", $source);
    }

    public function test_a_failure_is_recorded_rather_than_retried_forever(): void
    {
        $this->fakeWhatsApp(WhatsAppService::FAILED);

        $broadcast = $this->prepared(1);

        app(BroadcastSender::class)->sendBatch($broadcast);

        $this->assertSame('failed', $broadcast->recipients()->first()->status);
        $this->assertSame(0, $broadcast->pendingCount(), 'A failure must not stay pending forever.');
    }

    // ── who may send ────────────────────────────────────────────────────

    public function test_a_cashier_cannot_reach_the_page(): void
    {
        $this->actingAs($this->user(['cashier']))
            ->get(route('messages.index'))
            ->assertForbidden();
    }

    public function test_an_auditor_cannot_send(): void
    {
        $this->customer();

        $this->page($this->user(['auditor']))
            ->set('message', 'Hello')
            ->call('create');

        $this->assertSame(0, Broadcast::count());
    }
}
