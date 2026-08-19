<div>
    <x-header title="Products" subtitle="Manage products and batches">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search by name or barcode..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Import" wire:click="openImport" icon="o-arrow-up-tray" class="btn-outline btn-sm" />
            <x-button
                :label="$bulkEditMode ? 'Exit Bulk Edit' : 'Bulk Edit'"
                wire:click="toggleBulkEdit"
                icon="{{ $bulkEditMode ? 'o-x-mark' : 'o-pencil-square' }}"
                class="{{ $bulkEditMode ? 'btn-warning' : 'btn-outline' }} btn-sm"
            />
            @if(!$bulkEditMode)
                <x-button label="Quick Add" wire:click="openQuickAdd" icon="o-bolt" class="btn-secondary" />
                <x-button label="Add Product" wire:click="createProduct" icon="o-plus" class="btn-primary" />
            @endif
        </x-slot:actions>
    </x-header>

    @if($bulkEditMode)
        {{-- Bulk Edit Mode --}}
        <div class="bg-warning/10 border border-warning/30 rounded-lg p-3 mb-3 space-y-2">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="flex items-center gap-2 flex-1">
                    <x-icon name="o-pencil-square" class="w-4 h-4 text-warning shrink-0" />
                    <span class="text-sm font-semibold">Bulk Edit Mode — {{ count($bulkEdits) }} {{ Str::plural('product', count($bulkEdits)) }} loaded</span>
                    <span class="text-xs text-base-content/50 hidden sm:inline">Changes save across all pages</span>
                </div>
                <div class="flex gap-2">
                    <x-button label="Cancel" wire:click="toggleBulkEdit" class="btn-sm btn-ghost" />
                    <x-button
                        label="Save All ({{ count($bulkEdits) }})"
                        wire:click="saveBulkEdits"
                        class="btn-sm btn-success"
                        icon="o-check"
                        wire:loading.attr="disabled"
                        wire:target="saveBulkEdits"
                    />
                </div>
            </div>
            <label class="flex items-center gap-2 cursor-pointer w-fit">
                <input
                    type="checkbox"
                    id="bulk-markup-toggle"
                    wire:model.live="bulkApplyMarkup"
                    class="checkbox checkbox-warning checkbox-sm"
                />
                <span class="text-xs font-medium">
                    Auto-apply markup formula <span class="text-base-content/50">(cost × 1.4 → nearest ₦100)</span>
                    @if($bulkApplyMarkup)
                        <span class="text-warning font-semibold ml-1">— ON: overwrites all selling prices</span>
                    @endif
                </span>
            </label>
        </div>

        <div class="overflow-x-auto rounded-lg border border-base-300">
            <table class="table table-sm w-full">
                <thead class="bg-base-200 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="w-8">#</th>
                        <th class="min-w-52">Name</th>
                        <th class="min-w-40">Category</th>
                        <th class="min-w-32">Cost Price (₦)</th>
                        <th class="min-w-32">Selling Price (₦)</th>
                        <th class="min-w-28">Stock Qty</th>
                        <th class="min-w-32">Expiry Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                        <tr wire:key="bulk-{{ $product->id }}" class="hover:bg-base-100 border-b border-base-200">
                            <td class="text-base-content/40 text-xs">{{ $product->id }}</td>
                            <td>
                                <input
                                    type="text"
                                    wire:model="bulkEdits.{{ $product->id }}.name"
                                    class="input input-sm input-bordered w-full min-w-48 uppercase"
                                />
                            </td>
                            <td>
                                <select
                                    wire:model="bulkEdits.{{ $product->id }}.category_id"
                                    class="select select-sm select-bordered w-full uppercase"
                                >
                                    <option value="">— Select —</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    wire:model="bulkEdits.{{ $product->id }}.cost_price"
                                    data-bulk-cost
                                    class="input input-sm input-bordered w-full"
                                />
                            </td>
                            <td>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    wire:model="bulkEdits.{{ $product->id }}.selling_price"
                                    data-bulk-sell
                                    @disabled(! $this->canSetPrices())
                                    class="input input-sm input-bordered w-full disabled:opacity-60"
                                    @if(! $this->canSetPrices()) title="Only an admin or branch manager can change prices" @endif
                                />
                                <div class="bulk-price-warn text-warning text-xs mt-0.5 items-center gap-1" style="display:none">
                                    ⚠ Below cost
                                </div>
                            </td>
                            <td>
                                <input
                                    type="number"
                                    min="0"
                                    wire:model="bulkEdits.{{ $product->id }}.qty"
                                    class="input input-sm input-bordered w-24"
                                />
                            </td>
                            <td>
                                <input
                                    type="month"
                                    wire:model="bulkEdits.{{ $product->id }}.expiry_date"
                                    class="input input-sm input-bordered w-full"
                                />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $products->links() }}</div>

        <div class="mt-3 flex justify-end gap-2">
            <x-button label="Cancel" wire:click="toggleBulkEdit" class="btn-ghost" />
            <x-button
                label="Save All Changes ({{ count($bulkEdits) }})"
                wire:click="saveBulkEdits"
                class="btn-success"
                icon="o-check"
                wire:loading.attr="disabled"
                wire:target="saveBulkEdits"
            />
        </div>

    @else
        {{-- Normal table --}}
        <x-table :headers="$headers" :rows="$products" with-pagination>
            @scope('cell_image', $product)
                @if($product->image)
                    <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" class="w-10 h-10 rounded object-cover" />
                @else
                    <div class="w-10 h-10 rounded bg-base-200 flex items-center justify-center">
                        <x-icon name="o-cube" class="w-5 h-5 text-base-content/30" />
                    </div>
                @endif
            @endscope

            @scope('cell_selling_price', $product)
                @if((float) $product->selling_price <= 0)
                    <span class="badge badge-warning badge-sm gap-1">
                        <x-icon name="o-exclamation-triangle" class="w-3 h-3" />
                        Needs pricing
                    </span>
                @else
                ₦{{ number_format($product->selling_price, 2) }}
                    @if($product->wholesale_price)
                        <div class="text-xs text-info">W/S: ₦{{ number_format($product->wholesale_price, 2) }}{{ $product->wholesale_min_qty ? ' ('.$product->wholesale_min_qty.'+)' : '' }}</div>
                    @endif
                @endif
            @endscope

            @scope('cell_stock', $product)
                @php $total = $product->batches->sum('quantity'); @endphp
                <x-badge :value="$total" @class([
                    'badge-success' => $total > $product->reorder_level,
                    'badge-warning' => $total > 0 && $total <= $product->reorder_level,
                    'badge-error' => $total == 0,
                ]) />
            @endscope

            @scope('actions', $product)
                <div class="flex gap-1">
                    <x-button icon="o-eye" wire:click="viewBatches({{ $product->id }})" class="btn-xs btn-ghost" tooltip="View Batches" />
                    <x-button icon="o-plus-circle" wire:click="openBatchModal({{ $product->id }})" class="btn-xs btn-ghost text-success" tooltip="Add Batch" />
                    <x-button icon="o-pencil" wire:click="editProduct({{ $product->id }})" class="btn-xs btn-ghost" tooltip="Edit" />
                    <x-button icon="o-trash" wire:click="deleteProduct({{ $product->id }})" class="btn-xs btn-ghost text-error" wire:confirm="Delete this product and all its batches?" tooltip="Delete" />
                </div>
            @endscope
        </x-table>
    @endif

    <!-- Quick Add Modal -->
    <x-modal wire:model="quickModal" title="Quick Add Product" box-class="max-w-lg">
        @if($quickAddCount > 0)
            <div class="alert alert-success py-2 mb-4">
                <x-icon name="o-check-circle" class="w-4 h-4" />
                <span class="text-sm">{{ $quickAddCount }} {{ Str::plural('product', $quickAddCount) }} added this session.</span>
            </div>
        @endif

        <x-form wire:submit="saveQuickAdd">
            <x-input label="Product Name" wire:model="quick_name" placeholder="e.g. Paracetamol 500mg" />
            <x-select label="Category" wire:model="quick_category_id" :options="$categories" option-value="id" option-label="name" placeholder="Select category" hint="Stays selected between entries" />

            <div x-data
                 x-effect="$el.querySelector('.qa-price-warn').style.display =
                     (parseFloat($wire.quick_selling_price) > 0 && parseFloat($wire.quick_cost_price) > 0 && parseFloat($wire.quick_selling_price) < parseFloat($wire.quick_cost_price))
                     ? 'flex' : 'none'">
                <div class="grid grid-cols-2 gap-4">
                    <x-input label="Cost Price" wire:model.live="quick_cost_price" prefix="₦" type="number" step="0.01" />
                    @if($this->canSetPrices())
                        <x-input label="Selling Price" wire:model.live="quick_selling_price" prefix="₦" type="number" step="0.01" hint="Auto-filled from cost · type to override" />
                    @else
                        <x-input label="Selling Price" value="Set by manager" readonly
                            hint="Saves unpriced — a manager will set it" class="opacity-60" />
                    @endif
                    <x-input label="Quantity" wire:model="quick_quantity" type="number" min="1" />
                    <x-input label="Expiry Date" wire:model="quick_expiry_date" type="month" />
                </div>
                <div class="qa-price-warn alert alert-warning py-1.5 text-xs mt-1 gap-1" style="display:none">
                    <x-icon name="o-exclamation-triangle" class="w-3.5 h-3.5 shrink-0" />
                    Selling price is below cost — you will sell at a loss.
                </div>
            </div>

            <x-slot:actions>
                <x-button label="Done" @click="$wire.quickModal = false" />
                <x-button label="Save & Next" type="submit" class="btn-primary" icon-right="o-arrow-right" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <!-- Product Modal -->
    <x-modal wire:model="productModal" title="{{ $productId ? 'Edit Product' : 'New Product' }}" box-class="max-w-2xl">
        <x-form wire:submit="saveProduct">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Image Upload -->
                <div class="md:col-span-2">
                    <label class="label"><span class="label-text font-semibold">Product Image</span></label>
                    <div class="flex items-center gap-4">
                        @if($photo)
                            <img src="{{ $photo->temporaryUrl() }}" class="w-20 h-20 rounded object-cover border" />
                        @elseif($existingImage)
                            <img src="{{ asset('storage/' . $existingImage) }}" class="w-20 h-20 rounded object-cover border" />
                        @else
                            <div class="w-20 h-20 rounded bg-base-200 flex items-center justify-center border">
                                <x-icon name="o-photo" class="w-8 h-8 text-base-content/30" />
                            </div>
                        @endif
                        <div class="flex-1">
                            <input type="file" wire:model="photo" accept="image/*" class="file-input file-input-bordered file-input-sm w-full" />
                            @if($existingImage || $photo)
                                <x-button label="Remove" wire:click="removeImage" class="btn-xs btn-ghost text-error mt-1" icon="o-trash" />
                            @endif
                        </div>
                    </div>
                    @error('photo') <span class="text-error text-xs">{{ $message }}</span> @enderror
                </div>

                <x-input label="Product Name" wire:model="name" />
                <x-input label="SKU" wire:model="sku" placeholder="Optional" />
                <x-select label="Category" wire:model="category_id" :options="$categories" option-value="id" option-label="name" placeholder="Select category" />

                <!-- Barcode with scan button -->
                <div>
                    <x-input label="Barcode" wire:model="barcode" placeholder="Scan or type barcode">
                        <x-slot:append>
                            <x-button icon="o-camera" class="btn-sm btn-ghost rounded-l-none" onclick="startBarcodeScanner()" tooltip="Scan barcode" />
                        </x-slot:append>
                    </x-input>
                </div>

                <x-input
                    label="Cost Price (for calculation)"
                    wire:model.live.debounce.500ms="cost_price_hint"
                    prefix="₦"
                    type="number"
                    step="0.01"
                    hint="Not saved — used to auto-calculate selling price"
                />
                @if($this->canSetPrices())
                    <div x-data
                         x-effect="$el.querySelector('.price-warn').style.display =
                             (parseFloat($wire.selling_price) > 0 && parseFloat($wire.cost_price_hint) > 0 && parseFloat($wire.selling_price) < parseFloat($wire.cost_price_hint))
                             ? 'flex' : 'none'">
                        <x-input
                            label="Selling Price (Retail)"
                            wire:model.live="selling_price"
                            prefix="₦"
                            type="number"
                            step="0.01"
                            hint="Auto-filled from cost × 1.4 → nearest ₦100 · type to override"
                        />
                        <div class="price-warn alert alert-warning py-1.5 text-xs mt-1 gap-1" style="display:none">
                            <x-icon name="o-exclamation-triangle" class="w-3.5 h-3.5 shrink-0" />
                            Selling price is below cost — you will sell at a loss.
                        </div>
                    </div>
                    <x-input label="Wholesale Price" wire:model="wholesale_price" prefix="₦" type="number" step="0.01" hint="Leave empty if no wholesale pricing" />
                @else
                    <div class="md:col-span-2">
                        <div class="flex items-start gap-2 p-3 bg-base-200 rounded-lg">
                            <x-icon name="o-lock-closed" class="w-4 h-4 shrink-0 mt-0.5 text-base-content/50" />
                            <div class="text-sm">
                                <p class="font-medium">Pricing is set by a manager</p>
                                <p class="text-xs text-base-content/60 mt-0.5">
                                    @if($productId)
                                        Current price: <span class="font-semibold">₦{{ number_format((float) $selling_price, 2) }}</span>. Ask an admin or branch manager to change it.
                                    @else
                                        Save the product and stock now — an admin or branch manager will set the price.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
                <x-input label="Wholesale Min Qty" wire:model="wholesale_min_qty" type="number" hint="Retail buyers get wholesale price at this quantity" />
                <x-input label="Reorder Level" wire:model="reorder_level" type="number" hint="Alert when stock falls below this" />
                <div class="md:col-span-2">
                    <x-textarea label="Description" wire:model="description" placeholder="Optional" rows="2" />
                </div>
            </div>
            <x-slot:actions>
                <x-button :label="$productId ? 'Cancel' : 'Done'" @click="$wire.productModal = false" />
                <x-button label="Save" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <!-- Import Modal -->
    <x-modal wire:model="importModal" title="Import Products from Excel" box-class="max-w-lg">
        @if(empty($importResults))
            <div class="space-y-4">
                <div class="alert alert-info py-2 text-sm">
                    <x-icon name="o-information-circle" class="w-4 h-4 shrink-0" />
                    <span>
                        Download the template, fill it in Excel, then upload it here.
                        <a href="{{ route('products.import-template') }}" class="font-semibold underline" target="_blank">Download template ↓</a>
                    </span>
                </div>

                <div class="text-xs text-base-content/60 space-y-1">
                    <div class="font-semibold text-base-content/80 mb-1">Template columns:</div>
                    <div><span class="font-medium">A — Name</span> (required)</div>
                    <div><span class="font-medium">B — Batch Number</span> (optional)</div>
                    <div><span class="font-medium">C — Expiry Date</span> MM/YYYY format e.g. <code>08/2026</code></div>
                    <div><span class="font-medium">D — Qty</span> (required)</div>
                    <div><span class="font-medium">E — Cost Price</span> in ₦ (required)</div>
                    <div><span class="font-medium">F — Selling Price</span> (optional — auto-calculated if blank)</div>
                </div>

                <x-form wire:submit="processImport">
                    <div>
                        <label class="label"><span class="label-text font-semibold">Upload Excel File (.xlsx)</span></label>
                        <input type="file" wire:model="importFile" accept=".xlsx,.xls" class="file-input file-input-bordered w-full" />
                        @error('importFile') <span class="text-error text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <x-slot:actions>
                        <x-button label="Cancel" @click="$wire.importModal = false" />
                        <x-button label="Import" type="submit" class="btn-primary" icon="o-arrow-up-tray" wire:loading.attr="disabled" />
                    </x-slot:actions>
                </x-form>
            </div>
        @else
            {{-- Results view --}}
            <div class="space-y-3">
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="bg-success/10 rounded-lg p-3">
                        <div class="text-2xl font-bold text-success">{{ count($importResults['created']) }}</div>
                        <div class="text-xs text-base-content/60">New products</div>
                    </div>
                    <div class="bg-info/10 rounded-lg p-3">
                        <div class="text-2xl font-bold text-info">{{ count($importResults['batchAdded']) }}</div>
                        <div class="text-xs text-base-content/60">Batches added</div>
                    </div>
                    <div class="bg-error/10 rounded-lg p-3">
                        <div class="text-2xl font-bold text-error">{{ count($importResults['errors']) }}</div>
                        <div class="text-xs text-base-content/60">Errors</div>
                    </div>
                </div>

                @if(!empty($importResults['created']))
                    <div>
                        <div class="text-xs font-semibold text-success uppercase tracking-wide mb-1">New products created</div>
                        <div class="bg-base-200 rounded p-2 text-xs space-y-0.5 max-h-32 overflow-y-auto">
                            @foreach($importResults['created'] as $name)
                                <div class="flex items-center gap-1"><x-icon name="o-check-circle" class="w-3 h-3 text-success shrink-0" /> {{ $name }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($importResults['batchAdded']))
                    <div>
                        <div class="text-xs font-semibold text-info uppercase tracking-wide mb-1">Batches added to existing products</div>
                        <div class="bg-base-200 rounded p-2 text-xs space-y-0.5 max-h-32 overflow-y-auto">
                            @foreach($importResults['batchAdded'] as $name)
                                <div class="flex items-center gap-1"><x-icon name="o-plus-circle" class="w-3 h-3 text-info shrink-0" /> {{ $name }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($importResults['errors']))
                    <div>
                        <div class="text-xs font-semibold text-error uppercase tracking-wide mb-1">Errors (rows skipped)</div>
                        <div class="bg-error/5 border border-error/20 rounded p-2 text-xs space-y-0.5 max-h-32 overflow-y-auto">
                            @foreach($importResults['errors'] as $error)
                                <div class="text-error">{{ $error }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <x-slot:actions>
                <x-button label="Import Another File" wire:click="openImport" icon="o-arrow-path" />
                <x-button label="Done" @click="$wire.importModal = false" class="btn-primary" />
            </x-slot:actions>
        @endif
    </x-modal>

    <!-- Barcode Scanner Modal -->
    <dialog id="barcode-scanner-modal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg mb-4">Scan Barcode</h3>
            <div id="barcode-video-container" class="w-full aspect-video bg-black rounded overflow-hidden mb-4">
                <video id="barcode-video" class="w-full h-full object-cover"></video>
            </div>
            <p class="text-sm text-base-content/60 text-center">Point your camera at the barcode</p>
            <div class="modal-action">
                <button class="btn" onclick="stopBarcodeScanner()">Cancel</button>
            </div>
        </div>
    </dialog>

    <!-- Batch Modal -->
    <x-modal wire:model="batchModal" title="Add Batch">
        <x-form wire:submit="saveBatch">
            <x-input label="Batch Number" wire:model="batch_number" placeholder="Leave blank to auto-generate" hint="Optional" />
            <x-input label="Expiry Date" wire:model="expiry_date" type="month" />
            <div x-data
                 x-effect="$el.querySelector('.batch-price-warn').style.display =
                     (parseFloat($wire.cost_price) > 0 && $wire.batchProductSellingPrice > 0 && parseFloat($wire.cost_price) > $wire.batchProductSellingPrice)
                     ? 'flex' : 'none'">
                <x-input label="Cost Price" wire:model.live="cost_price" prefix="₦" type="number" step="0.01" />
                <div class="batch-price-warn alert alert-warning py-1.5 text-xs mt-1 gap-1" style="display:none">
                    <x-icon name="o-exclamation-triangle" class="w-3.5 h-3.5 shrink-0" />
                    Batch cost exceeds selling price (₦{{ number_format($batchProductSellingPrice, 2) }}) — you will sell at a loss.
                </div>
            </div>
            <x-input label="Quantity" wire:model="quantity" type="number" />
            <x-textarea label="Note" wire:model="batch_note" placeholder="Optional" />
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.batchModal = false" />
                <x-button label="Add Batch" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <!-- Batches Drawer -->
    <x-drawer wire:model="batchesDrawer" title="{{ $viewProduct?->name }} - Batches" right class="w-96 lg:w-1/3">
        @if($viewProduct && $viewProduct->batches->count())
            <div class="space-y-3">
                @foreach($viewProduct->batches as $batch)
                    <x-card>
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-semibold">{{ $batch->batch_number }}</div>
                                <div class="text-sm text-base-content/60">Cost: ₦{{ number_format($batch->cost_price, 2) }}</div>
                                <div class="text-sm text-base-content/60">Qty: {{ $batch->quantity }}</div>
                            </div>
                            <div class="text-right">
                                <x-badge :value="$batch->expiry_date->format('M d, Y')" @class([
                                    'badge-error' => $batch->expiry_date->isPast(),
                                    'badge-warning' => $batch->expiry_date->isBetween(now(), now()->addDays(90)),
                                    'badge-success' => $batch->expiry_date->isAfter(now()->addDays(90)),
                                ]) />
                            </div>
                        </div>
                    </x-card>
                @endforeach
            </div>
        @else
            <div class="text-center py-8 text-base-content/60">No batches yet.</div>
        @endif

        <x-slot:actions>
            <x-button label="Add Batch" wire:click="openBatchModal({{ $viewBatchesProductId }})" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-drawer>
</div>

@script
<script>
    $wire.on('focus-product-name', () => {
        setTimeout(() => {
            const el = document.querySelector('[wire\\:model="name"]');
            if (el) el.focus();
        }, 150);
    });

    $wire.on('focus-quick-name', () => {
        setTimeout(() => {
            const el = document.querySelector('input[wire\\:model="quick_name"]');
            if (el) el.focus();
        }, 300);
    });

    // Quick Add: auto-fill selling price from cost
    document.addEventListener('input', function (e) {
        const costInput = document.querySelector('input[wire\\:model="quick_cost_price"]');
        if (!costInput || e.target !== costInput) return;
        const cost = parseFloat(e.target.value) || 0;
        if (cost <= 0) return;
        const sellInput = document.querySelector('input[wire\\:model="quick_selling_price"]');
        if (sellInput) sellInput.value = Math.ceil(cost * 1.4 / 100) * 100;
    });

    // Bulk Edit: auto-fill selling price from cost when markup toggle is ON
    document.addEventListener('input', function (e) {
        if (!e.target.dataset.bulkCost) return;
        const row = e.target.closest('tr');
        const sellInput = row?.querySelector('[data-bulk-sell]');
        const toggle = document.getElementById('bulk-markup-toggle');
        if (toggle?.checked) {
            const cost = parseFloat(e.target.value) || 0;
            if (cost > 0 && sellInput) sellInput.value = Math.ceil(cost * 1.4 / 100) * 100;
        }
        // Update below-cost warning for this row
        const warnEl = row?.querySelector('.bulk-price-warn');
        if (warnEl && sellInput) {
            const cost = parseFloat(e.target.value) || 0;
            const sell = parseFloat(sellInput.value) || 0;
            warnEl.style.display = (cost > 0 && sell > 0 && sell < cost) ? 'flex' : 'none';
        }
    });

    // Bulk Edit: warn when selling price is edited below cost
    document.addEventListener('input', function (e) {
        if (!e.target.dataset.bulkSell) return;
        const row = e.target.closest('tr');
        const costInput = row?.querySelector('[data-bulk-cost]');
        const warnEl = row?.querySelector('.bulk-price-warn');
        if (warnEl && costInput) {
            const cost = parseFloat(costInput.value) || 0;
            const sell = parseFloat(e.target.value) || 0;
            warnEl.style.display = (cost > 0 && sell > 0 && sell < cost) ? 'flex' : 'none';
        }
    });


    let barcodeStream = null;

    window.startBarcodeScanner = async function() {
        const modal = document.getElementById('barcode-scanner-modal');
        const video = document.getElementById('barcode-video');
        modal.showModal();

        try {
            barcodeStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' }
            });
            video.srcObject = barcodeStream;
            video.play();

            if ('BarcodeDetector' in window) {
                const detector = new BarcodeDetector({
                    formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e', 'qr_code']
                });

                const scan = async () => {
                    if (!barcodeStream) return;
                    try {
                        const barcodes = await detector.detect(video);
                        if (barcodes.length > 0) {
                            $wire.set('barcode', barcodes[0].rawValue);
                            stopBarcodeScanner();
                            return;
                        }
                    } catch (e) {}
                    if (barcodeStream) requestAnimationFrame(scan);
                };
                requestAnimationFrame(scan);
            } else {
                alert('Barcode detection is not supported in this browser. Please type the barcode manually.');
                stopBarcodeScanner();
            }
        } catch (e) {
            alert('Camera access denied. Please allow camera access to scan barcodes.');
            stopBarcodeScanner();
        }
    };

    window.stopBarcodeScanner = function() {
        const modal = document.getElementById('barcode-scanner-modal');
        const video = document.getElementById('barcode-video');
        if (barcodeStream) {
            barcodeStream.getTracks().forEach(t => t.stop());
            barcodeStream = null;
        }
        video.srcObject = null;
        modal.close();
    };
</script>
@endscript
