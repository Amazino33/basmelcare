<div>
    <x-header title="Point of Sale" subtitle="Create invoices for customers" size="text-xl">
        @if($isWholesale)
            <x-slot:actions>
                <x-badge value="WHOLESALE" class="badge-info" />
            </x-slot:actions>
        @endif
    </x-header>

    <!-- Mobile: stacked, Desktop: side by side -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Product Selection -->
        <div class="lg:col-span-2">
            <x-input icon="o-magnifying-glass" placeholder="Search products..." wire:model.live.debounce="search" clearable class="mb-3" />

            <div class="grid grid-cols-2 gap-2">
                @forelse($products as $product)
                    @php $stock = $product->batches->sum('quantity'); @endphp
                    <button
                        wire:click="addToCart({{ $product->id }})"
                        @class([
                            'p-2 rounded-lg border text-left transition-all active:scale-95',
                            'border-base-300' => $stock > 0,
                            'border-error/30 opacity-50' => $stock == 0,
                        ])
                        @disabled($stock == 0)
                    >
                        <div class="font-semibold text-xs sm:text-sm truncate">{{ $product->name }}</div>
                        <div class="text-primary font-bold text-sm">
                            @if($isWholesale && $product->wholesale_price)
                                ₦{{ number_format($product->wholesale_price, 2) }}
                            @else
                                ₦{{ number_format($product->selling_price, 2) }}
                            @endif
                        </div>
                        <div class="text-xs text-base-content/60">Stock: {{ $stock }}</div>
                    </button>
                @empty
                    <div class="col-span-full text-center py-8 text-base-content/60">No products found.</div>
                @endforelse
            </div>
        </div>

        <!-- Cart -->
        <div>
            <x-card title="Cart" subtitle="{{ count($cart) }} item(s)">
                @if(count($cart))
                    <div class="space-y-2 max-h-60 sm:max-h-96 overflow-y-auto">
                        @foreach($cart as $key => $item)
                            <div class="flex items-center gap-2 p-2 rounded bg-base-200">
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-xs truncate">{{ $item['name'] }}</div>
                                    <div class="text-xs text-base-content/60">
                                        ₦{{ number_format($item['unit_price'], 2) }} × {{ $item['qty'] }}
                                        @if($item['unit_price'] < $item['retail_price'])
                                            <span class="text-info">(w/s)</span>
                                        @endif
                                    </div>
                                </div>
                                <input type="number" wire:change="updateQty('{{ $key }}', $event.target.value)" value="{{ $item['qty'] }}" min="1" max="{{ $item['max_qty'] }}" class="input input-xs input-bordered w-14 text-center" />
                                <x-button icon="o-x-mark" wire:click="removeFromCart('{{ $key }}')" class="btn-xs btn-ghost text-error" />
                            </div>
                        @endforeach
                    </div>

                    <x-hr />

                    <div class="text-right text-lg font-bold text-primary mb-3">
                        ₦{{ number_format($cartTotal, 2) }}
                    </div>

                    <x-choices label="Customer" wire:model.live="customer_id" :options="$customers" option-value="id" option-label="name" placeholder="Search customer..." single searchable />
                    <x-textarea label="Note" wire:model="note" placeholder="Optional" class="mt-2" rows="2" />

                    <x-button label="Create Invoice" wire:click="createInvoice" icon="o-document-text" class="btn-primary btn-block mt-3" wire:confirm="Create this invoice?" />
                @else
                    @if($lastSale)
                        <div class="text-center py-4">
                            <x-icon name="o-check-circle" class="w-10 h-10 mx-auto mb-2 text-success" />
                            <p class="font-semibold text-sm mb-1">{{ $lastSale->invoice_number }}</p>
                            <p class="text-xs text-base-content/60 mb-3">Invoice created. Print for customer.</p>
                            <x-button label="Print Invoice" link="{{ route('invoice.show', $lastSale->id) }}" icon="o-printer" class="btn-sm btn-primary" external />
                            <x-button label="New Invoice" wire:click="$set('lastSaleId', null)" class="btn-sm btn-ghost mt-2" icon="o-plus" />
                        </div>
                    @else
                        <div class="text-center py-6 text-base-content/60">
                            <x-icon name="o-shopping-cart" class="w-10 h-10 mx-auto mb-2 opacity-30" />
                            <p class="text-sm">Cart is empty</p>
                        </div>
                    @endif
                @endif
            </x-card>
        </div>
    </div>

    <!-- My Invoices -->
    @if($myInvoices->count() || $recentCompleted->count())
        <div class="mt-4">
            <x-card title="My Invoices" subtitle="Pending and paid">
                <!-- Mobile: card list, Desktop: table -->
                <div class="hidden md:block overflow-x-auto">
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

                <!-- Mobile card list -->
                <div class="md:hidden space-y-2">
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
            </x-card>
        </div>
    @endif
</div>
