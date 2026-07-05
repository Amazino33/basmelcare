<?php

namespace App\Livewire\Pos;

use App\Models\Batch;
use App\Models\Customer;
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
    public ?int $customer_id = null;
    public string $note = '';
    public ?int $lastSaleId = null;
    public int $lastPaidCount = 0;

    public function mount()
    {
        $this->cart = session('pos_cart', []);
        $this->customer_id = session('pos_customer_id');
        $this->lastPaidCount = Sale::where('user_id', auth()->id())->where('status', 'paid')->count();
    }

    private function saveCartToSession()
    {
        session(['pos_cart' => $this->cart, 'pos_customer_id' => $this->customer_id]);
    }

    public function updatedCustomerId()
    {
        $this->recalculatePrices();
        $this->saveCartToSession();
    }

    public function selectCustomer(int $id): void
    {
        $this->customer_id = $id;
        $this->customerSearch = '';
        $this->recalculatePrices();
        $this->saveCartToSession();
    }

    public function clearCustomer(): void
    {
        $this->customer_id = null;
        $this->customerSearch = '';
        $this->recalculatePrices();
        $this->saveCartToSession();
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
        $this->saveCartToSession();
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
        $this->saveCartToSession();
    }

    public function removeFromCart($key)
    {
        unset($this->cart[$key]);
        $this->saveCartToSession();
    }

    public function getCartTotalProperty()
    {
        return array_sum(array_column($this->cart, 'subtotal'));
    }

    public function clearCart()
    {
        $this->cart = [];
        $this->reset(['customer_id', 'note']);
        session()->forget(['pos_cart', 'pos_customer_id']);
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

    public function createInvoice()
    {
        if (empty($this->cart)) {
            $this->error('Cart is empty.');
            return;
        }

        $saleId = DB::transaction(function () {
            $sale = Sale::create([
                'invoice_number' => Sale::generateInvoiceNumber(),
                'user_id' => auth()->id(),
                'customer_id' => $this->customer_id,
                'total_amount' => $this->cartTotal,
                'status' => 'pending',
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
                    'reference' => $sale->invoice_number,
                    'user_id' => auth()->id(),
                ]);
            }

            return $sale->id;
        });

        $this->lastSaleId = $saleId;
        $this->cart = [];
        $this->reset(['customer_id', 'note']);
        session()->forget(['pos_cart', 'pos_customer_id']);
        $this->success('Invoice created. Print it for the customer.');
    }

    public function confirmHandover($saleId)
    {
        $sale = Sale::findOrFail($saleId);
        if ($sale->status !== 'paid') {
            $this->error('Invoice must be paid before handover.');
            return;
        }

        $sale->update([
            'status' => 'completed',
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
        ]);

        $this->success('Goods handed over. Sale completed.');
    }

    public function cancelInvoice($saleId)
    {
        $sale = Sale::with('saleItems')->findOrFail($saleId);

        if ($sale->status === 'completed') {
            $this->error('Cannot cancel a completed sale.');
            return;
        }

        if ($sale->status === 'paid') {
            $this->error('Cannot cancel a paid invoice. Refund first.');
            return;
        }

        DB::transaction(function () use ($sale) {
            foreach ($sale->saleItems as $item) {
                Batch::where('id', $item->batch_id)->increment('quantity', $item->quantity);

                StockMovement::create([
                    'batch_id' => $item->batch_id,
                    'quantity' => $item->quantity,
                    'type' => 'return',
                    'reference' => $sale->invoice_number . ' (cancelled)',
                    'user_id' => auth()->id(),
                ]);
            }

            $sale->update(['status' => 'cancelled']);
        });

        $this->success('Invoice cancelled. Stock restored.');
    }

    public function render()
    {
        $products = Product::with(['batches' => fn($q) => $q->where('quantity', '>', 0)])
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->latest()
            ->limit(20)
            ->get();

        $customers = Customer::orderBy('name')->get(['id', 'name', 'phone']);
        $selectedCustomer = $this->customer_id ? Customer::find($this->customer_id) : null;

        $myInvoices = Sale::with('customer')
            ->where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'paid'])
            ->latest()
            ->limit(20)
            ->get();

        $recentCompleted = Sale::with('customer')
            ->where('user_id', auth()->id())
            ->where('status', 'completed')
            ->latest()
            ->limit(5)
            ->get();

        $currentPaidCount = Sale::where('user_id', auth()->id())->where('status', 'paid')->count();
        if ($currentPaidCount > $this->lastPaidCount && $this->lastPaidCount > 0) {
            $this->dispatch('invoice-paid');
            $this->success('An invoice has been paid! Ready for handover.');
        }
        $this->lastPaidCount = $currentPaidCount;

        $lastSale = $this->lastSaleId ? Sale::find($this->lastSaleId) : null;

        return view('livewire.pos.index', [
            'products' => $products,
            'customers' => $customers,
            'selectedCustomer' => $selectedCustomer,
            'cartTotal' => $this->cartTotal,
            'isWholesale' => $selectedCustomer && $selectedCustomer->type === 'wholesale',
            'myInvoices' => $myInvoices,
            'recentCompleted' => $recentCompleted,
            'lastSale' => $lastSale,
        ]);
    }
}
