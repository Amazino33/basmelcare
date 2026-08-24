<div>
    <x-header title="Prescriptions" subtitle="Online orders waiting on a pharmacist" size="text-xl" />

    <div class="flex flex-wrap gap-2 mb-4">
        @foreach([
            'waiting'  => ['Waiting on you', $waitingCount],
            'reviewed' => ['Already reviewed', null],
        ] as $key => [$label, $count])
            <button type="button" wire:click="$set('filter', '{{ $key }}')"
                class="btn btn-sm {{ $filter === $key ? 'btn-primary' : 'btn-ghost bg-base-200' }}">
                {{ $label }}
                @if($count !== null)
                    <span class="badge badge-sm {{ $filter === $key ? 'badge-neutral' : 'badge-ghost' }}">{{ $count }}</span>
                @endif
            </button>
        @endforeach
    </div>

    @unless($canReview)
        <div class="alert alert-warning py-2 mb-4 text-sm gap-2">
            <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
            <span>Only a pharmacist can approve or reject a prescription. You can look, but not decide.</span>
        </div>
    @endunless

    <div class="space-y-2">
        @forelse($orders as $order)
            <x-card class="!p-3">
                <div class="flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-sm">{{ $order->order_number }}</span>

                            @if($order->prescriptionApproved())
                                <span class="badge badge-success badge-sm">Approved</span>
                            @elseif($order->prescriptionRejected())
                                <span class="badge badge-error badge-sm">Rejected</span>
                            @else
                                <span class="badge badge-warning badge-sm">Waiting</span>
                            @endif
                        </div>

                        <div class="text-xs text-base-content/60 mt-1">
                            {{ $order->customer->name ?? $order->guest_name ?? 'Guest' }}
                            &middot; {{ $order->created_at->diffForHumans() }}
                        </div>

                        {{-- What is actually being dispensed matters more than the total --}}
                        <div class="text-xs mt-1">
                            @foreach($order->items as $item)
                                <span class="{{ $item->product?->requires_prescription ? 'font-semibold' : 'text-base-content/50' }}">
                                    {{ $item->product->name ?? 'Removed product' }} &times;{{ $item->quantity }}@if(! $loop->last), @endif
                                </span>
                            @endforeach
                        </div>

                        @if($order->prescriptionRejected() && $order->prescription_note)
                            <div class="text-xs text-error mt-1">Rejected: {{ $order->prescription_note }}</div>
                        @endif

                        @if($order->prescription_reviewed_at)
                            <div class="text-xs text-base-content/40 mt-1">
                                {{ $order->prescriptionReviewer->name ?? 'Unknown' }},
                                {{ $order->prescription_reviewed_at->format('d M Y g:i A') }}
                            </div>
                        @endif
                    </div>

                    <x-button label="Open" icon="o-document-magnifying-glass"
                              wire:click="viewOrder({{ $order->id }})"
                              class="btn-sm {{ $order->awaitingPrescriptionReview() ? 'btn-primary' : 'btn-ghost' }} shrink-0" />
                </div>
            </x-card>
        @empty
            <x-card>
                <div class="text-center py-10 text-base-content/50">
                    @if($filter === 'waiting')
                        <x-icon name="o-check-circle" class="w-12 h-12 mx-auto mb-3 text-success opacity-40" />
                        <p class="font-semibold">Nothing waiting</p>
                        <p class="text-sm mt-1">Every prescription has been looked at.</p>
                    @else
                        <x-icon name="o-document-text" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                        <p class="font-semibold">Nothing reviewed yet</p>
                    @endif
                </div>
            </x-card>
        @endforelse
    </div>

    @if($orders->hasPages())
        <div class="mt-4">{{ $orders->links() }}</div>
    @endif

    {{-- One dialog, not stacked: the prescription and the decision together --}}
    <x-modal wire:model="viewOrderId" title="Prescription" separator box-class="max-w-2xl">
        @if($viewOrder)
            <div class="text-sm space-y-3">
                <div>
                    <div class="font-semibold">{{ $viewOrder->order_number }}</div>
                    <div class="text-xs text-base-content/60">
                        {{ $viewOrder->customer->name ?? $viewOrder->guest_name ?? 'Guest' }}
                        &middot; {{ $viewOrder->created_at->format('d M Y g:i A') }}
                    </div>
                </div>

                <div class="rounded-lg border border-base-200 p-3">
                    <div class="font-semibold text-xs uppercase tracking-wide text-base-content/60 mb-2">Being dispensed</div>
                    @foreach($viewOrder->items as $item)
                        <div class="flex justify-between text-sm py-0.5">
                            <span class="{{ $item->product?->requires_prescription ? 'font-semibold' : '' }}">
                                {{ $item->product->name ?? 'Removed product' }}
                                @if($item->product?->requires_prescription)
                                    <span class="badge badge-error badge-xs align-middle">Rx</span>
                                @endif
                            </span>
                            <span class="tabular-nums">&times;{{ $item->quantity }}</span>
                        </div>
                    @endforeach
                </div>

                @if($viewOrder->prescription_path)
                    <a href="{{ route('prescriptions.file', $viewOrder->id) }}" target="_blank"
                       class="btn btn-outline btn-sm btn-block">
                        <x-icon name="o-document" class="w-4 h-4" /> Open the prescription
                    </a>
                @else
                    <div class="alert alert-error py-2 text-sm gap-2">
                        <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
                        <span>No prescription was uploaded with this order.</span>
                    </div>
                @endif

                @if($viewOrder->awaitingPrescriptionReview() && $canReview)
                    @if($rejecting)
                        <x-textarea label="Why is it being rejected?" wire:model="rejectionNote" rows="3"
                                    placeholder="Illegible, expired, does not match the medicine ordered..."
                                    hint="The customer will be told this." />
                    @endif
                @elseif($viewOrder->prescription_reviewed_at)
                    <div class="text-xs text-base-content/60">
                        {{ $viewOrder->prescriptionApproved() ? 'Approved' : 'Rejected' }} by
                        {{ $viewOrder->prescriptionReviewer->name ?? 'Unknown' }} on
                        {{ $viewOrder->prescription_reviewed_at->format('d M Y g:i A') }}
                        @if($viewOrder->prescription_note)
                            <div class="text-error mt-1">{{ $viewOrder->prescription_note }}</div>
                        @endif
                    </div>
                @endif
            </div>

            <x-slot:actions>
                <x-button label="Close" wire:click="closeOrder" class="btn-ghost" />

                @if($viewOrder->awaitingPrescriptionReview() && $canReview)
                    @if($rejecting)
                        <x-button label="Confirm rejection" wire:click="reject({{ $viewOrder->id }})"
                                  class="btn-error" spinner />
                    @else
                        <x-button label="Reject" wire:click="startReject" class="btn-ghost text-error" />
                        <x-button label="Approve" icon="o-check" wire:click="approve({{ $viewOrder->id }})"
                                  class="btn-primary" spinner />
                    @endif
                @endif
            </x-slot:actions>
        @endif
    </x-modal>
</div>
