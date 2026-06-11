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
            --surface-500: #64748b;
            --surface-700: #334155;
            --surface-900: #0f172a;
            --primary-500: #ca8a04;
            --danger-100: #fee2e2;
            --danger-700: #b91c1c;
            
            --brand-maroon: #5b0d13;
            --brand-gold: #c59b27;
        }

        body {
            font-family: 'Poppins', Arial, sans-serif;
            margin: 0;
            padding: 24px;
            color: var(--surface-900);
            background: linear-gradient(180deg, #fffaf0 0%, #f8f3e3 100%);
            transition: background 0.15s ease-in-out;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .page {
            max-width: 800px;
            margin: 20px auto;
            border: 1px dashed var(--primary-500);
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            background: var(--surface-0);
            padding: 0;
            transition: all 0.15s ease-in-out;
            position: relative;
        }

        .invoice-body {
            padding-top: var(--preprinted-top-offset, 58mm);
            padding-bottom: 35mm;
            padding-left: 20mm;
            padding-right: 20mm;
            box-sizing: border-box;
        }

        .head,
        .meta,
        .totals {
            width: 100%;
            border-collapse: collapse;
            position: relative;
            z-index: 1;
        }
        .head td,
        .meta td,
        .totals td {
            vertical-align: top;
            padding: 4px 0;
        }
        .invoice-heading-label {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--brand-maroon);
        }

        .section {
            margin-top: 20px;
            position: relative;
            z-index: 1;
        }
        .section-title {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--surface-500);
            margin-bottom: 8px;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        table.items th,
        table.items td {
            border: 1px solid var(--surface-200);
            padding: 8px 12px;
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
        .align-right {
            text-align: right;
        }
        .totals-wrap {
            margin-left: auto;
            width: 300px;
        }
        .totals td:last-child {
            text-align: right;
            font-weight: 600;
        }
        .totals tr td {
            padding: 6px 0;
            border-bottom: 1px solid var(--surface-100);
        }
        .grand {
            font-size: 13px;
            font-weight: 700;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border: 1px solid var(--surface-200);
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 0.06em;
            background: var(--surface-50);
        }
        .badge-void {
            border-color: #fecaca;
            background: var(--danger-100);
            color: var(--danger-700);
        }
        .panel {
            border: 1px solid var(--surface-200);
            border-top: 3px solid var(--brand-maroon);
            background: var(--surface-50);
            padding: 14px 18px;
            font-size: 11.5px;
            transition: all 0.15s ease-in-out;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }
        .muted {
            color: var(--surface-500);
        }
        .accent {
            color: var(--brand-maroon);
            font-weight: 600;
        }

        /* Modern Toolbar Styling (Sakai Theme) */
        .toolbar {
            max-width: 800px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(115deg, #fffaf0 0%, #f8f3e3 18%, #ffffff 46%, #ffffff 100%);
            color: #21160a;
            padding: 12px 20px;
            border-radius: 6px;
            box-shadow: 0 8px 24px rgba(175, 140, 55, 0.06);
            border: 1px solid #d8c38a;
        }
        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .toolbar-title {
            font-weight: 700;
            font-size: 13px;
            color: #8e7b4d;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-right: 4px;
        }
        .toolbar button {
            border: 1px solid #b58a34;
            background: linear-gradient(145deg, #d7bb6a 0%, #f6e3a8 55%, #b58a34 100%);
            color: #5d4311;
            padding: 8px 16px;
            cursor: pointer;
            font-family: 'Poppins', Arial, sans-serif;
            font-size: 13px;
            font-weight: 700;
            border-radius: 4px;
            box-shadow: 0 4px 10px rgba(175, 140, 55, 0.12);
            transition: all 0.15s ease-in-out;
        }
        .toolbar button:hover {
            background: linear-gradient(145deg, #b58a34 0%, #ebd085 55%, #8e6818 100%);
            transform: translateY(-1px);
        }
        .slider-container {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(216, 195, 138, 0.4);
            padding: 6px 12px;
            border-radius: 4px;
            backdrop-filter: blur(8px);
        }
        .slider-container label {
            font-weight: 600;
            color: #7b6a42;
        }
        .slider-container input[type="range"] {
            width: 110px;
            accent-color: var(--brand-gold);
            cursor: pointer;
        }
        .offset-value {
            font-weight: 700;
            color: var(--brand-gold);
            min-width: 45px;
            text-align: right;
        }

        /* Print Specific Media Styles */
        @media print {
            @page {
                margin: 0;
                size: A4;
            }
            body {
                background: white;
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
                padding-bottom: 35mm !important;
                padding-left: 20mm !important;
                padding-right: 20mm !important;
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
            <span class="toolbar-title">Print Alignment</span>
            <div id="slider-container" class="slider-container">
                <label for="offset-slider">Top Offset:</label>
                <input id="offset-slider" type="range" min="30" max="100" step="1" value="58" oninput="updateTopOffset(this.value)">
                <span id="offset-val-text" class="offset-value">58 mm</span>
            </div>
        </div>
        
        <div class="toolbar-group">
            <button onclick="window.print()">Print Invoice</button>
        </div>
    </div>

    <div class="page">
        <div class="invoice-body">
            <table class="head">
                <tr>
                    <td>
                        <div class="invoice-heading-label">Retail Invoice</div>
                    </td>
                    <td class="align-right">
                        @if($invoice->status === 'CANCELLED')
                            <div class="badge badge-void">VOIDED</div>
                        @endif
                    </td>
                </tr>
            </table>

            <div class="section">
                <table class="meta">
                    <tr>
                        <td style="width: 50%; padding-right: 12px;">
                            <div class="panel">
                            <div class="section-title">Customer</div>
                            <div><strong>Name:</strong> {{ $invoice->customer?->name ?? 'Walk-in Customer' }}</div>
                            @if($invoice->customer?->mobile)
                                <div style="margin-top: 2px;"><strong>Mobile:</strong> {{ $invoice->customer->mobile }}</div>
                            @endif
                            @if($invoice->customer?->address)
                                <div style="margin-top: 2px;"><strong>Address:</strong> {{ $invoice->customer->address }}</div>
                            @endif
                            </div>
                        </td>
                        <td style="width: 50%; padding-left: 12px;">
                            <div class="panel">
                            <div class="section-title">Invoice</div>
                            <div><strong>Bill No:</strong> {{ $invoice->invoice_number }}</div>
                            <div><strong>Date:</strong> {{ \Carbon\Carbon::parse($invoice->date)->format('d M Y') }}</div>
                            <div><strong>Gold Rate:</strong> Rs {{ number_format((float) $invoice->gold_rate_applied, 2) }}/g</div>
                            <div style="margin-top: 2px;"><strong>Silver Rate:</strong> Rs {{ number_format((float) ($invoice->silver_rate_applied ?? 0), 2) }}/g</div>
                            @if($settings?->gst_number)
                                <div style="margin-top: 2px;"><strong>GSTIN:</strong> {{ $settings->gst_number }}</div>
                            @endif
                            <div style="margin-top: 2px;"><strong>Created By:</strong> {{ $invoice->user?->name ?? 'System' }}</div>
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
                                <td class="align-right">
                                    @if($item->product_id)
                                        {{ (float) $item->making_charges }}%
                                    @else
                                        Rs {{ number_format((float) $item->making_charges, 2) }}
                                    @endif
                                </td>
                                <td class="align-right">Rs {{ number_format((float) $item->final_price, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="section" style="display: flex; justify-content: space-between; align-items: flex-end; position: relative; z-index: 1;">
                <div style="font-size: 11px; font-style: italic; color: var(--surface-500); padding-bottom: 8px;">
                    Thank you for shopping with us!
                </div>
                <div class="totals-wrap" style="margin: 0; width: 300px;">
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
                                    <span class="muted" style="font-size: 9.5px;">
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
            </div>

            @if($invoice->status === 'CANCELLED')
                <div class="section">
                    <div class="section-title">Void Details</div>
                    <div class="panel">
                        <div><strong>Mode:</strong> <span class="accent">{{ $invoice->cancellation_mode === 'refund' ? 'Refunded' : 'Kept As Advance' }}</span></div>
                        <div><strong>Reason:</strong> {{ $invoice->cancellation_reason }}</div>
                        <div><strong>Cancelled At:</strong> {{ optional($invoice->cancelled_at)?->format('d M Y h:i A') }}</div>
                    </div>
                </div>
            @endif

            <div class="section" style="margin-top: 90px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-end; position: relative; z-index: 1;">
                <div style="text-align: center; width: 180px;">
                    <div style="border-bottom: 1px solid var(--surface-400); margin-bottom: 6px;"></div>
                    <div style="font-size: 10px; font-weight: 600; color: var(--surface-700); text-transform: uppercase; letter-spacing: 0.05em;">Customer's Signature</div>
                </div>
                
                <div style="text-align: center; width: 180px;">
                    <div style="border-bottom: 1px solid var(--surface-400); margin-bottom: 6px;"></div>
                    <div style="font-size: 10px; font-weight: 600; color: var(--surface-700); text-transform: uppercase; letter-spacing: 0.05em;">Authorized Signature</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateTopOffset(val) {
            document.documentElement.style.setProperty('--preprinted-top-offset', val + 'mm');
            document.getElementById('offset-val-text').innerText = val + ' mm';
            localStorage.setItem('maniratn_print_top_offset', val);
        }

        // Initialize state from local storage on load
        document.addEventListener('DOMContentLoaded', () => {
            const savedOffset = localStorage.getItem('maniratn_print_top_offset') || '58';
            document.getElementById('offset-slider').value = savedOffset;
            updateTopOffset(savedOffset);
        });
    </script>
</body>
</html>
