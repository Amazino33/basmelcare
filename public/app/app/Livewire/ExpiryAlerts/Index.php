<?php

namespace App\Livewire\ExpiryAlerts;

use App\Models\Batch;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $filter = '90';

    public function render()
    {
        $headers = [
            ['key' => 'product.name', 'label' => 'Product'],
            ['key' => 'batch_number', 'label' => 'Batch'],
            ['key' => 'quantity', 'label' => 'Qty'],
            ['key' => 'cost_price', 'label' => 'Cost'],
            ['key' => 'expiry_date', 'label' => 'Expiry Date'],
            ['key' => 'days_left', 'label' => 'Days Left'],
        ];

        $query = Batch::with('product')
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date');

        if ($this->filter === 'expired') {
            $query->where('expiry_date', '<', now());
        } else {
            $query->where('expiry_date', '<=', now()->addDays((int) $this->filter))
                  ->where('expiry_date', '>=', now());
        }

        return view('livewire.expiry-alerts.index', [
            'headers' => $headers,
            'batches' => $query->paginate(20),
        ]);
    }
}
