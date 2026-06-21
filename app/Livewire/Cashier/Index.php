<?php

namespace App\Livewire\Cashier;

use App\Models\Debt;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public string $searchInvoice = '';

    // Payment form
    public ?int $payingSaleId = null;
    public string $payment_method = 'cash';
    public string $split_cash = '';
    public string $split_transfer = '';
    public string $split_card = '';
    public bool $payModal = false;

    public function openPayment($saleId)
    {
        $this->payingSaleId = $saleId;
        $this->reset(['payment_method', 'split_cash', 'split_transfer', 'split_card']);
        $this->payment_method = 'cash';
        $this->payModal = true;
    }

    public function processPayment()
    {
        $sale = Sale::findOrFail($this->payingSaleId);

        if ($sale->status !== 'pending') {
            $this->error('This invoice is not pending.');
            return;
        }

        if ($this->payment_method === 'credit' && !$sale->customer_id) {
            $this->error('Credit requires a customer on the invoice.');
            return;
        }

        $paymentDetails = null;

        if ($this->payment_method === 'split') {
            $cash = (float) ($this->split_cash ?: 0);
            $transfer = (float) ($this->split_transfer ?: 0);
            $card = (float) ($this->split_card ?: 0);
            $splitTotal = $cash + $transfer + $card;

            if (abs($splitTotal - (float) $sale->total_amount) > 0.01) {
                $this->error('Split amounts (₦' . number_format($splitTotal, 2) . ') must equal the total (₦' . number_format($sale->total_amount, 2) . ').');
                return;
            }

            $paymentDetails = [];
            if ($cash > 0) $paymentDetails['cash'] = $cash;
            if ($transfer > 0) $paymentDetails['transfer'] = $transfer;
            if ($card > 0) $paymentDetails['card'] = $card;
        }

        DB::transaction(function () use ($sale, $paymentDetails) {
            $sale->update([
                'payment_method' => $this->payment_method,
                'payment_details' => $paymentDetails,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            if ($this->payment_method === 'credit') {
                Debt::create([
                    'sale_id' => $sale->id,
                    'customer_id' => $sale->customer_id,
                    'amount_owed' => $sale->total_amount,
                    'status' => 'unpaid',
                ]);
            }
        });

        $this->payModal = false;
        $this->success('Payment received for ' . $sale->invoice_number);
        $this->reset(['payingSaleId', 'payment_method', 'split_cash', 'split_transfer', 'split_card']);
    }

    public function render()
    {
        $pendingInvoices = Sale::with('customer', 'user', 'saleItems.product')
            ->where('status', 'pending')
            ->when($this->searchInvoice, fn($q) => $q->where('invoice_number', 'like', "%{$this->searchInvoice}%")
                ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$this->searchInvoice}%")))
            ->latest()
            ->get();

        $recentPaid = Sale::with('customer', 'user')
            ->where('status', 'paid')
            ->latest()
            ->limit(10)
            ->get();

        $payingSale = $this->payingSaleId
            ? Sale::with('saleItems.product', 'customer', 'user')->find($this->payingSaleId)
            : null;

        return view('livewire.cashier.index', [
            'pendingInvoices' => $pendingInvoices,
            'recentPaid' => $recentPaid,
            'payingSale' => $payingSale,
        ]);
    }
}
