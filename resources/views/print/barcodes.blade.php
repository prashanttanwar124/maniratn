<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Print Jewellery Tags</title>
    <style>
        /* --- 1. GENERAL SETUP --- */
        body {
            font-family: 'Arial', sans-serif;
            background: #e0e0e0;
            margin: 20px;
        }

        /* Screen Container (Keep this exactly as is for your perfect HTML view) */
        .label-container {
            width: 15mm;
            height: 100mm;
            background: white;
            position: relative;
            margin-bottom: 5px;
            overflow: hidden;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        }

        /* --- 2. POSITIONING --- */
        .rotated-content {
            transform: rotate(-90deg);
            transform-origin: center center;
            position: absolute;
            width: 30mm;
            height: 12mm;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        /* DEFAULT POSITIONS (For Screen - These look good in your screenshot) */
        .face-top {
            position: absolute;
            top: 15mm;
            left: 50%;
            margin-top: -6mm;
            margin-left: -15mm;
        }

        .face-bottom {
            position: absolute;
            top: 50mm;
            left: 50%;
            margin-top: -6mm;
            margin-left: -15mm;
        }

        /* Styling */
        .company-name {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .product-info {
            font-size: 8px;
            font-weight: 600;
            line-height: 1.1;
        }

        .barcode-img {
            width: 95%;
            height: 10mm;
        }

        .barcode-text {
            font-size: 8px;
            font-weight: bold;
            margin-top: 1px;
        }

        .tail {
            position: absolute;
            bottom: 0;
            width: 100%;
            height: 30mm;
            border-top: 1px dashed #ccc;
        }


        /* --- 3. PRINT FIX (THE IMPORTANT PART) --- */
        @media print {
            @page {
                size: 15mm 100mm;
                margin: 0;
            }

            body {
                margin: 0;
                padding: 0;
            }

            .no-print {
                display: none;
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

            /* COUNTER-ACT THE GAP:
           We reduce the 'top' value by 10mm specifically for the printer.
           Screen: 15mm -> Print: 5mm
           This pulls the content UP into the empty white space you see in the preview.
        */
            .face-top {
                top: 5mm !important;
            }

            .face-bottom {
                top: 40mm !important;
            }
        }
    </style>
</head>

<body>
    <div class="no-print" style="margin-bottom: 20px; padding: 10px; background: white;">
        <h3>🖨️ Settings</h3>
        <p>If you still see a gap, reduce the "top: 12mm" value to "10mm".</p>
        <button onclick="window.print()"
            style="padding: 10px 20px; background: black; color: white; border: none; cursor: pointer;">Print</button>
    </div>

    @foreach ($barcodes as $item)
        <div class="label-container">
            <div class="rotated-content face-top">
                <div class="company-name">MANIRATN</div>
                <div class="product-info">{{ $item['name'] }}</div>
                <div class="product-info"><b>{{ $item['weight'] }}g</b> | {{ $item['purity'] }}</div>
            </div>

            <div class="rotated-content face-bottom">
                <img src="data:image/png;base64,{{ $item['barcode'] }}" class="barcode-img">
                <div class="barcode-text">{{ $item['code'] }}</div>
            </div>

            <div class="tail"></div>
        </div>
    @endforeach
</body>

</html>
