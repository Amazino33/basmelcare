<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $sale->invoice_number ?? '#' . $sale->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 12px; color: #000; }

        .copy { width: 80mm; margin: 0 auto; padding: 10px; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #000; margin: 6px 0; }
        .header h1 { font-size: 15px; font-weight: bold; margin-bottom: 2px; }
        .header p { font-size: 10px; }
        .row { display: flex; justify-content: space-between; padding: 2px 0; font-size: 11px; }
        .item { padding: 3px 0; }
        .item-name { font-size: 11px; font-weight: bold; }
        .item-batch { font-size: 9px; color: #444; }
        .item-detail { font-size: 10px; }
        .total-row { font-size: 15px; font-weight: bold; }
        .footer { margin-top: 8px; font-size: 10px; text-align: center; }
        .stamp { text-align: center; font-size: 11px; font-weight: bold; border: 1px solid #000; padding: 3px; margin: 6px 0; letter-spacing: 1px; }

        .no-print { text-align: center; padding: 16px; background: #f5f5f5; border-bottom: 1px solid #ddd; }
        .no-print button { padding: 8px 18px; margin: 0 4px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .btn-print { background: #2563eb; color: white; }
        .btn-back  { background: #e2e8f0; color: #333; }

        @page {
            size: 80mm auto;
            margin: 0;
        }
        @media print {
            .no-print { display: none !important; }
            body { width: 80mm; margin: 0; padding: 0; }
        }
        @media screen {
            body { max-width: 360px; border: 1px solid #ccc; margin: 20px auto; background: white; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">&#128438; Print Invoice</button>
        <button class="btn-back" onclick="window.close()">Close</button>
    </div>

    <div class="copy">
        <div class="header center">
            <h1>{{ $pharmacyName ?: 'BasmelCare Pharmacy' }}</h1>
            @if($pharmacyAddress)<p>{{ $pharmacyAddress }}</p>@endif
            @if($pharmacyPhone)<p>Tel: {{ $pharmacyPhone }}</p>@endif
            @if($pharmacyEmail)<p>{{ $pharmacyEmail }}</p>@endif
        </div>

        <div class="line"></div>

        <div class="stamp">INVOICE</div>

        <div class="line"></div>

        <div class="row"><span>Invoice:</span><span class="bold">{{ $sale->invoice_number ?? '#' . $sale->id }}</span></div>
        <div class="row"><span>Date:</span><span>{{ $sale->created_at->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span>Cashier:</span><span>{{ $sale->user->name }}</span></div>
        @if($sale->customer)
            <div class="row"><span>Customer:</span><span class="bold">{{ $sale->customer->name }}</span></div>
            @if($sale->customer->phone)
                <div class="row"><span>Phone:</span><span>{{ $sale->customer->phone }}</span></div>
            @endif
        @else
            <div class="row"><span>Customer:</span><span>Walk-in</span></div>
        @endif

        <div class="line"></div>

        @foreach($sale->saleItems as $item)
            <div class="item">
                <div class="item-name">
                    {{ $item->product->name }}
                    @if($item->is_pack)
                        <span style="font-size:9px;font-weight:normal;">(Pack×{{ $item->pack_size }})</span>
                    @endif
                </div>
                @if($item->batch)
                    <div class="item-batch">Batch: {{ $item->batch->batch_number }}</div>
                @endif
                <div class="row item-detail">
                    <span>{{ $item->quantity }} x ₦{{ number_format($item->unit_price, 2) }}</span>
                    <span>₦{{ number_format($item->subtotal, 2) }}</span>
                </div>
            </div>
        @endforeach

        <div class="line"></div>

        <div class="row"><span>Items:</span><span>{{ $sale->saleItems->sum('quantity') }}</span></div>
        <div class="row total-row"><span>TOTAL:</span><span>₦{{ number_format($sale->total_amount, 2) }}</span></div>

        <div class="line"></div>

        <div class="row"><span>Payment:</span><span>{{ ucfirst($sale->payment_method) }}</span></div>
        @if($sale->payment_method === 'split' && $sale->payment_details)
            @foreach($sale->payment_details as $method => $amount)
                <div class="row"><span>&nbsp;&nbsp;{{ ucfirst($method) }}:</span><span>₦{{ number_format($amount, 2) }}</span></div>
            @endforeach
        @endif
        @if($sale->payment_method === 'credit')
            <div class="line"></div>
            <div class="center bold" style="color:#c00;">** CREDIT SALE **</div>
        @endif

        @if($sale->note)
            <div class="line"></div>
            <div style="font-size:10px;"><span class="bold">Note:</span> {{ $sale->note }}</div>
        @endif

        <div class="line"></div>

        <div class="footer">
            <p>Thank you for your patronage!</p>
            <p>{{ $pharmacyName ?: 'BasmelCare Pharmacy' }}</p>
            @if($pharmacyPhone)<p>{{ $pharmacyPhone }}</p>@endif
            @if($pharmacyWebsite)<p>{{ $pharmacyWebsite }}</p>@endif
        </div>
    </div>
</body>
</html>
