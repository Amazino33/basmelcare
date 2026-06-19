<?php

namespace App\Livewire\Sales;

use App\Models\Sale;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $detailsDrawer = false;
    public ?int $viewSaleId = null;

    public function viewDetails($id)
    {
        $this->viewSaleId = $id;
        $this->detailsDrawer = true;
    }

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'created_at', 'label' => 'Date'],
            ['key' => 'user.name', 'label' => 'Cashier'],
            ['key' => 'customer.name', 'label' => 'Customer'],
            ['key' => 'total_amount', 'label' => 'Total'],
            ['key' => 'payment_method', 'label' => 'Payment'],
            ['key' => 'status', 'label' => 'Status'],
        ];

        $sales = Sale::with('user', 'customer')
            ->latest()
            ->paginate(20);

        $viewSale = $this->viewSaleId
            ? Sale::with('saleItems.product', 'saleItems.batch', 'user', 'customer')->find($this->viewSaleId)
            : null;

        return view('livewire.sales.index', [
            'headers' => $headers,
            'sales' => $sales,
            'viewSale' => $viewSale,
        ]);
    }
}
