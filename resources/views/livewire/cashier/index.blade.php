<div>
    <x-header title="Cashier" subtitle="Process invoice payments">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search invoice # or customer..." wire:model.live.debounce="searchInvoice" clearable class="w-72" />
        </x-slot:middle>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Pending Invoices -->
        <x-card title="Pending Invoices" subtitle="{{ $pendingInvoices->count() }} awaiting payment">
            @forelse($pendingInvoices as $invoice)
                <div class="flex justify-between items-center p-3 bg-base-200 rounded-lg mb-2 hover:bg-base-300 transition-all">
                    <div>
                        <div class="font-bold">{{ $invoice->invoice_number }}</div>
                        <div class="text-sm">{{ $invoice->customer?->name ?? 'Walk-in' }}</div>
                        <div class="text-xs text-base-content/60">By: {{ $invoice->user->name }} | {{ $invoice->created_at->format('H:i') }}</div>
                        <div class="text-xs text-base-content/60 mt-1">
                            {{ $invoice->saleItems->count() }} item(s):
                            {{ $invoice->saleItems->take(3)->map(fn($i) => $i->product->name)->join(', ') }}{{ $invoice->saleItems->count() > 3 ? '...' : '' }}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-bold text-primary">₦{{ number_format($invoice->total_amount, 2) }}</div>
                        <x-button label="Pay" wire:click="openPayment({{ $invoice->id }})" class="btn-xs btn-primary mt-1" icon="o-banknotes" />
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-base-content/60">
                    <x-icon name="o-check-circle" class="w-12 h-12 mx-auto mb-2 opacity-30" />
                    <p>No pending invoices</p>
                </div>
            @endforelse
        </x-card>

        <!-- Recently Paid -->
        <x-card title="Recently Paid" subtitle="Awaiting goods handover">
            @forelse($recentPaid as $invoice)
                <div class="flex justify-between items-center p-3 border-b border-base-200 last:border-0">
                    <div>
                        <div class="font-semibold text-sm">{{ $invoice->invoice_number }}</div>
                        <div class="text-xs text-base-content/60">{{ $invoice->customer?->name ?? 'Walk-in' }} | {{ ucfirst($invoice->payment_method) }}</div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold">₦{{ number_format($invoice->total_amount, 2) }}</div>
                        <x-badge value="Paid" class="badge-success badge-xs" />
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-base-content/60 text-sm">No recent payments.</div>
            @endforelse
        </x-card>
    </div>

    <!-- Payment Modal -->
    <x-modal wire:model="payModal" title="Process Payment" box-class="max-w-lg">
        @if($payingSale)
            <!-- Invoice Summary -->
            <div class="bg-base-200 rounded-lg p-4 mb-4">
                <div class="flex justify-between mb-2">
                    <span class="font-bold text-lg">{{ $payingSale->invoice_number }}</span>
                    <span class="text-lg font-bold text-primary">₦{{ number_format($payingSale->total_amount, 2) }}</span>
                </div>
                <div class="text-sm text-base-content/60">
                    Customer: {{ $payingSale->customer?->name ?? 'Walk-in' }} |
                    Sales: {{ $payingSale->user->name }}
                </div>

                <x-hr class="my-2" />

                <div class="space-y-1">
                    @foreach($payingSale->saleItems as $item)
                        <div class="flex justify-between text-sm">
                            <span>{{ $item->product->name }} × {{ $item->quantity }}</span>
                            <span>₦{{ number_format($item->subtotal, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <x-form wire:submit="processPayment">
                <x-select label="Payment Method" wire:model.live="payment_method" :options="[
                    ['id' => 'cash', 'name' => 'Cash (Full)'],
                    ['id' => 'card', 'name' => 'Card (Full)'],
                    ['id' => 'transfer', 'name' => 'Transfer (Full)'],
                    ['id' => 'split', 'name' => 'Split Payment'],
                    ['id' => 'part_payment', 'name' => 'Part Payment (Balance on Debt)'],
                    ['id' => 'credit', 'name' => 'Full Credit (Debt)'],
                ]" option-value="id" option-label="name" />

                @if($payment_method === 'split')
                    <div class="bg-base-200 rounded-lg p-3 mt-3 space-y-2">
                        <div class="text-xs font-semibold text-base-content/60 uppercase">Split Amount (Total: ₦{{ number_format($payingSale->total_amount, 2) }})</div>
                        <x-input wire:model.blur="split_cash" prefix="₦" type="number" step="0.01" placeholder="0.00" label="Cash" />
                        <x-input wire:model.blur="split_transfer" prefix="₦" type="number" step="0.01" placeholder="0.00" label="Transfer" />
                        <x-input wire:model.blur="split_card" prefix="₦" type="number" step="0.01" placeholder="0.00" label="Card" />
                        @php $splitSum = (float)($split_cash ?: 0) + (float)($split_transfer ?: 0) + (float)($split_card ?: 0); @endphp
                        <div class="flex justify-between text-sm pt-1 border-t border-base-300">
                            <span>Split Total:</span>
                            <span @class(['font-bold', 'text-success' => abs($splitSum - $payingSale->total_amount) < 0.01, 'text-error' => abs($splitSum - $payingSale->total_amount) >= 0.01])>
                                ₦{{ number_format($splitSum, 2) }}
                            </span>
                        </div>
                    </div>
                @endif

                @if($payment_method === 'part_payment')
                    <div class="bg-base-200 rounded-lg p-3 mt-3 space-y-2">
                        <div class="text-xs font-semibold text-base-content/60 uppercase">Part Payment (Total: ₦{{ number_format($payingSale->total_amount, 2) }})</div>
                        <x-input wire:model.blur="part_amount" prefix="₦" type="number" step="0.01" label="Amount Paying Now" />
                        <x-select label="Paying With" wire:model="part_method" :options="[
                            ['id' => 'cash', 'name' => 'Cash'],
                            ['id' => 'card', 'name' => 'Card'],
                            ['id' => 'transfer', 'name' => 'Transfer'],
                        ]" option-value="id" option-label="name" />
                        @php $partPaid = (float)($part_amount ?: 0); $partBalance = (float)$payingSale->total_amount - $partPaid; @endphp
                        @if($partPaid > 0)
                            <div class="flex justify-between text-sm pt-1 border-t border-base-300">
                                <span>Balance to Debt Book:</span>
                                <span class="font-bold text-error">₦{{ number_format(max(0, $partBalance), 2) }}</span>
                            </div>
                        @endif
                        @if(!$payingSale->customer_id)
                            <x-alert title="No Customer" description="Part payment requires a customer. This invoice has none." icon="o-x-circle" class="alert-error" />
                        @endif
                    </div>
                @endif

                @if($payment_method === 'credit')
                    @if($payingSale->customer_id)
                        <x-alert title="Full Credit" description="The full amount (₦{{ number_format($payingSale->total_amount, 2) }}) will be added to {{ $payingSale->customer->name }}'s debt book." icon="o-exclamation-triangle" class="alert-warning mt-3" />
                    @else
                        <x-alert title="No Customer" description="Credit requires a customer. This invoice has no customer assigned." icon="o-x-circle" class="alert-error mt-3" />
                    @endif
                @endif

                <x-slot:actions>
                    <x-button label="Cancel" @click="$wire.payModal = false" />
                    <x-button label="Confirm Payment" type="submit" class="btn-primary" icon="o-check" />
                </x-slot:actions>
            </x-form>
        @endif
    </x-modal>
</div>
