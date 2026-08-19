<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $business->store_name ?? 'Maniratn Jewellers' }} - Google Review Counter Standee</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap');

        :root {
            --brand-maroon: #5b0d13;
            --brand-gold: #c59b27;
            --brand-gold-light: #fef9e7;
            --surface-900: #0f172a;
            --surface-700: #334155;
            --surface-500: #64748b;
            --surface-200: #e2e8f0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f1f5f9;
            color: var(--surface-900);
            padding: 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .toolbar {
            width: 100%;
            max-width: 460px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 10px 16px;
            border-radius: 6px;
            border: 1px solid var(--surface-200);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .btn-print {
            background: var(--brand-maroon);
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
        }

        .btn-print:hover {
            opacity: 0.92;
        }

        /* Standee Card (A5 / Tabletop proportions: 148mm x 210mm) */
        .standee-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border: 2px solid var(--brand-gold);
            border-radius: 8px;
            padding: 32px 28px;
            text-align: center;
            position: relative;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
            background-image: radial-gradient(circle at 50% 0%, rgba(197, 155, 39, 0.06) 0%, transparent 60%);
        }

        .card-inner-border {
            position: absolute;
            inset: 8px;
            border: 1px dashed rgba(197, 155, 39, 0.45);
            border-radius: 6px;
            pointer-events: none;
        }

        .store-logo {
            max-height: 54px;
            max-width: 180px;
            object-contain: fit;
            margin: 0 auto 8px;
            display: block;
        }

        .store-name {
            font-family: 'Cinzel', serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--brand-maroon);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .store-tagline {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: var(--brand-gold);
            font-weight: 600;
            margin-bottom: 20px;
        }

        .divider {
            width: 60px;
            height: 2px;
            background: var(--brand-gold);
            margin: 0 auto 20px;
        }

        .headline {
            font-family: 'Cinzel', serif;
            font-size: 16px;
            font-weight: 700;
            color: var(--surface-900);
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .stars {
            font-size: 22px;
            letter-spacing: 4px;
            color: #f59e0b;
            margin-bottom: 18px;
        }

        .qr-container {
            background: #ffffff;
            padding: 12px;
            border: 1.5px solid var(--brand-gold);
            border-radius: 8px;
            display: inline-block;
            margin-bottom: 18px;
            box-shadow: 0 4px 12px rgba(197, 155, 39, 0.12);
        }

        .qr-container svg,
        .qr-container img {
            width: 170px;
            height: 170px;
            display: block;
        }

        .instruction-badge {
            display: inline-block;
            background: var(--brand-gold-light);
            border: 1px solid rgba(197, 155, 39, 0.35);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: var(--brand-maroon);
            margin-bottom: 22px;
            letter-spacing: 0.02em;
        }

        .footer-info {
            font-size: 10px;
            color: var(--surface-500);
            line-height: 1.5;
            border-top: 1px solid var(--surface-200);
            padding-top: 14px;
        }

        .footer-info strong {
            color: var(--surface-700);
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .toolbar {
                display: none !important;
            }

            .standee-card {
                box-shadow: none;
                border: 2px solid var(--brand-gold);
                margin: 0 auto;
                page-break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <div style="font-size: 13px; font-weight: 600; color: var(--surface-700);">
            Counter Display Standee
        </div>
        <button class="btn-print" onclick="window.print()">
            🖨️ Print Standee
        </button>
    </div>

    <div class="standee-card">
        <div class="card-inner-border"></div>

        @if (!empty($business->logo_url))
            <img src="{{ $business->logo_url }}" alt="Store Logo" class="store-logo" />
        @endif

        <h1 class="store-name">{{ $business->store_name ?: 'Maniratn Jewellers' }}</h1>
        <p class="store-tagline">Fine Gold & Diamond Jewellery</p>

        <div class="divider"></div>

        <h2 class="headline">Rate Us On Google</h2>
        <div class="stars">★★★★★</div>

        <div class="qr-container">
            @if (!empty($qrSvg))
                {!! $qrSvg !!}
            @elseif (!empty($qrCodeBase64))
                <img src="{{ $qrCodeBase64 }}" alt="Google Review QR" />
            @else
                <p style="font-size: 12px; color: red; padding: 20px;">No Google Review URL set in Settings.</p>
            @endif
        </div>

        <div>
            <div class="instruction-badge">
                📱 Scan QR code with your phone camera
            </div>
        </div>

        <div class="footer-info">
            @if (!empty($business->address))
                <p>{{ $business->address }}</p>
            @endif
            <p>
                @if (!empty($business->phone))
                    <strong>Ph:</strong> {{ $business->phone }}
                @endif
                @if (!empty($business->website))
                    • <strong>Web:</strong> {{ $business->website }}
                @endif
            </p>
            <p style="margin-top: 4px; font-size: 9px; color: var(--brand-gold); font-weight: 600;">
                Thank you for your love & trust!
            </p>
        </div>
    </div>
</body>

</html>
