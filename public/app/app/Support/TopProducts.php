<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The same sales read three ways, because the three disagree.
 *
 * Demand is counted in SALES, not units. Quantity adds up things that are not
 * the same kind of thing - a pharmacy sells vitamin C as loose tablets and
 * antibiotics as packs - so ranking by it only ever finds whatever is sold in
 * the smallest unit. Revenue and profit are money, which is comparable across
 * products in a way units are not.
 *
 * Lives here rather than on the dashboard because the printed report shows the
 * same figures, and two copies of this query would eventually disagree about
 * which sales count.
 */
class TopProducts
{
    /** Only settled sales. A pending invoice is not a sale. */
    public const COUNTED_STATUSES = ['paid', 'completed'];

    /**
     * @return array{byTimesSold: Collection, byRevenue: Collection, byProfit: Collection, any: bool}
     */
    public static function between($from, $to, int $limit = 5): array
    {
        $rows = static::rows($from, $to);

        $top = fn (string $column) => $rows
            ->sortByDesc(fn ($row) => (float) $row->$column)
            ->take($limit)
            ->values();

        return [
            'byTimesSold' => $top('times_sold'),
            'byRevenue'   => $top('revenue'),
            'byProfit'    => $top('profit'),
            'any'         => $rows->isNotEmpty(),
        ];
    }

    /** Every product sold in the period, unranked. */
    public static function rows($from, $to): Collection
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereIn('sales.status', static::COUNTED_STATUSES)
            ->whereBetween('sales.created_at', [$from, $to])
            ->groupBy('products.id', 'products.name')
            ->selectRaw('products.id, products.name,
                         SUM(sale_items.quantity) AS units,
                         COUNT(DISTINCT sales.id) AS times_sold,
                         SUM(sale_items.subtotal) AS revenue,
                         SUM(sale_items.subtotal - sale_items.cost_price * sale_items.quantity) AS profit')
            ->get();
    }
}
