<div>
    <x-header title="Expiry Alerts" subtitle="Track products nearing expiration">
        <x-slot:actions>
            <x-select wire:model.live="filter" :options="[
                ['id' => '30', 'name' => 'Next 30 days'],
                ['id' => '60', 'name' => 'Next 60 days'],
                ['id' => '90', 'name' => 'Next 90 days'],
                ['id' => 'expired', 'name' => 'Already Expired'],
            ]" option-value="id" option-label="name" />
        </x-slot:actions>
    </x-header>

    <x-table :headers="$headers" :rows="$batches" with-pagination>
        @scope('cell_cost_price', $batch)
            ₦{{ number_format($batch->cost_price, 2) }}
        @endscope

        @scope('cell_expiry_date', $batch)
            {{ $batch->expiry_date->format('M d, Y') }}
        @endscope

        @scope('cell_days_left', $batch)
            @php $days = (int) now()->diffInDays($batch->expiry_date, false); @endphp
            @if($days < 0)
                <x-badge value="Expired {{ abs($days) }}d ago" class="badge-error" />
            @elseif($days <= 30)
                <x-badge value="{{ $days }} days" class="badge-error" />
            @elseif($days <= 60)
                <x-badge value="{{ $days }} days" class="badge-warning" />
            @else
                <x-badge value="{{ $days }} days" class="badge-info" />
            @endif
        @endscope
    </x-table>
</div>
