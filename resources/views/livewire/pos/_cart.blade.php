@if(count($cart))
    <div class="space-y-2 max-h-60 lg:max-h-96 overflow-y-auto">
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
