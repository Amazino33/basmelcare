<?php

namespace App\Livewire\Shop;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Layout('layouts.public')]
class Index extends Component
{
    use Toast, WithPagination;

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

    /**
     * Add straight from the grid.
     *
     * The card carries a basket button now, so the shop no longer sends
     * somebody to the product page for a box of paracetamol they already know
     * they want.
     */
    public function addToCart(int $productId): void
    {
        $product = Product::where('show_in_shop', true)->find($productId);

        if (! $product) {
            return;
        }

        // Checked here rather than trusted from the card: the button is not
        // rendered for an empty product, but the action is what decides.
        if ($product->batches()->sum('quantity') < 1) {
            $this->warning('That one is out of stock. Ask at the counter and we will source it.');

            return;
        }

        (new \App\Services\CartService)->add($productId);

        $this->success(Str::title(Str::lower($product->name)) . ' added to your basket.');
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
