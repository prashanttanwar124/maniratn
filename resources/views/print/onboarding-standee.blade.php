<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $business->store_name ?? 'Maniratn Jewellers' }} - VIP Club QR Onboarding Standee</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap');

        :root {
            --brand-emerald: #1c3633;
            --brand-emerald-dark: #122422;
            --brand-gold: #c4922a;
            --brand-gold-light: #fef9ed;
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
            border-radius: 8px;
            border: 1px solid var(--surface-200);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .btn-print {
            background: var(--brand-emerald);
            color: #ffffff;
            border: none;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
            transition: opacity 0.15s;
        }

        .btn-print:hover {
            opacity: 0.90;
        }

        /* Standee Card (A5 / Tabletop proportions: 148mm x 210mm) */
        .standee-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border: 2.5px solid var(--brand-gold);
            border-radius: 12px;
            padding: 32px 28px 24px;
            text-align: center;
            position: relative;
            box-shadow: 0 12px 30px -5px rgba(0, 0, 0, 0.10);
            background-image: radial-gradient(circle at 50% 0%, rgba(196, 146, 42, 0.08) 0%, transparent 65%);
        }

        .card-inner-border {
            position: absolute;
            inset: 8px;
            border: 1px dashed rgba(196, 146, 42, 0.45);
            border-radius: 8px;
            pointer-events: none;
        }

        .store-logo {
            max-height: 52px;
            max-width: 180px;
            object-contain: fit;
            margin: 0 auto 8px;
            display: block;
        }

        .store-name {
            font-family: 'Cinzel', serif;
            font-size: 21px;
            font-weight: 700;
            color: var(--brand-emerald);
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .store-tagline {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.22em;
            color: var(--brand-gold);
            font-weight: 600;
            margin-bottom: 16px;
        }

        .divider {
            width: 64px;
            height: 2px;
            background: var(--brand-gold);
            margin: 0 auto 16px;
        }

        .vip-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--brand-emerald), var(--brand-emerald-dark));
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            padding: 4px 14px;
            border-radius: 20px;
            border: 1px solid var(--brand-gold);
            margin-bottom: 12px;
        }

        .headline {
            font-family: 'Cinzel', serif;
            font-size: 17px;
            font-weight: 700;
            color: var(--surface-900);
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .subheadline {
            font-size: 11px;
            color: var(--surface-500);
            margin-bottom: 16px;
        }

        .qr-container {
            background: #ffffff;
            padding: 10px;
            border: 2px solid var(--brand-gold);
            border-radius: 12px;
            display: inline-block;
            margin-bottom: 16px;
            box-shadow: 0 4px 16px rgba(196, 146, 42, 0.16);
        }

        .qr-container img {
            width: 190px;
            height: 190px;
            display: block;
            margin: 0 auto;
            image-rendering: -webkit-optimize-contrast;
        }

        .steps-box {
            background: var(--brand-gold-light);
            border: 1px solid rgba(196, 146, 42, 0.35);
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 16px;
            text-align: left;
        }

        .steps-box p {
            font-size: 10.5px;
            color: var(--brand-emerald);
            font-weight: 500;
            line-height: 1.6;
        }

        .steps-box strong {
            font-weight: 700;
        }

        .pin-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ffffff;
            border: 1px solid var(--brand-gold);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            color: var(--brand-emerald);
            margin-top: 4px;
        }

        .footer-info {
            font-size: 10px;
            color: var(--surface-500);
            line-height: 1.5;
            border-top: 1px solid var(--surface-200);
            padding-top: 12px;
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
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="font-size: 13px; font-weight: 600; color: var(--surface-700);">
                Counter VIP Onboarding Standee
            </div>
            <a href="{{ $joinUrl }}" target="_blank" style="font-size: 11px; color: #b45309; text-decoration: underline;">
                🔗 Test Link ({{ Str::limit($joinUrl, 45) }})
            </a>
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

        <div class="vip-badge">✨ VIP Privé Club</div>
        <h2 class="headline">Join Our Customer Club</h2>
        <p class="subheadline">Instant Digital Gold Vault & Exclusive Birthday Privileges</p>

        <div class="qr-container">
            @if (!empty($qrCodeBase64))
                <img src="{{ $qrCodeBase64 }}" alt="VIP Registration QR Code" />
            @elseif (!empty($qrSvg))
                {!! $qrSvg !!}
            @else
                <p style="font-size: 12px; color: red; padding: 20px;">QR Token not configured in Settings.</p>
            @endif
        </div>


        <div class="steps-box">
            <p><strong>1.</strong> Scan QR code with your phone camera</p>
            <p><strong>2.</strong> Enter your Name, Mobile & Birthday</p>
            <p><strong>3.</strong> Enjoy instant Smart Vault & special occasion rewards</p>
            @if (!empty($business->qr_onboarding_pin))
                <div style="text-align: center; margin-top: 6px;">
                    <span class="pin-pill">Counter Code: <span style="letter-spacing: 2px; color: #b45309;">{{ $business->qr_onboarding_pin }}</span></span>
                </div>
            @endif
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
                We look forward to serving you with purity & perfection!
            </p>
        </div>
    </div>
</body>

</html>
