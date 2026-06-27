<?php

namespace App\Livewire\Shop;

use App\Services\CartService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class Cart extends Component
{
    public function updateQuantity($productId, $quantity)
    {
        $cart = new CartService();
        $cart->update((int) $productId, (int) $quantity);
    }

    public function removeItem($productId)
    {
        $cart = new CartService();
        $cart->remove((int) $productId);
    }

    public function clearCart()
    {
        $cart = new CartService();
        $cart->clear();
    }

    public function render()
    {
        $cart = new CartService();

        return view('livewire.shop.cart', [
            'items' => $cart->get(),
            'subtotal' => $cart->subtotal(),
            'itemCount' => $cart->count(),
            'requiresPrescription' => $cart->requiresPrescription(),
        ]);
    }
}
