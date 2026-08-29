<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\InsuranceClaim;
use App\Models\InsuranceSubscription;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * What cover pays for a basket, and the taking of it.
 *
 * One place, because the till and the online shop must reach the same answer.
 * The rule being in two implementations is how a customer ends up covered at
 * the counter and charged online for the same medicine on the same day.
 *
 * quote() decides and changes nothing. apply() takes the cover and writes the
 * claim. They are separate because the till shows the customer what they will
 * pay well before anybody presses pay, and asking twice must not spend twice.
 */
class InsuranceCover
{
    /**
     * Is the pharmacy selling cover at all?
     *
     * Everything else is written and tested, but inert until this is switched
     * on in Settings. Checked here rather than at each call site so there is
     * one answer.
     */
    public static function enabled(): bool
    {
        return AppSetting::bool('insurance_enabled', false);
    }

    /**
     * What would cover pay for these lines?
     *
     * $lines: [['product' => Product, 'subtotal' => float, 'cost' => float]]
     *
     * Returns the covered amount, the co-pay the subscriber still owes, what
     * is left for them to pay, and - when nothing is covered - a reason in
     * words a cashier can read out.
     */
    public function quote(?InsuranceSubscription $subscription, array $lines): array
    {
        $total = round(array_sum(array_map(fn ($l) => (float) $l['subtotal'], $lines)), 2);

        $nothing = [
            'covered'   => 0.0,
            'copay'     => 0.0,
            'payable'   => $total,
            'cost'      => 0.0,
            'excluded'  => [],
            'reason'    => null,
            'remaining' => 0.0,
        ];

        if (! static::enabled() || ! $subscription) {
            return $nothing;
        }

        if (! $subscription->isClaimable()) {
            return array_merge($nothing, ['reason' => $subscription->blockedReason()]);
        }

        $plan      = $subscription->plan;
        $remaining = $subscription->coverRemaining();

        // Claimable lines first, then spend what is left of the cover across
        // them in order. Ordering matters once the ceiling bites: the customer
        // pays for whatever the cover did not reach.
        $claimable = [];
        $excluded  = [];

        foreach ($lines as $line) {
            $product = $line['product'] ?? null;

            if ($product instanceof Product && $plan->covers($product)) {
                $claimable[] = $line;
            } else {
                $excluded[] = $product?->name ?? 'item';
            }
        }

        $covered = 0.0;
        $copay   = 0.0;
        $cost    = 0.0;
        $budget  = $remaining;

        foreach ($claimable as $line) {
            if ($budget <= 0) {
                break;
            }

            $subtotal = (float) $line['subtotal'];

            // The co-pay is the subscriber's share of the line and is taken off
            // the top, so it stretches the cover rather than draining it.
            $lineCopay = $plan->copayOn($subtotal);
            $claimable_ = round($subtotal - $lineCopay, 2);

            $take = min($claimable_, $budget);

            if ($take <= 0) {
                continue;
            }

            $covered += $take;
            $copay   += $lineCopay;
            $budget  -= $take;

            // Cost is apportioned when the cover only reached part of a line,
            // so a half-covered line does not book its whole cost as a claim.
            $share = $claimable_ > 0 ? $take / $claimable_ : 0;
            $cost += round((float) ($line['cost'] ?? 0) * $share, 2);
        }

        $covered = round($covered, 2);

        return [
            'covered'   => $covered,
            'copay'     => round($copay, 2),
            'payable'   => round(max(0, $total - $covered), 2),
            'cost'      => round($cost, 2),
            'excluded'  => $excluded,
            'reason'    => $covered <= 0 && $remaining <= 0
                ? 'This month\'s cover is already used up.'
                : null,
            'remaining' => round($remaining, 2),
        ];
    }

    /**
     * Take the cover and record the claim.
     *
     * Re-quotes inside the transaction rather than trusting the figure the
     * screen was showing. Between the cashier seeing "₦8,000 covered" and
     * pressing pay, the same customer's cover can have been spent on an online
     * order - and paying out on a stale quote is how a plan pays twice.
     *
     * Returns what was actually covered, which may be less than quoted, or
     * nothing at all.
     */
    public function apply(
        InsuranceSubscription $subscription,
        array $lines,
        ?int $saleId = null,
        ?int $orderId = null,
    ): array {
        return DB::transaction(function () use ($subscription, $lines, $saleId, $orderId) {
            $subscription = InsuranceSubscription::with('plan')
                ->lockForUpdate()
                ->findOrFail($subscription->getKey());

            $quote = $this->quote($subscription, $lines);

            if ($quote['covered'] <= 0) {
                return $quote;
            }

            $taken = $subscription->drawDown($quote['covered']);

            if ($taken <= 0) {
                return array_merge($quote, [
                    'covered' => 0.0,
                    'payable' => round(array_sum(array_map(fn ($l) => (float) $l['subtotal'], $lines)), 2),
                    'reason'  => 'This month\'s cover is already used up.',
                ]);
            }

            // Cost follows whatever was actually taken, not what was quoted.
            $costShare = $quote['covered'] > 0 ? $taken / $quote['covered'] : 0;

            InsuranceClaim::create([
                'insurance_subscription_id' => $subscription->getKey(),
                'sale_id'      => $saleId,
                'order_id'     => $orderId,
                'amount'       => $taken,
                'cost_amount'  => round($quote['cost'] * $costShare, 2),
                'period_start' => $subscription->period_start,
                'branch_id'    => $subscription->branch_id,
            ]);

            return array_merge($quote, [
                'covered'   => round($taken, 2),
                'payable'   => round(
                    array_sum(array_map(fn ($l) => (float) $l['subtotal'], $lines)) - $taken, 2
                ),
                'remaining' => $subscription->coverRemaining(),
            ]);
        });
    }

    /**
     * Turn a sale's lines into something quote() understands.
     *
     * cost_price on a sale item is per unit and captured at the time, which is
     * what a claim has to book - the batch it came out of may be long gone by
     * the time anybody reads the report.
     */
    public static function linesFromSale(\App\Models\Sale $sale): array
    {
        return $sale->saleItems->map(fn ($item) => [
            'product'  => $item->product,
            'subtotal' => (float) $item->subtotal,
            'cost'     => (float) $item->cost_price * (float) $item->quantity,
        ])->all();
    }

    /** The same, for an online order. */
    public static function linesFromOrder(\App\Models\Order $order): array
    {
        return $order->items->map(fn ($item) => [
            'product'  => $item->product,
            'subtotal' => (float) $item->subtotal,
            'cost'     => (float) ($item->cost_price ?? 0) * (float) $item->quantity,
        ])->all();
    }
}
