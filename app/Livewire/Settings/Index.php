<?php

namespace App\Livewire\Settings;

use App\Models\AppSetting;
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

    // Paystack
    public string $paystack_public_key = '';
    public string $paystack_secret_key = '';

    // Notifications
    public bool $notify_low_stock = true;
    public bool $notify_expiry = true;
    public int $expiry_alert_days = 90;

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

        $this->paystack_public_key = AppSetting::get('paystack_public_key', '');
        $this->paystack_secret_key = AppSetting::get('paystack_secret_key', '');

        $this->notify_low_stock = AppSetting::bool('notify_low_stock', true);
        $this->notify_expiry = AppSetting::bool('notify_expiry', true);
        $this->expiry_alert_days = (int) AppSetting::get('expiry_alert_days', 90);

        $this->hifastlink_api_key = AppSetting::get('hifastlink_api_key', '');
        $this->hifastlink_url = AppSetting::get('hifastlink_url', '');
        $this->voucher_validity_hours = (int) AppSetting::get('voucher_validity_hours', 24);
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
            'wawp_instance_id' => 'nullable|string|max:255',
            'wawp_access_token' => 'nullable|string|max:255',
        ]);

        AppSetting::set('wawp_instance_id', $this->wawp_instance_id);
        AppSetting::set('wawp_access_token', $this->wawp_access_token);
        AppSetting::set('wawp_enabled', $this->wawp_enabled ? '1' : '0');

        $this->success('WhatsApp settings saved.');
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

        $service = new WhatsAppService();
        $result = $service->send($this->test_phone, $this->test_message);

        if ($result) {
            $this->success('Test message sent!');
        } else {
            $this->error('Failed to send. Check your WhatsApp credentials and logs.');
        }
    }

    public function render()
    {
        return view('livewire.settings.index');
    }
}
