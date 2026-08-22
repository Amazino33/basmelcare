<?php

namespace App\Livewire\Pos;

use App\Models\AppSetting;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ReferralCommission;
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

    /** Set during render when a search matched nothing at all. */
    public bool $foundNothing = false;

    /** Terms already flagged this session, so a re-render cannot double-log. */
    public array $notedTerms = [];

    /** The term just flagged, to confirm it briefly in place. */
    public string $justNoted = '';
    public array $cart = [];
    public ?int $customer_id = null;
    public string $note = '';
    public ?int $lastSaleId = null;

    // Create customer inline
    public bool $createCustomerModal = false;
    public string $newCustomerName = '';
    public string $newCustomerPhone = '';
    public string $newCustomerEmail = '';
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
        $this->recalculatePrices();
        $this->saveCartToSession();
    }

    public function openCreateCustomer(string $name = ''): void
    {
        $this->newCustomerName = $name;
        $this->newCustomerPhone = '';
        $this->newCustomerEmail = '';
        $this->createCustomerModal = true;
    }

    public function createCustomer(): void
    {
        $this->validate([
            'newCustomerName'  => 'required|string|max:255',
            'newCustomerPhone' => 'nullable|string|max:20',
            'newCustomerEmail' => 'nullable|email|max:255',
        ]);

        $customer = Customer::create([
            'name'  => $this->newCustomerName,
            'phone' => $this->newCustomerPhone ?: null,
            'email' => $this->newCustomerEmail ?: null,
        ]);

        $this->createCustomerModal = false;
        $this->selectCustomer($customer->id);
        $this->success($customer->name . ' created and selected.');
    }

    /**
     * Record that a customer actually asked for something we do not stock.
     *
     * Deliberate, not automatic: a tap means a person judged this a real
     * request, which is a far stronger signal than a search that failed.
     */
    public function noteMissedDemand(): void
    {
        $term = strtoupper(trim($this->search));

        if ($term === '' || in_array($term, $this->notedTerms, true)) {
            return;
        }

        \App\Models\FailedSearch::record($term, auth()->id());

        $this->notedTerms[] = $term;
        $this->justNoted    = $term;
    }

    public function updatedSearch(): void
    {
        // Clear the confirmation as soon as they carry on working.
        $this->justNoted = '';
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
                'wifi_code'      => Sale::generateWifiCode(),
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

        return [
            'id'  => $saleId,
            'url' => route('invoice.show', $saleId),
        ];
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

        // Commission for sales person per completed handover (customer sales only)
        if ($sale->customer_id && in_array('sales', auth()->user()->role ?? [])) {
            ReferralCommission::create([
                'user_id'     => auth()->id(),
                'customer_id' => $sale->customer_id,
                'amount'      => (float) AppSetting::get('commission_amount', 100),
            ]);
        }

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

    /** True when the visible results came from typo-tolerant matching. */
    public bool $searchWasFuzzy = false;

    /**
     * POS search.
     *
     * Staff type in whatever order comes to mind ("500 amox"), so each word is
     * matched independently against name, SKU and barcode rather than the whole
     * query being one substring.
     *
     * Typo tolerance runs ONLY when exact matching finds nothing. On a dispensing
     * screen a near-miss must never outrank or hide a real match, and the caller
     * flags fuzzy results in the UI so staff know to look twice.
     */
    private function searchProducts()
    {
        $this->searchWasFuzzy = false;

        $base = fn() => Product::with(['batches' => fn($q) => $q->where('quantity', '>', 0)]);

        $term = trim($this->search);

        if ($term === '') {
            return $base()->latest()->limit(20)->get();
        }

        $words = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY);

        // Every word must appear in at least one searchable field.
        $matches = $base()
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->where(fn($w) => $w
                        ->where('name', 'like', "%{$word}%")
                        ->orWhere('sku', 'like', "%{$word}%")
                        ->orWhere('barcode', 'like', "%{$word}%"));
                }
            })
            ->limit(40)
            ->get();

        if ($matches->isNotEmpty()) {
            return $this->rankByRelevance($matches, $term)->take(20);
        }

        $fuzzy = $this->fuzzyMatch($base(), $words);

        // Nothing matched, even loosely. This is NOT recorded automatically:
        // most failed searches are typos and half-typed words, and a list of
        // those is ignored rather than acted on. The salesperson confirms it
        // was a real request — see noteMissedDemand().
        $this->foundNothing = $fuzzy->isEmpty();

        return $fuzzy;
    }

    /** Closest match first: exact, then starts-with, then contains. */
    private function rankByRelevance($products, string $term)
    {
        $needle = strtoupper($term);

        return $products->sortBy(function ($product) use ($needle) {
            $name = strtoupper((string) $product->name);

            return match (true) {
                $name === $needle             => 0,
                str_starts_with($name, $needle) => 1,
                str_contains($name, $needle)  => 2,
                default                       => 3,
            };
        })->values();
    }

    /**
     * Last resort: allow a small number of typos per word. Only reached when
     * exact matching returned nothing, so it can never mask a real result.
     */
    private function fuzzyMatch($query, array $words)
    {
        // Score against id+name only. Scoring the full models with their batches
        // would put a hard cap on how much of the catalogue we can consider, and
        // a cap here fails silently — it just stops finding things.
        $candidates = Product::query()->get(['id', 'name']);

        $scored = $candidates->map(function ($product) use ($words) {
            $haystack = preg_split('/\s+/', strtoupper((string) $product->name), -1, PREG_SPLIT_NO_EMPTY);
            $total    = 0;

            foreach ($words as $word) {
                $word = strtoupper($word);
                // Short words are easy to confuse, so allow fewer mistakes in them.
                $allowed = strlen($word) >= 6 ? 2 : (strlen($word) >= 4 ? 1 : 0);
                $best    = PHP_INT_MAX;

                foreach ($haystack as $piece) {
                    $best = min($best, levenshtein($word, $piece));
                }

                if ($best > $allowed) {
                    return null;   // this word simply is not here
                }

                $total += $best;
            }

            $product->fuzzy_distance = $total;

            return $product;
        })->filter()->sortBy('fuzzy_distance')->take(10)->values();

        $this->searchWasFuzzy = $scored->isNotEmpty();

        if ($scored->isEmpty()) {
            return $scored;
        }

        // Load the full models for just the handful we are actually showing,
        // preserving the closest-first order.
        $ids = $scored->pluck('id')->all();

        return Product::with(['batches' => fn($q) => $q->where('quantity', '>', 0)])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn($p) => array_search($p->id, $ids, true))
            ->values();
    }

    public function render()
    {
        $products = $this->searchProducts();

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
