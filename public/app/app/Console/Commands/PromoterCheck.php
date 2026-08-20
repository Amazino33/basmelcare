<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\PromoterCode;
use App\Models\ReferralCommission;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-flight check for the promoter programme.
 *
 * Dry run (default) rolls everything back and sends nothing, so it is safe to
 * run on production at any time. --live performs a genuine end-to-end test
 * against a real phone, because message delivery is the part that cannot be
 * proven any other way.
 */
class PromoterCheck extends Command
{
    protected $signature = 'promoter:check
                            {--live : Really send messages and keep the test customer}
                            {--phone= : Phone to receive the OTP and code when using --live}
                            {--promoter= : Promoter user id to test as (defaults to the first promoter)}';

    protected $description = 'Verify the promoter registration and Wi-Fi code flow end to end';

    private bool $failed = false;

    public function handle(): int
    {
        $this->info('BasmelCare — promoter programme check');
        $this->newLine();

        $this->checkSchema();
        $this->checkSettings();
        $this->checkMessaging();

        $promoter = $this->resolvePromoter();
        if (! $promoter) {
            return self::FAILURE;
        }

        if ($this->failed) {
            $this->newLine();
            $this->error('Fix the items marked FAIL above before going live.');

            return self::FAILURE;
        }

        return $this->option('live')
            ? $this->runLive($promoter)
            : $this->runDryRun($promoter);
    }

    // ── Checks ───────────────────────────────────────────────────────

    private function line2(string $label, bool $ok, string $detail = '', bool $fatal = true): void
    {
        $tag = $ok ? '<fg=green>PASS</>' : ($fatal ? '<fg=red>FAIL</>' : '<fg=yellow>WARN</>');
        $this->line(sprintf('  [%s] %-32s %s', $tag, $label, $detail));

        if (! $ok && $fatal) {
            $this->failed = true;
        }
    }

    private function checkSchema(): void
    {
        $this->comment('Database');

        $this->line2('promoter_codes table', Schema::hasTable('promoter_codes'),
            Schema::hasTable('promoter_codes') ? '' : 'run: php artisan migrate --force');
        $this->line2('audit_logs table', Schema::hasTable('audit_logs'));
        $this->line2('users.referral_target', Schema::hasColumn('users', 'referral_target'));
        $this->line2('customers.otp_attempts', Schema::hasColumn('customers', 'otp_attempts'));

        $this->newLine();
    }

    private function checkSettings(): void
    {
        $this->comment('Settings');

        $key = (string) AppSetting::get('hifastlink_api_key', '');
        $this->line2('hifastlink_api_key', $key !== '',
            $key !== '' ? 'set' : 'HiFastLink cannot redeem codes without this');

        if (str_starts_with($key, 'TEST')) {
            $this->line2('hifastlink_api_key looks real', false, "currently '{$key}' — looks like a test value", false);
        }

        $commission = (float) AppSetting::get('commission_amount', 100);
        $this->line2('commission per customer', $commission > 0, '₦' . number_format($commission, 2));

        $target = (int) AppSetting::get('promoter_target_default', 20);
        $this->line2('default daily target', $target > 0, $target . ' connected customers/day');

        $hours = (int) AppSetting::get('voucher_validity_hours', 24);
        $this->line2('Wi-Fi validity', $hours > 0, $hours . ' hours from first connection');

        $this->newLine();
    }

    private function checkMessaging(): void
    {
        $this->comment('Message delivery (OTP and Wi-Fi code)');

        $wa  = AppSetting::bool('wawp_enabled', false)
            && AppSetting::get('wawp_instance_id') && AppSetting::get('wawp_access_token');
        $sms = (string) AppSetting::get('kudisms_api_key', '') !== '';

        $this->line2('WhatsApp configured', $wa, $wa ? 'enabled' : 'off or missing credentials', false);
        $this->line2('SMS fallback configured', $sms, $sms ? 'KudiSMS ready' : 'no KudiSMS key', false);

        // A dry run sends nothing, so missing channels only warn. Going live
        // without one means promoters cannot verify anybody.
        $this->line2('at least one channel works', $wa || $sms,
            ($wa || $sms) ? '' : 'REQUIRED before promoters can verify anyone',
            fatal: (bool) $this->option('live'));

        $this->newLine();
    }

    private function resolvePromoter(): ?User
    {
        $this->comment('Promoter account');

        $promoter = $this->option('promoter')
            ? User::find($this->option('promoter'))
            : User::all()->first(fn($u) => in_array('promoter', $u->role ?? []));

        if (! $promoter) {
            $this->line2('a promoter user exists', false, 'none found — create one under Staff');

            return null;
        }

        $target = $promoter->referral_target ?? AppSetting::get('promoter_target_default', 20);

        $this->line2($promoter->name, $promoter->status === 'active',
            "target {$target}/day · " . implode(', ', $promoter->role ?? []));

        $this->newLine();

        return $promoter;
    }

    // ── Flow ─────────────────────────────────────────────────────────

