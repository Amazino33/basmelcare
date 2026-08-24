<div>
    <x-header title="Reports" subtitle="Generate and export reports" />

    <x-card>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            @php
                $types = [
                    ['id' => 'sales', 'name' => 'Sales Report'],
                    ['id' => 'profit', 'name' => 'Profit Report'],
                    ['id' => 'stock', 'name' => 'Stock Report'],
                    ['id' => 'expiry', 'name' => 'Expiry Report'],
                    ['id' => 'debts', 'name' => 'Outstanding Debts'],
                    ['id' => 'movements', 'name' => 'Stock Movements'],
                ];

                // Not offered to the auditor: the rankings expose margin, and
                // their view of it belongs in Financial Records with the rest
                // of the picture.
                if ($this->canSeeTopProducts()) {
                    $types[] = ['id' => 'top-products', 'name' => 'Top Products'];
                }
            @endphp

            <x-select label="Report Type" wire:model.live="reportType" :options="$types"
                      option-value="id" option-label="name" />

            @if(in_array($reportType, ['sales', 'profit', 'movements', 'top-products']))
                <x-input label="From" wire:model="dateFrom" type="date" />
                <x-input label="To" wire:model="dateTo" type="date" />
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-base-200 rounded-lg">
                <div class="font-semibold text-sm mb-1">Sales Report</div>
                <div class="text-xs text-base-content/60">All completed sales with customer, cashier, payment method, and totals.</div>
            </div>
            <div class="p-4 bg-base-200 rounded-lg">
                <div class="font-semibold text-sm mb-1">Profit Report</div>
                <div class="text-xs text-base-content/60">Per-item breakdown of revenue, cost, and profit for each sale.</div>
            </div>
            <div class="p-4 bg-base-200 rounded-lg">
                <div class="font-semibold text-sm mb-1">Stock Report</div>
                <div class="text-xs text-base-content/60">Current stock levels per product, batch, location with cost and expiry.</div>
            </div>
            <div class="p-4 bg-base-200 rounded-lg">
                <div class="font-semibold text-sm mb-1">Expiry Report</div>
                <div class="text-xs text-base-content/60">Products expiring within 90 days with quantities and days remaining.</div>
            </div>
            <div class="p-4 bg-base-200 rounded-lg">
                <div class="font-semibold text-sm mb-1">Outstanding Debts</div>
                <div class="text-xs text-base-content/60">All unpaid and partially paid customer debts with balances.</div>
            </div>
            <div class="p-4 bg-base-200 rounded-lg">
                <div class="font-semibold text-sm mb-1">Stock Movements</div>
                <div class="text-xs text-base-content/60">Full audit log of purchases, sales, transfers, and adjustments.</div>
            </div>
            @if($this->canSeeTopProducts())
                <div class="p-4 bg-base-200 rounded-lg">
                    <div class="font-semibold text-sm mb-1">Top Products</div>
                    <div class="text-xs text-base-content/60">What customers ask for most, what earns the most, and what makes the most profit.</div>
                </div>
            @endif
        </div>

        <div class="mt-6 flex flex-wrap gap-2">
            <x-button label="Download CSV" wire:click="export" icon="o-arrow-down-tray" class="btn-primary" />

            @if($reportType === 'top-products' && $this->canSeeTopProducts())
                {{-- Opens the printed sheet in its own tab, as inventory printing does --}}
                <x-button label="Print"
                          icon="o-printer"
                          link="{{ route('reports.top-products.print', ['from' => $dateFrom, 'to' => $dateTo]) }}"
                          class="btn-outline"
                          external />
            @endif
        </div>
    </x-card>
</div>
