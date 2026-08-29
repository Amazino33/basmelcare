<?php

namespace App\Livewire\Cashier;

use App\Models\AppSetting;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\InsuranceSubscription;
use App\Models\Order;
use App\Models\ReferralCommission;
use App\Models\Sale;
use App\Services\InsuranceCover;
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
    public bool $apply_credit = false;
    public bool $store_change_as_credit = false;
    public bool $payModal = false;
    public bool $payReview = false;
    public bool $paySuccess = false;
    public ?int $lastPaidSaleId = null;

    // Coupon
    public string $couponCode = '';
    public ?array $appliedCoupon = null;
    public float $couponDiscount = 0;

    /**
     * What this customer's monthly cover would pay for the sale on screen.
     *
     * A quote only - nothing is spent until processPayment, which asks again
     * rather than trusting this. Null when the customer has no cover, or when
     * the pharmacy has not switched the scheme on.
     */
    public ?array $insuranceQuote = null;
    public ?int $insuranceSubscriptionId = null;


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

        $this->reset(['couponCode', 'appliedCoupon', 'couponDiscount']);
        $this->autoApplyCoupon();
        $this->refreshInsuranceQuote();
        $this->payModal = true;
    }

    public function applyCoupon(): void
    {
        $code = strtoupper(trim($this->couponCode));

        if (!$code) {
            $this->error('Enter a coupon code.');
            return;
        }

        $sale = Sale::with('customer', 'saleItems.product')->find($this->payingSaleId);

        if (!$sale?->customer_id) {
            $this->error('Coupons can only be applied to sales with a registered customer. Attach a customer first.');
            return;
        }

        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon) {
            $this->error('Invalid coupon code.');
            return;
        }

        $ctx   = $this->couponContext($sale);
        $total = (float) $sale->total_amount;

        $error = $coupon->getValidationError(
            $total,
            $sale->customer,
            $ctx['categoryIds'],
            $ctx['productIds'],
            $ctx['itemCount']
        );
        if ($error) {
            $this->error($error);
            return;
        }

        $discount = $coupon->calculateDiscount($total, $ctx['items']);

        if ($discount <= 0) {
            $this->error('This coupon yields no discount for the items in this sale.');
            return;
        }

        $this->setAppliedCoupon($coupon, $discount, false);
        $this->success('Coupon applied! −₦' . number_format($discount, 2));
    }

    public function removeCoupon(): void
    {
        $this->reset(['couponCode', 'appliedCoupon', 'couponDiscount']);
    }

    /**
     * Build the cart context every coupon rule is evaluated against.
     */
    private function couponContext(Sale $sale): array
    {
        return [
            'items' => $sale->saleItems->map(fn($item) => [
                'product_id'  => $item->product_id,
                'category_id' => $item->product?->category_id,
                'subtotal'    => (float) $item->subtotal,
            ])->all(),
            'categoryIds' => $sale->saleItems->pluck('product.category_id')->filter()->unique()->map(fn($v) => (int) $v)->all(),
            'productIds'  => $sale->saleItems->pluck('product_id')->unique()->map(fn($v) => (int) $v)->all(),
            'itemCount'   => (int) $sale->saleItems->sum('quantity'),
        ];
    }

    private function setAppliedCoupon(Coupon $coupon, float $discount, bool $auto): void
    {
        $this->appliedCoupon = [
            'id'       => $coupon->id,
            'code'     => $coupon->code,
            'type'     => $coupon->type,
            'value'    => $coupon->value,
            'discount' => $discount,
            'auto'     => $auto,
        ];
        $this->couponDiscount = $discount;
    }

    /**
     * Apply the best-value auto-apply coupon the sale qualifies for.
     * Never overrides a coupon the cashier entered by hand.
     */
    private function autoApplyCoupon(): void
    {
        if ($this->appliedCoupon) {
            return;
        }

        $sale = Sale::with('customer', 'saleItems.product')->find($this->payingSaleId);

        if (!$sale?->customer_id) {
            return;
        }

        $ctx   = $this->couponContext($sale);
        $total = (float) $sale->total_amount;

        $best = null;

        foreach (Coupon::where('is_active', true)->where('auto_apply', true)->get() as $coupon) {
            $invalid = $coupon->getValidationError(
                $total,
                $sale->customer,
                $ctx['categoryIds'],
                $ctx['productIds'],
                $ctx['itemCount']
            );
            if ($invalid) {
                continue;
            }

            $discount = $coupon->calculateDiscount($total, $ctx['items']);

            if ($discount > 0 && (!$best || $discount > $best['discount'])) {
                $best = ['coupon' => $coupon, 'discount' => $discount];
            }
        }

        if ($best) {
            $this->setAppliedCoupon($best['coupon'], $best['discount'], true);
        }
    }

    /**
     * Work out what cover pays for the sale currently open.
     *
     * Called whenever the customer or the sale changes, because cover belongs
     * to a customer: attaching one to a walk-in sale is exactly when it starts
     * applying.
     */
    private function refreshInsuranceQuote(): void
    {
        $this->insuranceQuote = null;
        $this->insuranceSubscriptionId = null;

        if (! InsuranceCover::enabled() || ! $this->payingSaleId) {
            return;
        }

        $sale = Sale::with('saleItems.product')->find($this->payingSaleId);

        if (! $sale?->customer_id) {
            return;
        }

        $subscription = InsuranceSubscription::forCustomer($sale->customer_id);

        if (! $subscription) {
            return;
        }

        $this->insuranceSubscriptionId = $subscription->id;
        $this->insuranceQuote = app(InsuranceCover::class)
            ->quote($subscription, InsuranceCover::linesFromSale($sale));
    }

    public function detachCustomer(): void
    {
        $sale = Sale::findOrFail($this->payingSaleId);
        $sale->update(['customer_id' => null]);
        $this->apply_credit = false;
        $this->customerSearch = '';
        // Coupons require a registered customer, so any applied one is no longer valid.
        $this->reset(['couponCode', 'appliedCoupon', 'couponDiscount']);
        $this->refreshInsuranceQuote();
        $this->success('Customer removed from this sale.');
    }

    public function attachCustomer(int $customerId): void
    {
        $sale = Sale::findOrFail($this->payingSaleId);
        $sale->update(['customer_id' => $customerId]);

        $customer = Customer::findOrFail($customerId);
        $this->apply_credit = $customer->credit_balance > 0;
        $this->customerSearch = '';
        $this->autoApplyCoupon();
        $this->refreshInsuranceQuote();
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
        $this->validate([
            'newCustomerName'   => 'required|string|max:255',
            'newCustomerPhone'  => 'required|string|max:20',
            'newCustomerEmail'  => 'nullable|email|max:255',
        ]);

        $phone = Customer::normalizePhone($this->newCustomerPhone);

        if ($phone && $existing = Customer::where('phone', $phone)->first()) {
            $this->createCustomerModal = false;
            $this->attachCustomer($existing->id);
            $this->warning("{$existing->name} is already registered on this number — attached that record instead.");
            return;
        }

        $customer = Customer::create([
            'name'          => $this->newCustomerName,
            'phone'         => $phone,
            'email'         => $this->newCustomerEmail ?: null,
            'registered_by' => auth()->id(),
        ]);

        $this->createCustomerModal = false;
        $this->attachCustomer($customer->id);
    }

    /**
     * Draw on the customer's cover for this sale, if they have any.
     *
     * Returns what was actually covered. A quote can be stale by the time the
     * cashier presses pay, so this re-checks and may cover less than the screen
     * promised - which is why the amount is read back into the receipt rather
     * than assumed.
     */
    private function claimInsurance(Sale $sale): array
    {
        $none = ['covered' => 0.0, 'subscription_id' => null, 'plan' => null];

        if (! InsuranceCover::enabled() || ! $sale->customer_id) {
            return $none;
        }

        $subscription = InsuranceSubscription::forCustomer($sale->customer_id);

        if (! $subscription || ! $subscription->isClaimable()) {
            return $none;
        }

        $result = app(InsuranceCover::class)->apply(
            $subscription,
            InsuranceCover::linesFromSale($sale),
            saleId: $sale->id,
        );

        return [
            'covered'         => (float) $result['covered'],
            'subscription_id' => $subscription->id,
            'plan'            => $subscription->plan->name,
        ];
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

        // Cover is taken here, inside the payment, and asked for again rather
        // than trusted from the screen: the same customer's cover can have been
        // spent on an online order since this modal opened.
        $insurance = $this->claimInsurance($sale);

        $saleTotal = (float) $sale->total_amount - $this->couponDiscount - $insurance['covered'];

        // Apply existing store credit if toggled
        $creditUsed = 0;
        if ($this->apply_credit && $sale->customer_id) {
            $available  = (float) ($sale->customer->credit_balance ?? 0);
            $creditUsed = min($available, max(0, $saleTotal - $totalCash));
        }

        $totalCollected = $totalCash + $creditUsed;

        if ($totalCollected <= 0) {
            $this->error('Enter at least one payment amount.');
            return;
        }
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
            'cash'            => $cash > 0 ? $cash : null,
            'card'            => $card > 0 ? $card : null,
            'transfer'        => $transfer > 0 ? $transfer : null,
            'credit'          => $creditUsed > 0 ? $creditUsed : null,
            'coupon_code'     => $this->appliedCoupon ? $this->appliedCoupon['code'] : null,
            'coupon_discount' => $this->couponDiscount > 0 ? $this->couponDiscount : null,
            'debts_cleared'   => !empty($debtsToClear)
                ? array_map(fn($d) => ['invoice' => $d['invoice'], 'amount' => round($d['amount'], 2)], $debtsToClear)
                : null,
            'insurance'       => $insurance['covered'] > 0.01 ? [
                'plan'   => $insurance['plan'],
                'amount' => round($insurance['covered'], 2),
            ] : null,
            'shortfall'       => $shortfall > 0.01 ? round($shortfall, 2) : null,
            'change_given'    => $changeBack > 0.01 ? round($changeBack, 2) : null,
            'stored_credit'   => $storedCredit > 0.01 ? round($storedCredit, 2) : null,
        ]);

        $appliedCouponSnapshot = $this->appliedCoupon;
        $couponDiscountSnapshot = $this->couponDiscount;

        DB::transaction(function () use ($sale, $saleTotal, $totalCollected, $creditUsed, $shortfall, $paymentMethod, $paymentDetails, $debtsToClear, $storedCredit, $appliedCouponSnapshot, $couponDiscountSnapshot) {
            if ($creditUsed > 0) {
                $sale->customer->decrement('credit_balance', $creditUsed);
            }

            $sale->update([
                'payment_method'  => $paymentMethod,
                'payment_details' => $paymentDetails,
                'cashier_id'      => auth()->id(),
                'status'          => 'paid',
                'paid_at'         => now(),
                'coupon_id'       => $appliedCouponSnapshot ? $appliedCouponSnapshot['id'] : null,
                'coupon_discount' => $couponDiscountSnapshot,
            ]);

            if ($appliedCouponSnapshot) {
                Coupon::where('id', $appliedCouponSnapshot['id'])->increment('used_count');
            }

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
                        // Already recorded in the sale's payment_details; flagged so
                        // cash reporting does not count the same money twice.
                        'at_point_of_sale' => true,
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

        // Commission for cashier per completed payment (customer sales only)
        if ($sale->customer_id && in_array('cashier', auth()->user()->role ?? [])) {
            ReferralCommission::create([
                'user_id'     => auth()->id(),
                'customer_id' => $sale->customer_id,
                'amount'      => (float) AppSetting::get('commission_amount', 100),
            ]);
        }

        return [
            'id'  => $this->lastPaidSaleId,
            'url' => route('receipt.show', $this->lastPaidSaleId),
        ];
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

            $creditBalance  = (float) ($payingSale->customer?->credit_balance ?? 0);
            $creditUsed     = 0;
            $originalTotal  = (float) $payingSale->total_amount;
            $insuranceCover = (float) ($this->insuranceQuote['covered'] ?? 0);
            $saleTotal      = $originalTotal - $this->couponDiscount - $insuranceCover;

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
                    'coupon_discount' => $this->couponDiscount,
                    'insurance_cover' => $insuranceCover,
                    'original_total'  => $originalTotal,
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
