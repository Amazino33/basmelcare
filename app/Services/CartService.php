<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;

/**
 * The basket.
 *
 * Only the product and the quantity are kept in the session. Everything
 * else - price, name, stock, whether a prescription is needed - is read
 * from the product each time the cart is used.
 *
 * That matters because price now depends on who is looking: a customer
 * tagged as wholesale pays cost plus a markup, and buying past a product's
 * wholesale minimum earns the same price. A price frozen into the session
 * at the moment an item was added would survive logging in, logging out,
 * and any change of quantity - so a basket filled while signed in as a
 * wholesaler could be checked out by anybody, at wholesale prices.
 *
 * Reading it live removes that whole class of problem: the price charged is
 * always the price the current viewer is entitled to right now.
 */
class CartService
{
    /** Products are read once per request; the cart is consulted repeatedly. */
    private ?array $resolved = null;

    /**
     * The cart as the rest of the application sees it: full lines, priced
     * for whoever is logged in.
     */
    public function get(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $stored = $this->stored();

        if ($stored === []) {
            return $this->resolved = [];
        }

        $products = Product::with('batches')
            ->findMany(array_keys($stored))
            ->keyBy('id');

        $customer = $this->customer();
        $lines    = [];

        foreach ($stored as $productId => $quantity) {
            $product = $products->get($productId);

            // Deleted since it was added. Drop it rather than carry a line
            // that cannot be priced or fulfilled.
            if (! $product) {
                continue;
            }

            $stock    = (int) $product->batches->sum('quantity');
            $quantity = min((int) $quantity, $stock);

            if ($quantity <= 0) {
                continue;
            }

            $lines[(string) $productId] = [
                'product_id'            => $product->id,
                'name'                  => $product->name,
                // Quantity is passed in because a large enough order earns
                // the wholesale price on its own.
                'price'                 => $product->getPriceFor($customer, $quantity),
                'retail_price'          => (float) $product->selling_price,
                'image'                 => $product->image,
                'quantity'              => $quantity,
                'requires_prescription' => (bool) $product->requires_prescription,
                'max_stock'             => $stock,
            ];
        }

        return $this->resolved = $lines;
    }

    public function add(int $productId, int $quantity = 1): void
    {
        $stored = $this->stored();
        $stored[$productId] = ($stored[$productId] ?? 0) + $quantity;

        $this->put($stored);
    }

    public function update(int $productId, int $quantity): void
    {
        $stored = $this->stored();

        if (! isset($stored[$productId])) {
            return;
        }

        if ($quantity <= 0) {
            $this->remove($productId);

            return;
        }

        $stored[$productId] = $quantity;

        $this->put($stored);
    }

    public function remove(int $productId): void
    {
        $stored = $this->stored();
        unset($stored[$productId]);

        $this->put($stored);
    }

    public function clear(): void
    {
        session()->forget('cart');
        $this->resolved = null;
    }

    public function count(): int
    {
        return array_sum(array_column($this->get(), 'quantity'));
    }

    public function subtotal(): float
    {
        $total = 0.0;

        foreach ($this->get() as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        return $total;
    }

    /** What the same basket would cost at retail, for showing the saving. */
    public function retailSubtotal(): float
    {
        $total = 0.0;

        foreach ($this->get() as $item) {
            $total += $item['retail_price'] * $item['quantity'];
        }

        return $total;
    }

    /** True when the viewer is being charged less than the shelf price. */
    public function hasWholesalePricing(): bool
    {
        return $this->retailSubtotal() > $this->subtotal();
    }

    public function requiresPrescription(): bool
    {
        foreach ($this->get() as $item) {
            if ($item['requires_prescription']) {
                return true;
            }
        }

        return false;
    }

    private function customer(): ?Customer
    {
        $customer = auth('customer')->user();

        return $customer instanceof Customer ? $customer : null;
    }

    /**
     * The session's own shape: product id => quantity, and nothing else.
     *
     * Carts saved by the previous version stored a whole line per product.
     * Read those forward rather than emptying somebody's basket on deploy.
     */
    private function stored(): array
    {
        $raw    = session('cart', []);
        $stored = [];

        foreach ($raw as $key => $value) {
            if (is_array($value)) {
                $id  = (int) ($value['product_id'] ?? $key);
                $qty = (int) ($value['quantity'] ?? 0);
            } else {
                $id  = (int) $key;
                $qty = (int) $value;
            }

            if ($id > 0 && $qty > 0) {
                $stored[$id] = $qty;
            }
        }

        return $stored;
    }

    private function put(array $stored): void
    {
        session(['cart' => $stored]);
        $this->resolved = null;
    }
}