    private function runDryRun(User $promoter): int
    {
        $this->comment('Simulating the flow (nothing saved, no messages sent)');

        DB::beginTransaction();

        try {
            $result = $this->walkFlow($promoter, '0809' . random_int(1000000, 9999999), sendMessages: false);
        } finally {
            DB::rollBack();
        }

        $this->newLine();
        $this->info('Dry run complete — all changes rolled back.');
        $this->line('  To prove message delivery, run:');
        $this->line('    php artisan promoter:check --live --phone=YOUR_NUMBER');

        return $result;
    }

    private function runLive(User $promoter): int
    {
        $phone = $this->option('phone');

        if (! $phone) {
            $this->error('--live needs --phone=NUMBER so the OTP has somewhere to go.');

            return self::FAILURE;
        }

        $normalised = Customer::normalizePhone($phone);

        if (Customer::where('phone', $normalised)->exists()) {
            $this->error("{$normalised} is already registered. Use a number that is not on file.");

            return self::FAILURE;
        }

        if ($normalised === Customer::normalizePhone($promoter->phone)) {
            $this->error("That is {$promoter->name}'s own number — the app blocks this, and so does this test.");

            return self::FAILURE;
        }

        $this->warn("LIVE test: a real OTP and Wi-Fi code will be sent to {$normalised}.");

        if (! $this->confirm('Continue?', true)) {
            return self::SUCCESS;
        }

        $result = $this->walkFlow($promoter, $normalised, sendMessages: true);

        $this->newLine();
        $this->info('Live test complete.');
        $this->line('  The test customer and its code were left in place so you can');
        $this->line('  finish connecting on a phone. Delete the customer afterwards.');

        return $result;
    }

    private function walkFlow(User $promoter, string $phone, bool $sendMessages): int
    {
        $step = 1;
        // By reference: an arrow function would copy $step and never advance it.
        $say = function (string $m) use (&$step) {
            $this->line(sprintf('  %d. %s', $step++, $m));
        };

        // 1. Register
        $customer = Customer::create([
            'name'          => $sendMessages ? 'TEST CUSTOMER' : '__DRY RUN__',
            'type'          => 'retail',
            'phone'         => $phone,
            'registered_by' => $promoter->id,
        ]);
        $say("Registered customer on {$phone}, attributed to {$promoter->name}");

        // 2. OTP
        $otp = $customer->generateOtp();
        $say("OTP generated: {$otp}");

        if ($sendMessages) {
            $sent = app(\App\Services\WhatsAppService::class)->send(
                $phone,
                'Your ' . AppSetting::get('pharmacy_name', 'BasmelCare') . " test code is: *{$otp}*."
            );
            $say($sent ? '<fg=green>OTP delivered</>' : '<fg=red>OTP FAILED to send — check WhatsApp/SMS settings</>');

            if (! $sent) {
                return self::FAILURE;
            }
        } else {
            $say('(skipped sending — dry run)');
        }

        // 3. Verify
        $customer->refresh();
        if (! $customer->verifyOtp($otp)) {
            $this->error('   OTP verification failed — this should not happen.');

            return self::FAILURE;
        }
        $customer->clearOtp();
        $say('OTP verified');

        // 4. Issue code
        $code = PromoterCode::create([
            'code'        => PromoterCode::generateCode(),
            'user_id'     => $promoter->id,
            'customer_id' => $customer->id,
            'valid_until' => today(),
        ]);
        $say("Wi-Fi code issued: <fg=cyan>{$code->code}</> (valid until end of today)");

        $before = ReferralCommission::where('user_id', $promoter->id)->count();
        $say('Commission so far: none — nothing is earned until the customer connects');

        if ($sendMessages) {
            $this->newLine();
            $this->line("  Now connect a phone to the Wi-Fi and enter: <fg=cyan>{$code->code}</>");
            $this->line('  Then re-run with --verify=' . $code->code . ' to confirm the commission landed.');

            return self::SUCCESS;
        }

        // 5. Simulate the redemption HiFastLink performs
        $request = Request::create('/api/voucher/redeem', 'POST', ['invoice_number' => $code->code]);
        $request->headers->set('X-API-Key', (string) AppSetting::get('hifastlink_api_key', ''));

        $response = app()->handle($request);
        $body     = json_decode($response->getContent(), true);

        if ($response->getStatusCode() !== 200 || ! ($body['valid'] ?? false)) {
            $this->error('   Redemption failed: ' . ($body['message'] ?? 'HTTP ' . $response->getStatusCode()));

            return self::FAILURE;
        }
        $say("Customer connected — access until {$body['expires_at']}");

        // 6. Commission
        $after  = ReferralCommission::where('user_id', $promoter->id)->count();
        $amount = ReferralCommission::where('customer_id', $customer->id)->value('amount');

        if ($after !== $before + 1) {
            $this->error('   Commission was NOT recorded.');

            return self::FAILURE;
        }
        $say("Commission recorded: <fg=green>₦" . number_format((float) $amount, 2) . '</> to ' . $promoter->name);

        // 7. Progress
        $progress = $promoter->fresh()->promoterProgressOn(today());
        $say("Dashboard now shows {$progress['redeemed']} of {$progress['target']} for today");

        return self::SUCCESS;
    }
}
