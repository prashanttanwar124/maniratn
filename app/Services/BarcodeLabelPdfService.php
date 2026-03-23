<?php

namespace App\Services;

use TCPDF;
use TCPDF_FONTS;

class BarcodeLabelPdfService
{
    protected static ?array $fontFamilies = null;

    public function stream(array $labels, string $filename)
    {
        $pdf = new TCPDF('L', 'mm', [15, 100], true, 'UTF-8', false);
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

        foreach ($labels as $label) {
            $pdf->AddPage();
            $pdf->SetDrawColor(185, 185, 185);
            $pdf->SetLineWidth(0.08);

            // Test guides: middle split and tail start.
            $pdf->Line(35, 0.8, 35, 14.2);
            $pdf->Line(74, 0.8, 74, 14.2);

            $pdf->SetFont($fonts['bold'], '', 6.8);
            $pdf->SetTextColor(20, 20, 20);

            // Left info block
            $pdf->SetXY(2.0, 2.0);
            $pdf->Cell(34.0, 2.6, $this->fitText($label['category'] ?? '', 16), 0, 1, 'C', false, '', 0, false, 'T', 'M');

            $pdf->SetFont($fonts['bold'], '', 6.4);
            $pdf->SetXY(2.2, 5.3);
            $pdf->Cell(34, 2.8, 'GW ' . number_format((float) $label['gross_weight'], 3) . 'G', 0, 1, 'C', false, '', 0, false, 'T', 'M');

            $pdf->SetFont($fonts['bold'], '', 6.4);
            $pdf->SetXY(2.2, 8.4);
            $pdf->Cell(34, 2.8, 'NW ' . number_format((float) $label['net_weight'], 3) . 'G', 0, 1, 'C', false, '', 0, false, 'T', 'M');

            // Vector barcode in center block
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
                40.2,
                1.9,
                49.0,
                8.6,
                0.28,
                $style,
                'N'
            );

            $pdf->SetFont($fonts['bold'], '', 6.2);
            $barcodeText = $this->fitText($label['code'], 18) . ' MRTN';
            $barcodeBlockX = 40.2;
            $barcodeBlockWidth = 49.0;
            $barcodeCenterX = $barcodeBlockX + ($barcodeBlockWidth / 2);
            $textWidth = $pdf->GetStringWidth($barcodeText);
            $textX = ($barcodeCenterX - ($textWidth / 2)) - 9.6;

            $pdf->SetXY($textX, 10.6);
            $pdf->Cell($textWidth, 2.0, $barcodeText, 0, 1, 'L', false, '', 0, false, 'T', 'M');
        }

        return response($pdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
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
