<div>
    <x-header title="Returns" subtitle="What came back, and what was approved or requested" size="text-xl" />

    @if($pendingCount > 0)
        <div class="mb-4 p-3 rounded-lg bg-warning/10 border border-warning/30 flex items-center justify-between gap-2 flex-wrap">
            <div class="flex items-center gap-2">
                <x-icon name="o-clock" class="w-5 h-5 text-warning" />
                <span class="text-sm font-semibold text-warning-content">
                    {{ $pendingCount }} return {{ Str::plural('request', $pendingCount) }} (₦{{ number_format($pendingTotal, 2) }}) awaiting review and approval by an Auditor or Branch Manager.
                </span>
            </div>
            @if($statusFilter !== 'pending')
                <x-button label="View Pending" wire:click="$set('statusFilter', 'pending')" class="btn-xs btn-warning" />
            @endif
        </div>
    @endif

    <div class="flex flex-col sm:flex-row gap-2 mb-4">
        <x-input wire:model.live.debounce.300ms="search" placeholder="Product, customer or invoice"
                 icon="o-magnifying-glass" class="flex-1" clearable />
        <x-select wire:model.live="statusFilter" class="sm:w-44"
                  :options="[
                      ['id' => 'all',      'name' => 'All Statuses'],
                      ['id' => 'pending',  'name' => 'Pending Approval'],
                      ['id' => 'approved', 'name' => 'Approved'],
                      ['id' => 'rejected', 'name' => 'Rejected'],
                  ]" option-value="id" option-label="name" />
        <x-select wire:model.live="period" class="sm:w-36"
                  :options="[
                      ['id' => 'today', 'name' => 'Today'],
                      ['id' => 'week',  'name' => 'This week'],
                      ['id' => 'month', 'name' => 'This month'],
                      ['id' => 'year',  'name' => 'This year'],
                      ['id' => 'all',   'name' => 'Everything'],
                  ]" option-value="id" option-label="name" />
        <x-select wire:model.live="methodFilter" class="sm:w-40"
                  :options="[
                      ['id' => 'all',    'name' => 'Cash & credit'],
                      ['id' => 'cash',   'name' => 'Refunded in cash'],
                      ['id' => 'credit', 'name' => 'Given as credit'],
                  ]" option-value="id" option-label="name" />
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <x-card>
            <div class="text-xs text-base-content/50">Approved Returns</div>
            <div class="text-xl font-bold tabular-nums">{{ number_format($count) }}</div>
        </x-card>
        <x-card>
            <div class="text-xs text-base-content/50">Value returned</div>
            <div class="text-xl font-bold tabular-nums">&#8358;{{ number_format($total, 2) }}</div>
        </x-card>
        <x-card>
            <div class="text-xs text-base-content/50">Paid in cash</div>
            <div class="text-xl font-bold tabular-nums text-error">&#8358;{{ number_format($cash, 2) }}</div>
        </x-card>
        <x-card>
            <div class="text-xs text-base-content/50">Given as credit</div>
            <div class="text-xl font-bold tabular-nums">&#8358;{{ number_format($credit, 2) }}</div>
        </x-card>
    </div>

    @if($units > 0)
        <p class="text-sm text-base-content/60 mb-3">
            {{ number_format($units) }} {{ Str::plural('unit', $units) }} went back on the shelf.
            Tap a return to see the items and the batch each went back to.
        </p>
    @endif

    @forelse($returns as $return)
        <x-card class="mb-2 cursor-pointer hover:border-primary/40 border border-transparent transition-colors"
                wire:click="viewReturn({{ $return->id }})">
            <div class="flex flex-col sm:flex-row sm:items-start gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-mono text-sm font-semibold">
                            RT-{{ str_pad($return->id, 5, '0', STR_PAD_LEFT) }}
                        </span>

                        @if($return->isPending())
                            <span class="badge badge-sm badge-warning">Pending Approval</span>
                        @elseif($return->isApproved())
                            <span class="badge badge-sm badge-success">Approved</span>
                        @elseif($return->isRejected())
                            <span class="badge badge-sm badge-error">Rejected</span>
                        @endif

                        <span class="badge badge-sm {{ $return->isCash() ? 'badge-error badge-outline' : 'badge-ghost' }}">
                            {{ $return->isCash() ? 'Cash' : 'Credit' }}
                        </span>
                        <span class="text-xs text-base-content/50">
                            against {{ $return->sale?->invoice_number ?? 'sale #' . $return->sale_id }}
                        </span>
                    </div>

                    <div class="text-sm text-base-content/70 mt-1">
                        {{ $return->sale?->customer?->name ?? 'Walk-in customer' }}
                        &middot; Triggered by {{ $return->processor?->name ?? 'unknown' }}
                        &middot; {{ $return->created_at->format('j M Y, g:ia') }}
                    </div>

                    @if($return->isApproved() && $return->approver)
                        <div class="text-xs text-success mt-0.5">
                            Approved by {{ $return->approver->name }} &middot; {{ $return->approved_at?->format('j M Y, g:ia') }}
                        </div>
                    @elseif($return->isRejected() && $return->rejector)
                        <div class="text-xs text-error mt-0.5">
                            Rejected by {{ $return->rejector->name }}
                            @if($return->rejection_reason) — {{ $return->rejection_reason }} @endif
                        </div>
                    @endif

                    <div class="text-xs text-base-content/60 mt-1">
                        @foreach($return->items as $item)
                            {{ $item->quantity_returned }}&times; {{ $item->product?->name ?? 'item' }}@if(! $loop->last), @endif
                        @endforeach
                    </div>
                </div>

                <div class="sm:text-right shrink-0">
                    <div class="text-lg font-bold tabular-nums">&#8358;{{ number_format($return->total_credit, 2) }}</div>
                </div>
            </div>
        </x-card>
    @empty
        <x-card>
            <div class="text-center py-8">
                <x-icon name="o-arrow-uturn-left" class="w-10 h-10 mx-auto text-base-content/20" />
                <p class="text-base-content/60 mt-2">Nothing was returned in this period.</p>
            </div>
        </x-card>
    @endforelse

    {{ $returns->links() }}

    <!-- Detail Drawer -->
    <x-drawer wire:model="detailDrawer" title="Return Detail" right class="w-96 lg:w-1/3">
        @if($viewReturn)
            <div class="space-y-4">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-mono font-bold text-lg">
                            RT-{{ str_pad($viewReturn->id, 5, '0', STR_PAD_LEFT) }}
                        </div>
                        <div class="text-sm text-base-content/60">
                            {{ $viewReturn->refundLabel() }} &mdash;
                            &#8358;{{ number_format($viewReturn->total_credit, 2) }}
                        </div>
                    </div>
                    <div>
                        @if($viewReturn->isPending())
                            <span class="badge badge-warning">Pending Approval</span>
                        @elseif($viewReturn->isApproved())
                            <span class="badge badge-success">Approved</span>
                        @elseif($viewReturn->isRejected())
                            <span class="badge badge-error">Rejected</span>
                        @endif
                    </div>
                </div>

                <div class="divide-y divide-base-200 text-sm">
                    <div class="flex justify-between py-2">
                        <span class="text-base-content/60">Against Invoice</span>
                        <span class="font-mono">{{ $viewReturn->sale?->invoice_number ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-base-content/60">Customer</span>
                        <span>{{ $viewReturn->sale?->customer?->name ?? 'Walk-in' }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-base-content/60">Triggered by</span>
                        <span>{{ $viewReturn->processor?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-base-content/60">Requested At</span>
                        <span>{{ $viewReturn->created_at->format('j M Y, g:ia') }}</span>
                    </div>

                    @if($viewReturn->isApproved())
                        <div class="flex justify-between py-2">
                            <span class="text-base-content/60">Approved by</span>
                            <span class="text-success font-medium">{{ $viewReturn->approver?->name ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-base-content/60">Approved At</span>
                            <span>{{ $viewReturn->approved_at?->format('j M Y, g:ia') ?? '—' }}</span>
                        </div>
                    @elseif($viewReturn->isRejected())
                        <div class="flex justify-between py-2">
                            <span class="text-base-content/60">Rejected by</span>
                            <span class="text-error font-medium">{{ $viewReturn->rejector?->name ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-base-content/60">Rejected At</span>
                            <span>{{ $viewReturn->rejected_at?->format('j M Y, g:ia') ?? '—' }}</span>
                        </div>
                        @if($viewReturn->rejection_reason)
                            <div class="py-2">
                                <div class="text-base-content/60 mb-1">Rejection Reason</div>
                                <div class="text-error">{{ $viewReturn->rejection_reason }}</div>
                            </div>
                        @endif
                    @endif

                    @if($viewReturn->reason)
                        <div class="py-2">
                            <div class="text-base-content/60 mb-1">Return Reason</div>
                            <div>{{ $viewReturn->reason }}</div>
                        </div>
                    @endif
                </div>

                <div>
                    <div class="text-xs uppercase tracking-wide text-base-content/50 mb-2">Items to return</div>
                    <div class="space-y-2">
                        @foreach($viewReturn->items as $item)
                            <div class="rounded-lg border border-base-300 p-3">
                                <div class="flex justify-between gap-2">
                                    <span class="font-medium">{{ $item->product?->name ?? 'Product removed' }}</span>
                                    <span class="tabular-nums font-bold">{{ $item->quantity_returned }}</span>
                                </div>
                                <div class="text-xs text-base-content/60 mt-1">
                                    Batch
                                    <span class="font-mono">{{ $item->batch?->batch_number ?? '—' }}</span>
                                    @if($item->batch)
                                        &middot; current shelf stock:
                                        <span class="tabular-nums font-medium">{{ $item->batch->quantity }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($viewReturn->isPending() && $this->canApproveOrReject())
                    <div class="p-3 bg-base-200 rounded-lg space-y-2">
                        <div class="text-xs font-semibold text-base-content/70">Review & Decision</div>
                        <div class="flex gap-2">
                            <x-button
                                label="Approve Return"
                                wire:click="approveReturn({{ $viewReturn->id }})"
                                wire:confirm="Approve this return? Stock will be put back on the shelf and customer refund/credit will be processed."
                                class="btn-success btn-sm flex-1"
                                icon="o-check"
                                spinner="approveReturn"
                            />
                            <x-button
                                label="Reject"
                                wire:click="openRejectModal({{ $viewReturn->id }})"
                                class="btn-error btn-outline btn-sm flex-1"
                                icon="o-x-mark"
                            />
                        </div>
                    </div>
                @endif
            </div>

            <x-slot:actions>
                @if($viewReturn->isApproved())
                    <a href="{{ route('return.receipt', $viewReturn->id) }}" target="_blank" class="btn btn-primary btn-sm">
                        <x-icon name="o-printer" class="w-4 h-4 mr-1" /> Slip
                    </a>
                @endif
                <x-button label="Close" wire:click="closeDetail" class="btn-ghost" />
            </x-slot:actions>
        @endif
    </x-drawer>

    <!-- Reject Modal -->
    <x-modal wire:model="rejectModal" title="Reject Return Request">
        <div class="space-y-3">
            <p class="text-sm text-base-content/70">
                Are you sure you want to reject this return? The goods will not be restocked and no refund will be issued.
            </p>
            <x-textarea wire:model="rejectionReason" label="Reason for rejection (optional)" placeholder="Explain why the return was declined..." rows="3" />
        </div>
        <x-slot:actions>
            <x-button label="Cancel" wire:click="$set('rejectModal', false)" class="btn-ghost" />
            <x-button label="Confirm Rejection" wire:click="rejectReturn" class="btn-error" spinner="rejectReturn" />
        </x-slot:actions>
    </x-modal>
</div>
