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

        <x-tab name="cover" label="Monthly Cover" icon="o-shield-check">
            <x-card title="Monthly cover" subtitle="Customers who pay a premium and draw medicine against it" class="mt-4">
                <x-form wire:submit="saveInsurance">
                    <x-toggle label="Cover is live" wire:model.live="insurance_enabled"
                              hint="Until this is on, cover pays for nothing at the till or online" />

                    <div class="rounded-lg border border-base-300 bg-base-200/40 p-4 text-sm">
                        <p class="font-semibold mb-1">What switching this on does</p>
                        <ul class="list-disc list-inside space-y-1 text-base-content/70">
                            <li>A subscriber's bill drops by whatever their cover pays, at the
                                counter and at online checkout.</li>
                            <li>Each claim comes off that month's cover and is recorded against
                                their subscription.</li>
                            <li>Unused cover does not carry into the next month.</li>
                        </ul>
                        <p class="text-base-content/60 mt-2">
                            Switching it off later does not cancel anybody. Their cover simply
                            stops being applied, and resumes if it is switched back on.
                        </p>
                    </div>

                    @if($insurance_enabled)
                        <div class="alert alert-warning py-2 text-sm gap-2">
                            <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
                            <span>
                                Cover is medicine given away against a premium already collected.
                                Check <a href="{{ route('insurance.plans') }}" class="link">Cover Plans</a>
                                for what each plan could pay out in a month before you sell any.
                            </span>
                        </div>
                    @endif

                    <x-slot:actions>
                        <x-button label="Save" type="submit" class="btn-primary" spinner="saveInsurance" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </x-tab>

        <x-tab name="alerts" label="Counter Alerts" icon="o-bell-alert">
            <x-card title="Calling the pharmacist" subtitle="What happens when nobody answers on screen" class="mt-4">
                <x-form wire:submit="savePharmacistAlerts">
                    <x-toggle label="Also ring their phones"
                              wire:model.live="pharmacist_call_alert_enabled"
                              hint="Sends a WhatsApp, falling back to SMS, if the call goes unanswered" />

                    @if($pharmacist_call_alert_enabled)
                        <x-input label="Wait this long first" wire:model="pharmacist_call_alert_after_seconds"
                                 type="number" min="15" max="600" suffix="seconds"
                                 hint="A pharmacist at a screen sees the banner within five seconds" />
                    @endif

                    <div class="rounded-lg border border-base-300 bg-base-200/40 p-4 text-sm">
                        <p class="font-semibold mb-1">What happens when the counter calls</p>
                        <ol class="list-decimal list-inside space-y-1 text-base-content/70">
                            <li>A banner appears for any pharmacist with the app open, and it chimes.</li>
                            <li>If nobody taps &ldquo;On my way&rdquo; within the wait above, their phones ring
                                &mdash; but only if this is switched on.</li>
                            <li>The call goes quiet on its own after fifteen minutes.</li>
                        </ol>
                        <p class="text-base-content/60 mt-2">
                            Every active pharmacist at that branch with a number on file is messaged, once
                            per call. There is no rota in the system, so it cannot tell who is actually on
                            duty &mdash; everyone who could answer is told.
                        </p>
                        <p class="text-base-content/60 mt-2">
                            It goes through the same WhatsApp gateway as your receipts, which is why this
                            is off unless you turn it on.
                        </p>
                    </div>

                    <x-slot:actions>
                        <x-button label="Save Alert Settings" type="submit" class="btn-primary" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </x-tab>

        <x-tab name="consultations" label="Consultations" icon="o-chat-bubble-left-right">
            <x-card title="What a consultation costs" subtitle="Per provider and per mode" class="mt-4">
                <x-form wire:submit="saveConsultations">
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th></th>
                                    @foreach(\App\Support\ConsultationPricing::MODES as $mode => $modeLabel)
                                        <th>{{ $modeLabel }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(\App\Support\ConsultationPricing::PROVIDERS as $provider => $providerLabel)
                                    <tr>
                                        <td class="font-semibold whitespace-nowrap">{{ $providerLabel }}</td>
                                        @foreach(array_keys(\App\Support\ConsultationPricing::MODES) as $mode)
                                            <td>
                                                <x-input wire:model="consult_prices.{{ $provider }}.{{ $mode }}"
                                                         prefix="₦" type="number" step="0.01" min="0" class="input-sm" />
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <x-input label="Free consultations" wire:model="consult_free_count"
                                 type="number" min="0" max="20"
                                 hint="How many a customer gets at no charge" />

                        <x-select label="How often" wire:model="consult_free_period"
                                  :options="collect(\App\Support\ConsultationPricing::FREE_PERIODS)->map(fn($l, $v) => ['id' => $v, 'name' => $l])->values()"
                                  option-value="id" option-label="name" />
                    </div>

                    <div class="rounded-lg border border-base-300 bg-base-200/40 p-4 text-sm mt-2">
                        <p class="font-semibold mb-1">How the free one works</p>
                        <p class="text-base-content/70">
                            A customer inside their allowance is charged nothing, and the
                            consultation is recorded as free. Changing these settings later does
                            not re-price consultations already given &mdash; what was free stays
                            free, and the count is of what actually happened.
                        </p>
                        <p class="text-base-content/60 mt-2">
                            The system records whether a consultation is by video, text or in
                            person so it can be priced and prepared for. It does not host the call
                            or the chat &mdash; staff arrange those as they do now.
                        </p>
                    </div>

                    <x-slot:actions>
                        <x-button label="Save Consultation Settings" type="submit" class="btn-primary" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </x-tab>

        <x-tab name="pricing" label="Wholesale" icon="o-tag">
            <x-card title="Wholesale Pricing" subtitle="What customers tagged as wholesale pay" class="mt-4">
                <x-form wire:submit="saveWholesalePricing">
                    <x-input label="Markup over cost" wire:model="wholesale_markup_percent"
                             type="number" step="0.01" min="0" max="100" suffix="%"
                             hint="Applied to the dearest batch still in stock" />

                    <div class="rounded-lg border border-base-300 bg-base-200/40 p-4 text-sm space-y-2">
                        <p class="font-semibold">How a wholesale price is decided</p>
                        <ol class="list-decimal list-inside space-y-1 text-base-content/70">
                            <li>A price typed into the product wins, if one is set.</li>
                            <li>Otherwise the dearest batch still in stock, plus this markup.</li>
                            <li>If nothing is in stock, the normal retail price is used.</li>
                        </ol>
                        <p class="text-base-content/60 pt-1">
                            The dearest batch is used on purpose. Pricing off older, cheaper stock
                            sells goods for less than it costs to replace them &mdash; a profit on
                            paper and a loss in the drawer.
                        </p>
                        <p class="text-base-content/60">
                            Individual products can override this figure on the product form.
                        </p>
                    </div>

                    <x-slot:actions>
                        <x-button label="Save Wholesale Pricing" type="submit" class="btn-primary" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </x-tab>

        <x-tab name="whatsapp" label="WhatsApp" icon="o-chat-bubble-left-right">
            <x-card title="WhatsApp Integration (WAWP)" class="mt-4">
                <x-form wire:submit="saveWhatsApp">
                    <x-toggle label="Enable WhatsApp" wire:model.live="wawp_enabled" hint="Send notifications via WhatsApp" />
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
                $outstanding = $this->imagesAwaitingUpload();
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

                            <x-button label="{{ $syncedCount > 0 && $outstanding > 0 ? 'Continue uploading' : 'Upload images to Cloudinary' }}"
                                      icon="o-cloud-arrow-up"
                                      wire:click="uploadImagesToCloud"
                                      spinner="uploadImagesToCloud"
                                      class="btn-sm btn-outline mt-3"
                                      :disabled="$cloudinary_cloud_name === '' || $cloudinary_api_key === ''" />

                            <p class="text-xs text-base-content/50 mt-2">
                                Save your credentials first. Images go up 25 at a time, so a large
                                catalogue takes a few clicks &mdash; each one carries on from the last.
                            </p>

                            @if($outstanding > 25)
                                {{-- A few hundred images is a lot of clicking; the command does it in one go --}}
                                <p class="text-xs text-base-content/50 mt-1">
                                    For {{ $outstanding }} images it is quicker to run this once on the server:
                                    <code class="text-[11px]">php artisan products:upload-to-cloud</code>
                                </p>
                            @endif
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
                        label="Only registered customers may return"
                        wire:model="return_require_customer"
                        hint="Off by default. A walk-in return is refunded in cash from the till, since there is no account to credit. Turn this on only if every return must be tied to a named person."
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
