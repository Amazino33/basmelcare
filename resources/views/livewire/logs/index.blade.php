<div>
    <x-header title="System Logs" subtitle="Last 200 entries from laravel.log">
        <x-slot:actions>
            <x-button label="Refresh" wire:click="$refresh" icon="o-arrow-path" class="btn-ghost btn-sm" />
            <x-button label="Clear Log" wire:click="clearLog"
                wire:confirm="This will permanently delete all log entries. Are you sure?"
                icon="o-trash" class="btn-error btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Level filter chips --}}
    <div class="flex flex-wrap gap-2 mb-4">
        @foreach(['all' => 'All', 'info' => 'Info', 'warning' => 'Warning', 'error' => 'Error', 'debug' => 'Debug'] as $level => $label)
            <button wire:click="$set('filterLevel', '{{ $level }}')"
                class="badge badge-lg cursor-pointer select-none
                    {{ $filterLevel === $level ? 'badge-neutral' : 'badge-ghost' }}
                    {{ $level === 'error'   ? 'hover:badge-error'   : '' }}
                    {{ $level === 'warning' ? 'hover:badge-warning' : '' }}
                    {{ $level === 'info'    ? 'hover:badge-info'    : '' }}">
                {{ $label }}
                @if($level !== 'all' && isset($counts[$level]))
                    <span class="ml-1 opacity-70">({{ $counts[$level] }})</span>
                @endif
            </button>
        @endforeach

        <div class="ml-auto">
            <x-input wire:model.live="search" placeholder="Search messages..." icon="o-magnifying-glass" class="input-sm" />
        </div>
    </div>

    {{-- Entries --}}
    @if($entries->isEmpty())
        <x-card>
            <div class="text-center py-10 text-base-content/40">
                <x-icon name="o-document-text" class="w-12 h-12 mx-auto mb-3" />
                <p>No log entries found.</p>
            </div>
        </x-card>
    @else
        <div class="space-y-1">
            @foreach($entries as $entry)
                @php
                    $levelClass = match($entry['level']) {
                        'error'     => 'border-error/40 bg-error/5',
                        'warning'   => 'border-warning/40 bg-warning/5',
                        'info'      => 'border-info/20 bg-base-100',
                        default     => 'border-base-300 bg-base-100',
                    };
                    $badgeClass = match($entry['level']) {
                        'error'   => 'badge-error',
                        'warning' => 'badge-warning',
                        'info'    => 'badge-info',
                        default   => 'badge-ghost',
                    };
                @endphp

                <div class="border {{ $levelClass }} rounded-lg px-3 py-2 text-sm">
                    <div class="flex items-start gap-2">
                        <span class="badge badge-sm {{ $badgeClass }} shrink-0 mt-0.5">{{ strtoupper($entry['level']) }}</span>
                        <span class="text-base-content/40 text-xs shrink-0 mt-0.5 tabular-nums">{{ $entry['datetime'] }}</span>
                        <span class="flex-1 font-mono text-xs break-all">{{ $entry['message'] }}</span>
                    </div>

                    @if(trim($entry['trace']))
                        <details class="mt-1">
                            <summary class="text-xs text-base-content/40 cursor-pointer hover:text-base-content ml-16">
                                Stack trace
                            </summary>
                            <pre class="text-xs mt-1 ml-16 overflow-x-auto text-base-content/60 whitespace-pre-wrap">{{ trim($entry['trace']) }}</pre>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
