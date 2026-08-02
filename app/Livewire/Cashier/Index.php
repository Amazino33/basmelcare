<?php

namespace App\Livewire\Cashier;

use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Order;
use App\Models\Sale;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;
use App\Models\Customer;

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
    public bool $apply_credit = false;
    public bool $store_change_as_credit = false;
    public bool $payModal = false;
    public bool $payReview = false;
    public bool $paySuccess = false;
    public ?int $lastPaidSaleId = null;

    // Attach customer to walk-in sale
    public bool $createCustomerModal    = false;
    public string $newCustomerName     = '';
    public string $newCustomerPhone     = '';
    public string $newCustomerEmail     = '';
    public string $customerSearch       = '';

    // Online order payment
    public ?int $payingOrderId = null;
    public bool $orderPayModal = false;
    public bool $orderPayReview = false;
    public bool $orderPaySuccess = false;
    public ?int $lastPaidOrderId = null;

    public function openPayment(int $saleId)
    {
        $this->payingSaleId = $saleId;
        $this->paySuccess   = false;
        $this->payReview    = false;
        $this->lastPaidSaleId = null;
        $this->store_change_as_credit = false;
        $this->reset(['cash_tendered', 'card_amount', 'transfer_amount', 'walkin_phone']);

        $sale = Sale::with('customer')->find($saleId);
        $this->apply_credit = $sale?->customer_id && ($sale->customer->credit_balance ?? 0) > 0;

        $this->payModal = true;
    }

    public function attachCustomer(int $customerId): void
    {
        $sale = Sale::findOrFail($this->payingSaleId);
        $sale->update(['custoer_id' => $customerId]);

        $customer = Customer::findOrFail($customerId);
        $this->apply_credit = $customer->credit_balance > 0;
        $this->customerSearch = '';
        $this->success($customer->name . ' attached to this sale.');
    }

    public function openCreateCustomer(): void
    {
        $this->newCustomerName      = $this->customerSearch;
        $this->newCustomerPhone     = '';
        $this->newCustomerEmail     = '';
        $this->createCustomerModal  = true;
    }

    public function createAndAttachCustomer(): void
    {
        $this->valide([
            'newCustomerName'   => 'required|string|max:255',
            'newCustomerPhone'  => 'required|string|max:20',
            'newCustomerEmail'  => 'nullable|email|max:255',
        ]);

        $customer = Customer::create([
            'name'  => $this->newCustomerName,
            'phone' => $this->newCustomerPhone,
            'email' => $this->newCustomerEmail ?: null,
        ]);

        $this->createCustomerModal = false;
        $this->attachCustomer($customer->id);
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
        $totalCash = $cash + $card + $transfer;

        // Apply existing store credit if toggled
        $creditUsed = 0;
        if ($this->apply_credit && $sale->customer_id) {
            $available  = (float) ($sale->customer->credit_balance ?? 0);
            $saleTotal  = (float) $sale->total_amount;
            $creditUsed = min($available, max(0, $saleTotal - $totalCash));
        }

        $totalCollected = $totalCash + $creditUsed;

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

        $cashMethods = array_filter(['cash' => $cash, 'card' => $card, 'transfer' => $transfer]);
        if (empty($cashMethods) && $creditUsed > 0) {
            $paymentMethod = 'credit';
        } else {
            $paymentMethod = count($cashMethods) === 1 ? array_key_first($cashMethods) : 'split';
        }

        $storeCredit = $this->store_change_as_credit;
        $excess      = $totalCollected - $saleTotal;

        // Pre-compute debt allocation plan so it can be recorded in payment_details
        $debtsToClear = [];
        $changeBack   = 0;
        $storedCredit = 0;

        if ($excess > 0.01 && $sale->customer_id) {
            $oldDebts  = Debt::where('customer_id', $sale->customer_id)
                ->whereIn('status', ['unpaid', 'partial'])
                ->with('sale:id,invoice_number')
                ->orderBy('created_at')
                ->get();
            $remaining = $excess;
            foreach ($oldDebts as $oldDebt) {
                if ($remaining <= 0.01) break;
                $owed    = (float) $oldDebt->amount_owed - (float) ($oldDebt->amount_paid ?? 0);
                $toApply = min($remaining, $owed);
                $debtsToClear[] = ['debt' => $oldDebt, 'amount' => $toApply, 'invoice' => $oldDebt->sale->invoice_number ?? '—'];
                $remaining -= $toApply;
            }
            $changeBack = max(0, $remaining);
        } elseif ($excess > 0.01) {
            $changeBack = $excess;
        }

        if ($storeCredit && $changeBack > 0.01 && $sale->customer_id) {
            $storedCredit = $changeBack;
            $changeBack   = 0;
        }

        // Build complete payment_details including the full outcome
        $paymentDetails = array_filter([
            'cash'          => $cash > 0 ? $cash : null,
            'card'          => $card > 0 ? $card : null,
            'transfer'      => $transfer > 0 ? $transfer : null,
            'credit'        => $creditUsed > 0 ? $creditUsed : null,
            'debts_cleared' => !empty($debtsToClear)
                ? array_map(fn($d) => ['invoice' => $d['invoice'], 'amount' => round($d['amount'], 2)], $debtsToClear)
                : null,
            'shortfall'     => $shortfall > 0.01 ? round($shortfall, 2) : null,
            'change_given'  => $changeBack > 0.01 ? round($changeBack, 2) : null,
            'stored_credit' => $storedCredit > 0.01 ? round($storedCredit, 2) : null,
        ]);

        DB::transaction(function () use ($sale, $saleTotal, $totalCollected, $creditUsed, $shortfall, $paymentMethod, $paymentDetails, $debtsToClear, $storedCredit) {
            if ($creditUsed > 0) {
                $sale->customer->decrement('credit_balance', $creditUsed);
            }

            $sale->update([
                'payment_method'  => $paymentMethod,
                'payment_details' => $paymentDetails,
                'cashier_id'      => auth()->id(),
                'status'          => 'paid',
                'paid_at'         => now(),
            ]);

            // Shortfall → debt
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

            // Execute pre-computed debt clearing
            foreach ($debtsToClear as $item) {
                $d = $item['debt'];
                $d->amount_paid = ($d->amount_paid ?? 0) + $item['amount'];
                $d->status      = abs($d->amount_paid - $d->amount_owed) < 0.01 ? 'paid' : 'partial';
                $d->save();

                DebtPayment::create([
                    'debt_id'        => $d->id,
                    'amount'         => $item['amount'],
                    'payment_method' => $paymentMethod,
                    'received_by'    => auth()->id(),
                    'note'           => 'Auto-applied from overpayment on ' . $sale->invoice_number,
                ]);
            }

            if ($storedCredit > 0.01 && $sale->customer_id) {
                $sale->customer->increment('credit_balance', $storedCredit);
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
            $pd = $sale->payment_details;
            foreach (['cash', 'card', 'transfer', 'credit'] as $m) {
                if (!empty($pd[$m])) {
                    $lines[] = "  " . ucfirst($m) . ": ₦" . number_format($pd[$m], 2);
                }
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
        $this->payModal   = false;
        $this->payReview  = false;
        $this->paySuccess = false;
        $this->apply_credit = false;
        $this->store_change_as_credit = false;
        $this->reset(['payingSaleId', 'lastPaidSaleId', 'cash_tendered', 'card_amount', 'transfer_amount', 'walkin_phone']);
    }

    // ── Online Order Flow ────────────────────────────────────────────────────

    public function verifyOrderPayment(int $orderId): void
    {
        $order = Order::findOrFail($orderId);

        if ($order->payment_status !== 'paid') {
            $this->error('This order is not pre-paid. Use "Approve COD" instead.');
            return;
        }

        $order->update([
            'cashier_verified_at' => now(),
            'verified_by'         => auth()->id(),
        ]);

        $this->success('Payment verified. Sales can now dispatch the order.');
    }

    public function approveCodDispatch(int $orderId): void
    {
        $order = Order::findOrFail($orderId);

        if ($order->payment_status === 'paid') {
            $this->error('This order is already paid — use Verify instead.');
            return;
        }

        $order->update([
            'cashier_verified_at' => now(),
            'verified_by'         => auth()->id(),
        ]);

        $this->success('COD approved for dispatch. Rider will collect payment on delivery.');
    }

    // COD pickup — collect payment at counter
    public function openOrderPayment(int $orderId): void
    {
        $this->payingOrderId    = $orderId;
        $this->orderPaySuccess  = false;
        $this->orderPayReview   = false;
        $this->lastPaidOrderId  = null;
        $this->reset(['cash_tendered', 'card_amount', 'transfer_amount']);
        $this->orderPayModal    = true;
    }

    public function processOrderPayment(): void
    {
        $order = Order::with('customer', 'items.product')->findOrFail($this->payingOrderId);

        if ($order->payment_status === 'paid') {
            $this->error('This order is already paid.');
            return;
        }

        $cash           = (float) ($this->cash_tendered ?: 0);
        $card           = (float) ($this->card_amount ?: 0);
        $transfer       = (float) ($this->transfer_amount ?: 0);
        $totalCollected = $cash + $card + $transfer;

        if ($totalCollected <= 0) {
            $this->error('Enter at least one payment amount.');
            return;
        }

        $orderTotal = (float) $order->total_amount;
        if ($totalCollected < $orderTotal - 0.01) {
            $this->error('Must be paid in full (₦' . number_format($orderTotal, 2) . ').');
            return;
        }

        $methods       = array_filter(['cash' => $cash, 'card' => $card, 'transfer' => $transfer]);
        $paymentMethod = count($methods) === 1 ? array_key_first($methods) : 'split';

        $order->update([
            'payment_method'      => $paymentMethod,
            'payment_status'      => 'paid',
            'paid_at'             => now(),
            'cashier_verified_at' => now(),
            'verified_by'         => auth()->id(),
            'status'              => 'completed',
        ]);

        $this->lastPaidOrderId = $order->id;
        $this->orderPaySuccess = true;
    }

    public function closeOrderPay(): void
    {
        $this->orderPayModal   = false;
        $this->orderPayReview  = false;
        $this->orderPaySuccess = false;
        $this->reset(['payingOrderId', 'lastPaidOrderId', 'cash_tendered', 'card_amount', 'transfer_amount']);
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
            $totalCash = $cash + $card + $transfer;

            $creditBalance = (float) ($payingSale->customer?->credit_balance ?? 0);
            $creditUsed    = 0;
            $saleTotal     = (float) $payingSale->total_amount;

            if ($this->apply_credit && $payingSale->customer_id && $creditBalance > 0) {
                $creditUsed = min($creditBalance, max(0, $saleTotal - $totalCash));
            }

            $totalCollected = $totalCash + $creditUsed;

            if ($totalCollected > 0) {
                $excess          = $totalCollected - $saleTotal;
                $shortfall       = max(0, -$excess);
                $debtAllocations = [];
                $changeBack      = 0;
                $storedAsCredit  = 0;

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
                            'invoice'   => $debt->sale->invoice_number ?? '—',
                            'owed'      => $owed,
                            'paying'    => $toApply,
                            'remaining' => round($owed - $toApply, 2),
                        ];
                        $remaining -= $toApply;
                    }
                    $changeBack = max(0, $remaining);
                } elseif ($excess > 0.01) {
                    $changeBack = $excess;
                }

                if ($this->store_change_as_credit && $changeBack > 0.01 && $payingSale->customer_id) {
                    $storedAsCredit = $changeBack;
                    $changeBack     = 0;
                }

                $breakdown = [
                    'total_cash'      => $totalCash,
                    'credit_used'     => $creditUsed,
                    'total_collected' => $totalCollected,
                    'sale_total'      => $saleTotal,
                    'excess'          => $excess,
                    'shortfall'       => $shortfall,
                    'debt_allocations'=> $debtAllocations,
                    'change_back'     => $changeBack,
                    'stored_as_credit'=> $storedAsCredit,
                    'can_confirm'     => $shortfall < 0.01 || (bool) $payingSale->customer_id,
                ];
            }
        }

        // Online orders split by action needed
        $readyBase = Order::with('customer', 'claimedByUser', 'items')
            ->where('status', 'ready')
            ->whereNull('cashier_verified_at');

        // Pre-paid online — cashier must verify payment before dispatch
        $prePaidOrders = (clone $readyBase)
            ->where('payment_status', 'paid')
            ->latest()->get();

        // COD delivery — cashier approves dispatch; rider collects on delivery
        $codDeliveryOrders = (clone $readyBase)
            ->where('payment_status', 'pending')
            ->where('fulfillment_type', 'delivery')
            ->latest()->get();

        // COD pickup — customer pays at counter before collecting
        $codPickupOrders = (clone $readyBase)
            ->where('payment_status', 'pending')
            ->where('fulfillment_type', 'pickup')
            ->latest()->get();

        $payingOrder = $this->payingOrderId
            ? Order::with('items.product', 'customer', 'claimedByUser')->find($this->payingOrderId)
            : null;

        // Live breakdown for order payment modal
        $orderBreakdown = null;
        if ($payingOrder && !$this->orderPaySuccess) {
            $cash     = (float) ($this->cash_tendered ?: 0);
            $card     = (float) ($this->card_amount ?: 0);
            $transfer = (float) ($this->transfer_amount ?: 0);
            $totalCollected = $cash + $card + $transfer;

            if ($totalCollected > 0) {
                $orderTotal = (float) $payingOrder->total_amount;
                $orderBreakdown = [
                    'total_collected' => $totalCollected,
                    'order_total'     => $orderTotal,
                    'change'          => max(0, $totalCollected - $orderTotal),
                    'shortfall'       => max(0, $orderTotal - $totalCollected),
                    'can_confirm'     => $totalCollected >= $orderTotal - 0.01,
                ];
            }
        }

        $currentCount = $pendingInvoices->count();
        if ($currentCount > $this->lastPendingCount && $this->lastPendingCount > 0) {
            $this->dispatch('new-invoice');
            $this->success('New invoice received!');
        }
        $this->lastPendingCount = $currentCount;

        $customers = Customer::when($this->customerSearch,
                fn($q) => $q->where('name', 'like', "%{$this->customerSearch}%")
                        ->orWhere('phone', 'like', "%{$this->customerSearch}%")
            )
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'phone']);

        return view('livewire.cashier.index', [
            'pendingInvoices'   => $pendingInvoices,
            'recentPaid'        => $recentPaid,
            'payingSale'        => $payingSale,
            'customerDebt'      => $customerDebt,
            'breakdown'         => $breakdown,
            'prePaidOrders'     => $prePaidOrders,
            'codDeliveryOrders' => $codDeliveryOrders,
            'codPickupOrders'   => $codPickupOrders,
            'payingOrder'       => $payingOrder,
            'orderBreakdown'    => $orderBreakdown,
            'payReview'         => $this->payReview,
            'orderPayReview'    => $this->orderPayReview,
            'customers'         => $customers,
        ]);
    }
}
