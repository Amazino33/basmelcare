<?php

namespace App\Livewire\Media;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithPagination, WithFileUploads;

    public string $search = '';

    /** missing | has | all — defaults to the work that still needs doing. */
    #[Url]
    public string $filter = 'missing';

    public $photo = null;
    public ?int $uploadingId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->cancelUpload();
        $this->resetPage();
    }

    public function startUpload(int $id): void
    {
        $this->uploadingId = $id;
        $this->photo = null;
        $this->resetValidation();
    }

    public function cancelUpload(): void
    {
        $this->uploadingId = null;
        $this->photo = null;
        $this->resetValidation();
    }

    public function saveImage(): void
    {
        $this->validate(['photo' => 'required|image|max:2048']);

        $product = Product::findOrFail($this->uploadingId);

        if ($product->image) {
            Storage::disk('product_images')->delete($product->image);
        }

        // 'products' disk points at the public site's storage — the shop cannot
        // serve a file written into this app's own storage.
        $product->update(['image' => $this->photo->store('products', 'product_images')]);

        $name = $product->name;
        $this->uploadingId = null;
        $this->photo = null;
        $this->success("Image saved for {$name}");
    }

    private function scopeFilter($query, string $filter)
    {
        return match ($filter) {
            'missing' => $query->where(fn($q) => $q->whereNull('image')->orWhere('image', '')),
            'has'     => $query->whereNotNull('image')->where('image', '!=', ''),
            default   => $query,
        };
    }

    public function render()
    {
        $products = $this->scopeFilter(
                Product::query()->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")),
                $this->filter
            )
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.media.index', [
            'products'     => $products,
            'missingCount' => $this->scopeFilter(Product::query(), 'missing')->count(),
            'hasCount'     => $this->scopeFilter(Product::query(), 'has')->count(),
            'allCount'     => Product::count(),
        ]);
    }
}
