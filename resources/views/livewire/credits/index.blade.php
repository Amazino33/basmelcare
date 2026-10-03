<div>
    <x-header title="Change Owed" subtitle="Customers with stored credit — change the cashier couldn't give at the time" size="text-xl">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search name or phone..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
    </x-header>

    <!-- Summary cards -->
    <div class="grid grid-cols-2 gap-3 mb-4">
        <x-stat
            title="Customers Owed"
            :value="$totalCount"
            icon="o-users"
            class="bg-info/10"
        />
        <x-stat
            title="Total Credit"
            value="₦{{ number_format($totalCredit, 2) }}"
            icon="o-banknotes"
            class="bg-warning/10"
        />
    </div>

    <!-- Customer list -->
    @forelse($customers as $customer)
        <div class="flex justify-between items-center p-3 bg-base-200 rounded-lg mb-2">
            <div class="min-w-0 flex-1">
                <div class="font-bold text-sm">{{ $customer->name }}</div>
                @if($customer->phone)
                    <div class="text-xs text-base-content/60">{{ $customer->phone }}</div>
                @endif
            </div>
            <div class="text-right ml-3 shrink-0 space-y-1">
                <div class="font-bold text-info text-base">₦{{ number_format($customer->credit_balance, 2) }}</div>
                <div class="flex items-center justify-end gap-1">
                    @if($customer->credit_balance > 0)
                        <x-button
                            label="Pay Out"
                            wire:click="openPayout({{ $customer->id }})"
                            class="btn-xs btn-info"
                            icon="o-banknotes"
                        />
                    @endif
                    @if($this->canManageCredit())
                        <x-button
                            label="Adjust"
                            wire:click="openAdjustment({{ $customer->id }})"
                            class="btn-xs btn-outline btn-warning"
                            icon="o-pencil-square"
                            tooltip="Correct or adjust balance"
                        />
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="text-center py-16 text-base-content/60">
            <x-icon name="o-check-circle" class="w-14 h-14 mx-auto mb-3 opacity-30" />
            <p class="font-semibold">No credits outstanding</p>
            <p class="text-sm mt-1">All change has been settled.</p>
        </div>
    @endforelse

    {{ $customers->links() }}

    <!-- Payout History -->
    <div class="mt-8">
        <div class="text-sm font-bold text-base-content/70 uppercase tracking-wide mb-3">Payout & Adjustment History</div>

        @forelse($history as $payout)
            <div class="flex justify-between items-center p-3 bg-base-200 rounded-lg mb-2 text-sm">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold">{{ $payout->customer->name ?? 'Customer' }}</span>
                        @if(str_contains($payout->note ?? '', 'VOIDED'))
                            <span class="badge badge-error badge-xs">Voided</span>
                        @elseif($payout->amount <= 0.001)
                            <span class="badge badge-warning badge-xs">Balance Correction</span>
                        @endif
                    </div>
                    <div class="text-xs text-base-content/60">
                        {{ $payout->created_at->format('d M Y, h:i A') }}
                        · by {{ $payout->cashier->name ?? 'Staff' }}
                    </div>
                    @if($payout->note)
                        <div class="text-xs text-base-content/70 mt-0.5 italic">{{ $payout->note }}</div>
                    @endif
                    @if($payout->balance_after > 0)
                        <div class="text-xs text-warning mt-0.5">Balance after: ₦{{ number_format($payout->balance_after, 2) }}</div>
                    @else
                        <div class="text-xs text-success mt-0.5">Fully settled</div>
                    @endif
                </div>
                <div class="text-right ml-3 shrink-0 space-y-1">
                    <div class="font-bold {{ $payout->amount > 0 ? 'text-success' : 'text-warning' }}">
                        ₦{{ number_format($payout->amount, 2) }}
                    </div>
                    <div class="flex items-center justify-end gap-1">
                        @if($payout->amount > 0 && !str_starts_with($payout->note ?? '', '[VOIDED]'))
                            <a href="{{ route('credit-payout.receipt', $payout->id) }}" target="_blank"
                                class="btn btn-xs btn-ghost tooltip" data-tip="Print receipt">
                                <x-icon name="o-printer" class="w-3 h-3" />
                            </a>
                            @if($this->canManageCredit())
                                <x-button
                                    wire:click="voidPayout({{ $payout->id }})"
                                    wire:confirm="Void this payout? The amount (₦{{ number_format($payout->amount, 2) }}) will be restored to the customer's credit balance."
                                    icon="o-x-mark"
                                    class="btn-xs btn-ghost text-error tooltip"
                                    data-tip="Void payout"
                                />
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-8 text-base-content/40 text-sm">No payouts recorded yet.</div>
        @endforelse

        {{ $history->links() }}
    </div>

    <!-- Payout Modal -->
    <x-modal wire:model="payoutModal" title="{{ $payoutSuccess ? 'Payout Complete' : 'Pay Out Credit' }}" box-class="max-w-sm">
        @if($payoutSuccess && $lastPayoutId)
            <div class="text-center py-4">
                <x-icon name="o-check-circle" class="w-14 h-14 text-success mx-auto mb-3" />
                <div class="font-bold text-lg mb-1">Paid Out Successfully</div>
                <p class="text-sm text-base-content/60 mb-4">Print 2 copies — customer keeps one as proof of collection.</p>
                <div class="flex gap-2 justify-center">
                    <a href="{{ route('credit-payout.receipt', $lastPayoutId) }}" target="_blank"
                        class="btn btn-primary btn-sm gap-2">
                        <x-icon name="o-printer" class="w-4 h-4" /> Print Receipt
                    </a>
                    <x-button label="Done" @click="$wire.payoutModal = false" class="btn-ghost btn-sm" />
                </div>
            </div>
        @elseif($payingCustomer)
            <div class="bg-base-200 rounded-lg p-3 mb-4 text-sm">
                <div class="font-bold">{{ $payingCustomer->name }}</div>
                @if($payingCustomer->phone)
                    <div class="text-base-content/60">{{ $payingCustomer->phone }}</div>
                @endif
                <div class="mt-1">Total credit: <span class="font-bold text-info">₦{{ number_format($payingCustomer->credit_balance, 2) }}</span></div>
            </div>

            <x-form wire:submit="recordPayout">
                <x-input
                    wire:model="payout_amount"
                    label="Amount to pay out (₦)"
                    type="number"
                    step="0.01"
                    min="0.01"
                    max="{{ $payingCustomer->credit_balance }}"
                    prefix="₦"
                    hint="Defaults to full balance — reduce if paying partial"
                />
                <x-slot:actions>
                    <x-button label="Cancel" @click="$wire.payoutModal = false" />
                    <x-button label="Confirm Pay Out" type="submit" class="btn-info" icon="o-check" />
                </x-slot:actions>
            </x-form>
        @endif
    </x-modal>

    <!-- Adjustment Modal -->
    <x-modal wire:model="adjustModal" title="Correct / Adjust Credit Balance" box-class="max-w-md">
        @if($adjustCustomer)
            <div class="bg-base-200 rounded-lg p-3 mb-4 text-sm">
                <div class="font-bold">{{ $adjustCustomer->name }}</div>
                @if($adjustCustomer->phone)
                    <div class="text-base-content/60">{{ $adjustCustomer->phone }}</div>
                @endif
                <div class="mt-1">
                    Current Credit Balance:
                    <span class="font-bold text-info">₦{{ number_format($adjustCustomer->credit_balance, 2) }}</span>
                </div>
            </div>

            <x-form wire:submit="saveAdjustment">
                <x-input
                    wire:model="new_balance"
                    label="Corrected Balance (₦)"
                    type="number"
                    step="0.01"
                    min="0"
                    prefix="₦"
                    hint="Enter the actual balance this customer should have (e.g. 0.00 if mistakenly entered)"
                    required
                />

                <x-textarea
                    wire:model="adjust_reason"
                    label="Reason for Correction"
                    placeholder="e.g., Mistakenly entered ₦400 change at till, cashier error corrected"
                    hint="Required for audit and accountability"
                    rows="3"
                    required
                />

                <x-slot:actions>
                    <x-button label="Cancel" @click="$wire.adjustModal = false" />
                    <x-button label="Save Correction" type="submit" class="btn-warning" icon="o-check" spinner="saveAdjustment" />
                </x-slot:actions>
            </x-form>
        @endif
    </x-modal>
</div>
