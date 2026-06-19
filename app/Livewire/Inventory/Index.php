<?php

namespace App\Livewire\Inventory;

use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filter = 'all';

    public function render()
    {
        $headers = [
            ['key' => 'name', 'label' => 'Product'],
            ['key' => 'category.name', 'label' => 'Category'],
            ['key' => 'selling_price', 'label' => 'Price'],
            ['key' => 'stock', 'label' => 'Total Stock'],
            ['key' => 'reorder_level', 'label' => 'Reorder Level'],
            ['key' => 'status', 'label' => 'Status'],
        ];

        $products = Product::with('category', 'batches')
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(20);

        return view('livewire.inventory.index', [
            'headers' => $headers,
            'products' => $products,
        ]);
    }
}
