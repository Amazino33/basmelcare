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
            // Tapping again means one more of the product, which may have to
            // come from a later batch once this one is used up. Re-laying the
            // whole allocation handles that in one place.
            $line   = $this->cart[$key];
            $inCart = 0;

            foreach ($this->cart as $existing) {
                if ((int) $existing['product_id'] === (int) $productId) {
                    $inCart += (int) $existing['qty'];
                }
            }

            if ($this->allocate($productId, $inCart + 1, (bool) $line['is_pack'],
                    $line['pack_size'] ? (int) $line['pack_size'] : null, $line) > 0) {
                $this->error('No more stock for this product.');
            }

            $this->saveCartToSession();
            return;
        } else {
            $this->cart[$key] = [
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'name' => $product->name,
                'batch_number' => $batch->batch_number,
                'unit_price' => 0.0,
                'retail_price' => (float) $product->selling_price,
                // Cost is per unit and stays that way whether the line is sold
                // loose or by the pack. Profit is computed as subtotal minus
                // cost_price times quantity, and quantity is always in units,
                // so this is the only reading that keeps that true.
                'cost_price' => (float) $batch->cost_price,
                'qty' => 1,
                'units' => 1,
                'subtotal' => 0.0,
                'is_pack' => false,
                'pack_size' => $product->sellsInPacks() ? (int) $product->pack_size : null,
                'max_qty' => (int) $batch->quantity,
            ];
        }

        $this->resolvePrice($key);
        $this->saveCartToSession();
    }

    /**
     * Set how much of this product the customer is taking.
     *
     * The number typed is the TOTAL for the product, not for the one line it
     * was typed into. A line is tied to a single batch, and a large order is
     * spread over several, so "400" has to mean four hundred tablets rather
     * than four hundred more on top of whatever is already allocated.
     */
    public function updateQty($key, $qty)
    {
        if (! isset($this->cart[$key])) return;

        $line = $this->cart[$key];

        // Anything that is not a number is somebody mid-edit: a cleared box, a
        // half-typed figure, a stray paste from a phone keyboard. Leave the
        // line exactly as it was; the box is redrawn with the real quantity.
        if (! is_numeric($qty)) {
            return;
        }

        $qty = (int) $qty;

        // Zero is not a request to delete. Clearing the field to retype is the
        // commonest thing anybody does to this box, and losing the product at
        // that moment - with a customer waiting - meant finding it and adding
        // it again. Removing a line is the X beside it, which is unambiguous.
        if ($qty < 1) {
            $qty = 1;
        }

        $short = $this->allocate(
            (int) $line['product_id'],
            $qty,
            (bool) $line['is_pack'],
            $line['pack_size'] ? (int) $line['pack_size'] : null,
            $line
        );

        $this->saveCartToSession();

        if ($short > 0) {
            $unit = $line['is_pack'] ? 'packs' : 'units';
            $this->error('Short by ' . $short . ' ' . $unit . ' - that is all the stock there is.');
        }
    }

    /**
     * Lay a product's quantity across its batches, earliest expiry first.
     *
     * Rebuilt from scratch each time rather than adjusted, so the allocation
     * cannot drift out of step with what was asked for. Each line carries ITS
     * OWN batch cost: profit is subtotal minus cost_price times quantity, per
     * line, so copying one batch's cost across the rest would misstate the
     * margin on every delivery but the first.
     *
     * @return int  how much of the request could not be met
     */
    private function allocate(int $productId, int $wanted, bool $asPack, ?int $packSize, array $template): int
    {
        $batches = Batch::where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date')
            ->get();

        $remaining = $wanted;
        $lines     = [];

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $capacity = $asPack
                ? (int) floor($batch->quantity / max((int) $packSize, 1))
                : (int) $batch->quantity;

            if ($capacity < 1) {
                // A pack is a sealed card - it cannot be made up out of two
                // deliveries, so a batch holding less than one is skipped.
                continue;
            }

            $take = min($capacity, $remaining);
            $key  = $productId . '-' . $batch->id;

            $lines[$key] = [
                'product_id'   => $productId,
                'batch_id'     => $batch->id,
                'name'         => $template['name'],
                'batch_number' => $batch->batch_number,
                'unit_price'   => 0.0,
                'retail_price' => $template['retail_price'],
                'cost_price'   => (float) $batch->cost_price,
                'qty'          => $take,
                'units'        => 0,
                'subtotal'     => 0.0,
                'is_pack'      => $asPack,
                'pack_size'    => $packSize,
                'max_qty'      => $capacity,
            ];

            $remaining -= $take;
        }

        // Put the product back exactly where it was in the list.
        //
        // This used to unset the old lines and then assign the new ones, which
        // appends them - so changing the quantity on the first item sent it to
        // the bottom of the cart. The rows are drawn in cart order, so from the
        // counter it looked as though typing a quantity had switched one
        // product for another.
        $before = [];
        $after  = [];
        $passed = false;

        foreach ($this->cart as $key => $item) {
            if ((int) $item['product_id'] === $productId) {
                $passed = true;
                continue;
            }

            if ($passed) {
                $after[$key] = $item;
            } else {
                $before[$key] = $item;
            }
        }

        // Keys are "product-batch" strings and never collide across the three
        // groups, so union keeps every line and the order they are written in.
        $this->cart = $before + $lines + $after;

        // Priced only once the whole allocation exists: whether wholesale
        // applies depends on the total across every batch, not on what landed
        // on any single line.
        foreach (array_keys($this->cart) as $key) {
            if ((int) $this->cart[$key]['product_id'] === $productId) {
                $this->resolvePrice($key);
            }
        }

        return $remaining;
    }

    /** Drop every line belonging to one product. */
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

    /**
     * Price one cart line for the customer currently selected.
     *
     * Asks the product rather than reimplementing the rule. This method used
     * to carry its own copy of the wholesale logic, which then failed to learn
     * about prices calculated from stock cost - so changing a quantity snapped
     * the line back to the retail price.
     *
     * qty is what the operator typed: packs when the line is set to packs,
     * otherwise loose units. Everything downstream works in units.
     */
    private function resolvePrice(string $key): void
    {
        $item = &$this->cart[$key];

        $product = Product::find($item['product_id']);

        if (! $product) {
            return;
        }

        $customer = $this->customer_id ? Customer::find($this->customer_id) : null;

        $item['units'] = $units = $this->unitsFor($item);

        // A large order can be split across several batches, so the quantity
        // that decides whether wholesale applies is the total of this product
        // in the cart - not what happens to sit on this one line. Otherwise
        // asking for 200 against a minimum of 100 would lose the discount
        // simply because stock arrived in two deliveries.
        $qualifyingUnits = $this->unitsOfProductInCart($item['product_id']);

        if ($item['is_pack']) {
            $packPrice = $product->packPriceFor($customer, (int) ceil($qualifyingUnits / max((int) $item['pack_size'], 1)));

            if ($packPrice !== null) {
                // subtotal is authoritative: a pack price need not divide
                // evenly by the pack size, and money must not drift by a kobo
                // per line. unit_price is carried for the receipt.
                $item['subtotal']   = round($item['qty'] * $packPrice, 2);
                $item['unit_price'] = round($item['subtotal'] / max($units, 1), 2);

                return;
            }

            // Pack pricing withdrawn since it was added to the cart.
            $item['is_pack'] = false;
            $units = $this->unitsFor($item);
        }

        $price = $product->getPriceFor($customer, $qualifyingUnits);

        $item['unit_price'] = $price;
        $item['subtotal']   = round($units * $price, 2);
    }

    /** Total units of one product across every line in the cart. */
    private function unitsOfProductInCart(int $productId): int
    {
        $total = 0;

        foreach ($this->cart as $line) {
            if ((int) $line['product_id'] === $productId) {
                $total += $this->unitsFor($line);
            }
        }

        return max($total, 1);
    }

    /** Units of stock a line represents, whichever way it is being sold. */
    private function unitsFor(array $item): int
    {
        return ($item['is_pack'] ?? false)
            ? (int) $item['qty'] * (int) ($item['pack_size'] ?: 1)
            : (int) $item['qty'];
    }

    /**
     * Switch a line between loose units and packs.
     *
     * Quantity resets to one rather than being converted: "3" meaning three
     * tablets and "3" meaning three packs are different enough orders that
     * carrying the number across would be a way to sell thirty by accident.
     */
    public function togglePack(string $key): void
    {
        if (! isset($this->cart[$key]) || ! $this->cart[$key]['pack_size']) {
            return;
        }

        $item = &$this->cart[$key];

        $item['is_pack'] = ! $item['is_pack'];
        $item['qty']     = 1;
        $item['max_qty'] = $this->maxQtyFor($item);

        if ($item['max_qty'] < 1) {
            // Not enough stock for even one pack.
            $item['is_pack'] = false;
            $item['max_qty'] = $this->maxQtyFor($item);
            $this->error('Not enough stock for a full pack.');
        }

        $this->resolvePrice($key);
        $this->saveCartToSession();
    }

    /** The most the operator can enter, in whichever unit the line uses. */
    private function maxQtyFor(array $item): int
    {
        $inStock = (int) Batch::where('id', $item['batch_id'])->value('quantity');

        return ($item['is_pack'] ?? false)
            ? (int) floor($inStock / max((int) $item['pack_size'], 1))
            : $inStock;
    }

    public function createInvoice()
    {
        if (empty($this->cart)) {
            $this->error('Cart is empty.');
            return;
        }

        // Retried rather than plain DB::transaction: two tills can generate
        // the same invoice number at the same instant. See Sale::transactWithRetry.
        $saleId = Sale::transactWithRetry(function () {
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
                // quantity is ALWAYS units, never packs. Every profit and
                // margin figure in the system is subtotal minus cost_price
                // times quantity, and cost_price is per unit - so recording
                // packs here would overstate profit by the pack size.
                $units = $item['units'];

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'batch_id' => $item['batch_id'],
                    'quantity' => $units,
                    'unit_price' => $item['unit_price'],
                    'cost_price' => $item['cost_price'],
                    'subtotal' => $item['subtotal'],
                    // Presentation only: lets a receipt say "2 packs of 10".
                    'is_pack' => $item['is_pack'],
                    'pack_size' => $item['is_pack'] ? $item['pack_size'] : null,
                ]);

                Batch::where('id', $item['batch_id'])->decrement('quantity', $units);

                StockMovement::create([
                    'batch_id' => $item['batch_id'],
                    'quantity' => -$units,
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
