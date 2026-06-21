<div>
    <x-header title="Point of Sale" subtitle="Create invoices for customers">
        @if($isWholesale)
            <x-slot:actions>
                <x-badge value="WHOLESALE CUSTOMER" class="badge-info badge-lg" />
            </x-slot:actions>
        @endif
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Product Selection -->
        <div class="lg:col-span-2">
            <x-card title="Products">
                <x-slot:menu>
                    <x-input icon="o-magnifying-glass" placeholder="Search products..." wire:model.live.debounce="search" clearable class="w-64" />
                </x-slot:menu>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    @forelse($products as $product)
                        @php $stock = $product->batches->sum('quantity'); @endphp
                        <button
                            wire:click="addToCart({{ $product->id }})"
                            @class([
                                'p-3 rounded-lg border text-left transition-all hover:shadow-md',
                                'border-base-300 hover:border-primary' => $stock > 0,
                                'border-error/30 opacity-50 cursor-not-allowed' => $stock == 0,
                            ])
                            @disabled($stock == 0)
                        >
                            <div class="font-semibold text-sm truncate">{{ $product->name }}</div>
                            <div class="text-primary font-bold">
                                @if($isWholesale && $product->wholesale_price)
                                    ₦{{ number_format($product->wholesale_price, 2) }}
                                    <span class="text-xs line-through text-base-content/40">₦{{ number_format($product->selling_price, 2) }}</span>
                                @else
                                    ₦{{ number_format($product->selling_price, 2) }}
                                @endif
                            </div>
                            <div class="text-xs text-base-content/60">
                                Stock: {{ $stock }}
                                @if($product->wholesale_price && $product->wholesale_min_qty && !$isWholesale)
                                    | W/S: {{ $product->wholesale_min_qty }}+
                                @endif
                            </div>
                        </button>
                    @empty
                        <div class="col-span-full text-center py-8 text-base-content/60">No products found.</div>
                    @endforelse
                </div>
            </x-card>
        </div>

        <!-- Cart -->
        <div>
            <x-card title="Cart" subtitle="{{ count($cart) }} item(s)">
                @if(count($cart))
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        @foreach($cart as $key => $item)
                            <div class="flex items-center gap-2 p-2 rounded bg-base-200">
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-sm truncate">{{ $item['name'] }}</div>
                                    <div class="text-xs text-base-content/60">
                                        ₦{{ number_format($item['unit_price'], 2) }} × {{ $item['qty'] }}
                                        @if($item['unit_price'] < $item['retail_price'])
                                            <span class="text-info">(wholesale)</span>
                                        @endif
                                    </div>
                                </div>
                                <input type="number" wire:change="updateQty('{{ $key }}', $event.target.value)" value="{{ $item['qty'] }}" min="1" max="{{ $item['max_qty'] }}" class="input input-xs input-bordered w-16 text-center" />
                                <x-button icon="o-x-mark" wire:click="removeFromCart('{{ $key }}')" class="btn-xs btn-ghost text-error" />
                            </div>
                        @endforeach
                    </div>

                    <x-hr />

                    <div class="text-right text-xl font-bold text-primary mb-4">
                        ₦{{ number_format($cartTotal, 2) }}
                    </div>

                    <x-choices label="Customer" wire:model.live="customer_id" :options="$customers" option-value="id" option-label="name" placeholder="Search customer..." single searchable />
                    <x-textarea label="Note" wire:model="note" placeholder="Optional" class="mt-3" rows="2" />

                    <x-button label="Create Invoice" wire:click="createInvoice" icon="o-document-text" class="btn-primary btn-block mt-4" wire:confirm="Create this invoice?" />
                @else
                    @if($lastSale)
                        <div class="text-center py-6">
                            <x-icon name="o-check-circle" class="w-12 h-12 mx-auto mb-2 text-success" />
                            <p class="font-semibold mb-1">{{ $lastSale->invoice_number }}</p>
                            <p class="text-sm text-base-content/60 mb-4">Invoice created. Print for customer.</p>
                            <div class="flex gap-2 justify-center">
                                <x-button label="Print Invoice" link="{{ route('invoice.show', $lastSale->id) }}" icon="o-printer" class="btn-sm btn-primary" external />
                                <x-button label="Receipt" link="{{ route('receipt.show', $lastSale->id) }}" icon="o-document-text" class="btn-sm btn-ghost" external />
                            </div>
                            <x-button label="New Invoice" wire:click="$set('lastSaleId', null)" class="btn-sm btn-ghost mt-3" icon="o-plus" />
                        </div>
                    @else
                        <div class="text-center py-8 text-base-content/60">
                            <x-icon name="o-shopping-cart" class="w-12 h-12 mx-auto mb-2 opacity-30" />
                            <p>Cart is empty</p>
                        </div>
                    @endif
                @endif
            </x-card>
        </div>
    </div>

    <!-- My Invoices -->
    @if($myInvoices->count() || $recentCompleted->count())
        <div class="mt-6">
            <x-card title="My Invoices" subtitle="Track your pending and paid invoices">
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Created</th>
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
                                            <x-button icon="o-printer" link="{{ route('invoice.show', $invoice->id) }}" class="btn-xs btn-ghost" tooltip="Print" external />
                                            @if($invoice->status === 'paid')
                                                <x-button label="Handover" wire:click="confirmHandover({{ $invoice->id }})" class="btn-xs btn-success" wire:confirm="Confirm goods handed to customer?" />
                                            @endif
                                            @if($invoice->status === 'pending')
                                                <x-button icon="o-x-mark" wire:click="cancelInvoice({{ $invoice->id }})" class="btn-xs btn-ghost text-error" tooltip="Cancel" wire:confirm="Cancel this invoice? Stock will be restored." />
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
                                    <td><x-badge value="Completed" class="badge-ghost" /></td>
                                    <td class="text-xs">{{ $invoice->created_at->format('H:i') }}</td>
                                    <td>
                                        <x-button icon="o-printer" link="{{ route('invoice.show', $invoice->id) }}" class="btn-xs btn-ghost" tooltip="Print" external />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    @endif
</div>
