@if($myInvoices->count() || $recentCompleted->count())
    <div class="mt-4">
        <x-card title="My Invoices" subtitle="Pending and paid">
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($myInvoices as $invoice)
                            <tr @class(['bg-success/10' => $invoice->status === 'paid'])>
                                <td class="font-semibold">{{ $invoice->invoice_number }}</td>
                                <td>{{ $invoice->customer?->name ?? 'Walk-in' }}</td>
                                <td>₦{{ number_format($invoice->total_amount, 2) }}</td>
                                <td>
                                    <x-badge :value="ucfirst($invoice->status)" @class([
                                        'badge-warning' => $invoice->status === 'pending',
                                        'badge-success' => $invoice->status === 'paid',
                                    ]) />
                                </td>
                                <td class="text-xs">{{ $invoice->created_at->format('H:i') }}</td>
                                <td>
                                    <div class="flex gap-1">
                                        <x-button icon="o-printer" link="{{ route('invoice.show', $invoice->id) }}" class="btn-xs btn-ghost" external />
                                        @if($invoice->status === 'paid')
                                            <x-button label="Handover" wire:click="confirmHandover({{ $invoice->id }})" class="btn-xs btn-success" wire:confirm="Confirm goods handed to customer?" />
                                        @endif
                                        @if($invoice->status === 'pending')
                                            <x-button icon="o-x-mark" wire:click="cancelInvoice({{ $invoice->id }})" class="btn-xs btn-ghost text-error" wire:confirm="Cancel this invoice?" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        @foreach($recentCompleted as $invoice)
                            <tr class="opacity-60">
                                <td class="font-semibold">{{ $invoice->invoice_number }}</td>
                                <td>{{ $invoice->customer?->name ?? 'Walk-in' }}</td>
                                <td>₦{{ number_format($invoice->total_amount, 2) }}</td>
                                <td><x-badge value="Done" class="badge-ghost" /></td>
                                <td class="text-xs">{{ $invoice->created_at->format('H:i') }}</td>
                                <td><x-button icon="o-printer" link="{{ route('invoice.show', $invoice->id) }}" class="btn-xs btn-ghost" external /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endif
