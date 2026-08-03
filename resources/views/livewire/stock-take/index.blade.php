<div>
    <x-header title="Stock Takes" subtitle="Full store inventory count history">
        <x-slot:actions>
            @if($activeStockTake)
                <a href="{{ route('stock-take.show', $activeStockTake) }}"
                   class="btn btn-warning btn-sm gap-2">
                    <x-icon name="o-arrow-right" class="w-4 h-4" />
                    @if($activeStockTake->status === 'in_progress') Continue Stock Take
                    @else Review Pending Approval
                    @endif
                </a>
            @else
                <x-button
                    label="Start New Stock Take"
                    wire:click="startStockTake"
                    class="btn-primary btn-sm"
                    icon="o-play"
                    wire:confirm="Start a new full-store stock take? This will snapshot current inventory quantities."
                    spinner="startStockTake"
                />
            @endif
        </x-slot:actions>
    </x-header>

    @if($stockTakes->isEmpty())
        <div class="text-center py-16 text-base-content/40">
            <x-icon name="o-clipboard-document-check" class="w-16 h-16 mx-auto mb-3 opacity-30" />
            <p class="font-semibold text-lg">No stock takes yet</p>
            <p class="text-sm mt-1">Start your first stock take to compare physical vs system inventory.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-base-300">
            <table class="table table-sm w-full">
                <thead>
                    <tr class="bg-base-200">
                        <th>#</th>
                        <th>Status</th>
                        <th>Started By</th>
                        <th>Date Started</th>
                        <th class="text-center">Products</th>
                        <th class="text-center">Discrepancies</th>
                        <th>Approved By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stockTakes as $take)
                        <tr class="hover">
                            <td class="text-base-content/50 font-mono text-xs">{{ $take->id }}</td>
                            <td>
                                <x-badge :value="ucfirst(str_replace('_', ' ', $take->status))" @class([
                                    'badge-warning' => $take->status === 'in_progress',
                                    'badge-info'    => $take->status === 'pending_approval',
                                    'badge-success' => $take->status === 'approved',
                                    'badge-error'   => $take->status === 'rejected',
                                ]) />
                            </td>
                            <td>{{ $take->starter->name }}</td>
                            <td class="text-sm text-base-content/70">{{ $take->created_at->format('M d, Y H:i') }}</td>
                            <td class="text-center font-mono">{{ $take->items_count }}</td>
                            <td class="text-center font-mono font-bold">
                                @if($take->discrepancies_count > 0)
                                    <span class="text-error">{{ $take->discrepancies_count }}</span>
                                @else
                                    <span class="text-success">0</span>
                                @endif
                            </td>
                            <td class="text-sm text-base-content/70">
                                {{ $take->approver?->name ?? '—' }}
                                @if($take->approved_at)
                                    <span class="block text-xs text-base-content/40">{{ $take->approved_at->format('M d, Y') }}</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('stock-take.show', $take) }}" class="btn btn-xs btn-ghost">
                                    <x-icon name="o-eye" class="w-4 h-4" />
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $stockTakes->links() }}</div>
    @endif
</div>
