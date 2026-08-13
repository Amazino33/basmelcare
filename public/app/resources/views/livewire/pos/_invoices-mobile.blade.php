@if($myInvoices->count() || $recentCompleted->count())
    <div class="space-y-2">
        @foreach($myInvoices as $invoice)
            <div @class(['p-3 rounded-lg border', 'bg-success/10 border-success/30' => $invoice->status === 'paid', 'border-base-300' => $invoice->status !== 'paid'])>
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-bold text-sm">{{ $invoice->invoice_number }}</div>
                        <div class="text-xs text-base-content/60">{{ $invoice->customer?->name ?? 'Walk-in' }} | {{ $invoice->created_at->format('H:i') }}</div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-sm">₦{{ number_format($invoice->total_amount, 2) }}</div>
                        <x-badge :value="ucfirst($invoice->status)" @class([
                            'badge-xs',
                            'badge-warning' => $invoice->status === 'pending',
                            'badge-success' => $invoice->status === 'paid',
                        ]) />
                    </div>
                </div>
                <div class="flex gap-2 mt-2">
                    <x-button icon="o-printer" link="{{ route('invoice.show', $invoice->id) }}" class="btn-xs btn-ghost" external />
                    @if($invoice->status === 'paid')
                        <x-button label="Handover" wire:click="confirmHandover({{ $invoice->id }})" class="btn-xs btn-success" wire:confirm="Confirm goods handed to customer?" />
                    @endif
                    @if($invoice->status === 'pending')
                        <x-button icon="o-x-mark" wire:click="cancelInvoice({{ $invoice->id }})" class="btn-xs btn-ghost text-error" wire:confirm="Cancel this invoice?" />
                    @endif
                </div>
            </div>
        @endforeach
        @foreach($recentCompleted as $invoice)
            <div class="p-3 rounded-lg border border-base-300 opacity-60">
                <div class="flex justify-between">
                    <div>
                        <div class="font-bold text-sm">{{ $invoice->invoice_number }}</div>
                        <div class="text-xs text-base-content/60">{{ $invoice->customer?->name ?? 'Walk-in' }}</div>
                    </div>
                    <div class="font-bold text-sm">₦{{ number_format($invoice->total_amount, 2) }}</div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="text-center py-6 text-base-content/60">
        <x-icon name="o-clipboard-document-list" class="w-10 h-10 mx-auto mb-2 opacity-30" />
        <p class="text-sm">No invoices yet</p>
    </div>
@endif
