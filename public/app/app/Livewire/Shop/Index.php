<?php

namespace App\Livewire\Shop;

use App\Models\Category;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.public')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $category = null;

    #[Url]
    public string $sort = 'latest';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedCategory()
    {
        $this->resetPage();
    }

    public function setCategory(?int $id)
    {
        $this->category = $this->category === $id ? null : $id;
        $this->resetPage();
    }

    public function render()
    {
        $categories = Category::withCount(['products' => fn($q) => $q->where('show_in_shop', true)])
            ->orderBy('name')
            ->get();

        $query = Product::with('category', 'batches')
            ->where('show_in_shop', true)
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->category, fn($q) => $q->where('category_id', $this->category));

        $query = match ($this->sort) {
            'price_low' => $query->orderBy('selling_price'),
            'price_high' => $query->orderByDesc('selling_price'),
            'name' => $query->orderBy('name'),
            default => $query->latest(),
        };

        $products = $query->paginate(12);

        return view('livewire.shop.index', [
            'categories' => $categories,
            'products' => $products,
        ]);
    }
}
