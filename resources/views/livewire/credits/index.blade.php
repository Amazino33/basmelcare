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
                <x-button
                    label="Pay Out"
                    wire:click="openPayout({{ $customer->id }})"
                    class="btn-xs btn-info"
                    icon="o-banknotes"
                />
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
        <div class="text-sm font-bold text-base-content/70 uppercase tracking-wide mb-3">Payout History</div>

        @forelse($history as $payout)
            <div class="flex justify-between items-center p-3 bg-base-200 rounded-lg mb-2 text-sm">
                <div class="min-w-0 flex-1">
                    <div class="font-semibold">{{ $payout->customer->name }}</div>
                    <div class="text-xs text-base-content/60">
                        {{ $payout->created_at->format('d M Y, h:i A') }}
                        · by {{ $payout->cashier->name }}
                    </div>
                    @if($payout->balance_after > 0)
                        <div class="text-xs text-warning mt-0.5">Balance after: ₦{{ number_format($payout->balance_after, 2) }}</div>
                    @else
                        <div class="text-xs text-success mt-0.5">Fully settled</div>
                    @endif
                </div>
                <div class="text-right ml-3 shrink-0">
                    <div class="font-bold text-success">₦{{ number_format($payout->amount, 2) }}</div>
                </div>
            </div>
        @empty
            <div class="text-center py-8 text-base-content/40 text-sm">No payouts recorded yet.</div>
        @endforelse

        {{ $history->links() }}
    </div>

    <!-- Payout Modal -->
    <x-modal wire:model="payoutModal" title="Pay Out Credit" box-class="max-w-sm">
        @if($payingCustomer)
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
</div>
