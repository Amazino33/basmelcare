<?php

namespace App\Livewire\PurchaseOrders;

use App\Models\Batch;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithPagination;

    public string $search = '';
    public string $statusFilter = '';

    // Create PO
    public ?int $supplier_id = null;
    public string $expected_date = '';
    public string $po_note = '';
    public array $orderItems = [];
    public bool $createModal = false;

    // Add item to PO
    public ?int $addProduct_id = null;
    public int $addQty = 1;
    public string $addCost = '';

    // Receive stock
    public ?int $receivePOId = null;
    public array $receiveQtys = [];
    public ?int $receive_location_id = null;
    public bool $receiveModal = false;

    // Details
    public ?int $viewPOId = null;
    public bool $detailsDrawer = false;

    public function openCreate()
    {
        $this->reset(['supplier_id', 'expected_date', 'po_note', 'orderItems', 'addProduct_id', 'addQty', 'addCost']);
        $this->createModal = true;
    }

    public function addItem()
    {
        $this->validate([
            'addProduct_id' => 'required|exists:products,id',
            'addQty' => 'required|integer|min:1',
            'addCost' => 'required|numeric|min:0',
        ]);

        $product = Product::findOrFail($this->addProduct_id);

        foreach ($this->orderItems as $item) {
            if ($item['product_id'] == $this->addProduct_id) {
                $this->error('Product already added.');
                return;
            }
        }

        $this->orderItems[] = [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $this->addQty,
            'unit_cost' => (float) $this->addCost,
            'subtotal' => $this->addQty * (float) $this->addCost,
        ];

        $this->reset(['addProduct_id', 'addQty', 'addCost']);
    }

    public function removeItem($index)
    {
        unset($this->orderItems[$index]);
        $this->orderItems = array_values($this->orderItems);
    }

    public function savePO()
    {
        $this->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'expected_date' => 'nullable|date',
        ]);

        if (empty($this->orderItems)) {
            $this->error('Add at least one item.');
            return;
        }

        $total = array_sum(array_column($this->orderItems, 'subtotal'));

        $po = PurchaseOrder::create([
            'po_number' => PurchaseOrder::generatePoNumber(),
            'supplier_id' => $this->supplier_id,
            'user_id' => auth()->id(),
            'status' => 'draft',
            'total_amount' => $total,
            'expected_date' => $this->expected_date ?: null,
            'note' => $this->po_note,
        ]);

        foreach ($this->orderItems as $item) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'product_id' => $item['product_id'],
                'quantity_ordered' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
                'subtotal' => $item['subtotal'],
            ]);
        }

        $this->createModal = false;
        $this->success('Purchase order ' . $po->po_number . ' created.');
        $this->reset(['supplier_id', 'expected_date', 'po_note', 'orderItems']);
    }

    public function markSent($id)
    {
        $po = PurchaseOrder::findOrFail($id);
        if ($po->status !== 'draft') {
            $this->error('Only draft orders can be sent.');
            return;
        }
        $po->update(['status' => 'sent']);
        $this->success('PO marked as sent.');
    }

    public function cancelPO($id)
    {
        $po = PurchaseOrder::findOrFail($id);
        if ($po->status === 'received') {
            $this->error('Cannot cancel a fully received order.');
            return;
        }
        $po->update(['status' => 'cancelled']);
        $this->success('PO cancelled.');
    }

    public function openReceive($id)
    {
        $this->receivePOId = $id;
        $po = PurchaseOrder::with('items.product')->findOrFail($id);
        $this->receiveQtys = [];
        foreach ($po->items as $item) {
            if ($item->remaining > 0) {
                $this->receiveQtys[$item->id] = $item->remaining;
            }
        }
        $defaultLocation = Location::where('is_default', true)->first();
        $this->receive_location_id = $defaultLocation?->id;
        $this->receiveModal = true;
    }

    public function receiveStock()
    {
        $this->validate([
            'receive_location_id' => 'required|exists:locations,id',
        ]);

        $po = PurchaseOrder::with('items')->findOrFail($this->receivePOId);

        DB::transaction(function () use ($po) {
            foreach ($this->receiveQtys as $itemId => $qty) {
                $qty = (int) $qty;
                if ($qty <= 0) continue;

                $item = $po->items->find($itemId);
                if (!$item || $qty > $item->remaining) continue;

                $item->increment('quantity_received', $qty);

                $batch = Batch::create([
                    'product_id' => $item->product_id,
                    'location_id' => $this->receive_location_id,
                    'batch_number' => $po->po_number . '-' . $item->id,
                    'expiry_date' => now()->addYear(),
                    'cost_price' => $item->unit_cost,
                    'quantity' => $qty,
                    'note' => 'Received from PO ' . $po->po_number,
                ]);

                StockMovement::create([
                    'batch_id' => $batch->id,
                    'quantity' => $qty,
                    'type' => 'purchase',
                    'reference' => $po->po_number,
                    'to_location_id' => $this->receive_location_id,
                    'user_id' => auth()->id(),
                ]);
            }

            $po->refresh();
            $allReceived = $po->items->every(fn($i) => $i->quantity_received >= $i->quantity_ordered);
            $anyReceived = $po->items->some(fn($i) => $i->quantity_received > 0);

            if ($allReceived) {
                $po->update(['status' => 'received']);
            } elseif ($anyReceived) {
                $po->update(['status' => 'partially_received']);
            }
        });

        $this->receiveModal = false;
        $this->success('Stock received and added to inventory.');
        $this->reset(['receivePOId', 'receiveQtys', 'receive_location_id']);
    }

    public function viewDetails($id)
    {
        $this->viewPOId = $id;
        $this->detailsDrawer = true;
    }

    public function render()
    {
        $headers = [
            ['key' => 'po_number', 'label' => 'PO #'],
            ['key' => 'supplier.name', 'label' => 'Supplier'],
            ['key' => 'total_amount', 'label' => 'Total'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'created_at', 'label' => 'Date'],
        ];

        $pos = PurchaseOrder::with('supplier')
            ->when($this->search, fn($q) => $q->where('po_number', 'like', "%{$this->search}%")
                ->orWhereHas('supplier', fn($s) => $s->where('name', 'like', "%{$this->search}%")))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->paginate(20);

        $products = Product::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();

        $viewPO = $this->viewPOId
            ? PurchaseOrder::with('items.product', 'supplier', 'user')->find($this->viewPOId)
            : null;

        $receivePO = $this->receivePOId
            ? PurchaseOrder::with('items.product')->find($this->receivePOId)
            : null;

        return view('livewire.purchase-orders.index', [
            'headers' => $headers,
            'pos' => $pos,
            'products' => $products,
            'suppliers' => $suppliers,
            'locations' => $locations,
            'viewPO' => $viewPO,
            'receivePO' => $receivePO,
        ]);
    }
}
