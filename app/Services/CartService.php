<?php

namespace App\Services;

use App\Models\Product;

class CartService
{
    public function get(): array
    {
        return session('cart', []);
    }

    public function add(int $productId, int $quantity = 1): void
    {
        $cart = $this->get();
        $product = Product::with('batches')->findOrFail($productId);
        $stock = $product->batches->sum('quantity');

        $key = (string) $productId;
        $currentQty = $cart[$key]['quantity'] ?? 0;
        $newQty = $currentQty + $quantity;

        if ($newQty > $stock) $newQty = $stock;
        if ($newQty <= 0) return;

        $cart[$key] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => (float) $product->selling_price,
            'image' => $product->image,
            'quantity' => $newQty,
            'requires_prescription' => $product->requires_prescription,
            'max_stock' => $stock,
        ];

        session(['cart' => $cart]);
    }

    public function update(int $productId, int $quantity): void
    {
        $cart = $this->get();
        $key = (string) $productId;

        if (!isset($cart[$key])) return;

        if ($quantity <= 0) {
            $this->remove($productId);
            return;
        }

        if ($quantity > $cart[$key]['max_stock']) $quantity = $cart[$key]['max_stock'];

        $cart[$key]['quantity'] = $quantity;
        session(['cart' => $cart]);
    }

    public function remove(int $productId): void
    {
        $cart = $this->get();
        unset($cart[(string) $productId]);
        session(['cart' => $cart]);
    }

    public function clear(): void
    {
        session()->forget('cart');
    }

    public function count(): int
    {
        return array_sum(array_column($this->get(), 'quantity'));
    }

    public function subtotal(): float
    {
        $total = 0;
        foreach ($this->get() as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        return $total;
    }

    public function requiresPrescription(): bool
    {
        foreach ($this->get() as $item) {
            if ($item['requires_prescription'] ?? false) return true;
        }
        return false;
    }
}
