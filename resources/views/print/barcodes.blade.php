<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Print Jewellery Tags</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

        :root {
            --label-width: 15mm;
            --label-height: 100mm;
            --top-zone: 35mm;
            --middle-zone: 35mm;
            --tail-zone: 30mm;
            --screen-bg: #f3efe6;
            --screen-panel: #fffdf8;
            --screen-border: #ddd4c4;
            --screen-text: #251f17;
            --screen-muted: #746a5d;
        }

        body {
            font-family: 'Poppins', Arial, sans-serif;
            background:
                linear-gradient(180deg, #f7f3ea 0%, #efe8dc 100%);
            color: var(--screen-text);
            margin: 0;
            padding: 24px;
        }

        .screen-shell {
            max-width: 1280px;
            margin: 0 auto;
        }

        .screen-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 20px 24px;
            border: 1px solid var(--screen-border);
            background: var(--screen-panel);
            box-shadow: 0 10px 30px rgba(55, 41, 21, 0.08);
        }

        .screen-title {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .screen-subtitle {
            margin: 6px 0 0;
            font-size: 13px;
            color: var(--screen-muted);
        }

        .screen-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 12px;
        }

        .meta-chip {
            border: 1px solid var(--screen-border);
            background: #f8f3e9;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .screen-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
        }

        .print-button {
            padding: 12px 22px;
            background: #18130d;
            color: #fff;
            border: none;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            cursor: pointer;
        }

        .screen-note {
            max-width: 320px;
            font-size: 12px;
            line-height: 1.5;
            color: var(--screen-muted);
        }

        .preview-stage {
            margin-top: 18px;
            border: 1px solid var(--screen-border);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(247, 243, 234, 0.98));
            box-shadow: 0 18px 40px rgba(55, 41, 21, 0.08);
            padding: 24px;
        }

        .label-list {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            align-items: flex-start;
        }

        .label-container {
            width: var(--label-width);
            height: var(--label-height);
            background: white;
            position: relative;
            display: grid;
            grid-template-rows: var(--top-zone) var(--middle-zone) var(--tail-zone);
            margin-bottom: 5px;
            overflow: hidden;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        }

        .label-container::before {
            content: '';
            position: absolute;
            inset: 0;
            pointer-events: none;
        }

        .face-zone {
            position: relative;
            overflow: hidden;
        }

        .face-zone--code {
            background: #fff;
        }

        .face-content {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 29mm;
            transform: translate(-50%, -50%) rotate(-90deg);
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .info-stack {
            width: 27.5mm;
            gap: 1.6px;
        }

        .brand-strip {
            min-width: 25.5mm;
            padding: 1px 3px 0.8px;
            font-size: 6.6px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            line-height: 1;
        }

        .product-block {
            width: 26.5mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1px;
            padding: 0.8px 0 0;
        }

        .product-name {
            font-size: 7.1px;
            font-weight: 600;
            line-height: 1.1;
            max-width: 27mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-transform: uppercase;
            letter-spacing: 0.15px;
        }

        .product-specs {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            font-size: 6.3px;
            font-weight: 700;
            line-height: 1.1;
        }

        .spec-divider {
            width: 2.8px;
            height: 2.8px;
            background: #111;
            display: inline-block;
        }

        .code-stack {
            width: 29mm;
            gap: 1.2px;
        }

        .barcode-frame {
            width: 28.3mm;
            padding: 1px 0.6px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .barcode-img {
            width: 28mm;
            height: 8.5mm;
            object-fit: contain;
            image-rendering: crisp-edges;
        }

        .barcode-text {
            font-size: 6.1px;
            font-weight: bold;
            line-height: 1;
            margin-top: 0.8px;
            letter-spacing: 0.45px;
            font-family: 'Poppins', Arial, sans-serif;
        }

        .tail {
            border-top: none;
        }

        @media print {
            @page {
                size: var(--label-width) var(--label-height);
                margin: 0;
            }

            body {
                margin: 0;
                padding: 0;
                background: white;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .no-print {
                display: none;
            }

            .screen-shell,
            .preview-stage,
            .label-list {
                margin: 0;
                padding: 0;
                border: none;
                box-shadow: none;
                background: transparent;
                display: block;
            }

            .face-zone--code {
                background: white;
            }

            .label-container {
                margin-bottom: 0 !important;
                box-shadow: none !important;
                border: none !important;
                page-break-after: always;
            }

            .label-container:last-child {
                page-break-after: auto;
            }
        }
    </style>
</head>

<body>
    <div class="screen-shell">
        <div class="screen-toolbar no-print">
            <div>
                <h1 class="screen-title">Barcode Label Print</h1>
                <p class="screen-subtitle">Thermal preview for TSC TE244 jewellery tags. Printed output stays locked to 15mm x 100mm.</p>
                <div class="screen-meta">
                    <div class="meta-chip">{{ count($barcodes) }} Label{{ count($barcodes) === 1 ? '' : 's' }}</div>
                    <div class="meta-chip">15mm x 100mm</div>
                    <div class="meta-chip">Code 128</div>
                </div>
            </div>

            <div class="screen-actions">
                <div class="screen-note">
                    Use 100% scale, zero margins, and disable browser headers or footers. These labels print using the product barcode stored in the app.
                </div>
                <button onclick="window.print()" class="print-button">Print Labels</button>
            </div>
        </div>

        <div class="preview-stage">
            <div class="label-list">
                @foreach ($barcodes as $item)
                    <div class="label-container">
                        <div class="face-zone face-zone--info">
                            <div class="face-content info-stack">
                                <div class="brand-strip">MANIRATN</div>
                                <div class="product-block">
                                    <div class="product-name">{{ $item['name'] }}</div>
                                    <div class="product-specs">
                                        <span>{{ $item['weight'] }}g</span>
                                        <span class="spec-divider"></span>
                                        <span>{{ $item['purity'] }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="face-zone face-zone--code">
                            <div class="face-content code-stack">
                                <div class="barcode-frame">
                                    <img src="data:image/png;base64,{{ $item['barcode'] }}" class="barcode-img">
                                </div>
                                <div class="barcode-text">{{ $item['code'] }}</div>
                            </div>
                        </div>

                        <div class="tail"></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</body>

</html>
