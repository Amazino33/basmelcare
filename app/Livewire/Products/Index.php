<?php

namespace App\Livewire\Products;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithPagination, WithFileUploads;

    public string $search = '';

    // Product form
    public string $name = '';
    public ?string $sku = null;
    public ?int $category_id = null;
    public string $selling_price = '';
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

    public function openQuickAdd(): void
    {
        $this->reset(['quick_name', 'quick_selling_price', 'quick_cost_price', 'quick_expiry_date']);
        $this->quick_quantity = 1;
        $this->quickAddCount = 0;
        $this->quickModal = true;
        $this->dispatch('focus-quick-name');
    }

    public function saveQuickAdd(): void
    {
        $this->validate([
            'quick_name'           => 'required|string|max:255',
            'quick_category_id'    => 'required|exists:categories,id',
            'quick_selling_price'  => 'required|numeric|min:0',
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
            'selling_price' => $this->quick_selling_price,
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
        $this->reset(['name', 'sku', 'category_id', 'selling_price', 'wholesale_price', 'wholesale_min_qty', 'reorder_level', 'description', 'barcode', 'photo', 'existingImage', 'productId']);
        $this->productModal = true;
    }

    public function saveProduct()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku,' . $this->productId,
            'category_id' => 'required|exists:categories,id',
            'selling_price' => 'required|numeric|min:0',
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
            'selling_price' => $this->selling_price,
            'wholesale_price' => $this->wholesale_price ?: null,
            'wholesale_min_qty' => $this->wholesale_min_qty ?: null,
            'reorder_level' => $this->reorder_level,
            'description' => $this->description,
            'barcode' => $this->barcode,
        ];

        if ($this->photo) {
            $data['image'] = $this->photo->store('products', 'public');
        }

        Product::updateOrCreate(
            ['id' => $this->productId],
            $data
        );

        $this->reset(['name', 'sku', 'category_id', 'selling_price', 'wholesale_price', 'wholesale_min_qty', 'reorder_level', 'description', 'barcode', 'photo', 'existingImage', 'productId']);

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
        $this->photo = null;
        $this->productModal = true;
    }

    public function removeImage()
    {
        $this->photo = null;
        $this->existingImage = null;
        if ($this->productId) {
            Product::where('id', $this->productId)->update(['image' => null]);
        }
    }

    public function deleteProduct($id)
    {
        Product::findOrFail($id)->delete();
        $this->success('Product deleted.');
    }

    public function openBatchModal($productId)
    {
        $this->reset(['batch_number', 'expiry_date', 'cost_price', 'quantity', 'batch_note']);
        $this->batchProductId = $productId;
        $this->batchModal = true;
    }

    public function saveBatch()
    {
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
            ->latest()
            ->paginate(15);

        $categories = Category::orderBy('name')->get();

        $viewProduct = $this->viewBatchesProductId
            ? Product::with(['batches' => fn($q) => $q->orderBy('expiry_date')])->find($this->viewBatchesProductId)
            : null;

        return view('livewire.products.index', [
            'headers' => $headers,
            'products' => $products,
            'categories' => $categories,
            'viewProduct' => $viewProduct,
        ]);
    }
}
