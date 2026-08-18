<?php

namespace App\Livewire\Media;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithPagination, WithFileUploads;

    public string $search = '';
    public $photo = null;
    public ?int $uploadingId = null;

    public function updatedSearch(): void
    {
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
            Storage::disk('public')->delete($product->image);
        }

        $product->update(['image' => $this->photo->store('products', 'public')]);

        $name = $product->name;
        $this->uploadingId = null;
        $this->photo = null;
        $this->success("Image saved for {$name}");
    }

    public function render()
    {
        $products = Product::query()
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.media.index', compact('products'));
    }
}
