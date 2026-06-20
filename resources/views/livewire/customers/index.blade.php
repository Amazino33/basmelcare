<div>
    <x-header title="Customers" subtitle="Manage customer records">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Add Customer" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-table :headers="$headers" :rows="$customers" with-pagination>
        @scope('cell_type', $customer)
            <x-badge :value="ucfirst($customer->type)" @class([
                'badge-ghost' => $customer->type === 'retail',
                'badge-info' => $customer->type === 'wholesale',
            ]) />
        @endscope

        @scope('actions', $customer)
            <div class="flex gap-1">
                <x-button icon="o-eye" wire:click="viewProfile({{ $customer->id }})" class="btn-xs btn-ghost" tooltip="Profile" />
                <x-button icon="o-pencil" wire:click="edit({{ $customer->id }})" class="btn-xs btn-ghost" tooltip="Edit" />
                <x-button icon="o-trash" wire:click="delete({{ $customer->id }})" class="btn-xs btn-ghost text-error" wire:confirm="Delete this customer?" tooltip="Delete" />
            </div>
        @endscope
    </x-table>

    <!-- Create/Edit Modal -->
    <x-modal wire:model="modal" title="{{ $customerId ? 'Edit Customer' : 'New Customer' }}">
        <x-form wire:submit="save">
            <x-input label="Name" wire:model="name" />
            <x-select label="Customer Type" wire:model="type" :options="[
                ['id' => 'retail', 'name' => 'Retail'],
                ['id' => 'wholesale', 'name' => 'Wholesale'],
            ]" option-value="id" option-label="name" />
            <x-input label="Phone" wire:model="phone" />
            <x-input label="Email" wire:model="email" type="email" />
            <x-textarea label="Address" wire:model="address" rows="2" />
            <x-textarea label="Notes" wire:model="notes" rows="2" />
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.modal = false" />
                <x-button label="Save" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <!-- Customer Profile Drawer -->
    <x-drawer wire:model="profileDrawer" title="{{ $viewCustomer?->name }}" right class="w-[28rem] lg:w-[36rem]">
        @if($viewCustomer)
            <!-- Customer Info -->
            <div class="space-y-2 mb-4">
                <div class="flex justify-between"><span class="text-base-content/60">Type:</span>
                    <x-badge :value="ucfirst($viewCustomer->type)" class="{{ $viewCustomer->type === 'wholesale' ? 'badge-info' : 'badge-ghost' }}" />
                </div>
                <div class="flex justify-between"><span class="text-base-content/60">Phone:</span> <span>{{ $viewCustomer->phone ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Email:</span> <span>{{ $viewCustomer->email ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Address:</span> <span>{{ $viewCustomer->address ?? '—' }}</span></div>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-3 gap-2 mb-4">
                <div class="bg-base-200 rounded p-2 text-center">
                    <div class="text-lg font-bold">{{ $viewCustomer->sales->count() }}</div>
                    <div class="text-xs text-base-content/60">Sales</div>
                </div>
                <div class="bg-base-200 rounded p-2 text-center">
                    <div class="text-lg font-bold text-error">₦{{ number_format($viewCustomer->totalDebt, 2) }}</div>
                    <div class="text-xs text-base-content/60">Debt</div>
                </div>
                <div class="bg-base-200 rounded p-2 text-center">
                    <div class="text-lg font-bold">{{ $viewCustomer->medicalRecords->count() }}</div>
                    <div class="text-xs text-base-content/60">Records</div>
                </div>
            </div>

            <x-hr />

            <!-- Medical Records -->
            <div class="flex justify-between items-center mb-3">
                <div class="text-sm font-semibold text-base-content/60 uppercase">Medical Records</div>
                <x-button label="Add Record" wire:click="openMedicalRecord" icon="o-plus" class="btn-xs btn-primary" />
            </div>

            @forelse($viewCustomer->medicalRecords as $record)
                <div class="p-3 bg-base-200 rounded-lg mb-2">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="font-semibold text-sm">{{ $record->title }}</div>
                            <x-badge :value="ucfirst(str_replace('_', ' ', $record->type))" @class([
                                'badge-xs',
                                'badge-primary' => $record->type === 'prescription',
                                'badge-info' => $record->type === 'lab_result',
                                'badge-success' => $record->type === 'vitals',
                                'badge-warning' => $record->type === 'allergy',
                                'badge-error' => $record->type === 'diagnosis',
                                'badge-ghost' => !in_array($record->type, ['prescription', 'lab_result', 'vitals', 'allergy', 'diagnosis']),
                            ]) />
                            <div class="text-xs text-base-content/60 mt-1">{{ $record->record_date->format('M d, Y') }} | By: {{ $record->recorder->name }}</div>
                            @if($record->details)
                                <div class="text-sm mt-1">{{ $record->details }}</div>
                            @endif
                            @if($record->note)
                                <div class="text-xs text-base-content/60 italic mt-1">{{ $record->note }}</div>
                            @endif
                        </div>
                        <div class="flex gap-1">
                            @if($record->file_path)
                                <x-button icon="o-arrow-down-tray" link="{{ asset('storage/' . $record->file_path) }}" class="btn-xs btn-ghost" tooltip="Download" external />
                            @endif
                            <x-button icon="o-trash" wire:click="deleteMedicalRecord({{ $record->id }})" class="btn-xs btn-ghost text-error" wire:confirm="Delete this record?" />
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-4 text-base-content/60 text-sm">No medical records yet.</div>
            @endforelse

            <x-hr />

            <!-- Recent Sales -->
            <div class="text-sm font-semibold text-base-content/60 uppercase mb-2">Recent Sales</div>
            @forelse($viewCustomer->sales as $sale)
                <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                    <div>
                        <div class="text-sm">Sale #{{ $sale->id }}</div>
                        <div class="text-xs text-base-content/60">{{ $sale->created_at->format('M d, Y') }}</div>
                    </div>
                    <span class="font-bold">₦{{ number_format($sale->total_amount, 2) }}</span>
                </div>
            @empty
                <div class="text-center py-4 text-base-content/60 text-sm">No sales yet.</div>
            @endforelse

            <!-- Outstanding Debts -->
            @if($viewCustomer->debts->count())
                <x-hr />
                <div class="text-sm font-semibold text-base-content/60 uppercase mb-2">Outstanding Debts</div>
                @foreach($viewCustomer->debts as $debt)
                    <div class="flex justify-between items-center p-2 bg-error/10 rounded mb-1">
                        <div>
                            <div class="text-sm">Sale #{{ $debt->sale_id }}</div>
                            <div class="text-xs text-base-content/60">{{ ucfirst($debt->status) }}</div>
                        </div>
                        <span class="font-bold text-error">₦{{ number_format($debt->balance, 2) }}</span>
                    </div>
                @endforeach
            @endif
        @endif
    </x-drawer>

    <!-- Add Medical Record Modal -->
    <x-modal wire:model="mrModal" title="Add Medical Record" box-class="max-w-lg">
        <x-form wire:submit="saveMedicalRecord">
            <x-input label="Title" wire:model="mr_title" placeholder="e.g. Blood Pressure Reading, Lab Test Result" />
            <x-select label="Record Type" wire:model="mr_type" :options="[
                ['id' => 'prescription', 'name' => 'Prescription'],
                ['id' => 'lab_result', 'name' => 'Lab Result'],
                ['id' => 'vitals', 'name' => 'Vitals'],
                ['id' => 'allergy', 'name' => 'Allergy'],
                ['id' => 'diagnosis', 'name' => 'Diagnosis'],
                ['id' => 'consultation', 'name' => 'Consultation'],
                ['id' => 'vaccination', 'name' => 'Vaccination'],
                ['id' => 'other', 'name' => 'Other'],
            ]" option-value="id" option-label="name" />
            <x-input label="Record Date" wire:model="mr_date" type="date" />
            <x-textarea label="Details" wire:model="mr_details" placeholder="Record details, readings, results..." rows="3" />
            <div>
                <label class="label"><span class="label-text font-semibold">Attachment</span></label>
                <input type="file" wire:model="mr_file" class="file-input file-input-bordered file-input-sm w-full" />
                <div class="text-xs text-base-content/60 mt-1">PDF, image, or document (max 5MB)</div>
                @error('mr_file') <span class="text-error text-xs">{{ $message }}</span> @enderror
            </div>
            <x-textarea label="Note" wire:model="mr_note" placeholder="Internal notes" rows="2" />
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.mrModal = false" />
                <x-button label="Save Record" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
