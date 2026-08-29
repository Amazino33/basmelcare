<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Return Slip — {{ $saleReturn->sale->invoice_number ?? '#' . $saleReturn->sale_id }}</title>
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
        .item { padding: 3px 0; }
        .item-name { font-size: 11px; font-weight: bold; }
        .item-detail { font-size: 10px; }
        .footer { margin-top: 8px; font-size: 10px; text-align: center; }
        .copy-label-wrap { text-align: center; margin-bottom: 6px; }
        .copy-label { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; border: 1px solid #000; padding: 2px 6px; display: inline-block; }
        .stamp { border: 2px solid #000; padding: 2px 8px; display: inline-block; font-size: 11px; font-weight: bold; letter-spacing: 1px; margin: 4px 0; }
        .credit-box { border: 1px solid #000; padding: 4px 6px; margin: 6px 0; text-align: center; }
        .no-print { text-align: center; padding: 16px; background: #f5f5f5; border-bottom: 1px solid #ddd; }
        .no-print button { padding: 8px 18px; margin: 0 4px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .btn-print { background: #2563eb; color: white; }
        .btn-back  { background: #e2e8f0; color: #333; }
        @page { size: 80mm auto; margin: 0; }
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
    <button class="btn-print" onclick="window.print()">&#128438; Print Return Slip</button>
    <button class="btn-back" onclick="window.close()">Close</button>
</div>

@php
    $sale     = $saleReturn->sale;
    $customer = $sale->customer;
    $ref      = 'RT-' . str_pad($saleReturn->id, 5, '0', STR_PAD_LEFT);
@endphp

@for($copy = 1; $copy <= 2; $copy++)
<div class="copy">
    <div class="copy-label-wrap">
        <span class="copy-label">{{ $copy === 1 ? 'Customer Copy' : 'Pharmacy Copy' }}</span>
    </div>

    <div class="header center">
        <h1>{{ $pharmacyName ?: 'BasmelCare Pharmacy' }}</h1>
        @if($pharmacyAddress)<p>{{ $pharmacyAddress }}</p>@endif
        @if($pharmacyPhone)<p>Tel: {{ $pharmacyPhone }}</p>@endif
        <div style="margin-top:5px;"><span class="stamp">RETURN SLIP</span></div>
    </div>

    <div class="line"></div>

    <div class="row"><span>Ref #:</span><span class="bold">{{ $ref }}</span></div>
    <div class="row"><span>Date:</span><span>{{ $saleReturn->created_at->format('d/m/Y H:i') }}</span></div>
    <div class="row"><span>Processed by:</span><span>{{ $saleReturn->processor->name }}</span></div>
    <div class="row"><span>Original invoice:</span><span>{{ $sale->invoice_number ?? '#' . $sale->id }}</span></div>

    <div class="line"></div>

    @if($customer)
        <div class="row bold"><span>Customer:</span><span>{{ $customer->name }}</span></div>
        @if($customer->phone)
            <div class="row"><span>Phone:</span><span>{{ $customer->phone }}</span></div>
        @endif
        <div class="line"></div>
    @endif

    <div style="font-size:10px; font-weight:bold; text-transform:uppercase; letter-spacing:.5px; padding: 2px 0;">Items Returned</div>

    @foreach($saleReturn->items as $item)
        <div class="item">
            <div class="item-name">{{ $item->product->name }}</div>
            <div class="row item-detail">
                <span>{{ $item->quantity_returned }} x ₦{{ number_format($item->unit_price, 2) }}</span>
                <span>₦{{ number_format($item->subtotal, 2) }}</span>
            </div>
        </div>
    @endforeach

    <div class="line"></div>

    @if($saleReturn->reason)
        <div class="row" style="font-size:10px;"><span>Reason:</span><span style="max-width:55mm;text-align:right;">{{ $saleReturn->reason }}</span></div>
        <div class="line"></div>
    @endif

    {{-- What the customer actually walked away with. A slip that says "credit
         added" after a cash refund sends them back to the counter to claim a
         balance that was never created. --}}
    <div class="credit-box">
        <div style="font-size:10px; text-transform:uppercase; letter-spacing:.5px;">{{ $saleReturn->refundLabel() }}</div>
        <div class="total-row">₦{{ number_format($saleReturn->total_credit, 2) }}</div>
        @if($customer && ! $saleReturn->isCash())
            <div style="font-size:10px; margin-top:2px;">Balance: ₦{{ number_format($customer->credit_balance, 2) }}</div>
        @endif
    </div>

    <div class="footer">
        @if($saleReturn->isCash())
            <p>Refunded in cash at the counter.</p>
        @else
            <p>Credit is redeemable on your next purchase.</p>
        @endif
        <p style="margin-top:6px;">{{ $pharmacyName ?: 'BasmelCare Pharmacy' }}</p>
        @if($pharmacyPhone)<p>{{ $pharmacyPhone }}</p>@endif
        @if($pharmacyWebsite)<p>{{ $pharmacyWebsite }}</p>@endif
        <p style="margin-top:4px;">Customer signature: ____________________</p>
    </div>
</div>
@if($copy === 1)<div class="cut-line">✂ - - - - - - - - - - - - - - - - - - - - - ✂</div>@endif
@endfor

<script>window.onload = () => { window.print(); window.onafterprint = () => window.close(); };</script>
</body>
</html>
