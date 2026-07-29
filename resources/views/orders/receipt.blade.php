<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $order->order_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 12px; color: #000; }
        .copy { width: 80mm; margin: 0 auto; padding: 10px; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #000; margin: 6px 0; }
        .cut { border-top: 1px dashed #000; margin: 6px 0; text-align: center; font-size: 9px; color: #666; }
        .header h1 { font-size: 15px; font-weight: bold; margin-bottom: 2px; }
        .header p { font-size: 10px; }
        .row { display: flex; justify-content: space-between; padding: 2px 0; font-size: 11px; }
        .item { padding: 3px 0; }
        .item-name { font-size: 11px; font-weight: bold; }
        .item-detail { font-size: 10px; }
        .total-row { font-size: 15px; font-weight: bold; }
        .footer { margin-top: 8px; font-size: 10px; text-align: center; }
        .stamp { text-align: center; font-size: 11px; font-weight: bold; border: 1px solid #000; padding: 3px; margin: 6px 0; letter-spacing: 1px; }
        .paid-stamp { text-align: center; font-size: 18px; font-weight: bold; border: 3px solid #000; padding: 4px; margin: 6px 0; letter-spacing: 2px; }
        .no-print { text-align: center; padding: 16px; background: #f5f5f5; border-bottom: 1px solid #ddd; }
        .no-print button { padding: 8px 18px; margin: 0 4px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .btn-print { background: #2563eb; color: white; }
        .btn-back  { background: #e2e8f0; color: #333; }
        @page { size: 80mm auto; margin: 0; }
        @media print {
            .no-print { display: none !important; }
            body { width: 80mm; margin: 0; padding: 0; }
            .cut { page-break-after: always; break-after: page; visibility: hidden; height: 0; margin: 0; }
        }
        @media screen { body { max-width: 360px; border: 1px solid #ccc; margin: 20px auto; background: white; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">&#128438; Print Receipt (2 copies)</button>
        <button class="btn-back" onclick="window.close()">Close</button>
    </div>

    @for($copy = 1; $copy <= 2; $copy++)
    <div class="copy">
        <div class="header center">
            <h1>{{ $pharmacyName ?: 'BasmelCare Pharmacy' }}</h1>
            @if($pharmacyAddress)<p>{{ $pharmacyAddress }}</p>@endif
            @if($pharmacyPhone)<p>Tel: {{ $pharmacyPhone }}</p>@endif
        </div>

        <div class="line"></div>
        <div class="stamp">PAYMENT RECEIPT {{ $copy === 1 ? '— CUSTOMER COPY' : '— PHARMACY COPY' }}</div>
        <div class="line"></div>

        <div class="row"><span>Order #:</span><span class="bold">{{ $order->order_number }}</span></div>
        <div class="row"><span>Date Paid:</span><span>{{ ($order->paid_at ?? now())->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span>Type:</span><span>{{ ucfirst($order->fulfillment_type) }}</span></div>

        <div class="line"></div>

        <div class="row"><span>Customer:</span><span class="bold">{{ $order->customer?->name ?? $order->guest_name ?? 'Guest' }}</span></div>
        @php $phone = $order->customer?->phone ?? $order->guest_phone ?? ''; @endphp
        @if($phone)<div class="row"><span>Phone:</span><span>{{ $phone }}</span></div>@endif

        <div class="line"></div>

        @foreach($order->items as $item)
            <div class="item">
                <div class="item-name">{{ $item->product->name }}</div>
                <div class="row item-detail">
                    <span>{{ $item->quantity }} x ₦{{ number_format($item->unit_price, 2) }}</span>
                    <span>₦{{ number_format($item->subtotal, 2) }}</span>
                </div>
            </div>
        @endforeach

        <div class="line"></div>

        <div class="row"><span>Subtotal:</span><span>₦{{ number_format($order->subtotal, 2) }}</span></div>
        @if($order->delivery_fee > 0)
            <div class="row"><span>Delivery:</span><span>₦{{ number_format($order->delivery_fee, 2) }}</span></div>
        @endif
        <div class="row total-row"><span>TOTAL:</span><span>₦{{ number_format($order->total_amount, 2) }}</span></div>

        <div class="line"></div>

        <div class="paid-stamp">✓ PAID</div>
        <div class="row"><span>Method:</span><span class="bold">{{ ucfirst($order->payment_method ?? '—') }}</span></div>
        @if($order->payment_reference)
            <div class="row"><span>Ref:</span><span>{{ $order->payment_reference }}</span></div>
        @endif
        @if($order->verifiedBy)
            <div class="row"><span>Verified by:</span><span>{{ $order->verifiedBy->name }}</span></div>
        @endif

        <div class="line"></div>
        <div class="footer">
            <p>Thank you for your patronage!</p>
            <p>{{ $pharmacyName ?: 'BasmelCare Pharmacy' }}</p>
            @if($pharmacyPhone)<p>{{ $pharmacyPhone }}</p>@endif
        </div>
    </div>
    @if($copy === 1)<div class="cut">✂ - - - - - - - - - - - - - - - - - - - - - ✂</div>@endif
    @endfor
</body>
</html>
