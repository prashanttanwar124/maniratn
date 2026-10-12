<?php

namespace App\Services;

use App\Models\BusinessSetting;
use TCPDF;
use TCPDF_FONTS;

class BarcodeLabelPdfService
{
    public const DEFAULT_SETTINGS = [
        'preset' => 'dumbbell_100_15',
        'paper_width' => 100.0,
        'paper_height' => 15.0,
        'barcode_width' => 49.0,
        'barcode_height' => 8.6,
        'store_suffix' => 'MRTN',
        'show_guides' => true,
    ];

    protected static ?array $fontFamilies = null;

    public function resolveConfig(array $overrides = []): array
    {
        $setting = BusinessSetting::first();
        $dbSettings = is_array($setting?->barcode_settings) ? $setting->barcode_settings : [];

        $config = array_merge(self::DEFAULT_SETTINGS, array_filter($dbSettings, fn ($v) => $v !== null && $v !== ''));

        foreach ($overrides as $k => $v) {
            if ($v !== null && $v !== '') {
                $config[$k] = is_numeric($v) ? (float) $v : $v;
            }
        }

        $config['paper_width'] = max(20.0, (float) ($config['paper_width'] ?? 100.0));
        $config['paper_height'] = max(8.0, (float) ($config['paper_height'] ?? 15.0));
        $config['barcode_width'] = max(10.0, (float) ($config['barcode_width'] ?? 49.0));
        $config['barcode_height'] = max(4.0, (float) ($config['barcode_height'] ?? 8.6));
        $config['store_suffix'] = trim((string) ($config['store_suffix'] ?? 'MRTN'));
        $config['show_guides'] = filter_var($config['show_guides'] ?? true, FILTER_VALIDATE_BOOLEAN);

        return $config;
    }

