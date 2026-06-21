<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $sale->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 12px; color: #000; width: 80mm; margin: 0 auto; }
        .receipt { padding: 10px; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #000; margin: 8px 0; }
        .header { margin-bottom: 8px; }
        .header h1 { font-size: 16px; margin-bottom: 2px; }
        .header p { font-size: 10px; }
        .row { display: flex; justify-content: space-between; padding: 2px 0; }
        .item { padding: 3px 0; }
        .item-name { font-size: 12px; }
        .item-detail { font-size: 10px; color: #555; }
        .total { font-size: 16px; font-weight: bold; }
        .footer { margin-top: 10px; font-size: 10px; }
        @media print {
            body { width: 80mm; }
            .no-print { display: none !important; }
        }
        @media screen {
            body { max-width: 320px; border: 1px solid #ccc; margin: 20px auto; }
        }
        .no-print { text-align: center; margin: 15px; }
        .no-print button { padding: 8px 16px; margin: 0 4px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .btn-print { background: #2563eb; color: white; }
        .btn-back { background: #e2e8f0; color: #333; }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">Print Receipt</button>
        <button class="btn-back" onclick="window.close()">Close</button>
    </div>

    <div class="receipt">
        <div class="header center">
            <h1>{{ $pharmacyName ?: 'BasmelCare Pharmacy' }}</h1>
            @if($pharmacyAddress)<p>{{ $pharmacyAddress }}</p>@endif
            @if($pharmacyPhone)<p>Tel: {{ $pharmacyPhone }}</p>@endif
        </div>

        <div class="line"></div>

        <div class="row"><span>Invoice:</span><span>{{ $sale->invoice_number ?? '#' . $sale->id }}</span></div>
        <div class="row"><span>Date:</span><span>{{ $sale->created_at->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span>Cashier:</span><span>{{ $sale->user->name }}</span></div>
        @if($sale->customer)
            <div class="row"><span>Customer:</span><span>{{ $sale->customer->name }}</span></div>
        @endif

        <div class="line"></div>

        @foreach($sale->saleItems as $item)
            <div class="item">
                <div class="item-name">{{ $item->product->name }}</div>
                <div class="row">
                    <span class="item-detail">{{ $item->quantity }} x ₦{{ number_format($item->unit_price, 2) }}</span>
                    <span>₦{{ number_format($item->subtotal, 2) }}</span>
                </div>
            </div>
        @endforeach

        <div class="line"></div>

        <div class="row"><span>Items:</span><span>{{ $sale->saleItems->sum('quantity') }}</span></div>
        <div class="row total"><span>TOTAL:</span><span>₦{{ number_format($sale->total_amount, 2) }}</span></div>
        <div class="row"><span>Payment:</span><span>{{ ucfirst($sale->payment_method) }}</span></div>
        @if($sale->payment_method === 'split' && $sale->payment_details)
            @foreach($sale->payment_details as $method => $amount)
                <div class="row"><span>  {{ ucfirst($method) }}:</span><span>₦{{ number_format($amount, 2) }}</span></div>
            @endforeach
        @endif

        @if($sale->payment_method === 'credit')
            <div class="line"></div>
            <div class="center bold" style="color: red;">** CREDIT SALE **</div>
        @endif

        <div class="line"></div>

        <div class="footer center">
            <p>Thank you for your patronage!</p>
            <p>{{ $pharmacyName ?: 'BasmelCare Pharmacy' }}</p>
            @if($pharmacyPhone)<p>{{ $pharmacyPhone }}</p>@endif
        </div>
    </div>
</body>
</html>
