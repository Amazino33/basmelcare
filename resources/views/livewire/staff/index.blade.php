<div>
    <x-header title="Staff Management" subtitle="Manage pharmacy staff members">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Add Staff" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-table :headers="$headers" :rows="$staff" with-pagination>
        @scope('cell_role', $member)
            <x-badge :value="ucfirst(str_replace('_', ' ', $member->role))" @class([
                'badge-primary' => $member->role === 'admin',
                'badge-secondary' => $member->role === 'pharmacist',
                'badge-ghost' => $member->role === 'cashier',
                'badge-info' => $member->role === 'inventory_manager',
            ]) />
        @endscope

        @scope('cell_employment_date', $member)
            {{ $member->employment_date?->format('M d, Y') ?? '—' }}
        @endscope

        @scope('cell_status', $member)
            <x-badge :value="ucfirst($member->status)" @class([
                'badge-success' => $member->status === 'active',
                'badge-error' => $member->status === 'inactive',
                'badge-warning' => $member->status === 'suspended',
            ]) />
        @endscope

        @scope('actions', $member)
            <div class="flex gap-1">
                <x-button icon="o-eye" wire:click="viewDetails({{ $member->id }})" class="btn-xs btn-ghost" tooltip="Details" />
                <x-button icon="o-pencil" wire:click="edit({{ $member->id }})" class="btn-xs btn-ghost" tooltip="Edit" />
                <x-button icon="{{ $member->status === 'active' ? 'o-no-symbol' : 'o-check-circle' }}"
                    wire:click="toggleStatus({{ $member->id }})"
                    class="btn-xs btn-ghost {{ $member->status === 'active' ? 'text-warning' : 'text-success' }}"
                    wire:confirm="{{ $member->status === 'active' ? 'Deactivate this staff member?' : 'Reactivate this staff member?' }}"
                    tooltip="{{ $member->status === 'active' ? 'Deactivate' : 'Activate' }}" />
            </div>
        @endscope
    </x-table>

    <!-- Create/Edit Modal -->
    <x-modal wire:model="modal" title="{{ $staffId ? 'Edit Staff' : 'Add Staff Member' }}" box-class="max-w-2xl">
        <x-form wire:submit="save">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="Full Name" wire:model="name" />
                <x-input label="Email" wire:model="email" type="email" />
                <x-input label="Phone" wire:model="phone" />
                <x-input label="Password" wire:model="password" type="password" hint="{{ $staffId ? 'Leave blank to keep current' : 'Min 6 characters' }}" />
                <x-select label="Role" wire:model="role" :options="[
                    ['id' => 'admin', 'name' => 'Admin'],
                    ['id' => 'pharmacist', 'name' => 'Pharmacist'],
                    ['id' => 'cashier', 'name' => 'Cashier'],
                    ['id' => 'inventory_manager', 'name' => 'Inventory Manager'],
                ]" option-value="id" option-label="name" />
                <x-input label="Position/Title" wire:model="position" placeholder="e.g. Senior Pharmacist" />
                <x-input label="Employment Date" wire:model="employment_date" type="date" />
                <x-input label="Salary" wire:model="salary" prefix="₦" type="number" step="0.01" />
                <x-select label="Status" wire:model="status" :options="[
                    ['id' => 'active', 'name' => 'Active'],
                    ['id' => 'inactive', 'name' => 'Inactive'],
                    ['id' => 'suspended', 'name' => 'Suspended'],
                ]" option-value="id" option-label="name" />
                <x-input label="Address" wire:model="address" />
                <x-input label="Emergency Contact Name" wire:model="emergency_contact_name" />
                <x-input label="Emergency Contact Phone" wire:model="emergency_contact_phone" />
            </div>
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.modal = false" />
                <x-button label="Save" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <!-- Staff Details Drawer -->
    <x-drawer wire:model="detailsDrawer" title="Staff Details" right class="w-96 lg:w-1/3">
        @if($viewStaff)
            <div class="space-y-4">
                <div class="text-center mb-4">
                    <x-avatar :value="$viewStaff->name" class="!w-16 !h-16 mx-auto mb-2" />
                    <div class="text-lg font-bold">{{ $viewStaff->name }}</div>
                    <x-badge :value="ucfirst(str_replace('_', ' ', $viewStaff->role))" class="badge-primary" />
                    <x-badge :value="ucfirst($viewStaff->status)" @class([
                        'badge-success' => $viewStaff->status === 'active',
                        'badge-error' => $viewStaff->status === 'inactive',
                        'badge-warning' => $viewStaff->status === 'suspended',
                    ]) />
                </div>

                <x-hr />

                <div class="space-y-2">
                    <div class="text-sm font-semibold text-base-content/60 uppercase">Contact</div>
                    <div class="flex justify-between"><span class="text-base-content/60">Email:</span> <span>{{ $viewStaff->email }}</span></div>
                    <div class="flex justify-between"><span class="text-base-content/60">Phone:</span> <span>{{ $viewStaff->phone ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-base-content/60">Address:</span> <span>{{ $viewStaff->address ?? '—' }}</span></div>
                </div>

                <x-hr />

                <div class="space-y-2">
                    <div class="text-sm font-semibold text-base-content/60 uppercase">Employment</div>
                    <div class="flex justify-between"><span class="text-base-content/60">Position:</span> <span>{{ $viewStaff->position ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-base-content/60">Employed:</span> <span>{{ $viewStaff->employment_date?->format('M d, Y') ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-base-content/60">Salary:</span> <span>{{ $viewStaff->salary ? '₦' . number_format($viewStaff->salary, 2) : '—' }}</span></div>
                </div>

                <x-hr />

                <div class="space-y-2">
                    <div class="text-sm font-semibold text-base-content/60 uppercase">Emergency Contact</div>
                    <div class="flex justify-between"><span class="text-base-content/60">Name:</span> <span>{{ $viewStaff->emergency_contact_name ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-base-content/60">Phone:</span> <span>{{ $viewStaff->emergency_contact_phone ?? '—' }}</span></div>
                </div>

                <x-hr />

                <div class="space-y-2">
                    <div class="text-sm font-semibold text-base-content/60 uppercase">Activity</div>
                    <div class="flex justify-between"><span class="text-base-content/60">Total Sales:</span> <span>{{ $viewStaff->sales()->count() }}</span></div>
                    <div class="flex justify-between"><span class="text-base-content/60">Sales Value:</span> <span>₦{{ number_format($viewStaff->sales()->where('status', 'completed')->sum('total_amount'), 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-base-content/60">Joined:</span> <span>{{ $viewStaff->created_at->format('M d, Y') }}</span></div>
                </div>
            </div>
        @endif
    </x-drawer>
</div>
