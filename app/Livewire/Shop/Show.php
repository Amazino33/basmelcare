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
        // A product kept back from the shop has to be kept back everywhere on
        // it. Hiding it from the listings while its own page still answers
        // makes the setting a suggestion: anybody with the link - a bookmark,
        // a shared message, a tab left open - could still order it.
        abort_unless($product->show_in_shop, 404);

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
        // Checked again here rather than trusted from mount: the page can have
        // been open since before the product was taken off the shop.
        if (! $this->product->fresh()?->show_in_shop) {
            $this->warning('That one is no longer available online. Please ask at the counter.');

            return;
        }

        $cart = new CartService();
        $cart->add($this->product->id, $this->quantity);
        $this->success($this->product->name . ' added to cart.');
        $this->dispatch('cart-updated');
    }

    public function buyNow()
    {
        // Same door, same lock. addToCart already refuses a product taken off
        // the shop, and going straight to the basket must not be a way round it.
        $this->addToCart();

        if (! $this->product->fresh()?->show_in_shop) {
            return;
        }

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
