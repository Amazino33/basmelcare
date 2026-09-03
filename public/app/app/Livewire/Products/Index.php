<?php

namespace App\Livewire\Products;

use App\Livewire\Concerns\DeniesCatalogueWrites;

use App\Imports\ProductsImport;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use DeniesCatalogueWrites;
    use Toast, WithPagination, WithFileUploads;

    /**
     * Selling and wholesale price are commercial decisions, so they are limited
     * to admin and branch_manager. Everyone else may still manage the product's
     * details and stock — see the money-trail audit for who changes what.
     */
    public function canSetPrices(): bool
    {
        return (bool) array_intersect(auth()->user()->role ?? [], ['admin', 'branch_manager']);
    }

    public string $search = '';

    #[Url]
    public string $stockFilter = '';

    /** all | visible | hidden — which products the shop is showing. */
    #[Url]
    public string $shopFilter = 'all';

    // Product form
    public string $name = '';

    /**
     * What one of these is, when it is sold loose.
     *
     * Left empty for anything sold whole. It exists so the shop can say "per
     * tablet" - a bare price beside a picture of a box reads as the price of
     * the box.
     */
    public ?string $unit = null;

    /**
     * Whether customers can see this on the online shop.
     *
     * Defaults to on, because that is the column's default and every product
     * in the catalogue is already published under it. Turning it off is how a
     * pharmacy keeps something to the counter - a controlled item, something
     * dispensed only on advice, or a line it does not want strangers ordering.
     */
    public bool $show_in_shop = true;
    public ?string $sku = null;
    public ?int $category_id = null;
    public string $selling_price = '';
    public string $cost_price_hint = '';
    public ?string $wholesale_price = null;
    public ?int $wholesale_min_qty = null;
    public ?string $wholesale_markup_percent = null;

    public bool $has_pack = false;
    public ?string $pack_size = null;
    public ?string $pack_price = null;
    public int $reorder_level = 0;
    public string $description = '';
    public ?string $barcode = null;
    public $photo = null;
    public ?string $existingImage = null;
    public ?int $productId = null;
    public bool $productModal = false;

    // Batch form
    public string $batch_number = '';
    public string $expiry_date = '';
    public string $cost_price = '';
    public int $quantity = 0;
    public string $batch_note = '';
    public ?int $batchProductId = null;
    public bool $batchModal = false;
    public float $batchProductSellingPrice = 0;

    // View batches
    public ?int $viewBatchesProductId = null;
    public bool $batchesDrawer = false;

    // Quick-add form
    public string $quick_name = '';
    public ?int $quick_category_id = null;
    public string $quick_selling_price = '';
    public string $quick_cost_price = '';
    public string $quick_expiry_date = '';
    public int $quick_quantity = 1;
    public bool $quickModal = false;
    public int $quickAddCount = 0;

    // Import
    public $importFile = null;
    public bool $importModal = false;
    public array $importResults = [];

    private function calculateSellingPrice(float $cost): string
    {
        return (string) (ceil(($cost * 1.4) / 100) * 100);
    }

    public function updatedCostPriceHint(): void
    {
        $cost = (float) $this->cost_price_hint;
        if ($cost > 0 && $this->selling_price === '') {
            $this->selling_price = $this->calculateSellingPrice($cost);
        }
    }

    // ── Correcting a batch ──────────────────────────────────────────────
    //
    // Cost price and expiry could only ever be set when a batch was created,
    // so a typo was uncorrectable through the interface. That matters more
    // than it used to: cost drives profit, and since wholesale prices are
    // derived from the dearest batch in stock, a wrong cost quietly misprices
    // every wholesale sale of that drug.
    //
    // Quantity is deliberately NOT here. Changing how much stock exists is a
    // physical claim that needs a reason and a note, which Stock Adjustments
    // already asks for; correcting a mistyped number is a different act.

    public ?int $editingBatchId = null;
    public string $edit_batch_number = '';
    public string $edit_cost_price = '';
    public string $edit_expiry_date = '';

    public function editBatch(int $batchId): void
    {
        if ($this->blockedFromCatalogue()) return;

        $batch = Batch::findOrFail($batchId);

        $this->editingBatchId    = $batch->id;
        $this->edit_batch_number = (string) $batch->batch_number;
        $this->edit_cost_price   = (string) $batch->cost_price;
        $this->edit_expiry_date  = Carbon::parse($batch->expiry_date)->format('Y-m-d');
        $this->resetValidation();
    }

    public function cancelBatchEdit(): void
    {
        $this->editingBatchId = null;
        $this->resetValidation();
    }

    public function updateBatch(): void
    {
        if ($this->blockedFromCatalogue()) return;

        $this->validate([
            'edit_batch_number' => 'required|string|max:100',
            'edit_cost_price'   => 'required|numeric|min:0',
            'edit_expiry_date'  => 'required|date',
        ], [], [
            'edit_batch_number' => 'batch number',
            'edit_cost_price'   => 'cost price',
            'edit_expiry_date'  => 'expiry date',
        ]);

        $batch = Batch::findOrFail($this->editingBatchId);

        // Quantity is untouched by design; the audit trail on Batch records
        // the cost change against whoever made it.
        $batch->update([
            'batch_number' => $this->edit_batch_number,
            'cost_price'   => (float) $this->edit_cost_price,
            'expiry_date'  => $this->edit_expiry_date,
        ]);

        $this->editingBatchId = null;
        $this->success('Batch ' . $batch->batch_number . ' corrected.');
    }

    public function openImport(): void
    {
        if ($this->blockedFromCatalogue()) return;

        $this->reset(['importFile', 'importResults']);
        $this->importModal = true;
    }

    public function processImport(): void
    {
        if ($this->blockedFromCatalogue()) return;

        $this->validate(['importFile' => 'required|file|mimes:xlsx,xls|max:10240']);

        $path     = $this->importFile->store('imports/tmp', 'local');
        $fullPath = Storage::disk('local')->path($path);

        try {
            $import = new ProductsImport();
            $import->import($fullPath);

            $this->importResults = [
                'created'    => $import->created,
                'batchAdded' => $import->batchAdded,
                'errors'     => $import->errors,
            ];

            $total = count($import->created) + count($import->batchAdded);
            if ($total > 0) {
                $this->success("{$total} " . str('product')->plural($total) . " imported successfully.");
            }
        } finally {
            Storage::disk('local')->delete($path);
            $this->reset('importFile');
        }
    }

    public function openQuickAdd(): void
    {
        if ($this->blockedFromCatalogue()) return;

        $this->reset(['quick_name', 'quick_selling_price', 'quick_cost_price', 'quick_expiry_date']);
        $this->quick_quantity = 1;
        $this->quickAddCount = 0;
        $this->quickModal = true;
        $this->dispatch('focus-quick-name');
    }

    public function saveQuickAdd(): void
    {
        if ($this->blockedFromCatalogue()) return;

        // Auto-calculate selling price if the user left it blank
        if (empty($this->quick_selling_price) && (float) $this->quick_cost_price > 0) {
            $this->quick_selling_price = $this->calculateSellingPrice((float) $this->quick_cost_price);
        }

        $this->validate([
            'quick_name' => ['required', 'string', 'max:255', function ($attr, $value, $fail) {
                if (Product::whereRaw('LOWER(name) = ?', [strtolower($value)])->exists()) {
                    $fail('A product named "' . $value . '" already exists.');
                }
            }],
            'quick_category_id'    => 'required|exists:categories,id',
            'quick_selling_price'  => 'nullable|numeric|min:0',
            'quick_cost_price'     => 'required|numeric|min:0',
            'quick_expiry_date'    => ['required', 'date_format:Y-m', function ($attr, $value, $fail) {
                if (Carbon::createFromFormat('Y-m', $value)->endOfMonth()->isPast()) {
                    $fail('The expiry month has already passed.');
                }
            }],
            'quick_quantity'       => 'required|integer|min:1',
        ]);

        $quickExpiry = Carbon::createFromFormat('Y-m', $this->quick_expiry_date)->endOfMonth()->toDateString();

        $product = Product::create([
            'name'          => $this->quick_name,
            'category_id'   => $this->quick_category_id,
            // Saves unpriced for a pricing role to set — stock still gets booked in.
            'selling_price' => $this->canSetPrices() ? $this->quick_selling_price : 0,
            'reorder_level' => 0,
        ]);

        $batch = Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'AUTO-' . now()->format('Ymd-His'),
            'expiry_date'  => $quickExpiry,
            'cost_price'   => $this->quick_cost_price,
            'quantity'     => $this->quick_quantity,
        ]);

        StockMovement::create([
            'batch_id'  => $batch->id,
            'quantity'  => $this->quick_quantity,
            'type'      => 'purchase',
            // Distinguishes a product new to the catalogue from a top-up of
            // one already stocked, which is the split the auditor asks for.
            // user_id was missing entirely: stock could be taken in with no
            // record of who did it.
            'reference' => 'Opening stock',
            'user_id'   => auth()->id(),
        ]);

        $this->quickAddCount++;
        $savedName = $this->quick_name;

        // Reset per-product fields; keep category selected for faster entry
        $this->reset(['quick_name', 'quick_selling_price', 'quick_cost_price', 'quick_expiry_date']);
        $this->quick_quantity = 1;

        $this->success("{$savedName} added. ({$this->quickAddCount} " . str('product')->plural($this->quickAddCount) . " so far)");
        $this->dispatch('focus-quick-name');
    }

    public function createProduct()
    {
        if ($this->blockedFromCatalogue()) return;

        $this->show_in_shop = true;
        $this->reset(['name', 'unit', 'sku', 'category_id', 'selling_price', 'cost_price_hint', 'wholesale_price', 'wholesale_min_qty', 'wholesale_markup_percent', 'has_pack', 'pack_size', 'pack_price', 'reorder_level', 'description', 'barcode', 'photo', 'existingImage', 'productId']);
        $this->productModal = true;
    }

    public function saveProduct()
    {
        if ($this->blockedFromCatalogue()) return;

        $this->validate([
            'name' => ['required', 'string', 'max:255', function ($attr, $value, $fail) {
                $exists = Product::whereRaw('LOWER(name) = ?', [strtolower($value)])
                    ->when($this->productId, fn($q) => $q->where('id', '!=', $this->productId))
                    ->exists();
                if ($exists) {
                    $fail('A product named "' . $value . '" already exists.');
                }
            }],
            'sku' => 'nullable|string|max:100|unique:products,sku,' . $this->productId,
            'category_id' => 'required|exists:categories,id',
            // Pricing roles must give a price; others cannot set one at all.
            'selling_price' => $this->canSetPrices() ? 'required|numeric|min:0' : 'nullable',
            'wholesale_price' => 'nullable|numeric|min:0',
            'wholesale_min_qty' => 'nullable|integer|min:1',
            'wholesale_markup_percent' => 'nullable|numeric|min:0|max:100',
            // A pack of one is not a pack, and pricing one needs a price.
            'pack_size' => 'nullable|integer|min:2|required_if:has_pack,true',
            'pack_price' => 'nullable|numeric|min:0|required_if:has_pack,true',
            'reorder_level' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'barcode' => 'nullable|string|max:100',
            'photo' => 'nullable|image|max:2048',
        ]);

        $isNew = !$this->productId;

        $data = [
            'name' => $this->name,
            'unit' => $this->unit ?: null,
            'show_in_shop' => $this->show_in_shop,
            'sku' => $this->sku,
            'category_id' => $this->category_id,
            'wholesale_min_qty' => $this->wholesale_min_qty ?: null,
            'has_pack' => $this->has_pack,
            'pack_size' => $this->has_pack ? (int) $this->pack_size : null,
            'pack_price' => $this->has_pack ? (float) $this->pack_price : null,
            // '' means "use the pharmacy default"; 0 means "sell at cost".
            'wholesale_markup_percent' => $this->wholesale_markup_percent === null || $this->wholesale_markup_percent === ''
                ? null
                : (float) $this->wholesale_markup_percent,
            'reorder_level' => $this->reorder_level,
            'description' => $this->description,
            'barcode' => $this->barcode,
        ];

        // Prices are a commercial decision. Non-pricing roles may add a product
        // so a delivery isn't blocked, but it saves unpriced for admin to set.
        if ($this->canSetPrices()) {
            $data['selling_price']   = $this->selling_price;
            $data['wholesale_price'] = $this->wholesale_price ?: null;
        } elseif (!$this->productId) {
            $data['selling_price'] = 0;
        }

        if ($this->photo) {
            // 'products' disk is the PUBLIC site's storage. An image written to
            // this app's own storage exists only on the staff subdomain, so the
            // shop renders a broken image for every customer.
            if ($this->existingImage) {
                Storage::disk('product_images')->delete($this->existingImage);
            }

            $data['image'] = $this->photo->store('products', 'product_images');
        }

        Product::updateOrCreate(
            ['id' => $this->productId],
            $data
        );

        $this->show_in_shop = true;
        $this->reset(['name', 'unit', 'sku', 'category_id', 'selling_price', 'cost_price_hint', 'wholesale_price', 'wholesale_min_qty', 'wholesale_markup_percent', 'has_pack', 'pack_size', 'pack_price', 'reorder_level', 'description', 'barcode', 'photo', 'existingImage', 'productId']);

        if ($isNew) {
            $this->success('Product saved. Add another or click Done.');
            $this->dispatch('focus-product-name');
        } else {
            $this->productModal = false;
            $this->success('Product updated.');
        }
    }

    /**
     * Publish or unpublish one product without opening the form.
     *
     * With a catalogue this size, doing it through the form one product at a
     * time is not a feature anybody would use.
     */
    public function toggleShopVisibility(int $id): void
    {
        if ($this->blockedFromCatalogue()) return;

        $product = Product::findOrFail($id);
        $product->update(['show_in_shop' => ! $product->show_in_shop]);

        $this->success($product->show_in_shop
            ? $product->name . ' is now on the online shop.'
            : $product->name . ' is hidden from the online shop. It can still be sold at the counter.');
    }

    public function updatedShopFilter(): void
    {
        $this->resetPage();
    }

    public function editProduct($id)
    {
        if ($this->blockedFromCatalogue()) return;

        $product = Product::findOrFail($id);
        $this->productId = $product->id;
        $this->name = $product->name;
        $this->unit = $product->unit;
        $this->show_in_shop = (bool) $product->show_in_shop;
        $this->sku = $product->sku;
        $this->category_id = $product->category_id;
        $this->selling_price = $product->selling_price;
        $this->wholesale_price = $product->wholesale_price;
        $this->wholesale_min_qty = $product->wholesale_min_qty;
        $this->wholesale_markup_percent = $product->wholesale_markup_percent;
        $this->has_pack = (bool) $product->has_pack;
        $this->pack_size = $product->pack_size;
        $this->pack_price = $product->pack_price;
        $this->reorder_level = $product->reorder_level;
        $this->description = $product->description ?? '';
        $this->barcode = $product->barcode;
        $this->existingImage = $product->image;
        $this->cost_price_hint = '';
        $this->photo = null;
        $this->productModal = true;
    }

    public function removeImage()
    {
        if ($this->blockedFromCatalogue()) return;

        $this->photo = null;

        if ($this->existingImage) {
            Storage::disk('product_images')->delete($this->existingImage);
        }

        $this->existingImage = null;

        if ($this->productId) {
            Product::where('id', $this->productId)->update(['image' => null]);
        }
    }

    public function deleteProduct($id)
    {
        if ($this->blockedFromCatalogue()) return;

        Product::findOrFail($id)->delete();
        $this->success('Product deleted.');
    }

    public function openBatchModal($productId)
    {
        if ($this->blockedFromCatalogue()) return;

        $this->reset(['batch_number', 'expiry_date', 'cost_price', 'quantity', 'batch_note']);
        $this->batchProductId = $productId;
        $this->batchProductSellingPrice = (float) Product::find($productId)?->selling_price ?? 0;
        $this->batchModal = true;
    }

    public function saveBatch()
    {
        if ($this->blockedFromCatalogue()) return;

        $this->validate([
            'batch_number' => 'nullable|string|max:100',
            'expiry_date'  => ['required', 'date_format:Y-m', function ($attr, $value, $fail) {
                if (Carbon::createFromFormat('Y-m', $value)->endOfMonth()->isPast()) {
                    $fail('The expiry month has already passed.');
                }
            }],
            'cost_price'   => 'required|numeric|min:0',
            'quantity'     => 'required|integer|min:1',
            'batch_note'   => 'nullable|string',
        ]);

        $expiry = Carbon::createFromFormat('Y-m', $this->expiry_date)->endOfMonth()->toDateString();

        $product = Product::find($this->batchProductId);
        if ($product && $this->cost_price > $product->selling_price) {
            $this->warning("Batch cost price (₦{$this->cost_price}) exceeds the product's current selling price (₦{$product->selling_price}). You will sell at a loss.");
        }

        $batch = Batch::create([
            'product_id'   => $this->batchProductId,
            'batch_number' => $this->batch_number ?: 'AUTO-' . now()->format('Ymd-His'),
            'expiry_date'  => $expiry,
            'cost_price' => $this->cost_price,
            'quantity' => $this->quantity,
            'note' => $this->batch_note,
        ]);

        StockMovement::create([
            'batch_id'  => $batch->id,
            'quantity'  => $this->quantity,
            'type'      => 'purchase',
            'reference' => 'Stock intake',
            'user_id'   => auth()->id(),
        ]);

        $this->batchModal = false;
        $this->success('Batch added with ' . $this->quantity . ' units.');
        $this->reset(['batch_number', 'expiry_date', 'cost_price', 'quantity', 'batch_note', 'batchProductId']);
    }

    public function viewBatches($productId)
    {
        $this->viewBatchesProductId = $productId;
        $this->batchesDrawer = true;
    }

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'image', 'label' => ''],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'category.name', 'label' => 'Category'],
            ['key' => 'selling_price', 'label' => 'Price'],
            ['key' => 'stock', 'label' => 'Stock'],
        ];

        $products = Product::with('category', 'batches')
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('barcode', 'like', "%{$this->search}%"))
            ->when($this->shopFilter === 'visible', fn($q) => $q->where('show_in_shop', true))
            ->when($this->shopFilter === 'hidden', fn($q) => $q->where('show_in_shop', false))
            ->when($this->stockFilter === 'out_of_stock', fn($q) =>
                $q->whereDoesntHave('batches', fn($bq) => $bq->where('quantity', '>', 0))
            )
            ->when($this->stockFilter === 'low_stock', fn($q) =>
                $q->whereRaw('(SELECT COALESCE(SUM(quantity),0) FROM batches WHERE batches.product_id = products.id) > 0')
                  ->whereRaw('(SELECT COALESCE(SUM(quantity),0) FROM batches WHERE batches.product_id = products.id) <= products.reorder_level')
            )
            ->latest()
            ->paginate(15);

        $categories = Category::orderBy('name')->get();

        $viewProduct = $this->viewBatchesProductId
            ? Product::with(['batches' => fn($q) => $q->orderBy('expiry_date')])->find($this->viewBatchesProductId)
            : null;

        return view('livewire.products.index', [
            'headers'     => $headers,
            'products'    => $products,
            'categories'  => $categories,
            'viewProduct' => $viewProduct,
            'onShopCount' => Product::where('show_in_shop', true)->count(),
            'hiddenCount' => Product::where('show_in_shop', false)->count(),
        ]);
    }
}
