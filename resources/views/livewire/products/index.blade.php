<div>
    <x-header title="Products" subtitle="Manage products and batches">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search by name or barcode..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Quick Add" wire:click="openQuickAdd" icon="o-bolt" class="btn-secondary" />
            <x-button label="Add Product" wire:click="createProduct" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

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
            ₦{{ number_format($product->selling_price, 2) }}
            @if($product->wholesale_price)
                <div class="text-xs text-info">W/S: ₦{{ number_format($product->wholesale_price, 2) }}{{ $product->wholesale_min_qty ? ' ('.$product->wholesale_min_qty.'+)' : '' }}</div>
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

    <!-- Quick Add Modal -->
    <x-modal wire:model="quickModal" title="Quick Add Product" box-class="max-w-lg">
        @if($quickAddCount > 0)
            <div class="alert alert-success py-2 mb-4">
                <x-icon name="o-check-circle" class="w-4 h-4" />
                <span class="text-sm">{{ $quickAddCount }} {{ Str::plural('product', $quickAddCount) }} added this session.</span>
            </div>
        @endif

        <x-form wire:submit="saveQuickAdd">
            <x-input label="Product Name" wire:model="quick_name" id="quick-name-input" placeholder="e.g. Paracetamol 500mg" />
            <x-select label="Category" wire:model="quick_category_id" :options="$categories" option-value="id" option-label="name" placeholder="Select category" hint="Stays selected between entries" />

            <div class="grid grid-cols-2 gap-4">
                <x-input label="Cost Price" wire:model="quick_cost_price" prefix="₦" type="number" step="0.01" />
                <x-input label="Selling Price" wire:model="quick_selling_price" prefix="₦" type="number" step="0.01" />
                <x-input label="Expiry Date" wire:model="quick_expiry_date" type="date" />
                <x-input label="Quantity" wire:model="quick_quantity" type="number" min="1" />
            </div>

            <x-slot:actions>
                <x-button label="Done" @click="$wire.quickModal = false" />
                <x-button label="Save & Next" type="submit" class="btn-primary" icon="o-arrow-right" icon-right />
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

                <x-input label="Selling Price (Retail)" wire:model="selling_price" prefix="₦" type="number" step="0.01" />
                <x-input label="Wholesale Price" wire:model="wholesale_price" prefix="₦" type="number" step="0.01" hint="Leave empty if no wholesale pricing" />
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
            <x-input label="Expiry Date" wire:model="expiry_date" type="date" />
            <x-input label="Cost Price" wire:model="cost_price" prefix="₦" type="number" step="0.01" />
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
            const el = document.getElementById('quick-name-input');
            if (el) el.focus();
        }, 150);
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
