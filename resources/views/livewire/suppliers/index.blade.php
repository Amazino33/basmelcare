<div>
    <x-header title="Suppliers" subtitle="Manage supplier records">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Add Supplier" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-table :headers="$headers" :rows="$suppliers" with-pagination>
        @scope('actions', $supplier)
            <x-button icon="o-eye" wire:click="viewProfile({{ $supplier->id }})" class="btn-sm btn-ghost" tooltip="Profile" />
            <x-button icon="o-pencil" wire:click="edit({{ $supplier->id }})" class="btn-sm btn-ghost" />
            <x-button icon="o-trash" wire:click="delete({{ $supplier->id }})" class="btn-sm btn-ghost text-error" wire:confirm="Delete this supplier?" />
        @endscope
    </x-table>

    <x-modal wire:model="modal" title="{{ $supplierId ? 'Edit Supplier' : 'New Supplier' }}">
        <x-form wire:submit="save">
            <x-input label="Company Name" wire:model="name" />
            <x-input label="Contact Person" wire:model="contact_person" />
            <x-input label="Phone" wire:model="phone" />
            <x-input label="Email" wire:model="email" type="email" />
            <x-textarea label="Address" wire:model="address" rows="2" />
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.modal = false" />
                <x-button label="Save" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <!-- Supplier Profile Drawer -->
    <x-drawer wire:model="profileDrawer" title="{{ $viewSupplier?->name }}" right class="w-96 lg:w-1/3">
        @if($viewSupplier)
            <div class="space-y-2 mb-4">
                <div class="flex justify-between"><span class="text-base-content/60">Contact:</span> <span>{{ $viewSupplier->contact_person ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Phone:</span> <span>{{ $viewSupplier->phone ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Email:</span> <span>{{ $viewSupplier->email ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Address:</span> <span>{{ $viewSupplier->address ?? '—' }}</span></div>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-2 gap-2 mb-4">
                <div class="bg-base-200 rounded p-2 text-center">
                    <div class="text-lg font-bold">{{ $viewSupplier->purchaseOrders->count() }}</div>
                    <div class="text-xs text-base-content/60">Orders</div>
                </div>
                <div class="bg-base-200 rounded p-2 text-center">
                    <div class="text-lg font-bold text-primary">₦{{ number_format($viewSupplier->purchaseOrders->sum('total_amount'), 2) }}</div>
                    <div class="text-xs text-base-content/60">Total Value</div>
                </div>
            </div>

            <x-hr />

            <div class="text-sm font-semibold text-base-content/60 uppercase mb-2">Purchase Orders</div>
            @forelse($viewSupplier->purchaseOrders as $po)
                <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                    <div>
                        <div class="font-semibold text-sm">{{ $po->po_number }}</div>
                        <div class="text-xs text-base-content/60">{{ $po->created_at->format('M d, Y') }}</div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold">₦{{ number_format($po->total_amount, 2) }}</div>
                        <x-badge :value="ucfirst(str_replace('_', ' ', $po->status))" @class([
                            'badge-xs',
                            'badge-ghost' => $po->status === 'draft',
                            'badge-info' => $po->status === 'sent',
                            'badge-warning' => $po->status === 'partially_received',
                            'badge-success' => $po->status === 'received',
                            'badge-error' => $po->status === 'cancelled',
                        ]) />
                    </div>
                </div>
            @empty
                <div class="text-center py-4 text-base-content/60 text-sm">No purchase orders yet.</div>
            @endforelse
        @endif
    </x-drawer>
</div>
