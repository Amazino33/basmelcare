<?php

namespace App\Livewire\Delivery;

use App\Models\Order;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What went out, where, who carried it, and what the pharmacy earned for it.
 *
 * Delivery fees are money the shop takes and could not report on separately:
 * they sat inside each order's total with nothing adding them up. A pharmacy
 * paying riders needs to know whether the charges cover them.
 *
 * Two figures that must not be added together:
 *
 *   Earned      Fees on deliveries that actually arrived. This is the number
 *               to weigh the riders against.
 *   Still out   Fees on orders dispatched but not yet marked delivered. Real,
 *               but not yet earned - the run could still fail.
 *
 * Cancelled orders are in neither. A cancelled delivery earned nothing, and
 * counting its fee would flatter the figure with money nobody paid.
 *
 * Separately from both, and deliberately not mixed in: whether the customer
 * has actually paid. A pay-on-delivery order that has been delivered has
 * earned its fee, but the cash only exists once the rider hands it in.
 */
class Report extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';

    /** all, or one area name. */
    public string $area = 'all';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    /**
     * Delivery orders in the window.
     *
     * Dated on when the order was placed rather than when it arrived, because
     * that is the date on every other report here and a delivery report that
     * counts a different month from the sales report is worse than none.
     */
    private function orders()
    {
        return Order::query()
            ->where('fulfillment_type', 'delivery')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo . ' 23:59:59'])
            ->when($this->area !== 'all', fn ($q) => $q->where('delivery_area', $this->area));
    }

    /** The areas that actually appear in this window, for the filter. */
    public function areasInWindow(): array
    {
        return Order::query()
            ->where('fulfillment_type', 'delivery')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo . ' 23:59:59'])
            ->whereNotNull('delivery_area')
            ->distinct()
            ->orderBy('delivery_area')
            ->pluck('delivery_area')
            ->all();
    }

    public function export(): StreamedResponse
    {
        $orders = $this->orders()->with('customer')->orderBy('created_at')->get();

        $rows = $orders->map(fn (Order $o) => [
            $o->created_at->format('Y-m-d H:i'),
            $o->order_number,
            $o->delivery_area ?? '',
            $o->customer?->name ?? $o->guest_name ?? '',
            $o->delivery_phone ?? '',
            $o->delivery_address ?? '',
            $o->delivery_person_name ?? '',
            $o->dispatched_at?->format('Y-m-d H:i') ?? '',
            $o->status,
            $o->payment_status,
            (float) $o->subtotal,
            (float) $o->delivery_fee,
            (float) $o->total_amount,
        ])->all();

        return $this->streamCsv('delivery-report.csv', [
            'Date', 'Order', 'Area', 'Customer', 'Phone', 'Address',
            'Carried by', 'Dispatched', 'Status', 'Payment',
            'Goods', 'Delivery fee', 'Total',
        ], $rows);
    }

    private function streamCsv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        $orders = $this->orders()->get([
            'id', 'delivery_area', 'delivery_fee', 'subtotal', 'status',
            'payment_status', 'delivery_person_name',
        ]);

        $delivered = $orders->where('status', 'completed');
        $stillOut  = $orders->where('status', 'dispatched');
        $cancelled = $orders->where('status', 'cancelled');
        $inHand    = $orders->whereNotIn('status', ['completed', 'dispatched', 'cancelled']);

        // Grouped on the name the order kept, not on the zone it points at:
        // a zone renamed last week must not move last month's deliveries into
        // a heading that did not exist then.
        $byArea = $orders
            ->where('status', '!=', 'cancelled')
            ->groupBy(fn ($o) => $o->delivery_area ?: 'Not recorded')
            ->map(fn ($group, $name) => [
                'area'      => $name,
                'orders'    => $group->count(),
                'delivered' => $group->where('status', 'completed')->count(),
                'goods'     => (float) $group->sum('subtotal'),
                'fees'      => (float) $group->where('status', 'completed')->sum('delivery_fee'),
                'pending'   => (float) $group->where('status', '!=', 'completed')->sum('delivery_fee'),
            ])
            ->sortByDesc('fees')
            ->values();

        // Riders are a name typed in at dispatch, not a user account, so this
        // is only as good as what was typed. Worth knowing before anybody is
        // paid on the strength of it.
        $byRider = $orders
            ->whereNotNull('delivery_person_name')
            ->groupBy(fn ($o) => trim($o->delivery_person_name))
            ->map(fn ($group, $name) => [
                'rider'     => $name,
                'carried'   => $group->count(),
                'delivered' => $group->where('status', 'completed')->count(),
                'still_out' => $group->where('status', 'dispatched')->count(),
                'fees'      => (float) $group->where('status', 'completed')->sum('delivery_fee'),
            ])
            ->sortByDesc('carried')
            ->values();

        return view('livewire.delivery.report', [
            'totalOrders'   => $orders->count(),
            'deliveredCount' => $delivered->count(),
            'earned'        => (float) $delivered->sum('delivery_fee'),
            'stillOutCount' => $stillOut->count(),
            'stillOutFees'  => (float) $stillOut->sum('delivery_fee'),
            'inHandCount'   => $inHand->count(),
            'cancelledCount' => $cancelled->count(),
            // Of what has been earned, how much has actually been paid for.
            'earnedPaid'    => (float) $delivered->where('payment_status', 'paid')->sum('delivery_fee'),
            'freeDeliveries' => $delivered->where('delivery_fee', 0)->count(),
            'byArea'        => $byArea,
            'byRider'       => $byRider,
            'areas'         => $this->areasInWindow(),
        ]);
    }
}
