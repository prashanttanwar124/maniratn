<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $scheme->scheme_number }} Print</title>
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
            --warning-100: #fef3c7;
            --warning-700: #a16207;
            --danger-100: #fee2e2;
            --danger-700: #b91c1c;
        }

        body {
            font-family: 'Poppins', Arial, sans-serif;
            margin: 0;
            padding: 16px;
            color: var(--surface-900);
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        }
        .toolbar {
            max-width: 960px;
            margin: 0 auto 8px;
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
        .page {
            max-width: 960px;
            margin: 0 auto;
            background: var(--surface-0);
            border: 1px solid var(--surface-200);
            padding: 22px;
        }
        .brand-strip {
            height: 4px;
            background: linear-gradient(90deg, #ca8a04 0%, #facc15 45%, #fef3c7 100%);
            margin: -22px -22px 18px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
        }
        .title {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.01em;
        }
        .subtitle {
            color: var(--surface-500);
            font-size: 12px;
        }
        .badge {
            display: inline-block;
            padding: 5px 9px;
            border: 1px solid var(--surface-200);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            background: var(--surface-50);
        }
        .badge-active {
            border-color: #fde68a;
            background: var(--warning-100);
            color: var(--warning-700);
        }
        .badge-matured {
            border-color: #bbf7d0;
            background: var(--success-100);
            color: var(--success-700);
        }
        .badge-cancelled {
            border-color: #fecaca;
            background: var(--danger-100);
            color: var(--danger-700);
        }
        .section {
            margin-top: 16px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--surface-500);
            margin-bottom: 8px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .panel {
            border: 1px solid var(--surface-200);
            background: var(--surface-50);
            padding: 12px 14px;
        }
        .panel p {
            margin: 0;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 4px 0;
            border-bottom: 1px solid var(--surface-100);
            font-size: 12px;
        }
        .meta-row:last-child {
            border-bottom: 0;
        }
        .meta-label {
            color: var(--surface-500);
        }
        .meta-value {
            font-weight: 600;
            text-align: right;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }
        .summary-card {
            border: 1px solid var(--surface-200);
            background: white;
            padding: 10px 12px;
        }
        .summary-card p {
            margin: 0;
        }
        .summary-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--surface-500);
        }
        .summary-value {
            margin-top: 6px;
            font-size: 16px;
            font-weight: 700;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            table-layout: fixed;
        }
        table.items th,
        table.items td {
            border: 1px solid var(--surface-200);
            padding: 7px 8px;
            text-align: left;
            vertical-align: top;
        }
        table.items th {
            background: var(--surface-50);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--surface-700);
        }
        .align-right {
            text-align: right;
        }
        .muted {
            color: var(--surface-500);
        }
        .foot-note {
            margin-top: 12px;
            padding: 10px 12px;
            border: 1px solid var(--surface-200);
            background: var(--surface-50);
            font-size: 11px;
            line-height: 1.5;
            color: var(--surface-700);
        }
        table.items th:nth-child(1),
        table.items td:nth-child(1) { width: 8%; }
        table.items th:nth-child(2),
        table.items td:nth-child(2) { width: 18%; }
        table.items th:nth-child(3),
        table.items td:nth-child(3) { width: 14%; }
        table.items th:nth-child(4),
        table.items td:nth-child(4) { width: 18%; }
        table.items th:nth-child(5),
        table.items td:nth-child(5) { width: 16%; }
        table.items th:nth-child(6),
        table.items td:nth-child(6) { width: 18%; }
        @page {
            size: A4;
            margin: 10mm;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .toolbar {
                display: none;
            }
            .page {
                border: 0;
                padding: 0;
                max-width: none;
            }
            .section,
            .panel,
            .summary-card,
            table.items tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Print Scheme</button>
    </div>

    <div class="page">
        <div class="brand-strip"></div>

        <div class="header">
            <div>
                <div class="title">JewelFlow</div>
                <div class="subtitle">Customer Gold Scheme Confirmation</div>
            </div>
            <div class="badge {{ $scheme->status === 'MATURED' ? 'badge-matured' : ($scheme->status === 'CANCELLED' ? 'badge-cancelled' : 'badge-active') }}">
                {{ $scheme->status }}
            </div>
        </div>

        <div class="section">
            <div class="grid">
                <div class="panel">
                    <div class="section-title">Customer</div>
                    <div class="meta-row">
                        <span class="meta-label">Name</span>
                        <span class="meta-value">{{ $scheme->customer?->name ?? '—' }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Mobile</span>
                        <span class="meta-value">{{ $scheme->customer?->mobile ?? '—' }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Scheme No</span>
                        <span class="meta-value">{{ $scheme->scheme_number }}</span>
                    </div>
                </div>

                <div class="panel">
                    <div class="section-title">Scheme Terms</div>
                    <div class="meta-row">
                        <span class="meta-label">Start Date</span>
                        <span class="meta-value">{{ optional($scheme->start_date)->format('d M Y') ?? '—' }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Maturity Date</span>
                        <span class="meta-value">{{ optional($scheme->maturity_date)->format('d M Y') ?? '—' }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Monthly Amount</span>
                        <span class="meta-value">Rs {{ number_format((float) $scheme->monthly_amount, 2) }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Months</span>
                        <span class="meta-value">{{ (int) $scheme->total_months }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Scheme Summary</div>
            <div class="summary">
                <div class="summary-card">
                    <p class="summary-label">Customer Contribution</p>
                    <p class="summary-value">Rs {{ number_format((float) $scheme->expected_customer_total, 2) }}</p>
                </div>
                <div class="summary-card">
                    <p class="summary-label">Store Bonus</p>
                    <p class="summary-value">Rs {{ number_format((float) $scheme->bonus_amount, 2) }}</p>
                </div>
                <div class="summary-card">
                    <p class="summary-label">Paid So Far</p>
                    <p class="summary-value">Rs {{ number_format((float) $scheme->paid_total, 2) }}</p>
                </div>
                <div class="summary-card">
                    <p class="summary-label">Redeemable Value</p>
                    <p class="summary-value">Rs {{ number_format((float) $scheme->redeemable_total, 2) }}</p>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Installment Progress</div>
            <table class="items">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Paid On</th>
                        <th>Payment</th>
                        <th class="align-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($scheme->installments as $installment)
                        <tr>
                            <td>{{ $installment->installment_no }}</td>
                            <td>{{ optional($installment->due_date)->format('d M Y') ?? '—' }}</td>
                            <td>{{ $installment->status }}</td>
                            <td>{{ optional($installment->paid_on)->format('d M Y') ?? '—' }}</td>
                            <td>{{ $installment->payment_method ?? '—' }}</td>
                            <td class="align-right">Rs {{ number_format((float) ($installment->amount_paid ?: $installment->amount_due), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="grid">
                <div class="panel">
                    <div class="section-title">Collection Snapshot</div>
                    <div class="meta-row">
                        <span class="meta-label">Paid Months</span>
                        <span class="meta-value">{{ $paidInstallments->count() }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Pending Months</span>
                        <span class="meta-value">{{ $pendingInstallments->count() }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Next Due</span>
                        <span class="meta-value">
                            @if($pendingInstallments->isNotEmpty())
                                Month {{ $pendingInstallments->first()->installment_no }} on {{ optional($pendingInstallments->first()->due_date)->format('d M Y') }}
                            @else
                                All months cleared
                            @endif
                        </span>
                    </div>
                </div>

                <div class="panel">
                    <div class="section-title">Notes</div>
                    <p>{{ $scheme->notes ?: 'No extra notes recorded for this scheme.' }}</p>
                    <div class="foot-note">
                        This document confirms the current terms and installment position of the customer gold scheme. Please keep it for your records and refer to the scheme number for future collections or maturity settlement.
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
