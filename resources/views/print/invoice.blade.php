<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->invoice_number }} Print</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

        :root {
            --surface-0: #ffffff;
            --surface-50: #f8fafc;
            --surface-100: #f1f5f9;
            --surface-200: #e2e8f0;
            --surface-400: #94a3b8;
            --surface-500: #64748b;
            --surface-700: #334155;
            --surface-900: #0f172a;
            --primary-500: #ca8a04;
            --primary-100: #fef3c7;
            --success-100: #dcfce7;
            --success-700: #15803d;
            --danger-100: #fee2e2;
            --danger-700: #b91c1c;
        }

        body {
            font-family: 'Poppins', Arial, sans-serif;
            margin: 0;
            padding: 24px;
            color: var(--surface-900);
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        }
        .page {
            max-width: 900px;
            margin: 0 auto;
            background: var(--surface-0);
            border: 1px solid var(--surface-200);
            padding: 32px;
        }
        .head,
        .meta,
        .totals,
        .payments {
            width: 100%;
            border-collapse: collapse;
        }
        .head td,
        .meta td,
        .totals td,
        .payments td {
            vertical-align: top;
            padding: 4px 0;
        }
        .title {
            font-size: 30px;
            font-weight: 700;
            letter-spacing: 0.01em;
        }
        .subtitle {
            color: var(--surface-500);
            font-size: 13px;
        }
        .section {
            margin-top: 28px;
        }
        .section-title {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--surface-500);
            margin-bottom: 12px;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        table.items th,
        table.items td {
            border: 1px solid var(--surface-200);
            padding: 12px 14px;
            text-align: left;
        }
        table.items th {
            background: var(--surface-50);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--surface-700);
        }
        .align-right {
            text-align: right;
        }
        .totals-wrap {
            margin-left: auto;
            width: 360px;
        }
        .totals td:last-child,
        .payments td:last-child {
            text-align: right;
            font-weight: 600;
        }
        .totals tr td,
        .payments tr td {
            padding: 8px 0;
            border-bottom: 1px solid var(--surface-100);
        }
        .grand {
            font-size: 18px;
            font-weight: 700;
        }
        .badge {
            display: inline-block;
            padding: 6px 10px;
            border: 1px solid var(--surface-200);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.06em;
            background: var(--surface-50);
        }
        .badge-valid {
            border-color: #bbf7d0;
            background: var(--success-100);
            color: var(--success-700);
        }
        .badge-void {
            border-color: #fecaca;
            background: var(--danger-100);
            color: var(--danger-700);
        }
        .brand-strip {
            height: 4px;
            background: linear-gradient(90deg, #ca8a04 0%, #facc15 45%, #fef3c7 100%);
            margin: -32px -32px 24px;
        }
        .panel {
            border: 1px solid var(--surface-200);
            background: var(--surface-50);
            padding: 16px 18px;
        }
        .muted {
            color: var(--surface-500);
        }
        .accent {
            color: var(--primary-500);
        }
        .toolbar {
            max-width: 900px;
            margin: 0 auto 12px;
            display: flex;
            justify-content: flex-end;
        }
        .toolbar button {
            border: 1px solid var(--surface-900);
            background: var(--surface-900);
            color: white;
            padding: 10px 16px;
            cursor: pointer;
            font-family: 'Poppins', Arial, sans-serif;
            font-weight: 600;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .page {
                border: 0;
                padding: 0;
                max-width: none;
            }
            .toolbar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Print Invoice</button>
    </div>

    <div class="page">
        <div class="brand-strip"></div>
        <table class="head">
            <tr>
                <td>
                    <div class="title">JewelFlow</div>
                    <div class="subtitle">Jewellery Billing Invoice</div>
                </td>
                <td class="align-right">
                    <div class="badge {{ $invoice->status === 'CANCELLED' ? 'badge-void' : 'badge-valid' }}">{{ $invoice->status === 'CANCELLED' ? 'VOIDED' : 'VALID' }}</div>
                </td>
            </tr>
        </table>

        <div class="section">
            <table class="meta">
                <tr>
                    <td style="width: 50%; padding-right: 12px;">
                        <div class="panel">
                        <div class="section-title">Customer</div>
                        <div>{{ $invoice->customer?->name ?? 'Walk-in Customer' }}</div>
                        @if($invoice->customer?->mobile)
                            <div class="subtitle">{{ $invoice->customer->mobile }}</div>
                        @endif
                        @if($invoice->customer?->city)
                            <div class="subtitle">{{ $invoice->customer->city }}</div>
                        @endif
                        </div>
                    </td>
                    <td style="width: 50%; padding-left: 12px;">
                        <div class="panel">
                        <div class="section-title">Invoice</div>
                        <div><strong>Bill No:</strong> {{ $invoice->invoice_number }}</div>
                        <div><strong>Date:</strong> {{ \Carbon\Carbon::parse($invoice->date)->format('d M Y') }}</div>
                        <div><strong>Rate Applied:</strong> Rs {{ number_format((float) $invoice->gold_rate_applied, 2) }}/g</div>
                        <div><strong>Created By:</strong> {{ $invoice->user?->name ?? 'System' }}</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Items</div>
            <table class="items">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Description</th>
                        <th>Weight</th>
                        <th>Purity</th>
                        <th>Making</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->items as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item->description }}</td>
                            <td>{{ number_format((float) $item->weight, 3) }} g</td>
                            <td>{{ $item->purity }}</td>
                            <td class="align-right">Rs {{ number_format((float) $item->making_charges, 2) }}</td>
                            <td class="align-right">Rs {{ number_format((float) $item->final_price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="section totals-wrap">
            @php
                $subTotal = (float) $invoice->items->sum('final_price');
            @endphp
            <table class="totals">
                <tr>
                    <td>Sub Total</td>
                    <td>Rs {{ number_format($subTotal, 2) }}</td>
                </tr>
                <tr>
                    <td>
                        Discount
                        @if(($invoice->discount_value ?? 0) > 0)
                            <span class="muted">
                                ({{ $invoice->discount_type === 'percentage' ? number_format((float) $invoice->discount_value, 2) . '%' : 'manual' }})
                            </span>
                        @endif
                    </td>
                    <td>- Rs {{ number_format((float) ($invoice->discount_amount ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td>GST</td>
                    <td>Rs {{ number_format((float) ($invoice->tax_amount ?? 0), 2) }}</td>
                </tr>
                <tr class="grand">
                    <td>Total Amount</td>
                    <td>Rs {{ number_format((float) $invoice->total_amount, 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="section totals-wrap">
            <div class="section-title">Payment Summary</div>
            <table class="payments">
                <tr>
                    <td>Received</td>
                    <td>Rs {{ number_format((float) $paidAmount, 2) }}</td>
                </tr>
                <tr>
                    <td>Pending</td>
                    <td>Rs {{ number_format((float) $balanceDue, 2) }}</td>
                </tr>
            </table>
        </div>

        @if($invoice->status === 'CANCELLED')
            <div class="section">
                <div class="section-title">Void Details</div>
                <div class="panel">
                    <div><strong>Mode:</strong> <span class="accent">{{ $invoice->cancellation_mode === 'refund' ? 'Refunded' : 'Kept As Advance' }}</span></div>
                    <div style="margin-top: 8px;"><strong>Reason:</strong> {{ $invoice->cancellation_reason }}</div>
                    <div style="margin-top: 8px;"><strong>Cancelled At:</strong> {{ optional($invoice->cancelled_at)?->format('d M Y h:i A') }}</div>
                </div>
            </div>
        @endif
    </div>
</body>
</html>
