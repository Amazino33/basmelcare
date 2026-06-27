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

            <x-card title="Send Test Message" class="mt-4">
                <x-form wire:submit="sendTest">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-input label="Phone Number" wire:model="test_phone" placeholder="08012345678" />
                        <x-input label="Message" wire:model="test_message" />
                    </div>
                    <x-slot:actions>
                        <x-button label="Send Test" type="submit" class="btn-secondary" icon="o-paper-airplane" />
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
    </x-tabs>
</div>
