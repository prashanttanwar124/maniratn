<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->invoice_number }} - Tax Invoice</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap');

        :root {
            --surface-0: #ffffff;
            --surface-50: #f8fafc;
            --surface-100: #f1f5f9;
            --surface-200: #e2e8f0;
            --surface-300: #cbd5e1;
            --surface-500: #64748b;
            --surface-700: #334155;
            --surface-900: #0f172a;

            --brand-maroon: #5b0d13;
            --brand-gold: #c59b27;
            --danger-100: #fee2e2;
            --danger-700: #b91c1c;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: var(--surface-900);
            background: linear-gradient(180deg, #fffaf0 0%, #f8f3e3 100%);
            font-size: 11.5px;
            line-height: 1.4;
        }

        .page {
            max-width: 800px;
            margin: 15px auto;
            border: 1px dashed var(--brand-gold);
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.08);
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

        /* Gold Luxury Toolbar */
        .toolbar {
            max-width: 800px;
            margin: 0 auto 14px;
            background: linear-gradient(115deg, #fffaf0 0%, #f8f3e3 18%, #ffffff 46%, #ffffff 100%);
            color: #21160a;
            padding: 10px 18px;
            border: 1px solid #d8c38a;
            box-shadow: 0 4px 12px rgba(175, 140, 55, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .toolbar-title {
            font-weight: 700;
            font-size: 12px;
            color: #8e7b4d;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .btn-print {
            border: 1px solid #b58a34;
            background: linear-gradient(145deg, #d7bb6a 0%, #f6e3a8 55%, #b58a34 100%);
            color: #5d4311;
            padding: 7px 18px;
            cursor: pointer;
            font-family: 'Poppins', Arial, sans-serif;
            font-size: 12px;
            font-weight: 700;
            box-shadow: 0 2px 6px rgba(175, 140, 55, 0.15);
            transition: all 0.15s ease-in-out;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-print:hover {
            background: linear-gradient(145deg, #b58a34 0%, #ebd085 55%, #8e6818 100%);
            transform: translateY(-1px);
        }

        .btn-whatsapp {
            border: 1px solid #16a34a;
            background: #16a34a;
            color: #ffffff;
            padding: 7px 14px;
            cursor: pointer;
            font-family: 'Poppins', Arial, sans-serif;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease-in-out;
        }

        .btn-whatsapp:hover {
            background: #15803d;
            transform: translateY(-1px);
        }

        .slider-container {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(216, 195, 138, 0.5);
            padding: 4px 10px;
        }

        .slider-container label {
            font-weight: 600;
            color: #7b6a42;
        }

        .slider-container input[type="range"] {
            width: 90px;
            accent-color: var(--brand-gold);
            cursor: pointer;
        }

        .offset-value {
            font-weight: 700;
            color: var(--brand-gold);
            min-width: 40px;
            text-align: right;
            font-family: 'JetBrains Mono', monospace;
        }

        /* Invoice Header & Meta */
        .invoice-heading-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            border-bottom: 1px solid var(--surface-200);
            padding-bottom: 6px;
        }

        .invoice-heading-label {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--brand-maroon);
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 14px;
        }

        .panel {
            border: 1px solid var(--surface-200);
            border-top: 3px solid var(--brand-maroon);
            background: var(--surface-50);
            padding: 10px 14px;
            font-size: 11px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
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

        .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* Items Table with Maroon Header */
        .items-section {
            margin-top: 12px;
            margin-bottom: 14px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        table.items th,
        table.items td {
            border: 1px solid var(--surface-200);
            padding: 7px 10px;
            text-align: left;
        }

        table.items th {
            background: var(--brand-maroon);
            color: white;
            font-weight: 600;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border: 1px solid var(--brand-maroon);
        }

        table.items tbody tr:nth-child(even) {
            background-color: #fafaf9;
        }

        .barcode-pill {
            display: inline-block;
            font-family: 'JetBrains Mono', monospace;
            font-size: 9.5px;
            font-weight: 600;
            background: #fff8eb;
            color: #78350f;
            padding: 1px 6px;
            border: 1px solid #fde68a;
            letter-spacing: 0.02em;
        }

        .align-right {
            text-align: right;
        }

        .align-center {
            text-align: center;
        }

        /* Bottom Totals Section */
        .bottom-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-top: 14px;
            gap: 20px;
        }

        .totals-wrap {
            width: 300px;
            border: 1px solid var(--surface-200);
            background: var(--surface-50);
            padding: 10px 14px;
            border-top: 2px solid var(--brand-maroon);
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
            color: var(--brand-maroon);
            border-top: 1.5px solid var(--brand-maroon);
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 0.06em;
            background: var(--surface-50);
        }

        .badge-void {
            border: 1px solid #fecaca;
            background: var(--danger-100);
            color: var(--danger-700);
        }

        /* Digital Vault Box */
        .invoice-vault-qr {
            text-align: center;
            background: #ffffff;
            padding: 4px;
            border: 1px solid var(--surface-200);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
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
        $storeName = $settings->store_name ?? 'Maniratn Jewellers';

        // WhatsApp greeting link
        $customerMobile = $invoice->customer?->mobile ? preg_replace('/\D/', '', $invoice->customer->mobile) : '';
        $cleanPhone = str_starts_with($customerMobile, '91') ? $customerMobile : '91' . $customerMobile;
        $waMessage = urlencode("Dear " . ($invoice->customer?->name ?? 'Customer') . ",\n\nThank you for choosing {$storeName}! ✨\n\nYour Tax Invoice *#{$invoice->invoice_number}* for *Rs " . number_format((float) $invoice->total_amount, 2) . "* has been generated.\n\n" . (!empty($vaultUrl) ? "View your Digital Jewellery Vault & Certificate:\n{$vaultUrl}\n\n" : "") . "Warm regards,\n{$storeName}");
        $waLink = "https://wa.me/{$cleanPhone}?text={$waMessage}";
    @endphp

    <!-- Top UX Toolbar -->
    <div class="toolbar">
        <div class="toolbar-group">
            <span class="toolbar-title">Print Controls</span>
            <button class="btn-print" onclick="window.print()">
                Print Invoice <span style="font-size: 10px; opacity: 0.8; font-weight: normal;">(⌘P)</span>
            </button>
            @if ($customerMobile)
                <a href="{{ $waLink }}" target="_blank" class="btn-whatsapp" title="Share invoice link on customer's WhatsApp">
                    WhatsApp Bill
                </a>
            @endif
        </div>

        <div class="toolbar-group">
            <div class="slider-container">
                <label for="offset-slider">Offset:</label>
                <input id="offset-slider" type="range" min="0" max="100" step="1" value="58"
                    oninput="updateTopOffset(this.value)">
                <span id="offset-val-text" class="offset-value">58 mm</span>
            </div>
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
                        <span class="meta-val">{{ $invoice->customer?->name ?? 'Walk-in Customer' }}</span>
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
                        <span class="meta-key">Bill No:</span>
                        <span class="meta-val mono">{{ $invoice->invoice_number }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-key">Date:</span>
                        <span class="meta-val">{{ \Carbon\Carbon::parse($invoice->date)->format('d M Y') }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-key">24K Gold Rate:</span>
                        <span class="meta-val mono">Rs {{ number_format((float) $invoice->gold_rate_applied, 2) }}/g</span>
                    </div>
                    @if ((float) ($invoice->silver_rate_applied ?? 0) > 0)
                        <div class="meta-row">
                            <span class="meta-key">Silver Rate:</span>
                            <span class="meta-val mono">Rs {{ number_format((float) $invoice->silver_rate_applied, 2) }}/g</span>
                        </div>
                    @endif
                    @if ($settings?->gst_number)
                        <div class="meta-row">
                            <span class="meta-key">GSTIN:</span>
                            <span class="meta-val mono">{{ $settings->gst_number }}</span>
                        </div>
                    @endif
                    <div class="meta-row">
                        <span class="meta-key">Billed By:</span>
                        <span class="meta-val">{{ $invoice->user?->name ?? 'System' }}</span>
                    </div>
                </div>
            </div>

            <!-- Items Table with Brand Maroon Header -->
            <div class="items-section">
                <table class="items">
                    <thead>
                        <tr>
                            <th style="width: 32px;" class="align-center">#</th>
                            <th>Description & Tag</th>
                            <th style="width: 85px;" class="align-right">Weight</th>
                            <th style="width: 65px;" class="align-center">Purity</th>
                            <th style="width: 90px;" class="align-right">Making</th>
                            <th style="width: 115px;" class="align-right">Total</th>
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
                                    <div style="font-weight: 600; color: var(--surface-900);">{{ $item->description }}</div>
                                    <div style="font-size: 9.5px; color: var(--surface-500); font-family: 'JetBrains Mono', monospace; margin-top: 1px;">
                                        @if ($barcode)
                                            Tag: <span style="font-weight: 600; color: var(--surface-800);">{{ $barcode }}</span>
                                        @else
                                            Tag: <span style="color: var(--surface-600);">#{{ str_pad($item->id, 4, '0', STR_PAD_LEFT) }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="align-right mono font-semibold">
                                    {{ number_format((float) $item->weight, 3) }} g
                                </td>
                                <td class="align-center font-bold">
                                    {{ $item->purity }}
                                </td>
                                <td class="align-right mono">
                                    @if ($item->making_charge_type === 'flat' || $item->making_charge_type === 'lump_sum')
                                        Rs {{ number_format((float) $item->making_charges, 2) }}
                                    @elseif ($item->making_charge_type === 'per_gram' || (!$item->product_id && $item->making_charge_type !== 'percentage'))
                                        Rs {{ number_format((float) $item->making_charges, 2) }}/g
                                    @else
                                        {{ (float) $item->making_charges }}%
                                    @endif
                                </td>
                                <td class="align-right mono" style="font-weight: 700;">
                                    Rs {{ number_format((float) $item->final_price, 2) }}
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
                                <span style="font-size: 7.5px; font-weight: 700; color: var(--brand-maroon); letter-spacing: 0.05em; text-transform: uppercase; margin-top: 3px; line-height: 1;">
                                    Digital Vault
                                </span>
                            </a>
                        </div>
                    @endif
                    <div style="font-size: 11px; font-style: italic; color: var(--surface-500);">
                        Thank you for shopping with us!
                    </div>
                </div>

                <!-- Right: Summary Card -->
                <div class="totals-wrap">
                    @php
                        $subTotal = (float) $invoice->items->sum('final_price');
                    @endphp
                    <table class="totals-table">
                        <tr>
                            <td class="meta-key">Sub Total:</td>
                            <td class="align-right mono font-semibold">Rs {{ number_format($subTotal, 2) }}</td>
                        </tr>
                        @if ((float) ($invoice->discount_amount ?? 0) > 0)
                            <tr>
                                <td class="meta-key">
                                    Discount:
                                    @if (($invoice->discount_value ?? 0) > 0)
                                        <span style="font-size: 9px; color: var(--surface-500);">
                                            ({{ $invoice->discount_type === 'percentage' ? number_format((float) $invoice->discount_value, 2) . '%' : 'manual' }})
                                        </span>
                                    @endif
                                </td>
                                <td class="align-right mono" style="color: var(--danger-700);">- Rs {{ number_format((float) ($invoice->discount_amount ?? 0), 2) }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="meta-key">GST:</td>
                            <td class="align-right mono">Rs {{ number_format((float) ($invoice->tax_amount ?? 0), 2) }}</td>
                        </tr>
                        <tr class="grand-row">
                            <td>Total Amount:</td>
                            <td class="align-right mono font-bold">Rs {{ number_format((float) $invoice->total_amount, 2) }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Void Details -->
            @if ($invoice->status === 'CANCELLED')
                <div style="margin-top: 14px;">
                    <div class="panel" style="border-top-color: var(--danger-700);">
                        <div class="panel-title" style="color: var(--danger-700);">Void Details</div>
                        <div class="meta-row">
                            <span class="meta-key">Mode:</span>
                            <span class="meta-val" style="color: var(--danger-700);">{{ $invoice->cancellation_mode === 'refund' ? 'Refunded' : ($invoice->cancellation_mode === 'keep_advance' ? 'Kept As Advance' : 'Unpaid Bill (No Payments Collected)') }}</span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-key">Reason:</span>
                            <span class="meta-val">{{ $invoice->cancellation_reason }}</span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-key">Cancelled At:</span>
                            <span class="meta-val mono">{{ optional($invoice->cancelled_at)?->format('d M Y h:i A') }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Signatures Section -->
            <div style="margin-top: 45px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
                <div style="text-align: center; width: 170px;">
                    <div style="border-bottom: 1px solid var(--surface-300); margin-bottom: 5px;"></div>
                    <div style="font-size: 9.5px; font-weight: 600; color: var(--surface-700); text-transform: uppercase; letter-spacing: 0.05em;">
                        Customer's Signature
                    </div>
                </div>

                <div style="text-align: center; width: 170px;">
                    <div style="border-bottom: 1px solid var(--surface-300); margin-bottom: 5px;"></div>
                    <div style="font-size: 9.5px; font-weight: 600; color: var(--surface-700); text-transform: uppercase; letter-spacing: 0.05em;">
                        Authorized Signature
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
                            <div style="font-size: 10.5px; font-weight: 700; color: var(--brand-maroon); letter-spacing: 0.04em;">
                                ⭐ RATE YOUR EXPERIENCE ON GOOGLE
                            </div>
                            <div style="font-size: 9.5px; color: var(--surface-700); margin-top: 1px;">
                                Scan QR code to leave us a 5-star review on Google Maps!
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

        // Keyboard Shortcut: Cmd/Ctrl + P triggers print
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>

</html>
