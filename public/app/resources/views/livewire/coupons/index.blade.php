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
                            @php
                                $rules = [];
                                $ct = $coupon->customer_type ?? 'all';
                                if ($ct !== 'all') $rules[] = ucfirst($ct) . ' customers only';
                                if ($coupon->min_order_amount) $rules[] = 'Min ₦' . number_format($coupon->min_order_amount, 0);
                                if ($coupon->max_order_amount) $rules[] = 'Max ₦' . number_format($coupon->max_order_amount, 0);
                                if (!empty($coupon->restricted_categories)) $rules[] = count($coupon->restricted_categories) . ' categor' . (count($coupon->restricted_categories) > 1 ? 'ies' : 'y');
                                if (!empty($coupon->restricted_products))   $rules[] = count($coupon->restricted_products) . ' product' . (count($coupon->restricted_products) > 1 ? 's' : '');
                                if ($coupon->min_item_count) $rules[] = 'Min ' . $coupon->min_item_count . ' items';
                            @endphp
                            <tr class="hover">
                                <td class="font-mono font-bold">{{ $coupon->code }}</td>
                                <td>
                                    @if($coupon->type === 'percent')
                                        {{ $coupon->value }}% off
                                        @if($coupon->max_discount)
                                            <span class="text-xs text-base-content/50">(max ₦{{ number_format($coupon->max_discount, 2) }})</span>
                                        @endif
                                    @else
                                        ₦{{ number_format($coupon->value, 2) }} off
                                    @endif
                                    @if($rules)
                                        <div class="text-xs text-base-content/40 mt-0.5 font-normal">{{ implode(' · ', $rules) }}</div>
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
    <x-modal wire:model="couponModal" title="{{ $couponId ? 'Edit Coupon' : 'New Coupon' }}" box-class="max-w-lg">
        <x-form wire:submit="save">
            {{-- Core fields --}}
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

            @if($type === 'percent')
                <x-input
                    label="Maximum Discount Cap (₦)"
                    wire:model="max_discount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    prefix="₦"
                    hint="e.g. 10% off but never more than ₦500. Leave blank for no cap."
                />
            @endif

            <x-input label="Max Uses" wire:model="max_uses" type="number" min="1" hint="Leave blank for unlimited" />
            <x-input label="Expiry Date" wire:model="expires_at" type="date" hint="Leave blank for no expiry" />
            <x-checkbox label="Active" wire:model="is_active" />

            {{-- Restrictions --}}
            <div class="divider text-xs text-base-content/40 my-1">Optional Restrictions</div>

            {{-- Customer Type --}}
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model.live="enableCustomerType" class="checkbox checkbox-sm checkbox-primary" />
                <span class="text-sm font-medium">Restrict by customer type</span>
            </label>
            @if($enableCustomerType)
                <div class="pl-6">
                    <x-select
                        wire:model="customer_type"
                        :options="[
                            ['id'=>'new',      'name'=>'New customers only (first purchase)'],
                            ['id'=>'returning','name'=>'Returning customers only'],
                        ]"
                        option-value="id"
                        option-label="name"
                    />
                </div>
            @endif

            {{-- Order Amount --}}
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model.live="enableOrderAmount" class="checkbox checkbox-sm checkbox-primary" />
                <span class="text-sm font-medium">Restrict by order amount</span>
            </label>
            @if($enableOrderAmount)
                <div class="pl-6 grid grid-cols-2 gap-3">
                    <x-input label="Minimum (₦)" wire:model="min_order_amount" type="number" step="0.01" min="0.01" prefix="₦" hint="Leave blank for no minimum" />
                    <x-input label="Maximum (₦)" wire:model="max_order_amount" type="number" step="0.01" min="0.01" prefix="₦" hint="Leave blank for no maximum" />
                </div>
            @endif

            {{-- Category Restriction --}}
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model.live="enableCategories" class="checkbox checkbox-sm checkbox-primary" />
                <span class="text-sm font-medium">Restrict to specific categories</span>
            </label>
            @if($enableCategories)
                <div class="pl-6">
                    <x-choices-offline
                        label="Categories (discount applies only to items in these categories)"
                        wire:model="restricted_categories"
                        :options="$categories"
                        option-value="id"
                        option-label="name"
                        searchable
                    />
                </div>
            @endif

            {{-- Product Restriction --}}
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model.live="enableProducts" class="checkbox checkbox-sm checkbox-primary" />
                <span class="text-sm font-medium">Restrict to specific products</span>
            </label>
            @if($enableProducts)
                <div class="pl-6">
                    <x-choices-offline
                        label="Products (discount applies only to these products)"
                        wire:model="restricted_products"
                        :options="$products"
                        option-value="id"
                        option-label="name"
                        searchable
                    />
                </div>
            @endif

            {{-- Min Item Count --}}
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model.live="enableItemCount" class="checkbox checkbox-sm checkbox-primary" />
                <span class="text-sm font-medium">Require a minimum number of items</span>
            </label>
            @if($enableItemCount)
                <div class="pl-6">
                    <x-input
                        label="Minimum Items in Cart"
                        wire:model="min_item_count"
                        type="number"
                        min="1"
                        hint="Counts total quantity — 2 packs of the same product counts as 2."
                    />
                </div>
            @endif

            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.couponModal = false" />
                <x-button label="Save" type="submit" class="btn-primary" icon="o-check" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
