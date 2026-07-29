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
    public string $cash_tendered = '';
    public string $card_amount = '';
    public string $transfer_amount = '';
    public string $walkin_phone = '';
    public bool $payModal = false;
    public bool $paySuccess = false;
    public ?int $lastPaidSaleId = null;

    public function openPayment(int $saleId)
    {
        $this->payingSaleId = $saleId;
        $this->paySuccess = false;
        $this->lastPaidSaleId = null;
        $this->reset(['cash_tendered', 'card_amount', 'transfer_amount', 'walkin_phone']);
        $this->payModal = true;
    }

    public function processPayment()
    {
        $sale = Sale::with('customer', 'saleItems.product')->findOrFail($this->payingSaleId);

        if ($sale->status !== 'pending') {
            $this->error('This invoice is not pending.');
            return;
        }

        $cash     = (float) ($this->cash_tendered ?: 0);
        $card     = (float) ($this->card_amount ?: 0);
        $transfer = (float) ($this->transfer_amount ?: 0);
        $totalCollected = $cash + $card + $transfer;

        if ($totalCollected <= 0) {
            $this->error('Enter at least one payment amount.');
            return;
        }

        $saleTotal = (float) $sale->total_amount;
        $shortfall = $saleTotal - $totalCollected;

        if ($shortfall > 0.01 && !$sale->customer_id) {
            $this->error('Walk-in customers must pay the full amount (₦' . number_format($saleTotal, 2) . ').');
            return;
        }

        $methods = array_filter(['cash' => $cash, 'card' => $card, 'transfer' => $transfer]);
        $paymentMethod  = count($methods) === 1 ? array_key_first($methods) : 'split';
        $paymentDetails = array_filter([
            'cash'     => $cash > 0 ? $cash : null,
            'card'     => $card > 0 ? $card : null,
            'transfer' => $transfer > 0 ? $transfer : null,
        ]);

        DB::transaction(function () use ($sale, $saleTotal, $totalCollected, $shortfall, $paymentMethod, $paymentDetails) {
            $sale->update([
                'payment_method' => $paymentMethod,
                'payment_details' => $paymentDetails,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            // Shortfall → debt for the balance
            if ($shortfall > 0.01 && $sale->customer_id) {
                $debt = Debt::create([
                    'sale_id'      => $sale->id,
                    'customer_id'  => $sale->customer_id,
                    'amount_owed'  => $saleTotal,
                    'amount_paid'  => $totalCollected > 0 ? $totalCollected : null,
                    'status'       => $totalCollected > 0 ? 'partial' : 'unpaid',
                ]);

                if ($totalCollected > 0) {
                    DebtPayment::create([
                        'debt_id'        => $debt->id,
                        'amount'         => $totalCollected,
                        'payment_method' => $paymentMethod,
                        'received_by'    => auth()->id(),
                        'note'           => 'Partial payment at checkout',
                    ]);
                }
            }

            // Excess → apply to oldest outstanding debts
            $excess = $totalCollected - $saleTotal;
            if ($excess > 0.01 && $sale->customer_id) {
                $debts = Debt::where('customer_id', $sale->customer_id)
                    ->whereIn('status', ['unpaid', 'partial'])
                    ->orderBy('created_at')
                    ->get();

                $remaining = $excess;
                foreach ($debts as $debt) {
                    if ($remaining <= 0.01) break;
                    $owed    = (float) $debt->amount_owed - (float) ($debt->amount_paid ?? 0);
                    $toApply = min($remaining, $owed);

                    $debt->amount_paid = ($debt->amount_paid ?? 0) + $toApply;
                    $debt->status = abs($debt->amount_paid - $debt->amount_owed) < 0.01 ? 'paid' : 'partial';
                    $debt->save();

                    DebtPayment::create([
                        'debt_id'        => $debt->id,
                        'amount'         => $toApply,
                        'payment_method' => $paymentMethod,
                        'received_by'    => auth()->id(),
                        'note'           => 'Auto-applied from overpayment on ' . $sale->invoice_number,
                    ]);

                    $remaining -= $toApply;
                }
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

        if ($sale->payment_method === 'split' && $sale->payment_details) {
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
        $this->reset(['payingSaleId', 'lastPaidSaleId', 'cash_tendered', 'card_amount', 'transfer_amount', 'walkin_phone']);
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
                ->selectRaw('SUM(amount_owed - COALESCE(amount_paid, 0)) as total_balance, COUNT(*) as debt_count')
                ->first();
            if (!$customerDebt?->debt_count) {
                $customerDebt = null;
            }
        }

        // Live payment breakdown preview
        $breakdown = null;
        if ($payingSale && !$this->paySuccess) {
            $cash     = (float) ($this->cash_tendered ?: 0);
            $card     = (float) ($this->card_amount ?: 0);
            $transfer = (float) ($this->transfer_amount ?: 0);
            $totalCollected = $cash + $card + $transfer;

            if ($totalCollected > 0) {
                $saleTotal        = (float) $payingSale->total_amount;
                $excess           = $totalCollected - $saleTotal;
                $shortfall        = max(0, -$excess);
                $debtAllocations  = [];
                $changeBack       = 0;

                if ($excess > 0.01 && $payingSale->customer_id) {
                    $debts     = Debt::where('customer_id', $payingSale->customer_id)
                        ->whereIn('status', ['unpaid', 'partial'])
                        ->with('sale:id,invoice_number')
                        ->orderBy('created_at')
                        ->get();
                    $remaining = $excess;
                    foreach ($debts as $debt) {
                        if ($remaining <= 0.01) break;
                        $owed    = (float) $debt->amount_owed - (float) ($debt->amount_paid ?? 0);
                        $toApply = min($remaining, $owed);
                        $debtAllocations[] = [
                            'invoice' => $debt->sale->invoice_number ?? '—',
                            'owed'    => $owed,
                            'paying'  => $toApply,
                        ];
                        $remaining -= $toApply;
                    }
                    $changeBack = max(0, $remaining);
                } elseif ($excess > 0.01) {
                    $changeBack = $excess;
                }

                $breakdown = [
                    'total_collected' => $totalCollected,
                    'sale_total'      => $saleTotal,
                    'excess'          => $excess,
                    'shortfall'       => $shortfall,
                    'debt_allocations'=> $debtAllocations,
                    'change_back'     => $changeBack,
                    'can_confirm'     => $shortfall < 0.01 || (bool) $payingSale->customer_id,
                ];
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
            'recentPaid'      => $recentPaid,
            'payingSale'      => $payingSale,
            'customerDebt'    => $customerDebt,
            'breakdown'       => $breakdown,
        ]);
    }
}
