<?php

namespace App\Livewire\Settings;

use App\Models\AppSetting;
use App\Models\Product;
use App\Support\CloudinaryImage;
use App\Support\ConsultationPricing;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

    // Calling the pharmacist to the counter
    public bool $pharmacist_call_alert_enabled = false;
    public int  $pharmacist_call_alert_after_seconds = 60;

    // Monthly cover
    public bool $insurance_enabled = false;

    // Consultations
    public array $consult_prices = [];
    public int $consult_free_count = 1;
    public string $consult_free_period = 'ever';

    // Wholesale pricing
    public float $wholesale_markup_percent = 5;

    // Cloudinary (product images)
    public bool   $cloudinary_enabled    = false;
    public string $cloudinary_cloud_name = '';
    public string $cloudinary_api_key    = '';
    public string $cloudinary_api_secret = '';
    public string $cloudinary_folder     = 'basmelcare';

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

        $this->pharmacist_call_alert_enabled       = AppSetting::bool('pharmacist_call_alert_enabled', false);
        $this->pharmacist_call_alert_after_seconds = (int) AppSetting::get('pharmacist_call_alert_after_seconds', 60);

        $this->insurance_enabled = AppSetting::bool('insurance_enabled', false);

        $this->consult_free_count  = (int) AppSetting::get('consult_free_count', 1);
        $this->consult_free_period = (string) AppSetting::get('consult_free_period', 'ever');

        foreach (array_keys(ConsultationPricing::PROVIDERS) as $provider) {
            foreach (array_keys(ConsultationPricing::MODES) as $mode) {
                $this->consult_prices[$provider][$mode] =
                    (string) ConsultationPricing::price($provider, $mode);
            }
        }

        $this->wholesale_markup_percent = (float) AppSetting::get('wholesale_markup_percent', 5);

        $this->cloudinary_enabled    = AppSetting::bool('cloudinary_enabled', false);
        $this->cloudinary_cloud_name = AppSetting::get('cloudinary_cloud_name', '');
        $this->cloudinary_api_key    = AppSetting::get('cloudinary_api_key', '');
        $this->cloudinary_api_secret = AppSetting::get('cloudinary_api_secret', '');
        $this->cloudinary_folder     = AppSetting::get('cloudinary_folder', 'basmelcare');

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

    /**
     * The default margin on wholesale lines, applied over what the stock cost.
     *
     * Capped at 100% not because more is impossible but because a three-digit
     * figure here is almost always a typo, and it would quietly reprice the
     * whole catalogue for every wholesale customer.
     */
    /**
     * What a consultation costs, and how many are free.
     *
     * Prices are per provider and per mode because a video call and a text
     * exchange are not the same amount of anybody's time.
     */
    /**
     * Whether an unanswered call reaches the pharmacists' phones.
     *
     * Off by default. It costs a message per call through the same gateway
     * that sends receipts, so it should be a decision rather than something
     * that starts happening.
     */
    public function savePharmacistAlerts(): void
    {
        $this->validate([
            'pharmacist_call_alert_after_seconds' => 'required|integer|min:15|max:600',
        ], [], ['pharmacist_call_alert_after_seconds' => 'delay']);

        AppSetting::set('pharmacist_call_alert_enabled', $this->pharmacist_call_alert_enabled ? '1' : '0');
        AppSetting::set('pharmacist_call_alert_after_seconds', $this->pharmacist_call_alert_after_seconds);

        $this->success('Pharmacist alert settings saved.');
    }

    /**
     * Switch monthly cover on or off.
     *
     * Off until the pharmacy is ready to sell it. While it is off, plans and
     * subscriptions can be set up and looked at, but nothing pays for anything
     * at the till - so turning it on later is a decision, not a release.
     *
     * Turning it off does not cancel anybody: their cover simply stops being
     * applied, and picks up again if it is switched back on.
     */
    public function saveInsurance(): void
    {
        AppSetting::set('insurance_enabled', $this->insurance_enabled ? '1' : '0');

        $this->success($this->insurance_enabled
            ? 'Cover is now live. Subscribers will be charged less at the till.'
            : 'Cover is switched off. Nobody will be charged less at the till.');
    }

    public function saveConsultations(): void
    {
        $this->validate([
            'consult_free_count'    => 'required|integer|min:0|max:20',
            'consult_free_period'   => 'required|in:' . implode(',', array_keys(ConsultationPricing::FREE_PERIODS)),
            'consult_prices.*.*'    => 'nullable|numeric|min:0',
        ]);

        AppSetting::set('consult_free_count', $this->consult_free_count);
        AppSetting::set('consult_free_period', $this->consult_free_period);

        foreach ($this->consult_prices as $provider => $modes) {
            foreach ($modes as $mode => $price) {
                AppSetting::set(ConsultationPricing::priceKey($provider, $mode), (float) $price);
            }
        }

        $this->success('Consultation settings saved.');
    }

    public function saveWholesalePricing(): void
    {
        $this->validate([
            'wholesale_markup_percent' => 'required|numeric|min:0|max:100',
        ]);

        AppSetting::set('wholesale_markup_percent', $this->wholesale_markup_percent);
        Product::forgetDefaultMarkup();

        $this->success('Wholesale markup saved.');
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

    /** Product images with a file recorded against them. */
    public function imageCount(): int
    {
        return Product::whereNotNull('image')->where('image', '!=', '')->count();
    }

    /** When the last sync ran, for the status panel. */
    public function lastSyncedAt(): ?string
    {
        $at = AppSetting::get('cloudinary_synced_at');

        return $at ? \Carbon\Carbon::parse($at)->diffForHumans() : null;
    }

    /** How many are actually in Cloudinary right now. */
    public function syncedCount(): int
    {
        return max(0, $this->imageCount() - $this->imagesAwaitingUpload());
    }

    /**
     * Switching Cloudinary on redirects every image URL at once. Images that
     * were never uploaded do not fail quietly - they all break together, on
     * the customer-facing shop. So the switch stays locked until a sync has
     * accounted for every image.
     */
    public function canEnableCloudinary(): bool
    {
        $config = CloudinaryImage::config();

        if ($config['cloud_name'] === '' || $config['api_key'] === '' || $config['api_secret'] === '') {
            return false;
        }

        return $this->imagesAwaitingUpload() === 0 && $this->imageCount() > 0;
    }

    public function saveCloudinary(): void
    {
        $this->validate([
            'cloudinary_cloud_name' => 'nullable|string|max:100',
            'cloudinary_api_key'    => 'nullable|string|max:100',
            'cloudinary_api_secret' => 'nullable|string|max:200',
            'cloudinary_folder'     => 'nullable|string|max:100',
        ]);

        AppSetting::set('cloudinary_cloud_name', trim($this->cloudinary_cloud_name));
        AppSetting::set('cloudinary_api_key', trim($this->cloudinary_api_key));
        AppSetting::set('cloudinary_api_secret', trim($this->cloudinary_api_secret));
        AppSetting::set('cloudinary_folder', trim($this->cloudinary_folder) ?: 'basmelcare');

        CloudinaryImage::forget();

        // Refuse rather than let the operator switch on a half-migrated
        // catalogue and discover it from customers.
        if ($this->cloudinary_enabled && ! $this->canEnableCloudinary()) {
            $this->cloudinary_enabled = false;
            AppSetting::set('cloudinary_enabled', '0');
            CloudinaryImage::forget();

            $this->error('Upload the images to Cloudinary first - ' . $this->syncedCount() . ' of ' . $this->imageCount() . ' are there.');

            return;
        }

        AppSetting::set('cloudinary_enabled', $this->cloudinary_enabled ? '1' : '0');
        CloudinaryImage::forget();
        Storage::forgetDisk('product_images');

        $this->success('Cloudinary settings saved.');
    }

    /**
     * How many images one click sends.
     *
     * The whole catalogue in a single request produced a 504 on a few hundred
     * images: every upload is a round trip to Cloudinary, and the web server
     * gives up long before they finish. A batch small enough to return
     * comfortably, repeated, gets there without needing a queue worker -
     * which shared hosting does not reliably have.
     */
    private const UPLOAD_BATCH = 25;

    public function imagesAwaitingUpload(): int
    {
        return \App\Console\Commands\UploadProductImagesToCloud::outstandingCount();
    }

    public function uploadImagesToCloud(): void
    {
        $before = $this->imagesAwaitingUpload();

        $exitCode = Artisan::call('products:upload-to-cloud', ['--limit' => self::UPLOAD_BATCH]);

        $after = $this->imagesAwaitingUpload();
        $sent  = max(0, $before - $after);

        if ($exitCode !== 0) {
            // The per-image reasons matter and are too long for a toast.
            Log::warning('Cloudinary upload reported failures', ['output' => Artisan::output()]);

            $this->error('Some images failed. ' . $sent . ' sent, ' . $after . ' still to go. See the log.');

            return;
        }

        if ($after > 0) {
            // Nothing is lost by stopping here; the next click carries on from
            // where this one finished.
            $this->success($sent . ' sent. ' . $after . ' still to go - click again to continue.');

            return;
        }

        $this->success('Every product image is now in Cloudinary.');
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
