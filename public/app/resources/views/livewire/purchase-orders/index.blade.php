<div>
    <x-header title="Purchase Orders" subtitle="Order stock from suppliers">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search PO or supplier..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-select wire:model.live="statusFilter" :options="[
                ['id' => '', 'name' => 'All Status'],
                ['id' => 'draft', 'name' => 'Draft'],
                ['id' => 'sent', 'name' => 'Sent'],
                ['id' => 'partially_received', 'name' => 'Partial'],
                ['id' => 'received', 'name' => 'Received'],
                ['id' => 'cancelled', 'name' => 'Cancelled'],
            ]" option-value="id" option-label="name" class="w-36" />
            <x-button label="New Order" wire:click="openCreate" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-table :headers="$headers" :rows="$pos" with-pagination>
        @scope('cell_total_amount', $po)
            ₦{{ number_format($po->total_amount, 2) }}
        @endscope

        @scope('cell_status', $po)
            <x-badge :value="ucfirst(str_replace('_', ' ', $po->status))" @class([
                'badge-ghost' => $po->status === 'draft',
                'badge-info' => $po->status === 'sent',
                'badge-warning' => $po->status === 'partially_received',
                'badge-success' => $po->status === 'received',
                'badge-error' => $po->status === 'cancelled',
            ]) />
        @endscope

        @scope('cell_created_at', $po)
            {{ $po->created_at->format('M d, Y') }}
        @endscope

        @scope('actions', $po)
            <div class="flex gap-1">
                <x-button icon="o-eye" wire:click="viewDetails({{ $po->id }})" class="btn-xs btn-ghost" tooltip="Details" />
                @if($po->status === 'draft')
                    <x-button icon="o-paper-airplane" wire:click="markSent({{ $po->id }})" class="btn-xs btn-ghost text-info" tooltip="Mark Sent" wire:confirm="Mark this PO as sent?" />
                @endif
                @if(in_array($po->status, ['sent', 'partially_received']))
                    <x-button icon="o-arrow-down-tray" wire:click="openReceive({{ $po->id }})" class="btn-xs btn-ghost text-success" tooltip="Receive Stock" />
                @endif
                @if($po->status !== 'received')
                    <x-button icon="o-x-mark" wire:click="cancelPO({{ $po->id }})" class="btn-xs btn-ghost text-error" tooltip="Cancel" wire:confirm="Cancel this purchase order?" />
                @endif
            </div>
        @endscope
    </x-table>

    <!-- Create PO Modal -->
    <x-modal wire:model="createModal" title="New Purchase Order" box-class="max-w-2xl">
        <x-form wire:submit="savePO">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-select label="Supplier" wire:model="supplier_id" :options="$suppliers" option-value="id" option-label="name" placeholder="Select supplier" />
                <x-input label="Expected Delivery" wire:model="expected_date" type="date" />
            </div>
            <x-textarea label="Note" wire:model="po_note" placeholder="Optional" rows="2" class="mt-4" />

            <!-- Add items -->
            <div class="border rounded-lg p-4 mt-4">
                <div class="font-semibold mb-3">Order Items</div>
                <div class="grid grid-cols-12 gap-2 items-end">
                    <div class="col-span-5">
                        <x-select wire:model="addProduct_id" :options="$products" option-value="id" option-label="name" placeholder="Product" />
                    </div>
                    <div class="col-span-2">
                        <x-input wire:model="addQty" type="number" min="1" placeholder="Qty" />
                    </div>
                    <div class="col-span-3">
                        <x-input wire:model="addCost" type="number" step="0.01" placeholder="Cost" prefix="₦" />
                    </div>
                    <div class="col-span-2">
                        <x-button label="Add" wire:click="addItem" class="btn-sm btn-primary btn-block" icon="o-plus" />
                    </div>
                </div>

                @if(count($orderItems))
                    <div class="mt-4 space-y-2">
                        @foreach($orderItems as $index => $item)
                            <div class="flex justify-between items-center p-2 bg-base-200 rounded">
                                <div>
                                    <div class="font-semibold text-sm">{{ $item['product_name'] }}</div>
                                    <div class="text-xs text-base-content/60">{{ $item['quantity'] }} × ₦{{ number_format($item['unit_cost'], 2) }}</div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold">₦{{ number_format($item['subtotal'], 2) }}</span>
                                    <x-button icon="o-x-mark" wire:click="removeItem({{ $index }})" class="btn-xs btn-ghost text-error" />
                                </div>
                            </div>
                        @endforeach
                        <div class="text-right font-bold text-lg text-primary pt-2 border-t border-base-300">
                            Total: ₦{{ number_format(array_sum(array_column($orderItems, 'subtotal')), 2) }}
                        </div>
                    </div>
                @endif
            </div>

            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.createModal = false" />
                <x-button label="Create Order" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <!-- Receive Stock Modal -->
    <x-modal wire:model="receiveModal" title="Receive Stock" box-class="max-w-lg">
        @if($receivePO)
            <x-form wire:submit="receiveStock">
                <div class="text-sm mb-4">
                    <span class="text-base-content/60">PO:</span> <span class="font-semibold">{{ $receivePO->po_number }}</span>
                </div>

                <x-select label="Receive to Location" wire:model="receive_location_id" :options="$locations" option-value="id" option-label="name" />

                <div class="mt-4 space-y-3">
                    @foreach($receivePO->items as $item)
                        @if($item->remaining > 0)
                            <div class="flex justify-between items-center p-3 bg-base-200 rounded">
                                <div class="flex-1">
                                    <div class="font-semibold text-sm">{{ $item->product->name }}</div>
                                    <div class="text-xs text-base-content/60">Ordered: {{ $item->quantity_ordered }} | Received: {{ $item->quantity_received }} | Remaining: {{ $item->remaining }}</div>
                                </div>
                                <input type="number" wire:model="receiveQtys.{{ $item->id }}" min="0" max="{{ $item->remaining }}" class="input input-sm input-bordered w-20 text-center" />
                            </div>
                        @endif
                    @endforeach
                </div>

                <x-slot:actions>
                    <x-button label="Cancel" @click="$wire.receiveModal = false" />
                    <x-button label="Receive Stock" type="submit" class="btn-primary" icon="o-check" />
                </x-slot:actions>
            </x-form>
        @endif
    </x-modal>

    <!-- PO Details Drawer -->
    <x-drawer wire:model="detailsDrawer" title="PO {{ $viewPO?->po_number }}" right class="w-96 lg:w-1/3">
        @if($viewPO)
            <div class="space-y-2 mb-4">
                <div class="flex justify-between"><span class="text-base-content/60">Supplier:</span> <span class="font-semibold">{{ $viewPO->supplier->name }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Created by:</span> <span>{{ $viewPO->user->name }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Date:</span> <span>{{ $viewPO->created_at->format('M d, Y') }}</span></div>
                @if($viewPO->expected_date)
                    <div class="flex justify-between"><span class="text-base-content/60">Expected:</span> <span>{{ $viewPO->expected_date->format('M d, Y') }}</span></div>
                @endif
                <div class="flex justify-between"><span class="text-base-content/60">Status:</span>
                    <x-badge :value="ucfirst(str_replace('_', ' ', $viewPO->status))" @class([
                        'badge-ghost' => $viewPO->status === 'draft',
                        'badge-info' => $viewPO->status === 'sent',
                        'badge-warning' => $viewPO->status === 'partially_received',
                        'badge-success' => $viewPO->status === 'received',
                        'badge-error' => $viewPO->status === 'cancelled',
                    ]) />
                </div>
            </div>

            <x-hr />

            <div class="space-y-2">
                @foreach($viewPO->items as $item)
                    <div class="p-2 bg-base-200 rounded">
                        <div class="flex justify-between">
                            <span class="font-semibold text-sm">{{ $item->product->name }}</span>
                            <span class="font-bold">₦{{ number_format($item->subtotal, 2) }}</span>
                        </div>
                        <div class="text-xs text-base-content/60">
                            {{ $item->quantity_ordered }} ordered × ₦{{ number_format($item->unit_cost, 2) }}
                        </div>
                        <div class="mt-1">
                            <x-progress value="{{ $item->quantity_ordered > 0 ? ($item->quantity_received / $item->quantity_ordered) * 100 : 0 }}" max="100" class="progress-success h-2" />
                            <div class="text-xs text-base-content/60 mt-1">{{ $item->quantity_received }}/{{ $item->quantity_ordered }} received</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <x-hr />

            <div class="flex justify-between text-lg font-bold">
                <span>Total</span>
                <span class="text-primary">₦{{ number_format($viewPO->total_amount, 2) }}</span>
            </div>

            @if($viewPO->note)
                <div class="mt-3 text-sm text-base-content/60">Note: {{ $viewPO->note }}</div>
            @endif
        @endif
    </x-drawer>
</div>
