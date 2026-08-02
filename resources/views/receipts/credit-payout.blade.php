<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Credit Payout — {{ $payout->customer->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 12px; color: #000; }
        .copy { width: 80mm; margin: 0 auto; padding: 10px; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #000; margin: 6px 0; }
        .cut-line { text-align: center; font-size: 10px; letter-spacing: 2px; margin: 12px 0; color: #aaa; }
        .header h1 { font-size: 15px; font-weight: bold; margin-bottom: 2px; }
        .header p { font-size: 10px; }
        .row { display: flex; justify-content: space-between; padding: 2px 0; font-size: 11px; }
        .total-row { font-size: 14px; font-weight: bold; padding: 4px 0; }
        .footer { margin-top: 8px; font-size: 10px; text-align: center; }
        .copy-label-wrap { text-align: center; margin-bottom: 6px; }
        .copy-label { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; border: 1px solid #000; padding: 2px 6px; display: inline-block; }
        .no-print { text-align: center; padding: 16px; background: #f5f5f5; border-bottom: 1px solid #ddd; }
        .no-print button { padding: 8px 18px; margin: 0 4px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .btn-print { background: #2563eb; color: white; }
        .btn-back  { background: #e2e8f0; color: #333; }
        @page { size: 80mm auto; margin: 0; }
        @media print {
            .no-print { display: none !important; }
            body { width: 80mm; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨 Print</button>
    <button class="btn-back" onclick="window.close()">Close</button>
</div>

@for($copy = 1; $copy <= 2; $copy++)
<div class="copy">
    <div class="copy-label-wrap">
        <span class="copy-label">{{ $copy === 1 ? 'Customer Copy' : 'Pharmacy Copy' }}</span>
    </div>

    <div class="header center">
        <h1>{{ $pharmacyName }}</h1>
        @if($pharmacyAddress)<p>{{ $pharmacyAddress }}</p>@endif
        @if($pharmacyPhone)<p>Tel: {{ $pharmacyPhone }}</p>@endif
        <p style="margin-top:4px; font-weight:bold; font-size:13px; letter-spacing:1px;">CREDIT PAYOUT</p>
    </div>

    <div class="line"></div>

    <div class="row"><span>Ref #:</span><span>CP-{{ str_pad($payout->id, 5, '0', STR_PAD_LEFT) }}</span></div>
    <div class="row"><span>Date:</span><span>{{ $payout->created_at->format('d/m/Y H:i') }}</span></div>
    <div class="row"><span>Cashier:</span><span>{{ $payout->cashier->name }}</span></div>

    <div class="line"></div>

    <div class="row bold"><span>Customer:</span><span>{{ $payout->customer->name }}</span></div>
    @if($payout->customer->phone)
        <div class="row"><span>Phone:</span><span>{{ $payout->customer->phone }}</span></div>
    @endif

    <div class="line"></div>

    <div class="row"><span>Previous credit balance:</span><span>₦{{ number_format($payout->balance_before, 2) }}</span></div>
    <div class="row total-row"><span>Amount paid out:</span><span>₦{{ number_format($payout->amount, 2) }}</span></div>
    <div class="row"><span>Remaining balance:</span><span>₦{{ number_format($payout->balance_after, 2) }}</span></div>

    <div class="line"></div>

    <div class="footer">
        <p>Customer signature: ____________________</p>
        <p style="margin-top: 8px;">Thank you for your patronage!</p>
        @if($pharmacyWebsite)<p>{{ $pharmacyWebsite }}</p>@endif
    </div>
</div>
@if($copy === 1)<div class="cut-line">- - - - - - - - - - - - - - -</div>@endif
@endfor

    <script>window.onload = () => { window.print(); window.onafterprint = () => window.close(); };</script>
</body>
</html>
