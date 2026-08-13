<div>
    <x-header title="Coupons" subtitle="Manage discount codes for the cashier" size="text-xl">
        <x-slot:actions>
            <x-button label="New Coupon" wire:click="openCreate" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    @if($coupons->isEmpty())
        <x-card>
            <div class="text-center py-10 text-base-content/50">
                <x-icon name="o-tag" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                <p class="font-semibold">No coupons yet</p>
                <p class="text-sm mt-1">Create a coupon code to offer discounts at the cashier.</p>
            </div>
        </x-card>
    @else
        <x-card>
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Discount</th>
                            <th>Used</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($coupons as $coupon)
                            <tr class="hover">
                                <td class="font-mono font-bold">{{ $coupon->code }}</td>
                                <td>
                                    @if($coupon->type === 'percent')
                                        {{ $coupon->value }}% off
                                    @else
                                        ₦{{ number_format($coupon->value, 2) }} off
                                    @endif
                                </td>
                                <td>
                                    {{ $coupon->used_count }}
                                    @if($coupon->max_uses !== null)
                                        / {{ $coupon->max_uses }}
                                    @else
                                        <span class="text-base-content/40">/ ∞</span>
                                    @endif
                                </td>
                                <td>
                                    @if($coupon->expires_at)
                                        <span class="{{ $coupon->expires_at->isPast() ? 'text-error font-semibold' : 'text-base-content/70' }}">
                                            {{ $coupon->expires_at->format('d M Y') }}
                                        </span>
                                    @else
                                        <span class="text-base-content/40">No expiry</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" wire:click="toggleActive({{ $coupon->id }})"
                                        class="badge {{ $coupon->is_active ? 'badge-success' : 'badge-ghost' }} cursor-pointer text-xs">
                                        {{ $coupon->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <x-button icon="o-pencil" wire:click="openEdit({{ $coupon->id }})" class="btn-ghost btn-xs" />
                                        <x-button icon="o-trash" wire:click="delete({{ $coupon->id }})"
                                            wire:confirm="Delete coupon {{ $coupon->code }}?"
                                            class="btn-ghost btn-xs text-error" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    <!-- Coupon Modal -->
    <x-modal wire:model="couponModal" title="{{ $couponId ? 'Edit Coupon' : 'New Coupon' }}" box-class="max-w-sm">
        <x-form wire:submit="save">
            <x-input
                label="Code"
                wire:model="code"
                placeholder="e.g. SAVE500"
                hint="Uppercase letters, numbers, dashes only"
                class="uppercase"
            />

            <x-select
                label="Discount Type"
                wire:model.live="type"
                :options="[['id'=>'fixed','name'=>'Fixed Amount (₦)'],['id'=>'percent','name'=>'Percentage (%)']]"
                option-value="id"
                option-label="name"
            />

            <x-input
                label="{{ $type === 'percent' ? 'Percentage (1–100)' : 'Amount (₦)' }}"
                wire:model="value"
                type="number"
                step="0.01"
                min="0.01"
                :max="$type === 'percent' ? 100 : null"
                :prefix="$type === 'fixed' ? '₦' : null"
                :suffix="$type === 'percent' ? '%' : null"
            />

            <x-input
                label="Max Uses"
                wire:model="max_uses"
                type="number"
                min="1"
                hint="Leave blank for unlimited"
            />

            <x-input
                label="Expiry Date"
                wire:model="expires_at"
                type="date"
                hint="Leave blank for no expiry"
            />

            <x-checkbox label="Active" wire:model="is_active" />

            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.couponModal = false" />
                <x-button label="Save" type="submit" class="btn-primary" icon="o-check" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
