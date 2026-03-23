<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Barcode Labels</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');

        @page {
            margin: 0;
            size: 100mm 15mm;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', Arial, sans-serif;
            color: #000;
            background: #fff;
        }

        .label-page {
            width: 100mm;
            height: 15mm;
            padding: 0;
            position: relative;
        }

        .label-container {
            width: 100mm;
            height: 12mm;
            position: absolute;
            top: 50%;
            left: 0;
            margin-top: -6mm;
            display: table;
            table-layout: fixed;
            border: 0;
        }

        .face-zone {
            display: table-cell;
            vertical-align: middle;
        }

        .face-zone--info {
            width: 35mm;
            text-align: center;
            padding: 0 1.2mm;
        }

        .face-zone--code {
            width: 35mm;
            text-align: center;
            padding: 0 0.8mm;
        }

        .tail {
            width: 30mm;
            text-align: center;
        }

        .info-stack {
            width: 32mm;
            margin: 0 auto;
            text-align: center;
        }

        .brand-strip {
            font-size: 5.8px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            line-height: 1;
            margin: 0 0 0.8mm;
        }

        .product-block {
            width: 31mm;
            margin: 0 auto;
            text-align: center;
        }

        .product-name {
            font-size: 6.2px;
            font-weight: 700;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-transform: uppercase;
            letter-spacing: 0.15px;
            margin: 0 0 0.6mm;
        }

        .product-specs {
            font-size: 5.4px;
            font-weight: 700;
            line-height: 1.1;
            margin: 0;
        }

        .code-stack {
            width: 33mm;
            margin: 0 auto;
            text-align: center;
        }

        .barcode-frame {
            width: 33mm;
            height: 8.6mm;
            margin: 0 auto 0.9mm;
            text-align: center;
            overflow: hidden;
        }

        .barcode-img {
            width: 33mm;
            height: 8.6mm;
            display: block;
            margin: 0 auto;
        }

        .barcode-text {
            font-size: 5.5px;
            font-weight: 700;
            line-height: 1;
            letter-spacing: 0.35px;
            margin: 0;
            font-family: 'Poppins', Arial, sans-serif;
        }

        .tail-text {
            font-size: 4.6px;
            font-weight: 700;
            letter-spacing: 0.2px;
            text-transform: uppercase;
            color: #21160a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 0;
        }

        .code {
            font-size: 6pt;
            font-weight: 700;
            line-height: 1.1;
            word-break: break-all;
        }

        .spec-divider {
            display: inline-block;
            margin: 0 2px;
            font-weight: 700;
        }

        .empty-tail {
            font-size: 1px;
            color: #fff;
        }

        .fallback-name {
            font-size: 7pt;
            font-weight: 700;
            line-height: 1.05;
            margin: 0 0 0.8mm;
            word-break: break-word;
        }

        .fallback-meta {
            font-size: 6pt;
            font-weight: 600;
            line-height: 1.1;
            margin: 0;
        }
    </style>
</head>

<body>
    @foreach ($barcodes as $barcode)
        <div class="label-page" @if (! $loop->last) style="page-break-after: always;" @endif>
            <div class="label-container">
                <div class="face-zone face-zone--info">
                    <div class="info-stack">
                        <p class="brand-strip">MANIRATN</p>
                        <div class="product-block">
                            <p class="product-name">{{ $barcode['name'] }}</p>
                            <p class="product-specs">
                                <span>{{ number_format((float) $barcode['weight'], 3) }}g</span>
                                <span class="spec-divider">|</span>
                                <span>{{ $barcode['purity'] }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="face-zone face-zone--code">
                    <div class="code-stack">
                        <div class="barcode-frame">
                            <img src="data:image/png;base64,{{ $barcode['barcode_png'] }}" alt="Barcode" class="barcode-img">
                        </div>
                        <p class="barcode-text">{{ $barcode['code'] }}</p>
                    </div>
                </div>

                <div class="face-zone tail">
                    <p class="tail-text">&nbsp;</p>
                </div>
            </div>
        </div>
    @endforeach
</body>

</html>
