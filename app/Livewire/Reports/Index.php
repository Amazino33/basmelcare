<?php

namespace App\Livewire\Reports;

use App\Models\Batch;
use App\Models\Debt;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Index extends Component
{
    public string $reportType = 'sales';
    public string $dateFrom = '';
    public string $dateTo = '';

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function export(): StreamedResponse
    {
        return match ($this->reportType) {
            'sales' => $this->exportSales(),
            'profit' => $this->exportProfit(),
            'stock' => $this->exportStock(),
            'expiry' => $this->exportExpiry(),
            'debts' => $this->exportDebts(),
            'movements' => $this->exportMovements(),
            default => $this->exportSales(),
        };
    }

    private function exportSales(): StreamedResponse
    {
        $sales = Sale::with('user', 'customer')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo . ' 23:59:59'])
            ->where('status', 'completed')
            ->get();

        return $this->streamCsv('sales-report.csv', ['ID', 'Date', 'Customer', 'Cashier', 'Payment', 'Total'], $sales->map(fn($s) => [
            $s->id, $s->created_at->format('Y-m-d H:i'), $s->customer?->name ?? 'Walk-in', $s->user->name, ucfirst($s->payment_method), $s->total_amount,
        ])->toArray());
    }

    private function exportProfit(): StreamedResponse
    {
        $items = SaleItem::with('product', 'sale')
            ->whereHas('sale', fn($q) => $q->whereBetween('created_at', [$this->dateFrom, $this->dateTo . ' 23:59:59'])->where('status', 'completed'))
            ->get();

        return $this->streamCsv('profit-report.csv', ['Date', 'Product', 'Qty', 'Selling Price', 'Cost Price', 'Revenue', 'Cost', 'Profit'], $items->map(fn($i) => [
            $i->sale->created_at->format('Y-m-d'), $i->product->name, $i->quantity, $i->unit_price, $i->cost_price, $i->subtotal, $i->cost_price * $i->quantity, ($i->unit_price - $i->cost_price) * $i->quantity,
        ])->toArray());
    }

    private function exportStock(): StreamedResponse
    {
        $products = Product::with('category', 'batches.location')->get();
        $rows = [];
        foreach ($products as $p) {
            foreach ($p->batches as $b) {
                $rows[] = [$p->name, $p->category?->name, $b->batch_number, $b->location?->name ?? '—', $b->quantity, $b->cost_price, $p->selling_price, $b->expiry_date->format('Y-m-d')];
            }
        }
        return $this->streamCsv('stock-report.csv', ['Product', 'Category', 'Batch', 'Location', 'Qty', 'Cost', 'Selling Price', 'Expiry'], $rows);
    }

    private function exportExpiry(): StreamedResponse
    {
        $batches = Batch::with('product')
            ->where('quantity', '>', 0)
            ->where('expiry_date', '<=', now()->addDays(90))
            ->orderBy('expiry_date')
            ->get();

        return $this->streamCsv('expiry-report.csv', ['Product', 'Batch', 'Qty', 'Cost', 'Expiry Date', 'Days Left'], $batches->map(fn($b) => [
            $b->product->name, $b->batch_number, $b->quantity, $b->cost_price, $b->expiry_date->format('Y-m-d'), (int) now()->diffInDays($b->expiry_date, false),
        ])->toArray());
    }

    private function exportDebts(): StreamedResponse
    {
        $debts = Debt::with('customer', 'sale')
            ->whereIn('status', ['unpaid', 'partial'])
            ->get();

        return $this->streamCsv('debts-report.csv', ['Customer', 'Sale #', 'Amount Owed', 'Amount Paid', 'Balance', 'Status', 'Date'], $debts->map(fn($d) => [
            $d->customer->name, $d->sale_id, $d->amount_owed, $d->amount_paid, $d->balance, ucfirst($d->status), $d->created_at->format('Y-m-d'),
        ])->toArray());
    }

    private function exportMovements(): StreamedResponse
    {
        $movements = StockMovement::with('batch.product', 'fromLocation', 'toLocation', 'user')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo . ' 23:59:59'])
            ->latest()
            ->get();

        return $this->streamCsv('movements-report.csv', ['Date', 'Product', 'Batch', 'Type', 'Qty', 'From', 'To', 'Reference', 'By'], $movements->map(fn($m) => [
            $m->created_at->format('Y-m-d H:i'), $m->batch->product->name, $m->batch->batch_number, $m->type, $m->quantity, $m->fromLocation?->name ?? '—', $m->toLocation?->name ?? '—', $m->reference ?? '', $m->user?->name ?? '—',
        ])->toArray());
    }

    private function streamCsv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        return view('livewire.reports.index');
    }
}
