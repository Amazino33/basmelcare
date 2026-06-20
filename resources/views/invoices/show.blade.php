<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice #{{ $sale->id }} — {{ $pharmacyName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #333; font-size: 14px; }
        .invoice { max-width: 800px; margin: 0 auto; padding: 40px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; border-bottom: 3px solid #2563eb; padding-bottom: 20px; }
        .brand h1 { font-size: 24px; color: #2563eb; margin-bottom: 4px; }
        .brand p { font-size: 12px; color: #666; }
        .invoice-meta { text-align: right; }
        .invoice-meta h2 { font-size: 28px; color: #2563eb; text-transform: uppercase; }
        .invoice-meta p { font-size: 12px; color: #666; margin-top: 4px; }
        .parties { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .party { width: 48%; }
        .party-label { font-size: 11px; text-transform: uppercase; color: #999; font-weight: 600; margin-bottom: 6px; }
        .party-name { font-size: 16px; font-weight: 600; margin-bottom: 4px; }
        .party-detail { font-size: 12px; color: #666; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        thead th { background: #f1f5f9; padding: 10px 12px; text-align: left; font-size: 11px; text-transform: uppercase; color: #666; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
        tbody td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; }
        .text-right { text-align: right; }
        .totals { display: flex; justify-content: flex-end; margin-bottom: 30px; }
        .totals-table { width: 280px; }
        .totals-table .row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; }
        .totals-table .total-row { border-top: 2px solid #2563eb; padding-top: 10px; margin-top: 6px; font-size: 18px; font-weight: 700; color: #2563eb; }
        .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .footer p { font-size: 11px; color: #999; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-completed { background: #dcfce7; color: #166534; }
        .badge-credit { background: #fef3c7; color: #92400e; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none; }
            .invoice { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="invoice">
        <div class="no-print" style="text-align: right; margin-bottom: 20px;">
            <button onclick="window.print()" style="padding: 8px 20px; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">Print Invoice</button>
            <button onclick="window.history.back()" style="padding: 8px 20px; background: #e2e8f0; color: #333; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; margin-left: 8px;">Back</button>
        </div>

        <div class="header">
            <div class="brand">
                <h1>{{ $pharmacyName ?: 'BasmelCare Pharmacy' }}</h1>
                @if($pharmacyAddress)<p>{{ $pharmacyAddress }}</p>@endif
                @if($pharmacyPhone)<p>Tel: {{ $pharmacyPhone }}</p>@endif
                @if($pharmacyEmail)<p>{{ $pharmacyEmail }}</p>@endif
            </div>
            <div class="invoice-meta">
                <h2>Invoice</h2>
                <p><strong>#INV-{{ str_pad($sale->id, 5, '0', STR_PAD_LEFT) }}</strong></p>
                <p>Date: {{ $sale->created_at->format('M d, Y') }}</p>
                <p>Time: {{ $sale->created_at->format('h:i A') }}</p>
            </div>
        </div>

        <div class="parties">
            <div class="party">
                <div class="party-label">Bill To</div>
                @if($sale->customer)
                    <div class="party-name">{{ $sale->customer->name }}</div>
                    <div class="party-detail">
                        @if($sale->customer->phone){{ $sale->customer->phone }}<br>@endif
                        @if($sale->customer->email){{ $sale->customer->email }}<br>@endif
                        @if($sale->customer->address){{ $sale->customer->address }}@endif
                    </div>
                @else
                    <div class="party-name">Walk-in Customer</div>
                @endif
            </div>
            <div class="party" style="text-align: right;">
                <div class="party-label">Payment Info</div>
                <div class="party-detail">
                    Method: <strong>{{ ucfirst($sale->payment_method) }}</strong><br>
                    Status: <span class="badge {{ $sale->payment_method === 'credit' ? 'badge-credit' : 'badge-completed' }}">{{ $sale->payment_method === 'credit' ? 'Credit' : 'Paid' }}</span><br>
                    Cashier: {{ $sale->user->name }}
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Batch</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->saleItems as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item->product->name }}</td>
                        <td>{{ $item->batch->batch_number }}</td>
                        <td class="text-right">₦{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-right">{{ $item->quantity }}</td>
                        <td class="text-right">₦{{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-table">
                <div class="row">
                    <span>Items:</span>
                    <span>{{ $sale->saleItems->sum('quantity') }}</span>
                </div>
                <div class="row total-row">
                    <span>Total:</span>
                    <span>₦{{ number_format($sale->total_amount, 2) }}</span>
                </div>
            </div>
        </div>

        @if($sale->note)
            <div style="background: #f8fafc; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                <strong style="font-size: 11px; text-transform: uppercase; color: #999;">Note:</strong>
                <p style="margin-top: 4px; font-size: 13px;">{{ $sale->note }}</p>
            </div>
        @endif

        <div class="footer">
            <p>Thank you for your patronage!</p>
            <p>{{ $pharmacyName ?: 'BasmelCare Pharmacy' }} {{ $pharmacyPhone ? '| ' . $pharmacyPhone : '' }}</p>
        </div>
    </div>
</body>
</html>
