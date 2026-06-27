<div class="max-w-4xl mx-auto px-4 py-6">
    <!-- Profile Header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center">
                <x-icon name="o-user" class="w-6 h-6 text-primary" />
            </div>
            <div>
                <h1 class="font-bold text-lg">{{ $customer->name }}</h1>
                <p class="text-xs text-base-content/60">{{ $customer->email }}</p>
            </div>
        </div>
        <button wire:click="logout" class="btn btn-ghost btn-sm text-error">
            <x-icon name="o-arrow-right-start-on-rectangle" class="w-4 h-4" /> Logout
        </button>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-3 gap-3 mb-6">
        <div class="bg-base-200 rounded-lg p-3 text-center">
            <div class="text-lg font-bold text-primary">{{ $totalOrders }}</div>
            <div class="text-xs text-base-content/60">Orders</div>
        </div>
        <div class="bg-base-200 rounded-lg p-3 text-center">
            <div class="text-lg font-bold">₦{{ number_format($totalSpent, 0) }}</div>
            <div class="text-xs text-base-content/60">Spent</div>
        </div>
        <div class="bg-base-200 rounded-lg p-3 text-center">
            <div class="text-lg font-bold {{ $totalDebt > 0 ? 'text-error' : 'text-success' }}">₦{{ number_format($totalDebt, 0) }}</div>
            <div class="text-xs text-base-content/60">Balance</div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="flex overflow-x-auto gap-1 mb-4 border-b border-base-200 pb-1 scrollbar-hide">
        @foreach(['overview' => 'Overview', 'orders' => 'Orders', 'debts' => 'Debts', 'appointments' => 'Appointments', 'records' => 'Records'] as $tab => $label)
            <button wire:click="$set('activeTab', '{{ $tab }}')" @class([
                'px-4 py-2 text-sm font-medium rounded-t-lg whitespace-nowrap transition-colors',
                'bg-primary text-primary-content' => $activeTab === $tab,
                'text-base-content/60 hover:text-base-content' => $activeTab !== $tab,
            ])>{{ $label }}</button>
        @endforeach
    </div>

    <!-- Overview Tab -->
    @if($activeTab === 'overview')
        <div class="space-y-4">
            <div class="card bg-base-100 border border-base-200 p-4">
                <h3 class="font-semibold mb-3">Recent Orders</h3>
                @forelse($recentSales as $sale)
                    <div class="flex justify-between items-center py-2 border-b border-base-200 last:border-0">
                        <div>
                            <div class="font-semibold text-sm">{{ $sale->invoice_number ?? '#' . $sale->id }}</div>
                            <div class="text-xs text-base-content/60">{{ $sale->created_at->format('M d, Y') }} | {{ $sale->saleItems->count() }} items</div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold text-sm">₦{{ number_format($sale->total_amount, 2) }}</div>
                            <span @class([
                                'badge badge-xs',
                                'badge-warning' => $sale->status === 'pending',
                                'badge-success' => in_array($sale->status, ['paid', 'completed']),
                                'badge-error' => $sale->status === 'cancelled',
                            ])>{{ ucfirst($sale->status) }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-base-content/60 text-center py-4">No orders yet.</p>
                @endforelse
            </div>

            @if($totalDebt > 0)
                <div class="card bg-error/10 border border-error/20 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-error">Outstanding Balance</h3>
                            <p class="text-sm text-base-content/60">You have unpaid invoices</p>
                        </div>
                        <div class="text-xl font-bold text-error">₦{{ number_format($totalDebt, 2) }}</div>
                    </div>
                    <button wire:click="$set('activeTab', 'debts')" class="btn btn-error btn-sm btn-outline mt-3">View Details</button>
                </div>
            @endif

            <div class="card bg-base-100 border border-base-200 p-4">
                <h3 class="font-semibold mb-2">Account Info</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-base-content/60">Name</span><span>{{ $customer->name }}</span></div>
                    <div class="flex justify-between"><span class="text-base-content/60">Email</span><span>{{ $customer->email }}</span></div>
                    <div class="flex justify-between"><span class="text-base-content/60">Phone</span><span>{{ $customer->phone ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-base-content/60">Type</span><span class="badge badge-xs {{ $customer->type === 'wholesale' ? 'badge-info' : 'badge-ghost' }}">{{ ucfirst($customer->type) }}</span></div>
                </div>
            </div>
        </div>
    @endif

    <!-- Orders Tab -->
    @if($activeTab === 'orders')
        <div class="space-y-3">
            @forelse($allSales as $sale)
                <div class="card bg-base-100 border border-base-200 p-4">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <div class="font-bold text-sm">{{ $sale->invoice_number ?? '#' . $sale->id }}</div>
                            <div class="text-xs text-base-content/60">{{ $sale->created_at->format('M d, Y h:i A') }}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold text-primary">₦{{ number_format($sale->total_amount, 2) }}</div>
                            <span @class([
                                'badge badge-xs',
                                'badge-warning' => $sale->status === 'pending',
                                'badge-success' => in_array($sale->status, ['paid', 'completed']),
                                'badge-error' => $sale->status === 'cancelled',
                            ])>{{ ucfirst($sale->status) }}</span>
                        </div>
                    </div>
                    <div class="space-y-1">
                        @foreach($sale->saleItems as $item)
                            <div class="flex justify-between text-xs">
                                <span class="text-base-content/60">{{ $item->product->name }} × {{ $item->quantity }}</span>
                                <span>₦{{ number_format($item->subtotal, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                    @if($sale->payment_method)
                        <div class="mt-2 text-xs text-base-content/60">Paid via {{ ucfirst($sale->payment_method) }}</div>
                    @endif
                </div>
            @empty
                <div class="text-center py-8 text-base-content/60">
                    <x-icon name="o-shopping-bag" class="w-10 h-10 mx-auto mb-2 opacity-30" />
                    <p class="text-sm">No orders yet</p>
                    <a href="/shop" class="btn btn-primary btn-sm mt-3">Start Shopping</a>
                </div>
            @endforelse
        </div>
    @endif

    <!-- Debts Tab -->
    @if($activeTab === 'debts')
        <div class="space-y-3">
            @forelse($debts as $debt)
                <div @class(['card border p-4', 'bg-error/5 border-error/20' => $debt->status !== 'paid', 'bg-base-100 border-base-200' => $debt->status === 'paid'])>
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <div class="font-bold text-sm">Invoice {{ $debt->sale->invoice_number ?? '#' . $debt->sale_id }}</div>
                            <div class="text-xs text-base-content/60">{{ $debt->created_at->format('M d, Y') }}</div>
                        </div>
                        <span @class([
                            'badge badge-xs',
                            'badge-error' => $debt->status === 'unpaid',
                            'badge-warning' => $debt->status === 'partial',
                            'badge-success' => $debt->status === 'paid',
                        ])>{{ ucfirst($debt->status) }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center text-sm">
                        <div>
                            <div class="text-xs text-base-content/60">Owed</div>
                            <div class="font-semibold">₦{{ number_format($debt->amount_owed, 2) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-base-content/60">Paid</div>
                            <div class="font-semibold text-success">₦{{ number_format($debt->amount_paid, 2) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-base-content/60">Balance</div>
                            <div class="font-semibold text-error">₦{{ number_format($debt->balance, 2) }}</div>
                        </div>
                    </div>
                    @if($debt->payments->count())
                        <div class="mt-3 pt-2 border-t border-base-200">
                            <div class="text-xs font-semibold text-base-content/60 mb-1">Payments</div>
                            @foreach($debt->payments as $payment)
                                <div class="flex justify-between text-xs py-1">
                                    <span>{{ $payment->created_at->format('M d') }} — {{ ucfirst($payment->payment_method) }}</span>
                                    <span class="text-success">₦{{ number_format($payment->amount, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-center py-8 text-base-content/60">
                    <x-icon name="o-check-circle" class="w-10 h-10 mx-auto mb-2 text-success opacity-50" />
                    <p class="text-sm">No outstanding debts</p>
                </div>
            @endforelse
        </div>
    @endif

    <!-- Appointments Tab -->
    @if($activeTab === 'appointments')
        <div class="space-y-3">
            @forelse($appointments as $appt)
                <div class="card bg-base-100 border border-base-200 p-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-bold text-sm">{{ $appt->title }}</div>
                            <div class="text-xs text-base-content/60">{{ $appt->scheduled_at->format('M d, Y — h:i A') }}</div>
                            @if($appt->staff)
                                <div class="text-xs text-base-content/60">With: {{ $appt->staff->name }}</div>
                            @endif
                            @if($appt->description)
                                <p class="text-xs text-base-content/60 mt-1">{{ $appt->description }}</p>
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
                </div>
            @empty
                <div class="text-center py-8 text-base-content/60">
                    <x-icon name="o-calendar" class="w-10 h-10 mx-auto mb-2 opacity-30" />
                    <p class="text-sm">No appointments</p>
                </div>
            @endforelse
        </div>
    @endif

    <!-- Medical Records Tab -->
    @if($activeTab === 'records')
        <div class="space-y-3">
            @forelse($medicalRecords as $record)
                <div class="card bg-base-100 border border-base-200 p-4">
                    <div class="flex justify-between items-start">
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-sm">{{ $record->title }}</div>
                            <div class="flex items-center gap-2 mt-1">
                                <span @class([
                                    'badge badge-xs',
                                    'badge-primary' => $record->type === 'prescription',
                                    'badge-info' => $record->type === 'lab_result',
                                    'badge-success' => $record->type === 'vitals',
                                    'badge-warning' => $record->type === 'allergy',
                                    'badge-error' => $record->type === 'diagnosis',
                                    'badge-ghost' => !in_array($record->type, ['prescription', 'lab_result', 'vitals', 'allergy', 'diagnosis']),
                                ])>{{ ucfirst(str_replace('_', ' ', $record->type)) }}</span>
                                <span class="text-xs text-base-content/60">{{ $record->record_date->format('M d, Y') }}</span>
                            </div>
                            @if($record->details)
                                <p class="text-xs text-base-content/60 mt-2">{{ $record->details }}</p>
                            @endif
                            <div class="text-xs text-base-content/40 mt-1">By: {{ $record->recorder->name }}</div>
                        </div>
                        @if($record->file_path)
                            <a href="{{ asset('storage/' . $record->file_path) }}" class="btn btn-ghost btn-xs shrink-0" target="_blank">
                                <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-base-content/60">
                    <x-icon name="o-document-text" class="w-10 h-10 mx-auto mb-2 opacity-30" />
                    <p class="text-sm">No medical records</p>
                </div>
            @endforelse
        </div>
    @endif
</div>
