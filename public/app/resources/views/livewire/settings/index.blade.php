<div>
    <x-header title="Settings" subtitle="Configure pharmacy and integrations" />

    <x-tabs wire:model="activeTab">
        <x-tab name="general" label="General" icon="o-cog-6-tooth">
            <x-card title="Pharmacy Information" class="mt-4">
                <x-form wire:submit="saveGeneral">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-input label="Pharmacy Name" wire:model="pharmacy_name" />
                        <x-input label="Phone" wire:model="pharmacy_phone" />
                        <x-input label="Email" wire:model="pharmacy_email" type="email" />
                        <x-input label="Website" wire:model="pharmacy_website" placeholder="https://www.example.com" />
                        <x-input label="Currency Symbol" wire:model="currency_symbol" hint="e.g. ₦, $, £" />
                        <div class="md:col-span-2">
                            <x-textarea label="Address" wire:model="pharmacy_address" rows="2" />
                        </div>
                    </div>
                    <x-slot:actions>
                        <x-button label="Save General Settings" type="submit" class="btn-primary" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </x-tab>

        <x-tab name="whatsapp" label="WhatsApp" icon="o-chat-bubble-left-right">
            <x-card title="WhatsApp Integration (WAWP)" class="mt-4">
                <x-form wire:submit="saveWhatsApp">
                    <x-toggle label="Enable WhatsApp" wire:model="wawp_enabled" hint="Send notifications via WhatsApp" />
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <x-input label="WAWP Instance ID" wire:model="wawp_instance_id" />
                        <x-input label="WAWP Access Token" wire:model="wawp_access_token" type="password" />
                    </div>

                    @if(!$wawp_enabled)
                        <x-alert title="WhatsApp is disabled" description="Enable it above and provide your WAWP credentials to start sending messages." icon="o-information-circle" class="alert-info mt-4" />
                    @endif

                    <x-slot:actions>
                        <x-button label="Save WhatsApp Settings" type="submit" class="btn-primary" />
                    </x-slot:actions>
                </x-form>
            </x-card>

            <x-card title="SMS Fallback (KudiSMS)" class="mt-4">
                <p class="text-sm text-base-content/60 mb-4">
                    When WhatsApp is unavailable or fails, messages are automatically sent via SMS instead.
                </p>
                <x-form wire:submit="saveKudiSms">
                    <x-toggle label="Enable SMS Fallback" wire:model="kudisms_enabled" hint="Send via KudiSMS when WhatsApp fails" />
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <x-input label="KudiSMS API Token" wire:model="kudisms_token" type="password" placeholder="k4u3Ww..." />
                        <x-input label="Sender ID" wire:model="kudisms_sender_id" placeholder="BasmelCare" hint="Must be approved in your KudiSMS dashboard" />
                        <x-input label="Gateway" wire:model="kudisms_gateway" type="number" hint="Check your KudiSMS dashboard for the correct gateway number" class="max-w-xs" />
                    </div>
                    <x-slot:actions>
                        <x-button label="Save SMS Settings" type="submit" class="btn-primary" />
                    </x-slot:actions>
                </x-form>
            </x-card>

            <x-card title="Send Test Message" class="mt-4">
                <x-form wire:submit="sendTest">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-input label="Phone Number" wire:model="test_phone" placeholder="08012345678" />
                        <x-input label="Message" wire:model="test_message" />
                    </div>
                    <x-slot:actions>
                        <x-button label="Test WhatsApp" type="submit" class="btn-secondary" icon="o-paper-airplane" />
                        <x-button label="Test SMS" wire:click="sendSmsTest" class="btn-outline" icon="o-device-phone-mobile" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </x-tab>

        <x-tab name="paystack" label="Paystack" icon="o-credit-card">
            <x-card title="Paystack Payment Gateway" class="mt-4">
                <x-form wire:submit="savePaystack">
                    <x-input label="Public Key" wire:model="paystack_public_key" placeholder="pk_live_..." />
                    <x-input label="Secret Key" wire:model="paystack_secret_key" type="password" placeholder="sk_live_..." />
                    <x-slot:actions>
                        <x-button label="Save Paystack Settings" type="submit" class="btn-primary" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </x-tab>

        <x-tab name="images" label="Product Images" icon="o-photo">
            @php
                $imageCount  = $this->imageCount();
                $syncedCount = $this->syncedCount();
                $outstanding = max(0, $imageCount - $syncedCount);
                $canEnable   = $this->canEnableCloudinary();
            @endphp

            <x-card title="Cloudinary" subtitle="Serve product images from a CDN, resized for each screen" class="mt-4">
                {{-- Migration status first: it decides whether the switch below is usable --}}
                <div class="rounded-lg border p-4 mb-4 {{ $outstanding > 0 ? 'border-warning bg-warning/5' : 'border-success bg-success/5' }}">
                    <div class="flex items-start gap-3">
                        <x-icon name="{{ $outstanding > 0 ? 'o-exclamation-triangle' : 'o-check-circle' }}"
                                class="w-5 h-5 shrink-0 mt-0.5 {{ $outstanding > 0 ? 'text-warning' : 'text-success' }}" />
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm">
                                {{ $syncedCount }} of {{ $imageCount }} product images are in Cloudinary
                            </p>
                            @if($outstanding > 0)
                                <p class="text-sm text-base-content/70 mt-1">
                                    Turning Cloudinary on points every image link at it. The
                                    {{ $outstanding }} that {{ $outstanding === 1 ? 'has' : 'have' }} not been
                                    uploaded would show as broken on the shop, so the switch stays locked
                                    until they are all there.
                                </p>
                            @else
                                <p class="text-sm text-base-content/70 mt-1">
                                    Everything is uploaded. Safe to switch on.
                                </p>
                            @endif

                            @if($lastSynced = $this->lastSyncedAt())
                                <p class="text-xs text-base-content/50 mt-1">Last checked {{ $lastSynced }}</p>
                            @endif

                            <x-button label="Upload images to Cloudinary"
                                      icon="o-cloud-arrow-up"
                                      wire:click="uploadImagesToCloud"
                                      spinner="uploadImagesToCloud"
                                      class="btn-sm btn-outline mt-3"
                                      :disabled="$cloudinary_cloud_name === '' || $cloudinary_api_key === ''" />
                            <p class="text-xs text-base-content/50 mt-2">
                                Save your credentials first. This may take a moment for a large catalogue.
                            </p>
                        </div>
                    </div>
                </div>

                <x-form wire:submit="saveCloudinary">
                    <x-input label="Cloud name" wire:model="cloudinary_cloud_name" placeholder="your-cloud-name"
                             hint="From your Cloudinary dashboard" />
                    <x-input label="API key" wire:model="cloudinary_api_key" placeholder="123456789012345" />
                    <x-input label="API secret" wire:model="cloudinary_api_secret" type="password" placeholder="••••••••" />
                    <x-input label="Folder" wire:model="cloudinary_folder" placeholder="basmelcare"
                             hint="Keeps this pharmacy's images separate inside your Cloudinary account" />

                    <x-checkbox label="Serve product images from Cloudinary"
                                wire:model="cloudinary_enabled"
                                :disabled="! $canEnable"
                                hint="{{ $canEnable ? 'Switch off at any time to go back to local images.' : 'Upload the outstanding images first.' }}" />

                    <x-slot:actions>
                        <x-button label="Save Cloudinary Settings" type="submit" class="btn-primary" spinner="saveCloudinary" />
                    </x-slot:actions>
                </x-form>

                <x-slot:menu>
                    <span class="badge {{ $canEnable && $cloudinary_enabled ? 'badge-success' : 'badge-ghost' }}">
                        {{ $cloudinary_enabled ? 'Live' : 'Off' }}
                    </span>
                </x-slot:menu>
            </x-card>

            <x-card title="How images are sized" class="mt-4">
                <p class="text-sm text-base-content/70 mb-3">
                    With Cloudinary on, each screen asks for the size it needs and the image is
                    resized and cached on first request. Nothing is generated in advance, so a new
                    size can be added later without re-uploading anything.
                </p>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead><tr><th>Used on</th><th>Size</th></tr></thead>
                        <tbody>
                            <tr><td>Lists and upload previews</td><td>100 &times; 100</td></tr>
                            <tr><td>Shop grid and featured products</td><td>400 &times; 400</td></tr>
                            <tr><td>Product page</td><td>up to 1200 wide</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-base-content/50 mt-3">
                    Images are also converted to WebP or AVIF for browsers that accept them, which
                    is usually a large saving on mobile data.
                </p>
            </x-card>
        </x-tab>

        <x-tab name="incentives" label="Incentives" icon="o-gift">
            <x-card title="HifastLink Voucher Integration" class="mt-4">
                <p class="text-sm text-base-content/60 mb-4">
                    Customers who pay at BasmelCare can redeem their receipt number on HifastLink for free internet access.
                    Give the API key to your HifastLink administrator to paste into the integration settings.
                </p>
                <x-form wire:submit="saveIncentives">
                    <div>
                        <x-input label="API Key" wire:model="hifastlink_api_key" readonly
                            hint="Paste this into HifastLink → Network Settings → Pharmacy Integration" />
                        <div class="mt-2">
                            <x-button label="Generate New Key" wire:click="regenerateApiKey" class="btn-warning btn-sm"
                                icon="o-arrow-path"
                                wire:confirm="This will invalidate the old key. HifastLink will stop working until you update it there. Continue?" />
                        </div>
                    </div>
                    <x-input label="Voucher Validity (hours)" wire:model="voucher_validity_hours"
                        type="number" min="1" max="168"
                        hint="How long free internet lasts after redemption. Default: 24 hours" class="max-w-xs mt-4" />
                    <x-input label="HifastLink URL" wire:model="hifastlink_url"
                        placeholder="https://hifastlink.com"
                        hint="Base URL of HifastLink — used to revoke a receipt's internet access." class="mt-4" />
                    <x-slot:actions>
                        <x-button label="Save" type="submit" class="btn-primary" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </x-tab>

        <x-tab name="notifications" label="Notifications" icon="o-bell">
            <x-card title="Alert Preferences" class="mt-4">
                <x-form wire:submit="saveNotifications">
                    <x-toggle label="Low Stock Alerts" wire:model="notify_low_stock" hint="Alert when products fall below reorder level" />
                    <x-toggle label="Expiry Alerts" wire:model="notify_expiry" hint="Alert when products are nearing expiration" class="mt-4" />
                    <x-input label="Expiry Alert Window (days)" wire:model="expiry_alert_days" type="number" hint="Alert this many days before expiry" class="mt-4 max-w-xs" />
                    <x-slot:actions>
                        <x-button label="Save Notification Settings" type="submit" class="btn-primary" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </x-tab>

        <x-tab name="returns" label="Returns" icon="o-arrow-uturn-left">
            <x-card title="Return Policy" subtitle="Controls when and how product returns are accepted" class="mt-4">
                <x-form wire:submit="saveReturnSettings">
                    <x-input
                        label="Return Window (hours)"
                        wire:model="return_window_hours"
                        type="number"
                        min="1"
                        max="168"
                        hint="How many hours after a sale a return is allowed. Default: 48 hours."
                        class="max-w-xs"
                    />
                    <x-toggle
                        label="Require Customer on Return"
                        wire:model="return_require_customer"
                        hint="Block returns on walk-in sales (no customer attached). Disable to allow walk-in returns without credit."
                        class="mt-4"
                    />
                    <x-input
                        label="Commission per Customer (₦)"
                        wire:model="commission_amount"
                        type="number"
                        step="1"
                        min="0"
                        hint="Promoters earn this when a customer they registered connects to the Wi-Fi. Cashiers and sales earn it on a completed sale."
                        class="max-w-xs mt-4"
                    />
                    <x-input
                        label="Default Daily Target (promoters)"
                        wire:model="promoter_target_default"
                        type="number"
                        step="1"
                        min="1"
                        hint="Customers connected per day. Individual promoters can be given their own target on the Staff page."
                        class="max-w-xs mt-4"
                    />

                    <div class="mt-4 max-w-xl">
                        <x-select
                            label="Coupon to advertise to new customers"
                            wire:model.live="promoter_coupon_code"
                            :options="$advertisableCoupons"
                            option-value="id"
                            option-label="name"
                            placeholder="Don't advertise a coupon"
                            hint="Added to the Wi-Fi code message a promoter's customer receives."
                        />

                        @if($advertisableCoupons->isEmpty())
                            <div class="flex items-start gap-2 p-3 mt-2 bg-warning/10 rounded-lg text-sm">
                                <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0 mt-0.5 text-warning" />
                                <span>
                                    No coupon can be advertised right now. A coupon must be active, not
                                    expired, still have uses left, and not apply automatically
                                    &mdash; an auto-applying coupon needs no code.
                                </span>
                            </div>
                        @endif

                        @php
                            $previewCoupon = $promoter_coupon_code
                                ? \App\Models\Coupon::where('code', $promoter_coupon_code)->first()
                                : null;
                        @endphp

                        @if($previewCoupon && $previewCoupon->isAdvertisable())
                            <div class="mt-3">
                                <p class="text-xs text-base-content/60 mb-1">The customer receives:</p>
                                <div class="p-3 rounded-lg bg-base-200 text-sm leading-relaxed">
                                    Welcome to {{ \App\Models\AppSetting::get('pharmacy_name', 'BasmelCare') }}!
                                    Your free Wi-Fi code is: <span class="font-mono font-bold">7K4M2P</span>.
                                    Connect to the {{ \App\Models\AppSetting::get('pharmacy_name', 'BasmelCare') }}
                                    network and enter it to get {{ $voucher_validity_hours }} hours of internet.
                                    <span class="text-primary font-medium">
                                        Show this code at {{ \App\Models\AppSetting::get('pharmacy_name', 'BasmelCare') }}
                                        for {{ $previewCoupon->offerSummary() }}:
                                        <span class="font-mono font-bold">{{ $previewCoupon->code }}</span>.
                                        {{ $previewCoupon->conditionsSummary() }}
                                    </span>
                                </div>
                                <p class="text-xs text-base-content/50 mt-1">
                                    Sent by WhatsApp where possible. If it falls back to SMS this is more
                                    than one message, so it costs more to send.
                                </p>
                            </div>
                        @endif
                    </div>

                    <x-slot:actions>
                        <x-button label="Save Return Settings" type="submit" class="btn-primary" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </x-tab>
    </x-tabs>
</div>
