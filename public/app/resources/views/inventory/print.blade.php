<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Stock Count Sheet — {{ $pharmacyName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #000; }

        .header { text-align: center; margin-bottom: 12px; border-bottom: 2px solid #000; padding-bottom: 8px; }
        .header h1 { font-size: 16px; font-weight: bold; }
        .header p { font-size: 10px; color: #444; margin-top: 3px; }

        .meta { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 10px; color: #555; }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #000;
            color: #fff;
            padding: 5px 6px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .price { text-align: right; font-variant-numeric: tabular-nums; }
        tbody tr:nth-child(even) { background: #f5f5f5; }
        tbody tr { border-bottom: 1px solid #ddd; }
        tbody td { padding: 5px 6px; vertical-align: top; }

        .batch-row { font-size: 9.5px; color: #444; }
        .batch-row + .batch-row { border-top: 1px dashed #ccc; }

        .total-qty { font-weight: bold; font-size: 12px; }
        .out { color: #cc0000; font-weight: bold; }
        .low { color: #996600; }

        .count-box {
            border: 1px solid #999;
            width: 70px;
            height: 22px;
            display: inline-block;
        }

        tfoot td { padding-top: 8px; font-size: 10px; color: #555; }

        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @page { size: A4 landscape; margin: 12mm; }
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>{{ $pharmacyName }} — Stock Count Sheet</h1>
        <p>Printed: {{ $printedAt }} &nbsp;|&nbsp; Total Products: {{ $products->count() }}</p>
    </div>

    <div class="meta">
        <span>Counted by: ___________________________</span>
        <span>Date of Count: ___________________________</span>
        <span>Verified by: ___________________________</span>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:3%">#</th>
                <th style="width:20%">Product Name</th>
                <th style="width:9%">Category</th>
                <th style="width:8%; text-align:right">Selling Price</th>
                <th style="width:10%">Batch No.</th>
                <th style="width:7%">Expiry</th>
                <th style="width:8%; text-align:right">Cost Price</th>
                <th style="width:7%; text-align:center">Sys Qty</th>
                <th style="width:7%; text-align:center">Total</th>
                <th style="width:9%; text-align:center">Physical Count</th>
                <th style="width:12%">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $index => $product)
                @php $totalQty = $product->batches->sum('quantity'); @endphp
                @if($product->batches->count() > 0)
                    @foreach($product->batches as $batchIndex => $batch)
                        <tr>
                            @if($batchIndex === 0)
                                <td rowspan="{{ $product->batches->count() }}">{{ $index + 1 }}</td>
                                <td rowspan="{{ $product->batches->count() }}" style="font-weight:bold">
                                    {{ $product->name }}
                                </td>
                                <td rowspan="{{ $product->batches->count() }}" style="font-size:10px; color:#555">
                                    {{ $product->category?->name ?? '—' }}
                                </td>
                                <td rowspan="{{ $product->batches->count() }}" class="price" style="vertical-align:middle">
                                    ₦{{ number_format($product->selling_price, 2) }}
                                </td>
                            @endif
                            <td class="batch-row">{{ $batch->batch_number ?? '—' }}</td>
                            <td class="batch-row">
                                {{ $batch->expiry_date ? \Carbon\Carbon::parse($batch->expiry_date)->format('M Y') : '—' }}
                            </td>
                            <td class="batch-row price">
                                {{ $batch->cost_price ? '₦' . number_format($batch->cost_price, 2) : '—' }}
                            </td>
                            <td class="batch-row" style="text-align:center">{{ $batch->quantity }}</td>
                            @if($batchIndex === 0)
                                <td rowspan="{{ $product->batches->count() }}" style="text-align:center; vertical-align:middle">
                                    <span class="total-qty {{ $totalQty == 0 ? 'out' : ($totalQty <= $product->reorder_level ? 'low' : '') }}">
                                        {{ $totalQty }}
                                    </span>
                                </td>
                                <td rowspan="{{ $product->batches->count() }}" style="text-align:center; vertical-align:middle">
                                    <span class="count-box"></span>
                                </td>
                                <td rowspan="{{ $product->batches->count() }}" style="vertical-align:middle"></td>
                            @endif
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td style="font-weight:bold">{{ $product->name }}</td>
                        <td style="font-size:10px; color:#555">{{ $product->category?->name ?? '—' }}</td>
                        <td class="price">₦{{ number_format($product->selling_price, 2) }}</td>
                        <td>—</td>
                        <td>—</td>
                        <td class="price">—</td>
                        <td style="text-align:center">0</td>
                        <td style="text-align:center"><span class="total-qty out">0</span></td>
                        <td style="text-align:center"><span class="count-box"></span></td>
                        <td></td>
                    </tr>
                @endif
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="11">
                    <strong>Legend:</strong>
                    <span style="color:#cc0000">■ Out of stock</span> &nbsp;|&nbsp;
                    <span style="color:#996600">■ Low stock (at or below reorder level)</span>
                </td>
            </tr>
        </tfoot>
    </table>

    <script>window.onload = () => { window.print(); window.onafterprint = () => window.close(); };</script>
</body>
</html>
