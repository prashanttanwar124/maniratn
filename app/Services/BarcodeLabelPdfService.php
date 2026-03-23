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
            $pdf->SetXY(3.4, 1.8);
            $pdf->Cell(32.5, 2.8, 'MANIRATN', 0, 1, 'C', false, '', 0, false, 'T', 'M');

            $pdf->SetFont('helvetica', 'B', 6.9);
            $pdf->SetXY(1.9, 4.6);
            $pdf->Cell(35.5, 3.3, $this->fitText($label['name'], 18), 0, 1, 'C', false, '', 0, false, 'T', 'M');

            $pdf->SetFont('helvetica', 'B', 6.1);
            $pdf->SetXY(1.9, 8.4);
            $pdf->Cell(35.5, 2.8, $this->fitText(number_format((float) $label['weight'], 3) . 'g | ' . $label['purity'], 20), 0, 1, 'C', false, '', 0, false, 'T', 'M');

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
                37.8,
                1.6,
                34.5,
                8.8,
                0.29,
                $style,
                'N'
            );

            $pdf->SetFont('courier', 'B', 6.8);
            $pdf->SetXY(37.6, 11.2);
            $pdf->Cell(34.8, 2.5, $this->fitText($label['code'], 18), 0, 1, 'C', false, '', 0, false, 'T', 'M');
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
