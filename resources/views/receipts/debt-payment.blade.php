<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Debt Payment Receipt</title>
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
        .total-row { font-size: 15px; font-weight: bold; }
        .footer { margin-top: 8px; font-size: 10px; text-align: center; }
        .stamp { text-align: center; font-size: 13px; font-weight: bold; border: 2px solid #000; padding: 4px; margin: 8px 0; letter-spacing: 1px; }

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
        <button class="btn-print" onclick="window.print()">&#128438; Print Receipt</button>
        <button class="btn-back" onclick="window.close()">Close</button>
    </div>

    @php
        $remaining = (float) $debt->amount_owed - (float) $debt->amount_paid;
    @endphp

    <div class="copy">
        <div class="header center">
            <h1>{{ $pharmacyName ?: 'BasmelCare Pharmacy' }}</h1>
            @if($pharmacyAddress)<p>{{ $pharmacyAddress }}</p>@endif
            @if($pharmacyPhone)<p>Tel: {{ $pharmacyPhone }}</p>@endif
        </div>

        <div class="line"></div>

        <div class="stamp">DEBT PAYMENT RECEIPT</div>

        <div class="line"></div>

        <div class="row"><span>Date:</span><span>{{ $payment->created_at->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span>Received by:</span><span>{{ $payment->receiver->name }}</span></div>
        <div class="row"><span>Customer:</span><span class="bold">{{ $debt->customer->name }}</span></div>
        <div class="row"><span>Invoice Ref:</span><span>{{ $debt->sale->invoice_number ?? '#' . $debt->sale_id }}</span></div>

        <div class="line"></div>

        <div class="row"><span>Total Owed:</span><span>₦{{ number_format($debt->amount_owed, 2) }}</span></div>
        <div class="row"><span>Previously Paid:</span><span>₦{{ number_format((float) $debt->amount_paid - (float) $payment->amount, 2) }}</span></div>

        <div class="line"></div>

        <div class="row total-row"><span>PAID NOW:</span><span>₦{{ number_format($payment->amount, 2) }}</span></div>
        <div class="row"><span>Method:</span><span>{{ ucfirst($payment->payment_method) }}</span></div>

        <div class="line"></div>

        @if($remaining <= 0)
            <div class="stamp">FULLY SETTLED ✓</div>
        @else
            <div class="row bold"><span>Balance Remaining:</span><span>₦{{ number_format($remaining, 2) }}</span></div>
        @endif

        @if($payment->note)
            <div class="line"></div>
            <div style="font-size:10px;">Note: {{ $payment->note }}</div>
        @endif

        <div class="line"></div>

        <div class="footer">
            <p>Thank you!</p>
            <p>{{ $pharmacyName ?: 'BasmelCare Pharmacy' }}</p>
            @if($pharmacyPhone)<p>{{ $pharmacyPhone }}</p>@endif
        </div>
    </div>

    <script>window.onload = () => window.print();</script>
</body>
</html>
