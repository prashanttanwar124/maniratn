<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Print Jewellery Tags</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        :root {
            --label-width: 100mm;
            --label-height: 15mm;
            --left-zone: 35mm;
            --middle-zone: 35mm;
            --right-zone: 30mm;
            --accent: #b8860b;
            --accent-light: #f5ecd7;
            --surface: #faf9f7;
            --panel: #ffffff;
            --border: #e8e2d9;
            --text: #1a1612;
            --muted: #8a7e72;
            --radius: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--surface);
            color: var(--text);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .screen-shell {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        /* ── Header ── */
        .screen-header {
            margin-bottom: 28px;
        }

        .header-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .header-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--accent), #d4a017);
            border-radius: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .header-icon svg {
            width: 22px;
            height: 22px;
            fill: #fff;
        }

        .screen-title {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.3px;
            color: var(--text);
        }

        .screen-subtitle {
            margin: 3px 0 0;
            font-size: 13px;
            color: var(--muted);
            font-weight: 400;
        }

        /* ── Toolbar ── */
        .screen-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 20px;
            padding: 16px 20px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.2px;
            border-radius: 0;
            white-space: nowrap;
        }

        .meta-chip--count {
            background: var(--accent-light);
            color: #7a6320;
            border: 1px solid #e6d9a8;
        }

        .meta-chip--size {
            background: #eef2ff;
            color: #4338ca;
            border: 1px solid #c7d2fe;
        }

        .meta-chip--type {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .meta-chip svg {
            width: 13px;
            height: 13px;
            flex-shrink: 0;
        }

        .toolbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .screen-note {
            max-width: 280px;
            font-size: 11px;
            line-height: 1.5;
            color: var(--muted);
        }

        .print-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            background: linear-gradient(135deg, var(--accent), #d4a017);
            color: #fff;
            border: none;
            border-radius: 0;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(184, 134, 11, 0.3);
        }

        .print-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(184, 134, 11, 0.4);
        }

        .print-button:active {
            transform: translateY(0);
        }

        .print-button svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            background: var(--surface);
            color: var(--muted);
            border: 1px solid var(--border);
            border-radius: 0;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .back-button:hover {
            background: #fff;
            color: var(--text);
            border-color: #ccc;
        }

        .back-button svg {
            width: 14px;
            height: 14px;
        }

        /* ── Preview Stage ── */
        .preview-stage {
            margin-top: 24px;
            padding: 28px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .preview-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--muted);
            margin: 0 0 20px;
        }

        .label-list {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-start;
        }

        /* ── Label Card (screen wrapper) ── */
        .label-card {
            position: relative;
        }

        .label-card-index {
            position: absolute;
            top: -8px;
            left: -8px;
            width: 22px;
            height: 22px;
            background: var(--text);
            color: #fff;
            border-radius: 0;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }

        /* ── Label (actual printed area) ── */
        .label-container {
            width: var(--label-width);
            height: var(--label-height);
            background: white;
            position: relative;
            display: grid;
            grid-template-columns: var(--left-zone) var(--middle-zone) var(--right-zone);
            overflow: hidden;
            border: 1px solid #e0dbd4;
            border-radius: 0;
            box-shadow:
                0 1px 4px rgba(0,0,0,0.08),
                0 0 0 3px rgba(0,0,0,0.02);
        }

        .face-zone {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .face-zone--code {
            background: #fff;
        }

        .face-content {
            position: static;
            width: 100%;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .info-stack {
            width: 32mm;
            gap: 0.8px;
        }

        .brand-strip {
            min-width: 26mm;
            padding: 0;
            font-size: 5.2px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            line-height: 1;
            color: #1a1612;
        }

        .product-block {
            width: 31mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.6px;
            padding: 0.6px 0 0;
        }

        .product-name {
            font-size: 5.6px;
            font-weight: 600;
            line-height: 1.1;
            max-width: 31mm;
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
            gap: 3px;
            font-size: 4.9px;
            font-weight: 700;
            line-height: 1.1;
        }

        .spec-divider {
            width: 2px;
            height: 2px;
            background: #111;
            border-radius: 50%;
            display: inline-block;
        }

        .code-stack {
            width: 33mm;
            gap: 1.5px;
        }

        .barcode-frame {
            width: 33mm;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .barcode-img {
            width: 33mm;
            height: 7.6mm;
            object-fit: contain;
            image-rendering: crisp-edges;
        }

        .barcode-text {
            font-size: 4.8px;
            font-weight: bold;
            line-height: 1;
            margin-top: 1px;
            letter-spacing: 0.3px;
            font-family: 'Inter', system-ui, sans-serif;
        }

        .tail {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 1.5mm;
            font-size: 4.6px;
            font-weight: 700;
            letter-spacing: 0.2px;
            text-transform: uppercase;
            color: #21160a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: center;
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }

        .empty-state svg {
            width: 48px;
            height: 48px;
            opacity: 0.3;
            margin-bottom: 12px;
        }

        .empty-state p {
            margin: 0;
            font-size: 14px;
            font-weight: 500;
        }

        /* ── Print overrides ── */
        @media print {
            @page {
                size: 100mm 15mm;
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
                display: none !important;
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

            .label-card {
                display: block;
            }

            .label-card-index {
                display: none;
            }

            .face-zone--code {
                background: white;
            }

            .label-container {
                margin-bottom: 0 !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                page-break-after: always;
            }

            .label-container:last-child {
                page-break-after: auto;
            }
        }

        /* ── Responsive ── */
        @media (max-width: 640px) {
            .screen-shell {
                padding: 16px 12px;
            }

            .header-top {
                flex-direction: column;
            }

            .screen-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .toolbar-right {
                flex-direction: column;
            }

            .print-button {
                width: 100%;
                justify-content: center;
            }

            .preview-stage {
                padding: 16px;
            }

            .label-list {
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="screen-shell">
        {{-- ── Header ── --}}
        <div class="screen-header no-print">
            <div class="header-top">
                <div class="header-brand">
                    <div class="header-icon">
                        <svg viewBox="0 0 24 24"><path d="M3.5 5.5L5 2h14l1.5 3.5H3.5zM12 22l-8.5-14h17L12 22z"/></svg>
                    </div>
                    <div>
                        <h1 class="screen-title">Barcode Label Print</h1>
                        <p class="screen-subtitle">TSC TE244 thermal tags &middot; 100 &times; 15 mm</p>
                    </div>
                </div>

                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <button onclick="history.back()" class="back-button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        Back
                    </button>
                    <button onclick="window.print()" class="print-button">
                        <svg viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v4c0 1.1.9 2 2 2h2v2c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2v-2h2c1.1 0 2-.9 2-2v-4c0-1.66-1.34-3-3-3zM16 19H8v-4h8v4zM19 12c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zM18 3H6v4h12V3z"/></svg>
                        Print Labels
                    </button>
                </div>
            </div>

            {{-- ── Toolbar ── --}}
            <div class="screen-toolbar">
                <div class="toolbar-left">
                    <div class="meta-chip meta-chip--count">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                        {{ count($barcodes) }} Label{{ count($barcodes) === 1 ? '' : 's' }}
                    </div>
                    <div class="meta-chip meta-chip--size">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><path d="M3 9h18M9 21V9"/></svg>
                        100mm &times; 15mm
                    </div>
                    <div class="meta-chip meta-chip--type">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
                        Code 128
                    </div>
                </div>
                <div class="toolbar-right">
                    <div class="screen-note">
                        Set scale to 100%, margins to none, and disable headers/footers before printing.
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Preview ── --}}
        <div class="preview-stage">
            <p class="preview-title no-print">Preview</p>

            @if (count($barcodes) > 0)
                <div class="label-list">
                    @foreach ($barcodes as $index => $item)
                        <div class="label-card">
                            <span class="label-card-index no-print">{{ $index + 1 }}</span>
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
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty-state no-print">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 8H5c-1.66 0-3 1.34-3 3v4c0 1.1.9 2 2 2h2v2c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2v-2h2c1.1 0 2-.9 2-2v-4c0-1.66-1.34-3-3-3zM16 19H8v-4h8v4zM19 12c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zM18 3H6v4h12V3z"/></svg>
                    <p>No labels selected. Go back and select products to print.</p>
                </div>
            @endif
        </div>
    </div>
</body>

</html>
