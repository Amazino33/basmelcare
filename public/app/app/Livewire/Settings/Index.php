<?php

namespace App\Livewire\Settings;

use App\Models\AppSetting;
use App\Services\KudiSmsService;
use App\Services\WhatsAppService;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public string $activeTab = 'general';

    // General
    public string $pharmacy_name = '';
    public string $pharmacy_phone = '';
    public string $pharmacy_email = '';
    public string $pharmacy_address = '';
    public string $pharmacy_website = '';
    public string $currency_symbol = '₦';

    // WhatsApp
    public string $wawp_instance_id = '';
    public string $wawp_access_token = '';
    public bool $wawp_enabled = false;

    // KudiSMS
    public bool   $kudisms_enabled   = false;
    public string $kudisms_token     = '';
    public string $kudisms_sender_id = '';
    public int    $kudisms_gateway   = 2;

    // Paystack
    public string $paystack_public_key = '';
    public string $paystack_secret_key = '';

    // Notifications
    public bool $notify_low_stock = true;
    public bool $notify_expiry = true;
    public int $expiry_alert_days = 90;

    // Returns
    public int  $return_window_hours    = 48;
    public bool $return_require_customer = true;

    // Commissions
    public float $commission_amount = 100;
    public int $promoter_target_default = 20;
    public string $promoter_coupon_code = '';

    // Incentives / HifastLink
    public string $hifastlink_api_key = '';
    public string $hifastlink_url = '';
    public int    $voucher_validity_hours = 24;

    // Test message
    public string $test_phone = '';
    public string $test_message = 'Hello from BasmelCare Pharmacy!';

    public function mount()
    {
        $this->pharmacy_name = AppSetting::get('pharmacy_name', '');
        $this->pharmacy_phone = AppSetting::get('pharmacy_phone', '');
        $this->pharmacy_email = AppSetting::get('pharmacy_email', '');
        $this->pharmacy_address = AppSetting::get('pharmacy_address', '');
        $this->pharmacy_website = AppSetting::get('pharmacy_website', '');
        $this->currency_symbol = AppSetting::get('currency_symbol', '₦');

        $this->wawp_instance_id = AppSetting::get('wawp_instance_id', '');
        $this->wawp_access_token = AppSetting::get('wawp_access_token', '');
        $this->wawp_enabled = AppSetting::bool('wawp_enabled', false);

        $this->kudisms_enabled   = AppSetting::bool('kudisms_enabled', false);
        $this->kudisms_token     = AppSetting::get('kudisms_token', '');
        $this->kudisms_sender_id = AppSetting::get('kudisms_sender_id', 'BasmelCare');
        $this->kudisms_gateway   = (int) AppSetting::get('kudisms_gateway', 2);

        $this->paystack_public_key = AppSetting::get('paystack_public_key', '');
        $this->paystack_secret_key = AppSetting::get('paystack_secret_key', '');

        $this->notify_low_stock = AppSetting::bool('notify_low_stock', true);
        $this->notify_expiry = AppSetting::bool('notify_expiry', true);
        $this->expiry_alert_days = (int) AppSetting::get('expiry_alert_days', 90);

        $this->return_window_hours     = (int) AppSetting::get('return_window_hours', 48);
        $this->return_require_customer = AppSetting::bool('return_require_customer', true);
        $this->commission_amount       = (float) AppSetting::get('commission_amount', 100);
        $this->promoter_target_default = (int) AppSetting::get('promoter_target_default', 20);
        $this->promoter_coupon_code    = (string) AppSetting::get('promoter_coupon_code', '');

        $this->hifastlink_api_key = AppSetting::get('hifastlink_api_key', '');
        $this->hifastlink_url = AppSetting::get('hifastlink_url', '');
        $this->voucher_validity_hours = (int) AppSetting::get('voucher_validity_hours', 24);
    }

    public function saveReturnSettings(): void
    {
        $this->validate([
            'return_window_hours' => 'required|integer|min:1|max:168',
            'commission_amount'   => 'required|numeric|min:0',
            'promoter_target_default' => 'required|integer|min:1',
            'promoter_coupon_code'    => 'nullable|string|exists:coupons,code',
        ]);

        AppSetting::set('return_window_hours', $this->return_window_hours);
        AppSetting::set('return_require_customer', $this->return_require_customer ? '1' : '0');
        AppSetting::set('commission_amount', $this->commission_amount);
        AppSetting::set('promoter_target_default', $this->promoter_target_default);
        AppSetting::set('promoter_coupon_code', $this->promoter_coupon_code);

        $this->success('Return settings saved.');
    }

    public function savePaystack()
    {
        AppSetting::set('paystack_public_key', $this->paystack_public_key);
        AppSetting::set('paystack_secret_key', $this->paystack_secret_key);
        $this->success('Paystack settings saved.');
    }

    public function saveGeneral()
    {
        $this->validate([
            'pharmacy_name' => 'required|string|max:255',
            'pharmacy_phone' => 'nullable|string|max:20',
            'pharmacy_email' => 'nullable|email|max:255',
            'pharmacy_address' => 'nullable|string|max:500',
            'pharmacy_website' => 'nullable|url|max:255',
            'currency_symbol' => 'required|string|max:5',
        ]);

        AppSetting::set('pharmacy_name', $this->pharmacy_name);
        AppSetting::set('pharmacy_phone', $this->pharmacy_phone);
        AppSetting::set('pharmacy_email', $this->pharmacy_email);
        AppSetting::set('pharmacy_address', $this->pharmacy_address);
        AppSetting::set('pharmacy_website', $this->pharmacy_website);
        AppSetting::set('currency_symbol', $this->currency_symbol);

        $this->success('General settings saved.');
    }

    public function saveWhatsApp()
    {
        $this->validate([
            'wawp_instance_id'  => 'nullable|string|max:255',
            'wawp_access_token' => 'nullable|string|max:255',
        ]);

        AppSetting::set('wawp_instance_id', $this->wawp_instance_id);
        AppSetting::set('wawp_access_token', $this->wawp_access_token);
        AppSetting::set('wawp_enabled', $this->wawp_enabled ? '1' : '0');

        $this->success('WhatsApp settings saved.');
    }

    public function saveKudiSms(): void
    {
        $this->validate([
            'kudisms_token'     => 'nullable|string|max:255',
            'kudisms_sender_id' => 'nullable|string|max:50',
        ]);

        AppSetting::set('kudisms_enabled', $this->kudisms_enabled ? '1' : '0');
        AppSetting::set('kudisms_token', $this->kudisms_token);
        AppSetting::set('kudisms_sender_id', $this->kudisms_sender_id ?: 'BasmelCare');
        AppSetting::set('kudisms_gateway', $this->kudisms_gateway);

        $this->success('KudiSMS settings saved.');
    }

    public function sendSmsTest(): void
    {
        $this->validate([
            'test_phone'   => 'required|string|max:20',
            'test_message' => 'required|string|max:500',
        ]);

        $result = app(KudiSmsService::class)->send($this->test_phone, $this->test_message);

        $result
            ? $this->success('SMS sent via KudiSMS!')
            : $this->error('SMS failed. Check KudiSMS credentials and logs.');
    }

    public function saveNotifications()
    {
        $this->validate([
            'expiry_alert_days' => 'required|integer|min:1|max:365',
        ]);

        AppSetting::set('notify_low_stock', $this->notify_low_stock ? '1' : '0');
        AppSetting::set('notify_expiry', $this->notify_expiry ? '1' : '0');
        AppSetting::set('expiry_alert_days', $this->expiry_alert_days);

        $this->success('Notification settings saved.');
    }

    public function saveIncentives(): void
    {
        $this->validate([
            'voucher_validity_hours' => 'required|integer|min:1|max:168',
            'hifastlink_url'         => 'nullable|url|max:255',
        ]);

        AppSetting::set('voucher_validity_hours', $this->voucher_validity_hours);
        AppSetting::set('hifastlink_url', rtrim(trim($this->hifastlink_url), '/'));

        $this->success('Incentive settings saved.');
    }

    public function regenerateApiKey(): void
    {
        $key = \Illuminate\Support\Str::random(40);
        AppSetting::set('hifastlink_api_key', $key);
        $this->hifastlink_api_key = $key;
        $this->success('New API key generated. Update it on HifastLink.');
    }

    public function sendTest()
    {
        $this->validate([
            'test_phone' => 'required|string|max:20',
            'test_message' => 'required|string|max:500',
        ]);

        $result = app(WhatsAppService::class)->send($this->test_phone, $this->test_message);

        if ($result) {
            $this->success('Test message sent!');
        } else {
            $this->error('Failed to send. Check your WhatsApp credentials and logs.');
        }
    }

    public function render()
    {
        return view('livewire.settings.index', [
            // Only coupons a customer could actually redeem from a text message.
            'advertisableCoupons' => \App\Models\Coupon::orderBy('code')->get()
                ->filter(fn($c) => $c->isAdvertisable())
                ->map(fn($c) => [
                    'id'   => $c->code,
                    'name' => $c->code . ' — ' . $c->offerSummary(),
                ])
                ->values(),
        ]);
    }
}
