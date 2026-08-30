<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class QrCodeService
{
    /**
     * Generate camera-scannable QR code PNG base64 data URI with standard white quiet-zone margin.
     *
     * @param string $url Target URL to encode
     * @param int $scale Pixel multiplier per module (default 8)
     * @param int $marginPadding Quiet-zone padding in pixels (default 28)
     * @return string|null Base64 data URI (data:image/png;base64,...)
     */
    public static function generatePngDataUri(string $url, int $scale = 8, int $marginPadding = 28): ?string
    {
        if (! class_exists(\TCPDF2DBarcode::class)) {
            return null;
        }

        try {
            $barcode = new \TCPDF2DBarcode($url, 'QRCODE,M');
            $pngData = $barcode->getBarcodePngData($scale, $scale);

            if ($pngData && extension_loaded('gd')) {
                $srcImg = @imagecreatefromstring($pngData);
                if ($srcImg) {
                    $w = imagesx($srcImg);
                    $h = imagesy($srcImg);
                    $newW = $w + ($marginPadding * 2);
                    $newH = $h + ($marginPadding * 2);

                    $destImg = imagecreatetruecolor($newW, $newH);
                    $white = imagecolorallocate($destImg, 255, 255, 255);
                    imagefilledrectangle($destImg, 0, 0, $newW, $newH, $white);
                    imagecopy($destImg, $srcImg, $marginPadding, $marginPadding, 0, 0, $w, $h);

                    ob_start();
                    imagepng($destImg, null, 9);
                    $finalPng = ob_get_clean();
                    imagedestroy($srcImg);
                    imagedestroy($destImg);

                    return 'data:image/png;base64,' . base64_encode($finalPng);
                }
            }

            return $pngData ? ('data:image/png;base64,' . base64_encode($pngData)) : null;
        } catch (\Throwable $e) {
            Log::warning('Failed generating QR code: ' . $e->getMessage());
            return null;
        }
    }
}
