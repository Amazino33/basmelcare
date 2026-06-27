<?php

namespace App\Livewire\Shop;

use App\Models\Product;
use App\Services\CartService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

#[Layout('layouts.public')]
class Show extends Component
{
    use Toast;

    public Product $product;
    public int $quantity = 1;

    public function mount(Product $product)
    {
        $this->product = $product->load('category', 'batches');
    }

    public function increment()
    {
        $max = $this->product->batches->sum('quantity');
        if ($this->quantity < $max) $this->quantity++;
    }

    public function decrement()
    {
        if ($this->quantity > 1) $this->quantity--;
    }

    public function addToCart()
    {
        $cart = new CartService();
        $cart->add($this->product->id, $this->quantity);
        $this->success($this->product->name . ' added to cart.');
        $this->dispatch('cart-updated');
    }

    public function buyNow()
    {
        $cart = new CartService();
        $cart->add($this->product->id, $this->quantity);
        $this->redirect('/cart');
    }

    public function render()
    {
        $stock = $this->product->batches->sum('quantity');

        $relatedProducts = Product::with('category', 'batches')
            ->where('category_id', $this->product->category_id)
            ->where('id', '!=', $this->product->id)
            ->where('show_in_shop', true)
            ->limit(4)
            ->get();

        return view('livewire.shop.show', [
            'stock' => $stock,
            'relatedProducts' => $relatedProducts,
        ]);
    }
}
