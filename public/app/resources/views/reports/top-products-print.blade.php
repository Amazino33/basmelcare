<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Top Products — {{ $pharmacyName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #000; padding: 14px; }

        .header { text-align: center; margin-bottom: 12px; border-bottom: 2px solid #000; padding-bottom: 8px; }
        .header h1 { font-size: 16px; font-weight: bold; }
        .header p { font-size: 10px; color: #444; margin-top: 3px; }

        .meta { display: flex; justify-content: space-between; margin-bottom: 14px; font-size: 10px; color: #555; }

        /* Three rankings side by side on paper, stacked if the sheet is narrow. */
        .rankings { display: flex; gap: 14px; align-items: flex-start; }
        .ranking { flex: 1; min-width: 0; }

        .ranking h2 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1.5px solid #000;
            padding-bottom: 3px;
            margin-bottom: 2px;
        }
        .ranking .caption { font-size: 9px; color: #666; margin-bottom: 6px; }

        table { width: 100%; border-collapse: collapse; }
        tbody tr { border-bottom: 1px solid #ddd; }
        tbody tr:nth-child(even) { background: #f5f5f5; }
        tbody td { padding: 4px 5px; vertical-align: top; }

        .rank { width: 16px; color: #888; font-weight: bold; }
        .name { font-size: 10px; }
        .sub { font-size: 8.5px; color: #666; }
        .figure { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; font-weight: bold; }

        .empty { padding: 14px 4px; color: #888; font-size: 10px; text-align: center; }

        .note {
            margin-top: 16px;
            border-top: 1px solid #ccc;
            padding-top: 8px;
            font-size: 9px;
            color: #555;
            line-height: 1.5;
        }

        .btn-print {
            display: inline-block; margin-bottom: 12px; padding: 6px 14px;
            border: 1px solid #000; background: #fff; cursor: pointer; font-size: 11px;
        }

        @media print {
            body { padding: 0; }
            .btn-print { display: none; }
            .ranking { break-inside: avoid; }
        }
    </style>
</head>
<body>

<button class="btn-print" onclick="window.print()">&#128438; Print</button>

<div class="header">
    <h1>{{ $pharmacyName }}</h1>
    <p>Top Products</p>
</div>

<div class="meta">
    <span>{{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}</span>
    <span>Printed {{ $printedAt }} by {{ $printedBy }}</span>
</div>

@if(! $top['any'])
    <div class="empty">No sales were recorded in this period.</div>
@else
    <div class="rankings">
        @foreach([
            ['key' => 'byTimesSold', 'title' => 'Bought most often', 'caption' => 'how many customers asked for it'],
            ['key' => 'byRevenue',   'title' => 'Most revenue',      'caption' => 'biggest sellers by value'],
            ['key' => 'byProfit',    'title' => 'Most profit',       'caption' => 'what actually earns'],
        ] as $panel)
            <div class="ranking">
                <h2>{{ $panel['title'] }}</h2>
                <div class="caption">{{ $panel['caption'] }}</div>

                <table>
                    <tbody>
                        @foreach($top[$panel['key']] as $i => $row)
                            <tr>
                                <td class="rank">{{ $i + 1 }}</td>
                                <td class="name">
                                    {{ $row->name }}
                                    <div class="sub">
                                        @if($panel['key'] === 'byTimesSold')
                                            {{ number_format((float) $row->units) }} {{ Str::plural('unit', (float) $row->units) }} in total
                                        @else
                                            {{ $row->times_sold }} {{ Str::plural('sale', $row->times_sold) }}
                                        @endif
                                    </div>
                                </td>
                                <td class="figure">
                                    @if($panel['key'] === 'byTimesSold')
                                        {{ number_format((float) $row->times_sold) }}
                                    @else
                                        &#8358;{{ number_format((float) $row->{$panel['key'] === 'byRevenue' ? 'revenue' : 'profit'}, 0) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>

    <div class="note">
        <strong>Bought most often</strong> counts separate sales, not units. A pharmacy sells
        vitamin C as loose tablets and antibiotics as packs, so a total of &ldquo;units&rdquo;
        compares things that are not the same size &mdash; it would only ever rank whatever is
        sold in the smallest unit. Revenue and profit are money, which is comparable.
        <br>
        Profit is what each sale earned after the cost of the specific stock it came from.
        Cancelled and unpaid invoices are excluded.
    </div>
@endif

</body>
</html>
