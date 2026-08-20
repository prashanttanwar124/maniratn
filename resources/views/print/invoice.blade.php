<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->invoice_number }} - Tax Invoice</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap');

        :root {
            --surface-0: #ffffff;
            --surface-50: #f8fafc;
            --surface-100: #f1f5f9;
            --surface-200: #e2e8f0;
            --surface-300: #cbd5e1;
            --surface-500: #64748b;
            --surface-700: #334155;
            --surface-900: #0f172a;

            --brand-gold: #c4922a;
            --brand-dark: #0f172a;
            --brand-accent: #854d0e;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Instrument Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            color: var(--surface-900);
            background: #f1f5f9;
            font-size: 11.5px;
            line-height: 1.45;
        }

        .page {
            max-width: 820px;
            margin: 10px auto;
            border: 1px solid var(--surface-300);
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08);
            background: var(--surface-0);
            padding: 0;
            position: relative;
        }

        .invoice-body {
            padding-top: var(--preprinted-top-offset, 58mm);
            padding-bottom: 30mm;
            padding-left: 18mm;
            padding-right: 18mm;
            box-sizing: border-box;
        }

        /* Top Toolbar */
        .toolbar {
            max-width: 820px;
            margin: 0 auto 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            color: var(--surface-900);
            padding: 10px 18px;
            border: 1px solid var(--surface-200);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        }

        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toolbar-title {
            font-weight: 700;
            font-size: 12px;
            color: var(--surface-700);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .toolbar button {
            border: 1px solid var(--surface-900);
            background: var(--surface-900);
            color: #ffffff;
            padding: 7px 16px;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.15s ease-in-out;
        }

        .toolbar button:hover {
            background: #1e293b;
            transform: translateY(-1px);
        }

        .slider-container {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            background: var(--surface-50);
            border: 1px solid var(--surface-200);
            padding: 5px 10px;
        }

        .slider-container label {
            font-weight: 600;
            color: var(--surface-700);
        }

        .slider-container input[type="range"] {
            width: 100px;
            accent-color: var(--surface-900);
            cursor: pointer;
        }

        .offset-value {
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            color: var(--surface-900);
            min-width: 40px;
            text-align: right;
        }

        /* Invoice Layout Elements */
        .head,
        .meta,
        .totals {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-heading-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid var(--surface-900);
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .invoice-heading-label {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--surface-900);
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }

        .panel {
            border: 1px solid var(--surface-200);
            background: var(--surface-50);
            padding: 10px 14px;
            font-size: 11px;
        }

        .panel-title {
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--surface-500);
            margin-bottom: 6px;
            border-bottom: 1px solid var(--surface-200);
            padding-bottom: 4px;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }

        .meta-row:last-child {
            margin-bottom: 0;
        }

        .meta-key {
            color: var(--surface-500);
            font-weight: 500;
        }

        .meta-val {
            font-weight: 600;
            color: var(--surface-900);
            text-align: right;
        }

        /* Items Table */
        .items-section {
            margin-top: 14px;
            margin-bottom: 16px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            border: 1px solid var(--surface-300);
        }

        table.items th {
            background: var(--surface-900);
            color: #ffffff;
            font-weight: 600;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 7px 10px;
            border: 1px solid var(--surface-900);
        }

        table.items td {
            border: 1px solid var(--surface-200);
            padding: 7px 10px;
            vertical-align: middle;
            color: var(--surface-900);
        }

        table.items tbody tr:nth-child(even) {
            background-color: var(--surface-50);
        }

        .barcode-pill {
            display: inline-block;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            font-weight: 600;
            background: #f1f5f9;
            color: #0f172a;
            padding: 1px 6px;
            border: 1px solid #cbd5e1;
            letter-spacing: 0.02em;
        }

        .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        .align-right {
            text-align: right;
        }

        .align-center {
            text-align: center;
        }

        /* Totals */
        .bottom-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-top: 14px;
            gap: 20px;
        }

        .totals-card {
            width: 290px;
            border: 1px solid var(--surface-200);
            background: var(--surface-50);
            padding: 10px 14px;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .totals-table td {
            padding: 4px 0;
            border-bottom: 1px solid var(--surface-200);
        }

        .totals-table tr:last-child td {
            border-bottom: none;
        }

        .totals-table .grand-row td {
            padding-top: 6px;
            font-size: 13px;
            font-weight: 700;
            color: var(--surface-900);
            border-top: 1.5px solid var(--surface-900);
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.06em;
        }

        .badge-void {
            border: 1px solid #fecaca;
            background: #fee2e2;
            color: #b91c1c;
        }

        /* Digital Vault Box */
        .invoice-vault-qr {
            text-align: center;
            background: #ffffff;
            padding: 5px;
            border: 1px solid var(--surface-200);
            display: inline-block;
        }

        /* Google Review Banner */
        .google-review-banner {
            border: 1px solid var(--surface-200);
            border-left: 3px solid var(--brand-gold);
            background: #ffffff;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 14px;
        }

        .google-review-qr-box {
            background: #ffffff;
            padding: 2px;
            border: 1px solid var(--surface-200);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .google-review-qr-box svg,
        .google-review-qr-box img {
            width: 38px;
            height: 38px;
            max-width: 38px;
            max-height: 38px;
            display: block;
        }

        /* Print Media Styles */
        @media print {
            @page {
                margin: 0;
                size: A4;
            }

            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .toolbar {
                display: none !important;
            }

            .page {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                width: auto !important;
                height: auto !important;
            }

            .invoice-body {
                padding-top: var(--preprinted-top-offset, 58mm) !important;
                padding-bottom: 30mm !important;
                padding-left: 18mm !important;
                padding-right: 18mm !important;
            }
        }
    </style>
</head>

<body>
    @php
        $settings = \App\Models\BusinessSetting::first();
    @endphp

    <div class="toolbar">
        <div class="toolbar-group">
            <span class="toolbar-title">Print Header Offset</span>
            <div id="slider-container" class="slider-container">
                <label for="offset-slider">Offset:</label>
                <input id="offset-slider" type="range" min="20" max="100" step="1" value="58"
                    oninput="updateTopOffset(this.value)">
                <span id="offset-val-text" class="offset-value">58 mm</span>
            </div>
        </div>

        <div class="toolbar-group">
            <button onclick="window.print()">
                <i class="pi pi-print"></i> Print Tax Invoice
            </button>
        </div>
    </div>

    <div class="page">
        <div class="invoice-body">
            <!-- Header Status -->
            <div class="invoice-heading-row">
                <div class="invoice-heading-label">Retail Tax Invoice</div>
                <div>
                    @if ($invoice->status === 'CANCELLED')
                        <span class="badge badge-void">VOIDED / CANCELLED</span>
                    @else
                        <span style="font-size: 10px; font-weight: 700; color: var(--surface-500); letter-spacing: 0.05em; text-transform: uppercase;">Original Bill</span>
                    @endif
                </div>
            </div>

            <!-- Customer & Invoice Meta Grid -->
            <div class="meta-grid">
                <!-- Customer Details -->
                <div class="panel">
                    <div class="panel-title">Customer Information</div>
                    <div class="meta-row">
                        <span class="meta-key">Customer:</span>
                        <span class="meta-val">{{ $invoice->customer?->name ?? 'Walk-in Retail Client' }}</span>
                    </div>
                    @if ($invoice->customer?->mobile)
                        <div class="meta-row">
                            <span class="meta-key">Mobile:</span>
                            <span class="meta-val mono">{{ $invoice->customer->mobile }}</span>
                        </div>
                    @endif
                    @if ($invoice->customer?->city || $invoice->customer?->address)
                        <div class="meta-row">
                            <span class="meta-key">Location:</span>
                            <span class="meta-val">{{ $invoice->customer->city ?: $invoice->customer->address }}</span>
                        </div>
                    @endif
                    @if ($invoice->customer?->pan_no)
                        <div class="meta-row">
                            <span class="meta-key">PAN:</span>
                            <span class="meta-val mono">{{ $invoice->customer->pan_no }}</span>
                        </div>
                    @endif
                </div>

                <!-- Invoice Details -->
                <div class="panel">
                    <div class="panel-title">Invoice Details</div>
                    <div class="meta-row">
                        <span class="meta-key">Invoice #:</span>
                        <span class="meta-val mono">{{ $invoice->invoice_number }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-key">Invoice Date:</span>
                        <span class="meta-val">{{ \Carbon\Carbon::parse($invoice->date)->format('d M Y') }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-key">24K Gold Rate:</span>
                        <span class="meta-val mono">₹{{ number_format((float) $invoice->gold_rate_applied, 2) }}/g</span>
                    </div>
                    @if ((float) ($invoice->silver_rate_applied ?? 0) > 0)
                        <div class="meta-row">
                            <span class="meta-key">Silver Rate:</span>
                            <span class="meta-val mono">₹{{ number_format((float) $invoice->silver_rate_applied, 2) }}/g</span>
                        </div>
                    @endif
                    @if ($settings?->gst_number)
                        <div class="meta-row">
                            <span class="meta-key">GSTIN:</span>
                            <span class="meta-val mono">{{ $settings->gst_number }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Items Table with Dedicated Barcode Column -->
            <div class="items-section">
                <table class="items">
                    <thead>
                        <tr>
                            <th style="width: 32px;" class="align-center">#</th>
                            <th style="width: 100px;">Barcode / Tag</th>
                            <th>Description</th>
                            <th style="width: 75px;" class="align-right">Weight</th>
                            <th style="width: 60px;" class="align-center">Purity</th>
                            <th style="width: 85px;" class="align-right">Making</th>
                            <th style="width: 105px;" class="align-right">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->items as $index => $item)
                            @php
                                $barcode = $item->product?->barcode ?? $item->silverProduct?->barcode ?? $item->barcode ?? $item->tag_number ?? null;
                            @endphp
                            <tr>
                                <td class="align-center mono" style="color: var(--surface-500);">{{ $index + 1 }}</td>
                                <td>
                                    @if ($barcode)
                                        <span class="barcode-pill">{{ $barcode }}</span>
                                    @else
                                        <span class="barcode-pill" style="color: var(--surface-500); background: #f8fafc; border-color: #e2e8f0;">TAG-{{ str_pad($item->id, 4, '0', STR_PAD_LEFT) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $item->description }}</strong>
                                </td>
                                <td class="align-right mono font-semibold">
                                    {{ number_format((float) $item->weight, 3) }} g
                                </td>
                                <td class="align-center font-bold">
                                    {{ $item->purity }}
                                </td>
                                <td class="align-right mono">
                                    @if ($item->making_charge_type === 'flat' || $item->making_charge_type === 'lump_sum')
                                        ₹{{ number_format((float) $item->making_charges, 2) }}
                                    @elseif ($item->making_charge_type === 'per_gram' || (!$item->product_id && $item->making_charge_type !== 'percentage'))
                                        ₹{{ number_format((float) $item->making_charges, 2) }}/g
                                    @else
                                        {{ (float) $item->making_charges }}%
                                    @endif
                                </td>
                                <td class="align-right mono" style="font-weight: 700;">
                                    ₹{{ number_format((float) $item->final_price, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Bottom Section: Vault QR & Totals -->
            <div class="bottom-section">
                <!-- Left: Vault QR & Greetings -->
                <div style="display: flex; flex-direction: column; align-items: flex-start; gap: 8px;">
                    @if (!empty($qrSvg) || !empty($qrCodeBase64))
                        <div class="invoice-vault-qr">
                            <a href="{{ $vaultUrl }}" target="_blank" title="Scan or click to view Customer Digital Vault"
                                style="display: inline-flex; flex-direction: column; align-items: center; text-decoration: none; color: inherit;">
                                <div style="display: block; line-height: 0;">
                                    @if (!empty($qrSvg))
                                        {!! $qrSvg !!}
                                    @else
                                        <img src="{{ $qrCodeBase64 }}" alt="Customer Digital Vault QR Code"
                                            style="width: 55px; height: 55px; display: block;" />
                                    @endif
                                </div>
                                <span style="font-size: 7.5px; font-weight: 700; color: var(--surface-900); letter-spacing: 0.05em; text-transform: uppercase; margin-top: 3px; line-height: 1;">
                                    Customer Vault
                                </span>
                            </a>
                        </div>
                    @endif
                    <div style="font-size: 10.5px; color: var(--surface-500);">
                        Thank you for choosing us for your jewellery purchase!
                    </div>
                </div>

                <!-- Right: Summary Card -->
                <div class="totals-card">
                    @php
                        $subTotal = (float) $invoice->items->sum('final_price');
                    @endphp
                    <table class="totals-table">
                        <tr>
                            <td class="meta-key">Item Subtotal:</td>
                            <td class="align-right mono font-semibold">₹{{ number_format($subTotal, 2) }}</td>
                        </tr>
                        @if ((float) ($invoice->discount_amount ?? 0) > 0)
                            <tr>
                                <td class="meta-key">
                                    Discount:
                                    @if (($invoice->discount_value ?? 0) > 0)
                                        <span style="font-size: 9px; color: var(--surface-500);">
                                            ({{ $invoice->discount_type === 'percentage' ? number_format((float) $invoice->discount_value, 2) . '%' : 'flat' }})
                                        </span>
                                    @endif
                                </td>
                                <td class="align-right mono" style="color: #b91c1c;">- ₹{{ number_format((float) ($invoice->discount_amount ?? 0), 2) }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="meta-key">GST Tax:</td>
                            <td class="align-right mono">₹{{ number_format((float) ($invoice->tax_amount ?? 0), 2) }}</td>
                        </tr>
                        <tr class="grand-row">
                            <td>Grand Total:</td>
                            <td class="align-right mono font-bold">₹{{ number_format((float) $invoice->total_amount, 2) }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Void Cancellation Details if any -->
            @if ($invoice->status === 'CANCELLED')
                <div style="margin-top: 14px;">
                    <div class="panel" style="border-left: 3px solid #b91c1c;">
                        <div class="panel-title" style="color: #b91c1c;">Invoice Cancellation Record</div>
                        <div class="meta-row">
                            <span class="meta-key">Mode:</span>
                            <span class="meta-val" style="color: #b91c1c;">{{ $invoice->cancellation_mode === 'refund' ? 'Refunded' : ($invoice->cancellation_mode === 'keep_advance' ? 'Kept As Advance' : 'Unpaid Bill') }}</span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-key">Reason:</span>
                            <span class="meta-val">{{ $invoice->cancellation_reason }}</span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-key">Cancelled Date:</span>
                            <span class="meta-val mono">{{ optional($invoice->cancelled_at)?->format('d M Y h:i A') }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Signatures Section -->
            <div style="margin-top: 45px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
                <div style="text-align: center; width: 170px;">
                    <div style="border-bottom: 1px solid var(--surface-300); margin-bottom: 5px;"></div>
                    <div style="font-size: 9.5px; font-weight: 600; color: var(--surface-500); text-transform: uppercase; letter-spacing: 0.05em;">
                        Customer Signature
                    </div>
                </div>

                <div style="text-align: center; width: 170px;">
                    <div style="border-bottom: 1px solid var(--surface-300); margin-bottom: 5px;"></div>
                    <div style="font-size: 9.5px; font-weight: 600; color: var(--surface-500); text-transform: uppercase; letter-spacing: 0.05em;">
                        Authorized Signatory
                    </div>
                </div>
            </div>

            <!-- Rate Us On Google Banner -->
            @if (!empty($googleReviewUrl) && (!empty($googleReviewQrBase64) || !empty($googleReviewQrSvg)))
                <div class="google-review-banner">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="google-review-qr-box">
                            @if (!empty($googleReviewQrBase64))
                                <img src="{{ $googleReviewQrBase64 }}" alt="Google Review QR" style="width: 38px; height: 38px; display: block;" />
                            @elseif (!empty($googleReviewQrSvg))
                                <div style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                    {!! $googleReviewQrSvg !!}
                                </div>
                            @endif
                        </div>
                        <div>
                            <div style="font-size: 10.5px; font-weight: 700; color: var(--surface-900); letter-spacing: 0.03em;">
                                RATE US ON GOOGLE
                            </div>
                            <div style="font-size: 9px; color: var(--surface-500); margin-top: 1px;">
                                Scan QR code to share your feedback on Google Maps!
                            </div>
                        </div>
                    </div>
                    <div style="font-size: 13px; letter-spacing: 2px; color: #f59e0b; font-weight: bold; padding-right: 4px;">
                        ★★★★★
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        function updateTopOffset(val) {
            document.documentElement.style.setProperty('--preprinted-top-offset', val + 'mm');
            document.getElementById('offset-val-text').innerText = val + ' mm';
            localStorage.setItem('karatsetu_print_top_offset', val);
        }

        // Initialize state from local storage on load
        document.addEventListener('DOMContentLoaded', () => {
            const savedOffset = localStorage.getItem('karatsetu_print_top_offset') || localStorage.getItem('maniratn_print_top_offset') || '58';
            document.getElementById('offset-slider').value = savedOffset;
            updateTopOffset(savedOffset);
        });
    </script>
</body>

</html>