    public function stream(array $labels, string $filename, array $overrides = [])
    {
        $config = $this->resolveConfig($overrides);
        $paperWidth = (float) $config['paper_width'];
        $paperHeight = (float) $config['paper_height'];

        $pdf = new TCPDF('L', 'mm', [$paperHeight, $paperWidth], true, 'UTF-8', false);
        $fonts = $this->registerFonts();

        $pdf->SetCreator(config('app.name'));
        $pdf->SetAuthor(config('app.name'));
        $pdf->SetTitle('Barcode Labels');
        $pdf->SetSubject('Barcode Labels');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetCellPadding(0);
        $pdf->setCellMargins(0, 0, 0, 0);

        $isDefaultScale = abs($paperWidth - 100.0) < 0.1 && abs($paperHeight - 15.0) < 0.1;
        $scaleX = $isDefaultScale ? 1.0 : ($paperWidth / 100.0);
        $scaleY = $isDefaultScale ? 1.0 : ($paperHeight / 15.0);
        $minScale = min($scaleX, $scaleY);

        $showGuides = (bool) $config['show_guides'];
        $guide1X = $isDefaultScale ? 35.0 : round(35.0 * $scaleX, 2);
        $guide2X = $isDefaultScale ? 74.0 : round(74.0 * $scaleX, 2);
        $guideY1 = round(0.8 * $scaleY, 2);
        $guideY2 = round($paperHeight - (0.8 * $scaleY), 2);

        $leftColX = $isDefaultScale ? 2.0 : round(2.0 * $scaleX, 2);
        $leftColWidth = $isDefaultScale ? 34.0 : max(15.0, round(34.0 * $scaleX, 2));

        $fontSizeTitle = $isDefaultScale ? 6.8 : max(4.8, round(6.8 * $minScale, 1));
        $fontSizeBody = $isDefaultScale ? 6.4 : max(4.4, round(6.4 * $minScale, 1));
        $fontSizeBarcodeText = $isDefaultScale ? 6.2 : max(4.2, round(6.2 * $minScale, 1));

        $line1Y = $isDefaultScale ? 1.6 : round(1.6 * $scaleY, 2);
        $line1H = $isDefaultScale ? 2.6 : round(2.6 * $scaleY, 2);
        $line2Y = $isDefaultScale ? 4.8 : round(4.8 * $scaleY, 2);
        $line2H = $isDefaultScale ? 2.8 : round(2.8 * $scaleY, 2);
        $line3Y = $isDefaultScale ? 7.8 : round(7.8 * $scaleY, 2);
        $line3H = $isDefaultScale ? 2.8 : round(2.8 * $scaleY, 2);

        $barcodeX = $isDefaultScale ? 40.2 : (isset($overrides['barcode_x']) ? (float) $overrides['barcode_x'] : round(40.2 * $scaleX, 2));
        $barcodeY = isset($overrides['barcode_y']) ? (float) $overrides['barcode_y'] : ($isDefaultScale ? 1.5 : round(1.5 * $scaleY, 2));
        $barcodeW = (float) $config['barcode_width'];
        $barcodeH = (float) $config['barcode_height'];

        // Safety bounds clamp: ensure at least 3.0mm headroom for text under barcode
        if ($barcodeX + $barcodeW > $paperWidth - 0.5) {
            $barcodeW = max(10.0, ($paperWidth - 0.5) - $barcodeX);
        }
        if ($barcodeY + $barcodeH > $paperHeight - 3.0) {
            $barcodeH = max(4.0, ($paperHeight - 3.0) - $barcodeY);
        }

        $textY = round(($barcodeY + $barcodeH + 0.3), 2);
        $storeSuffix = (string) $config['store_suffix'];

        foreach ($labels as $label) {
            $pdf->AddPage();

            if ($showGuides) {
                $pdf->SetDrawColor(185, 185, 185);
                $pdf->SetLineWidth(0.08);
                $pdf->Line($guide1X, $guideY1, $guide1X, $guideY2);
                $pdf->Line($guide2X, $guideY1, $guide2X, $guideY2);
            }

            $pdf->SetFont($fonts['bold'], '', $fontSizeTitle);
            $pdf->SetTextColor(20, 20, 20);

            // Left info block
            $pdf->SetXY($leftColX, $line1Y);
            $categoryLine = trim($this->fitText($label['category'] ?? '', 12) . ' ' . $this->fitText($label['purity'] ?? '', 8));
            $pdf->Cell($leftColWidth, $line1H, $categoryLine, 0, 1, 'C', false, '', 0, false, 'T', 'M');

            $pdf->SetFont($fonts['bold'], '', $fontSizeBody);
            $pdf->SetXY($leftColX + ($isDefaultScale ? 0.2 : 0), $line2Y);
            $pdf->Cell($leftColWidth, $line2H, 'GW ' . number_format((float) $label['gross_weight'], 3) . 'G', 0, 1, 'C', false, '', 0, false, 'T', 'M');

            $pdf->SetFont($fonts['bold'], '', $fontSizeBody);
            $pdf->SetXY($leftColX + ($isDefaultScale ? 0.2 : 0), $line3Y);
            $pdf->Cell($leftColWidth, $line3H, 'NW ' . number_format((float) $label['net_weight'], 3) . 'G', 0, 1, 'C', false, '', 0, false, 'T', 'M');

            // Vector barcode
            $style = [
                'position' => '',
                'align' => 'C',
                'stretch' => false,
                'fitwidth' => true,
                'cellfitalign' => '',
                'border' => false,
                'hpadding' => 0,
                'vpadding' => 0,
                'fgcolor' => [0, 0, 0],
                'bgcolor' => false,
                'text' => false,
                'font' => 'helvetica',
                'fontsize' => 0,
                'stretchtext' => 0,
            ];

            $pdf->write1DBarcode(
                $label['code'],
                'C128',
                $barcodeX,
                $barcodeY,
                $barcodeW,
                $barcodeH,
                0.28,
                $style,
                'N'
            );

            $pdf->SetFont($fonts['bold'], '', $fontSizeBarcodeText);
            $barcodeText = $this->fitText($label['code'], 18) . ($storeSuffix !== '' ? ' ' . $storeSuffix : '');
            $barcodeCenterX = $barcodeX + ($barcodeW / 2);
            $textWidth = $pdf->GetStringWidth($barcodeText);
            $textX = ($barcodeCenterX - ($textWidth / 2)) - ($isDefaultScale ? 9.6 : round(9.6 * $scaleX, 2));

            $pdf->SetXY($textX, $textY);
            $pdf->Cell($textWidth, 2.0, $barcodeText, 0, 1, 'L', false, '', 0, false, 'T', 'M');
        }

        return response($pdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function getSampleLabels(): array
    {
        return [
            [
                'category' => 'GOLD',
                'purity' => '22K',
                'gross_weight' => 12.450,
                'net_weight' => 11.200,
                'code' => 'MJ-00001',
            ],
            [
                'category' => 'SILVER',
                'purity' => '92.5',
                'gross_weight' => 45.800,
                'net_weight' => 45.800,
                'code' => 'SJ-00002',
            ],
        ];
    }

    protected function fitText(string $value, int $maxLength): string
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $value)));

        return mb_strlen($normalized) > $maxLength
            ? mb_substr($normalized, 0, $maxLength - 1) . '…'
            : $normalized;
    }

    protected function registerFonts(): array
    {
        if (self::$fontFamilies !== null) {
            return self::$fontFamilies;
        }

        $regular = resource_path('fonts/Poppins/Poppins-Regular.ttf');
        $bold = resource_path('fonts/Poppins/Poppins-Bold.ttf');

        self::$fontFamilies = [
            'regular' => TCPDF_FONTS::addTTFfont($regular, 'TrueTypeUnicode', '', 96) ?: 'helvetica',
            'bold' => TCPDF_FONTS::addTTFfont($bold, 'TrueTypeUnicode', '', 96) ?: 'helvetica',
        ];

        return self::$fontFamilies;
    }
}
