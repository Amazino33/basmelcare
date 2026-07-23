<?php

namespace App\Livewire\Cashier;

use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Sale;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public string $searchInvoice = '';
    public int $lastPendingCount = 0;

    // Payment form
    public ?int $payingSaleId = null;
    public string $payment_method = 'cash';
    public string $split_cash = '';
    public string $split_transfer = '';
    public string $split_card = '';
    public string $part_amount = '';
    public string $part_method = 'cash';
    public string $walkin_phone = '';
    public bool $payModal = false;
    public bool $paySuccess = false;
    public ?int $lastPaidSaleId = null;

    public function openPayment(int $saleId)
    {
        $this->payingSaleId = $saleId;
        $this->paySuccess = false;
        $this->lastPaidSaleId = null;
        $this->reset(['payment_method', 'split_cash', 'split_transfer', 'split_card', 'part_amount', 'part_method', 'walkin_phone']);
        $this->payment_method = 'cash';
        $this->part_method = 'cash';
        $this->payModal = true;
    }

    public function processPayment()
    {
        $sale = Sale::with('customer', 'saleItems.product')->findOrFail($this->payingSaleId);

        if ($sale->status !== 'pending') {
            $this->error('This invoice is not pending.');
            return;
        }

        if (in_array($this->payment_method, ['credit', 'part_payment']) && !$sale->customer_id) {
            $this->error('This option requires a customer on the invoice.');
            return;
        }

        $paymentDetails = null;

        if ($this->payment_method === 'split') {
            $cash = (float) ($this->split_cash ?: 0);
            $transfer = (float) ($this->split_transfer ?: 0);
            $card = (float) ($this->split_card ?: 0);
            $splitTotal = $cash + $transfer + $card;

            if (abs($splitTotal - (float) $sale->total_amount) > 0.01) {
                $this->error('Split amounts must equal ₦' . number_format($sale->total_amount, 2));
                return;
            }

            $paymentDetails = [];
            if ($cash > 0) $paymentDetails['cash'] = $cash;
            if ($transfer > 0) $paymentDetails['transfer'] = $transfer;
            if ($card > 0) $paymentDetails['card'] = $card;
        }

        if ($this->payment_method === 'part_payment') {
            $paid = (float) ($this->part_amount ?: 0);
            $total = (float) $sale->total_amount;

            if ($paid <= 0) {
                $this->error('Enter the amount being paid.');
                return;
            }
            if ($paid >= $total) {
                $this->error('Part payment must be less than the total. Use full payment instead.');
                return;
            }

            $balance = $total - $paid;

            $paymentDetails = [
                'paid_now' => $paid,
                'balance' => $balance,
                'method' => $this->part_method,
            ];
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

            if ($this->payment_method === 'part_payment') {
                $paid = (float) $this->part_amount;

                /** @var Debt $debt */
                $debt = Debt::create([
                    'sale_id' => $sale->id,
                    'customer_id' => $sale->customer_id,
                    'amount_owed' => $sale->total_amount,
                    'amount_paid' => $paid,
                    'status' => 'partial',
                ]);

                DebtPayment::create([
                    'debt_id' => $debt->id,
                    'amount' => $paid,
                    'payment_method' => $this->part_method,
                    'received_by' => auth()->id(),
                    'note' => 'Initial part payment at checkout',
                ]);
            }
        });

        $this->lastPaidSaleId = $sale->id;
        $this->paySuccess = true;

        // Send WhatsApp receipt — must not throw, payment is already done
        try {
            $phone = $sale->customer?->phone ?? $this->walkin_phone;
            if ($phone) {
                app(WhatsAppService::class)->send($phone, $this->buildReceiptMessage($sale));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[WhatsApp Receipt] ' . $e->getMessage());
        }
    }

    private function buildReceiptMessage(Sale $sale): string
    {
        $pharmacy = \App\Models\AppSetting::get('pharmacy_name', 'BasmelCare Pharmacy');
        $phone    = \App\Models\AppSetting::get('pharmacy_phone', '');

        $lines = [];
        $lines[] = "*{$pharmacy}*";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🧾 *{$sale->invoice_number}*";
        $lines[] = "📅 " . ($sale->paid_at ?? now())->format('d/m/Y h:i A');

        if ($sale->customer) {
            $lines[] = "👤 " . $sale->customer->name;
        }

        $lines[] = "";
        $lines[] = "*Items:*";
        foreach ($sale->saleItems as $item) {
            $lines[] = "• {$item->product->name} × {$item->quantity} — ₦" . number_format($item->subtotal, 2);
        }

        $lines[] = "━━━━━━━━━━━━━━━━━━━━";

        if ($sale->payment_method === 'credit') {
            $lines[] = "*Total: ₦" . number_format($sale->total_amount, 2) . "*";
            $lines[] = "⚠️ *Recorded as Credit — Full balance due*";
            $lines[] = "";
            $lines[] = "Please settle at your earliest convenience.";
        } elseif ($sale->payment_method === 'part_payment' && $sale->payment_details) {
            $paid    = $sale->payment_details['paid_now'] ?? 0;
            $balance = $sale->payment_details['balance'] ?? 0;
            $lines[] = "*Total: ₦" . number_format($sale->total_amount, 2) . "*";
            $lines[] = "✅ Paid now: ₦" . number_format($paid, 2);
            $lines[] = "⚠️ Balance owed: ₦" . number_format($balance, 2);
            $lines[] = "";
            $lines[] = "Please settle the balance at your earliest convenience.";
        } elseif ($sale->payment_method === 'split' && $sale->payment_details) {
            $lines[] = "*Total: ₦" . number_format($sale->total_amount, 2) . "* ✅ Paid";
            $lines[] = "💳 Split payment:";
            foreach ($sale->payment_details as $method => $amount) {
                $lines[] = "  " . ucfirst($method) . ": ₦" . number_format($amount, 2);
            }
        } else {
            $lines[] = "*Total: ₦" . number_format($sale->total_amount, 2) . "* ✅ Paid";
            $lines[] = "💳 " . ucfirst($sale->payment_method);
        }

        $lines[] = "━━━━━━━━━━━━━━━━━━━━";

        if (\App\Models\AppSetting::get('hifastlink_api_key', '') !== '') {
            $lines[] = "🎁 *FREE INTERNET OFFER*";
            $lines[] = "Use your invoice *{$sale->invoice_number}* on HifastLink for 1 free day of internet!";
            $lines[] = "Visit: hifastlink.com → Pharmacy Voucher";
            $lines[] = "";
        }

        $lines[] = "Thank you for your patronage! 🙏";
        $lines[] = "*{$pharmacy}*" . ($phone ? " | {$phone}" : "");

        return implode("\n", $lines);
    }

    public function closePay(): void
    {
        $this->payModal = false;
        $this->paySuccess = false;
        $this->reset(['payingSaleId', 'lastPaidSaleId', 'payment_method', 'split_cash', 'split_transfer', 'split_card', 'part_amount', 'part_method', 'walkin_phone']);
        $this->payment_method = 'cash';
        $this->part_method = 'cash';
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

        $customerDebt = null;
        if ($payingSale?->customer_id) {
            $customerDebt = Debt::where('customer_id', $payingSale->customer_id)
                ->whereIn('status', ['unpaid', 'partial'])
                ->selectRaw('SUM(amount_owed - COALESCE(amount_paid, 0)) as balance, COUNT(*) as debt_count')
                ->first();
            if (!$customerDebt?->debt_count) {
                $customerDebt = null;
            }
        }

        $currentCount = $pendingInvoices->count();
        if ($currentCount > $this->lastPendingCount && $this->lastPendingCount > 0) {
            $this->dispatch('new-invoice');
            $this->success('New invoice received!');
        }
        $this->lastPendingCount = $currentCount;

        return view('livewire.cashier.index', [
            'pendingInvoices' => $pendingInvoices,
            'recentPaid' => $recentPaid,
            'payingSale' => $payingSale,
            'customerDebt' => $customerDebt,
        ]);
    }
}
