<?php

namespace App\Services;

use TCPDF;

class BarcodeLabelPdfService
{
    public function stream(array $labels, string $filename)
    {
        $pdf = new TCPDF('L', 'mm', [15, 100], true, 'UTF-8', false);

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

            $pdf->SetFont('helvetica', 'B', 7.1);
            $pdf->SetTextColor(20, 20, 20);

            // Left info block
            $pdf->SetXY(5.2, 2.0);
            $pdf->Cell(28.5, 2.8, 'MRTN', 0, 1, 'C', false, '', 0, false, 'T', 'M');

            $pdf->SetFont('helvetica', 'B', 6.2);
            $pdf->SetXY(2.2, 5.3);
            $pdf->Cell(34, 2.8, 'GW ' . number_format((float) $label['gross_weight'], 3) . 'G', 0, 1, 'C', false, '', 0, false, 'T', 'M');

            $pdf->SetFont('helvetica', 'B', 6.2);
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
                39.5,
                1.4,
                30,
                7.4,
                0.26,
                $style,
                'N'
            );

            $pdf->SetFont('courier', 'B', 6.2);
            $pdf->SetXY(39.5, 10.1);
            $pdf->Cell(30, 2.2, $this->fitText($label['code'], 18), 0, 1, 'C', false, '', 0, false, 'T', 'M');
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
}
