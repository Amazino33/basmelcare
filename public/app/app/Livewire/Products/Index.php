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

    // Product form
    public string $name = '';
    public ?string $sku = null;
    public ?int $category_id = null;
    public string $selling_price = '';
    public string $cost_price_hint = '';
    public ?string $wholesale_price = null;
    public ?int $wholesale_min_qty = null;
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

    // Bulk edit
    public bool $bulkEditMode    = false;
    public array $bulkEdits      = [];
    public bool $bulkApplyMarkup = false;

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

    public function toggleBulkEdit(): void
    {
        if ($this->blockedFromCatalogue()) return;

        $this->bulkEditMode = !$this->bulkEditMode;
        if (!$this->bulkEditMode) {
            $this->bulkEdits      = [];
            $this->bulkApplyMarkup = false;
        }
    }

    public function updatedBulkApplyMarkup(): void
    {
        if (!$this->bulkApplyMarkup) return;

        foreach ($this->bulkEdits as $id => $data) {
            $cost = (float) ($data['cost_price'] ?? 0);
            if ($cost > 0) {
                $this->bulkEdits[$id]['selling_price'] = (string) (ceil($cost * 1.4 / 100) * 100);
            }
        }
    }

    public function saveBulkEdits(): void
    {
        if ($this->blockedFromCatalogue()) return;

        if (empty($this->bulkEdits)) {
            $this->bulkEditMode = false;
            return;
        }

        $saved = 0;

        foreach ($this->bulkEdits as $productId => $data) {
            $product = Product::with('batches')->find($productId);
            if (!$product) continue;

            $update = [
                // Casing is normalised by Product::setNameAttribute().
                'name'        => $data['name'],
                'category_id' => $data['category_id'] ?: $product->category_id,
            ];

            if ($this->canSetPrices()) {
                $update['selling_price'] = (float) $data['selling_price'];
            }

            $product->update($update);

            $newQty     = (int) ($data['qty'] ?? 0);
            $newCost    = (float) ($data['cost_price'] ?? 0);
            $currentQty = $product->batches->sum('quantity');
            $diff       = $newQty - $currentQty;
            $batch      = $product->batches->first();

            $expiryRaw = trim($data['expiry_date'] ?? '');
            $newExpiry = $expiryRaw
                ? Carbon::createFromFormat('Y-m', $expiryRaw)->endOfMonth()->toDateString()
                : null;

            if ($batch) {
                $batchUpdate = ['cost_price' => $newCost];
                if ($newExpiry) $batchUpdate['expiry_date'] = $newExpiry;
                if ($diff !== 0) $batchUpdate['quantity'] = max(0, $batch->quantity + $diff);
                $batch->update($batchUpdate);

                if ($diff !== 0) {
                    StockMovement::create([
                        'batch_id'  => $batch->id,
                        'quantity'  => abs($diff),
                        'type'      => 'adjustment',
                        'reference' => 'Bulk stock adjustment',
                    ]);
                }
            } elseif ($newQty > 0 || $newCost > 0) {
                $batch = Batch::create([
                    'product_id'   => $product->id,
                    'batch_number' => 'INIT-' . now()->format('Ymd'),
                    'expiry_date'  => $newExpiry ?? now()->addYears(2)->endOfMonth()->toDateString(),
                    'cost_price'   => $newCost,
                    'quantity'     => $newQty,
                ]);
                if ($newQty > 0) {
                    StockMovement::create([
                        'batch_id'  => $batch->id,
                        'quantity'  => $newQty,
                        'type'      => 'adjustment',
                        'reference' => 'Bulk stock adjustment',
                    ]);
                }
            }

            $saved++;
        }

        $this->bulkEditMode = false;
        $this->bulkEdits    = [];
        $this->success("{$saved} " . str('product')->plural($saved) . " updated.");
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
            'reference' => 'Initial stock',
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

        $this->reset(['name', 'sku', 'category_id', 'selling_price', 'cost_price_hint', 'wholesale_price', 'wholesale_min_qty', 'reorder_level', 'description', 'barcode', 'photo', 'existingImage', 'productId']);
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
            'reorder_level' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'barcode' => 'nullable|string|max:100',
            'photo' => 'nullable|image|max:2048',
        ]);

        $isNew = !$this->productId;

        $data = [
            'name' => $this->name,
            'sku' => $this->sku,
            'category_id' => $this->category_id,
            'wholesale_min_qty' => $this->wholesale_min_qty ?: null,
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

        $this->reset(['name', 'sku', 'category_id', 'selling_price', 'cost_price_hint', 'wholesale_price', 'wholesale_min_qty', 'reorder_level', 'description', 'barcode', 'photo', 'existingImage', 'productId']);

        if ($isNew) {
            $this->success('Product saved. Add another or click Done.');
            $this->dispatch('focus-product-name');
        } else {
            $this->productModal = false;
            $this->success('Product updated.');
        }
    }

    public function editProduct($id)
    {
        if ($this->blockedFromCatalogue()) return;

        $product = Product::findOrFail($id);
        $this->productId = $product->id;
        $this->name = $product->name;
        $this->sku = $product->sku;
        $this->category_id = $product->category_id;
        $this->selling_price = $product->selling_price;
        $this->wholesale_price = $product->wholesale_price;
        $this->wholesale_min_qty = $product->wholesale_min_qty;
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
            'batch_id' => $batch->id,
            'quantity' => $this->quantity,
            'type' => 'purchase',
            'reference' => 'Initial stock',
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
            ->when($this->stockFilter === 'out_of_stock', fn($q) =>
                $q->whereDoesntHave('batches', fn($bq) => $bq->where('quantity', '>', 0))
            )
            ->when($this->stockFilter === 'low_stock', fn($q) =>
                $q->whereRaw('(SELECT COALESCE(SUM(quantity),0) FROM batches WHERE batches.product_id = products.id) > 0')
                  ->whereRaw('(SELECT COALESCE(SUM(quantity),0) FROM batches WHERE batches.product_id = products.id) <= products.reorder_level')
            )
            ->latest()
            ->paginate($this->bulkEditMode ? 50 : 15);

        $categories = Category::orderBy('name')->get();

        // Populate bulkEdits for current page (preserves edits across page navigation)
        if ($this->bulkEditMode) {
            foreach ($products as $product) {
                if (!isset($this->bulkEdits[$product->id])) {
                    $firstBatch = $product->batches->first();
                    $this->bulkEdits[$product->id] = [
                        'name'          => $product->name,
                        'category_id'   => $product->category_id,
                        'selling_price' => (string) $product->selling_price,
                        'cost_price'    => (string) ($firstBatch?->cost_price ?? ''),
                        'qty'           => (string) $product->batches->sum('quantity'),
                        'expiry_date'   => $firstBatch?->expiry_date
                            ? Carbon::parse($firstBatch->expiry_date)->format('Y-m')
                            : '',
                    ];
                }
            }
        }

        $viewProduct = $this->viewBatchesProductId
            ? Product::with(['batches' => fn($q) => $q->orderBy('expiry_date')])->find($this->viewBatchesProductId)
            : null;

        return view('livewire.products.index', [
            'headers'     => $headers,
            'products'    => $products,
            'categories'  => $categories,
            'viewProduct' => $viewProduct,
        ]);
    }
}
