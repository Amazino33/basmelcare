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

        @scope('cell_registered_by_name', $customer)
            <span class="text-sm {{ $customer->registeredBy ? '' : 'text-base-content/40' }}">
                {{ $customer->registeredBy?->name ?? '—' }}
            </span>
        @endscope

        @scope('actions', $customer)
            {{-- @scope does not inherit view variables — resolve the role here. --}}
            @php
                $actorRoles = auth()->user()->role ?? [];
                $actorIsPromoter = in_array('promoter', $actorRoles)
                    && ! array_intersect($actorRoles, ['admin', 'pharmacist', 'branch_manager', 'sales', 'cashier']);
            @endphp
            <div class="flex gap-1">
                <x-button icon="o-eye" wire:click="viewProfile({{ $customer->id }})" class="btn-xs btn-ghost" tooltip="Profile" />
                @unless($actorIsPromoter)
                    <x-button icon="o-pencil" wire:click="edit({{ $customer->id }})" class="btn-xs btn-ghost" tooltip="Edit" />
                    <x-button icon="o-trash" wire:click="delete({{ $customer->id }})" class="btn-xs btn-ghost text-error" wire:confirm="Delete this customer?" tooltip="Delete" />
                @endunless
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
            <x-input label="Phone" wire:model="phone"
                :hint="in_array('promoter', auth()->user()->role ?? []) && !$customerId ? 'Required — OTP will be sent here to verify the customer' : ''" />
            <x-input label="Email" wire:model="email" type="email" />
            <x-textarea label="Address" wire:model="address" rows="2" />
            <x-textarea label="Notes" wire:model="notes" rows="2" />
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.modal = false" />
                <x-button label="Save" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <!-- OTP Verification Modal (promoters only) -->
    <x-modal wire:model="otpModal" title="Verify Customer Phone" box-class="max-w-sm" persistent>
        <div class="space-y-4">
            <div class="flex items-start gap-3 p-3 bg-info/10 rounded-lg">
                <x-icon name="o-device-phone-mobile" class="w-5 h-5 text-info shrink-0 mt-0.5" />
                <p class="text-sm text-base-content/80">
                    An OTP was sent to <span class="font-bold">{{ $pendingPhone }}</span>.
                    Ask the customer for the code they received.
                </p>
            </div>
            <div>
                <x-input label="Enter OTP" wire:model="otpCode" placeholder="000000"
                    maxlength="6" inputmode="numeric"
                    hint="6-digit code from the customer's phone"
                    wire:keydown.enter="confirmOtp" />
                @if($otpError)
                    <p class="text-error text-xs mt-1">{{ $otpError }}</p>
                @endif
            </div>
        </div>
        <x-slot:actions>
            <x-button label="Skip (no code)" wire:click="skipOtp" class="btn-ghost btn-sm text-base-content/40 mr-auto" />
            <x-button label="Resend" wire:click="resendOtp" class="btn-outline btn-sm" />
            <x-button label="Verify & Issue Code" wire:click="confirmOtp" class="btn-primary btn-sm" />
        </x-slot:actions>
    </x-modal>

    <!-- Wi-Fi Code Handover (promoters) -->
    <x-modal wire:model="codeModal"
             title="{{ $noSmartDevice ? 'No Smartphone' : ($codeRedeemed ? 'Connected' : 'Wi-Fi Code Issued') }}"
             box-class="max-w-sm" persistent>
        <div class="space-y-4" @if(!$codeRedeemed) wire:poll.3s="checkRedemption" @endif>

            @unless($noSmartDevice)
                <div class="text-center py-2">
                    <p class="text-xs text-base-content/60 uppercase tracking-wide mb-1">Wi-Fi code</p>
                    <p class="text-3xl font-mono font-bold tracking-[0.2em] text-primary">{{ $issuedCode }}</p>
                </div>
            @endunless

            @if($noSmartDevice)
                <div class="flex items-start gap-3 p-3 bg-info/10 rounded-lg">
                    <x-icon name="o-device-phone-mobile" class="w-5 h-5 text-info shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-semibold">This phone can't use the Wi-Fi</p>
                        <p class="text-xs text-base-content/70 mt-0.5">
                            The message went by SMS, so there's no WhatsApp on
                            <span class="font-semibold">{{ $pendingPhone }}</span>.
                            No Wi-Fi code was issued — it would be no use to them.
                        </p>
                    </div>
                </div>
            @else
                <div class="flex items-start gap-2 p-2 rounded-lg {{ $codeSent ? 'bg-success/10' : 'bg-warning/10' }}">
                    <x-icon name="{{ $codeSent ? 'o-check-circle' : 'o-exclamation-triangle' }}"
                            class="w-4 h-4 shrink-0 mt-0.5 {{ $codeSent ? 'text-success' : 'text-warning' }}" />
                    <p class="text-xs text-base-content/80">
                        @if($codeSent)
                            Sent to <span class="font-semibold">{{ $pendingPhone }}</span>.
                        @else
                            Could not send by WhatsApp/SMS — read the code out to the customer.
                        @endif
                    </p>
                </div>
            @endif

            @if($codeRedeemed)
                <div class="flex items-center gap-3 p-3 bg-success/10 rounded-lg">
                    <x-icon name="o-check-badge" class="w-6 h-6 text-success shrink-0" />
                    <div>
                        <p class="text-sm font-bold text-success">
                            {{ $noSmartDevice ? 'Counted — no connection needed' : 'Customer is online' }}
                        </p>
                        <p class="text-xs text-base-content/70">
                            ₦{{ number_format($earnedAmount, 2) }} commission earned.
                        </p>
                    </div>
                </div>
            @else
                <div class="flex items-center gap-3 p-3 bg-base-200 rounded-lg">
                    <span class="loading loading-spinner loading-sm text-primary shrink-0"></span>
                    <div>
                        <p class="text-sm font-semibold">Waiting for customer to connect…</p>
                        <p class="text-xs text-base-content/60">
                            Have them join the Wi-Fi and enter this code. You earn once they're online.
                        </p>
                    </div>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-button label="{{ $codeRedeemed ? 'Done' : 'Close' }}"
                      wire:click="closeCodeModal"
                      class="{{ $codeRedeemed ? 'btn-primary' : 'btn-ghost' }} btn-sm" />
        </x-slot:actions>
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

            @if($isPromoter)
                <div class="flex items-start gap-2 p-3 bg-base-200 rounded-lg text-sm text-base-content/60">
                    <x-icon name="o-lock-closed" class="w-4 h-4 shrink-0 mt-0.5" />
                    <span>Purchase history, medical records and account balances are not available to promoters.</span>
                </div>
            @else
            <!-- Quick Stats -->
            <div class="grid grid-cols-2 gap-2 mb-4">
                <div class="bg-base-200 rounded p-2 text-center">
                    <div class="text-lg font-bold">{{ $viewCustomer->sales->count() }}</div>
                    <div class="text-xs text-base-content/60">In-Store</div>
                </div>
                <div class="bg-base-200 rounded p-2 text-center">
                    <div class="text-lg font-bold">{{ $viewCustomer->orders->count() }}</div>
                    <div class="text-xs text-base-content/60">Online</div>
                </div>
                <div class="bg-base-200 rounded p-2 text-center">
                    <div class="text-lg font-bold text-error">₦{{ number_format($viewCustomer->totalDebt, 2) }}</div>
                    <div class="text-xs text-base-content/60">Debt</div>
                </div>
                <div class="bg-base-200 rounded p-2 text-center">
                    <div class="text-lg font-bold">
                        {{ $canViewRecords ? $viewCustomer->medicalRecords->count() : '—' }}
                    </div>
                    <div class="text-xs text-base-content/60">Records</div>
                </div>
            </div>

            <x-hr />

            @if($canViewRecords)
            <!-- Medical Records -->
            <div class="flex justify-between items-center mb-3">
                <div class="text-sm font-semibold text-base-content/60 uppercase">Medical Records</div>
                @if($canEditRecords)
                    <x-button label="Add Record" wire:click="openMedicalRecord" icon="o-plus" class="btn-xs btn-primary" />
                @endif
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
                            @if($canEditRecords)
                                <x-button icon="o-trash" wire:click="deleteMedicalRecord({{ $record->id }})" class="btn-xs btn-ghost text-error" wire:confirm="Delete this record?" />
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-4 text-base-content/60 text-sm">No medical records yet.</div>
            @endforelse
            @endif

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

            <!-- Online Orders -->
            @if($viewCustomer->orders->count())
                <x-hr />
                <div class="text-sm font-semibold text-base-content/60 uppercase mb-2">Online Orders</div>
                @foreach($viewCustomer->orders as $order)
                    <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                        <div>
                            <div class="text-sm font-semibold">{{ $order->order_number }}</div>
                            <div class="text-xs text-base-content/60">{{ $order->created_at->format('M d, Y') }} | {{ ucfirst($order->fulfillment_type) }}</div>
                            <div class="text-xs text-base-content/60">{{ $order->items->count() }} items</div>
                        </div>
                        <div class="text-right">
                            <span class="font-bold">₦{{ number_format($order->total_amount, 2) }}</span>
                            <div class="flex gap-1 mt-1">
                                <span @class([
                                    'badge badge-xs',
                                    'badge-warning' => $order->status === 'pending',
                                    'badge-info' => $order->status === 'processing',
                                    'badge-success' => $order->status === 'completed',
                                    'badge-error' => $order->status === 'cancelled',
                                ])>{{ ucfirst($order->status) }}</span>
                                <span @class([
                                    'badge badge-xs',
                                    'badge-warning' => $order->payment_status === 'pending',
                                    'badge-success' => $order->payment_status === 'paid',
                                ])>{{ ucfirst($order->payment_status) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif

            <!-- Appointments -->
            @if($viewCustomer->appointments->count())
                <x-hr />
                <div class="text-sm font-semibold text-base-content/60 uppercase mb-2">Appointments</div>
                @foreach($viewCustomer->appointments as $appt)
                    <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                        <div>
                            <div class="text-sm font-semibold">{{ $appt->title }}</div>
                            <div class="text-xs text-base-content/60">{{ $appt->scheduled_at->format('M d, Y h:i A') }}</div>
                            @if($appt->staff)
                                <div class="text-xs text-base-content/60">With: {{ $appt->staff->name }}</div>
                            @endif
                        </div>
                        <span @class([
                            'badge badge-xs',
                            'badge-info' => $appt->status === 'scheduled',
                            'badge-primary' => $appt->status === 'confirmed',
                            'badge-success' => $appt->status === 'completed',
                            'badge-error' => $appt->status === 'cancelled',
                            'badge-warning' => $appt->status === 'no_show',
                        ])>{{ ucfirst(str_replace('_', ' ', $appt->status)) }}</span>
                    </div>
                @endforeach
            @endif

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
