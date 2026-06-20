<?php

namespace App\Livewire\Pos;

use App\Models\Batch;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public string $search = '';
    public array $cart = [];
    public string $payment_method = 'cash';
    public ?int $customer_id = null;
    public string $note = '';
    public ?int $lastSaleId = null;

    public function updatedCustomerId()
    {
        $this->recalculatePrices();
    }

    public function addToCart($productId)
    {
        $product = Product::with(['batches' => fn($q) => $q->where('quantity', '>', 0)->orderBy('expiry_date')])->findOrFail($productId);

        $batch = $product->batches->first();

        if (!$batch) {
            $this->error('No stock available for this product.');
            return;
        }

        $customer = $this->customer_id ? Customer::find($this->customer_id) : null;
        $key = $productId . '-' . $batch->id;

        if (isset($this->cart[$key])) {
            if ($this->cart[$key]['qty'] >= $batch->quantity) {
                $this->error('Not enough stock in this batch.');
                return;
            }
            $this->cart[$key]['qty']++;
            $price = $product->getPriceFor($customer, $this->cart[$key]['qty']);
            $this->cart[$key]['unit_price'] = $price;
            $this->cart[$key]['subtotal'] = $this->cart[$key]['qty'] * $price;
        } else {
            $price = $product->getPriceFor($customer, 1);
            $this->cart[$key] = [
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'name' => $product->name,
                'batch_number' => $batch->batch_number,
                'unit_price' => $price,
                'retail_price' => (float) $product->selling_price,
                'wholesale_price' => $product->wholesale_price ? (float) $product->wholesale_price : null,
                'wholesale_min_qty' => $product->wholesale_min_qty,
                'cost_price' => (float) $batch->cost_price,
                'qty' => 1,
                'subtotal' => $price,
                'max_qty' => $batch->quantity,
            ];
        }
    }

    public function updateQty($key, $qty)
    {
        if (!isset($this->cart[$key])) return;

        $qty = (int) $qty;
        if ($qty <= 0) {
            unset($this->cart[$key]);
            return;
        }

        if ($qty > $this->cart[$key]['max_qty']) {
            $this->error('Only ' . $this->cart[$key]['max_qty'] . ' available.');
            return;
        }

        $this->cart[$key]['qty'] = $qty;
        $this->resolvePrice($key);
    }

    public function removeFromCart($key)
    {
        unset($this->cart[$key]);
    }

    public function getCartTotalProperty()
    {
        return array_sum(array_column($this->cart, 'subtotal'));
    }

    private function recalculatePrices()
    {
        foreach (array_keys($this->cart) as $key) {
            $this->resolvePrice($key);
        }
    }

    private function resolvePrice(string $key)
    {
        $item = &$this->cart[$key];
        $customer = $this->customer_id ? Customer::find($this->customer_id) : null;
        $isWholesale = $customer && $customer->type === 'wholesale';

        if ($isWholesale && $item['wholesale_price']) {
            $price = $item['wholesale_price'];
        } elseif ($item['wholesale_price'] && $item['wholesale_min_qty'] && $item['qty'] >= $item['wholesale_min_qty']) {
            $price = $item['wholesale_price'];
        } else {
            $price = $item['retail_price'];
        }

        $item['unit_price'] = $price;
        $item['subtotal'] = $item['qty'] * $price;
    }

    public function checkout()
    {
        if (empty($this->cart)) {
            $this->error('Cart is empty.');
            return;
        }

        if ($this->payment_method === 'credit' && !$this->customer_id) {
            $this->error('Credit sales require a customer. Please select one.');
            return;
        }

        $saleId = DB::transaction(function () {
            $sale = Sale::create([
                'user_id' => auth()->id(),
                'customer_id' => $this->customer_id,
                'total_amount' => $this->cartTotal,
                'payment_method' => $this->payment_method,
                'status' => 'completed',
                'note' => $this->note,
            ]);

            foreach ($this->cart as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'batch_id' => $item['batch_id'],
                    'quantity' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'cost_price' => $item['cost_price'],
                    'subtotal' => $item['subtotal'],
                ]);

                Batch::where('id', $item['batch_id'])->decrement('quantity', $item['qty']);

                StockMovement::create([
                    'batch_id' => $item['batch_id'],
                    'quantity' => -$item['qty'],
                    'type' => 'sale',
                    'reference' => 'Sale #' . $sale->id,
                ]);
            }

            if ($this->payment_method === 'credit') {
                Debt::create([
                    'sale_id' => $sale->id,
                    'customer_id' => $this->customer_id,
                    'amount_owed' => $this->cartTotal,
                    'status' => 'unpaid',
                ]);
            }

            return $sale->id;
        });

        $msg = $this->payment_method === 'credit'
            ? 'Credit sale recorded. Debt added to customer.'
            : 'Sale completed!';

        $this->lastSaleId = $saleId;
        $this->cart = [];
        $this->reset(['payment_method', 'customer_id', 'note']);
        $this->success($msg);
    }

    public function render()
    {
        $products = Product::with(['batches' => fn($q) => $q->where('quantity', '>', 0)])
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->latest()
            ->limit(20)
            ->get();

        $customers = Customer::orderBy('name')->get();
        $selectedCustomer = $this->customer_id ? Customer::find($this->customer_id) : null;

        return view('livewire.pos.index', [
            'products' => $products,
            'customers' => $customers,
            'cartTotal' => $this->cartTotal,
            'isWholesale' => $selectedCustomer && $selectedCustomer->type === 'wholesale',
        ]);
    }
}
