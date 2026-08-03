<div>
    <x-header :title="'Stock Take #' . $stockTake->id"
              :subtitle="'Started ' . $stockTake->created_at->format('M d, Y H:i') . ' by ' . $stockTake->starter->name">
        <x-slot:actions>
            <a href="{{ route('stock-take.index') }}" class="btn btn-ghost btn-sm gap-2">
                <x-icon name="o-arrow-left" class="w-4 h-4" /> All Stock Takes
            </a>
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-3 mb-4">
        <x-stat title="Total Products" value="{{ $totalProducts }}" icon="o-cube" color="text-primary" />
        <x-stat
            title="Counted"
            value="{{ $countedProducts }}"
            :description="($totalProducts > 0 ? round(($countedProducts / $totalProducts) * 100) : 0) . '% done'"
            icon="o-check-circle"
            :color="$countedProducts === $totalProducts ? 'text-success' : 'text-warning'"
        />
        <x-stat
            title="Discrepancies"
            value="{{ $discrepancies }}"
            icon="o-exclamation-triangle"
            :color="$discrepancies > 0 ? 'text-error' : 'text-success'"
        />
    </div>

    {{-- Progress bar (in_progress only) --}}
    @if($stockTake->status === 'in_progress')
        @php $pct = $totalProducts > 0 ? round(($countedProducts / $totalProducts) * 100) : 0; @endphp
        <div class="w-full bg-base-300 rounded-full h-2.5 mb-2">
            <div class="bg-primary h-2.5 rounded-full transition-all duration-300" style="width: {{ $pct }}%"></div>
        </div>
        <p class="text-xs text-base-content/50 mb-4">Counts save automatically as you type. Enter 0 for products with no stock on shelf.</p>
    @endif

    {{-- Status banners --}}
    @if($stockTake->status === 'pending_approval')
        <div role="alert" class="alert alert-warning mb-4">
            <x-icon name="o-clock" class="w-5 h-5 shrink-0" />
            <span>Submitted {{ $stockTake->submitted_at->format('M d, Y H:i') }} — awaiting approval from admin or branch manager.</span>
        </div>
    @elseif($stockTake->status === 'approved')
        <div role="alert" class="alert alert-success mb-4">
            <x-icon name="o-check-circle" class="w-5 h-5 shrink-0" />
            <div>
                <p class="font-semibold">Approved by {{ $stockTake->approver->name }} on {{ $stockTake->approved_at->format('M d, Y H:i') }}. Inventory adjusted.</p>
                @if($stockTake->notes)
                    <p class="text-sm mt-1">Notes: {{ $stockTake->notes }}</p>
                @endif
            </div>
        </div>
    @elseif($stockTake->status === 'rejected')
        <div role="alert" class="alert alert-error mb-4">
            <x-icon name="o-x-circle" class="w-5 h-5 shrink-0" />
            <div>
                <p class="font-semibold">Rejected by {{ $stockTake->approver->name }} on {{ $stockTake->approved_at->format('M d, Y H:i') }}.</p>
                @if($stockTake->notes)
                    <p class="text-sm mt-1">Reason: {{ $stockTake->notes }}</p>
                @endif
            </div>
        </div>
    @endif

    {{-- Approval panel --}}
    @if($stockTake->status === 'pending_approval' && $isApprover)
        <div class="card bg-base-200 border border-base-300 mb-4">
            <div class="card-body p-4">
                <h3 class="font-semibold mb-3">Review & Approve</h3>
                <x-textarea wire:model="approvalNotes" label="Notes (optional)" placeholder="Any observations about the count..." rows="2" class="mb-3" />
                <div class="flex gap-2">
                    <x-button
                        label="Approve & Apply Adjustments"
                        wire:click="approve"
                        class="btn-success"
                        icon="o-check"
                        wire:confirm="Apply all stock adjustments and mark this stock take as approved? This cannot be undone."
                        spinner="approve"
                    />
                    <x-button
                        label="Reject"
                        wire:click="$set('rejectModal', true)"
                        class="btn-error btn-outline"
                        icon="o-x-mark"
                    />
                </div>
            </div>
        </div>
    @endif

    {{-- Filters + Search --}}
    <div class="flex flex-col sm:flex-row gap-2 mb-4">
        <div class="flex gap-1">
            <button wire:click="$set('filter','all')"
                @class(['btn btn-sm', 'btn-primary' => $filter === 'all', 'btn-ghost' => $filter !== 'all'])>
                All <span class="badge badge-xs ml-1">{{ $totalProducts }}</span>
            </button>
            <button wire:click="$set('filter','pending')"
                @class(['btn btn-sm', 'btn-warning' => $filter === 'pending', 'btn-ghost' => $filter !== 'pending'])>
                Not Counted <span class="badge badge-xs ml-1">{{ $totalProducts - $countedProducts }}</span>
            </button>
            <button wire:click="$set('filter','discrepancy')"
                @class(['btn btn-sm', 'btn-error' => $filter === 'discrepancy', 'btn-ghost' => $filter !== 'discrepancy'])>
                Discrepancies <span class="badge badge-xs ml-1">{{ $discrepancies }}</span>
            </button>
        </div>
        <x-input
            icon="o-magnifying-glass"
            placeholder="Search products..."
            wire:model.live.debounce="search"
            clearable
            class="flex-1"
        />
    </div>

    {{-- Products table --}}
    <div class="overflow-x-auto rounded-lg border border-base-300">
        <table class="table table-sm w-full">
            <thead>
                <tr class="bg-base-200">
                    <th>Product</th>
                    <th class="hidden sm:table-cell">Category</th>
                    <th class="text-center">System Qty</th>
                    <th class="text-center">
                        @if($stockTake->status === 'in_progress') Physical Count
                        @else Counted
                        @endif
                    </th>
                    <th class="text-center">Difference</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    @php
                        $phys    = $physicalQtys[$item->product_id] ?? '';
                        $counted = $phys !== '';
                        $diff    = $counted ? ((int) $phys) - $item->system_qty : null;
                    @endphp
                    <tr wire:key="stock-item-{{ $item->product_id }}" @class([
                        'hover',
                        'bg-error/5'   => $diff !== null && $diff < 0,
                        'bg-warning/5' => $diff !== null && $diff > 0,
                    ])>
                        <td class="font-medium text-sm">{{ $item->product->name }}</td>
                        <td class="hidden sm:table-cell text-xs text-base-content/60">
                            {{ $item->product->category?->name ?? '—' }}
                        </td>
                        <td class="text-center font-mono text-sm">{{ $item->system_qty }}</td>
                        <td class="text-center">
                            @if($stockTake->status === 'in_progress')
                                <input
                                    type="number"
                                    wire:model.live.debounce.600ms="physicalQtys.{{ $item->product_id }}"
                                    min="0"
                                    class="input input-bordered input-sm w-20 text-center font-mono"
                                    placeholder="—"
                                />
                            @else
                                <span class="font-mono text-sm {{ $counted ? '' : 'text-base-content/30' }}">
                                    {{ $counted ? (int) $phys : '—' }}
                                </span>
                            @endif
                        </td>
                        <td class="text-center font-mono font-bold text-sm">
                            @if($diff !== null)
                                <span @class([
                                    'text-base-content/40' => $diff === 0,
                                    'text-error'           => $diff < 0,
                                    'text-warning'         => $diff > 0,
                                ])>
                                    {{ $diff > 0 ? '+' : '' }}{{ $diff }}
                                </span>
                            @else
                                <span class="text-base-content/20">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-10 text-base-content/40">No products found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $items->links() }}</div>

    {{-- Submit button --}}
    @if($stockTake->status === 'in_progress')
        <div class="mt-6 p-4 bg-base-200 rounded-lg flex items-center justify-between gap-4">
            <p class="text-sm text-base-content/60">
                {{ $countedProducts }}/{{ $totalProducts }} products counted.
                @if($countedProducts < $totalProducts)
                    <span class="text-warning font-semibold">{{ $totalProducts - $countedProducts }} remaining.</span>
                @endif
            </p>
            <x-button
                label="Submit for Approval"
                wire:click="submit"
                class="btn-primary"
                icon="o-paper-airplane"
                wire:confirm="Submit this stock take for approval? All products must be counted (enter 0 if not in stock)."
                spinner="submit"
            />
        </div>
    @endif

    {{-- Reject Modal --}}
    <x-modal wire:model="rejectModal" title="Reject Stock Take">
        <p class="text-sm text-base-content/60 mb-3">
            Explain why this stock take is being rejected so the team knows what to recount.
        </p>
        <x-textarea wire:model="approvalNotes" label="Rejection reason" placeholder="e.g. Antibiotics section was not fully counted." rows="3" />
        <x-slot:actions>
            <x-button label="Cancel" wire:click="$set('rejectModal', false)" class="btn-ghost" />
            <x-button label="Reject" wire:click="reject" class="btn-error" icon="o-x-mark" spinner="reject" />
        </x-slot:actions>
    </x-modal>
</div>
