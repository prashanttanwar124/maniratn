<?php

namespace App\Http\Controllers\Settings;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\RedirectResponse;
use App\Http\Controllers\Controller;

class BusinessSettingController extends Controller
{
    public function edit(): Response
    {
        $businessSetting = BusinessSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'store_name' => '',
                'address' => '',
                'phone' => '',
                'email' => '',
                'website' => '',
                'google_review_url' => '',
                'gst_number' => '',
                'qr_onboarding_enabled' => true,
                'qr_onboarding_token' => BusinessSetting::generateQrOnboardingToken(),
                'qr_onboarding_pin' => '',
            ]
        );

        if (! $businessSetting->qr_onboarding_token || str_starts_with((string) $businessSetting->qr_onboarding_token, 'mani_join_')) {
            $businessSetting->qr_onboarding_token = BusinessSetting::generateQrOnboardingToken();
            $businessSetting->save();
        }


        $websiteUrl = trim((string) ($businessSetting->website ?: config('app.url')));
        $websiteUrl = rtrim($websiteUrl !== '' ? $websiteUrl : 'http://localhost:8000', '/');
        $qrOnboardingUrl = "{$websiteUrl}/join?code=" . urlencode((string) $businessSetting->qr_onboarding_token);
        if ($businessSetting->qr_onboarding_pin) {
            $qrOnboardingUrl .= '&pin=' . urlencode((string) $businessSetting->qr_onboarding_pin);
        }

        return Inertia::render('settings/BusinessProfile', [
            'businessSetting' => [
                'store_name' => $businessSetting->store_name,
                'address' => $businessSetting->address,
                'phone' => $businessSetting->phone,
                'email' => $businessSetting->email,
                'website' => $businessSetting->website,
                'google_review_url' => $businessSetting->google_review_url,
                'gst_number' => $businessSetting->gst_number,
                'logo_path' => $businessSetting->logo_path,
                'logo_url' => $businessSetting->logo_url,
                'ai_enabled' => (bool) $businessSetting->ai_enabled,
                'ai_hub_url' => $businessSetting->ai_hub_url ?? 'http://127.0.0.1:8001',
                'ai_api_key' => $businessSetting->ai_api_key,
                'ai_voice_enabled' => (bool) $businessSetting->ai_voice_enabled,
                'ai_voice_name' => $businessSetting->ai_voice_name ?? 'Aoede',
                'qr_onboarding_enabled' => (bool) $businessSetting->qr_onboarding_enabled,
                'qr_onboarding_token' => $businessSetting->qr_onboarding_token,
                'qr_onboarding_pin' => $businessSetting->qr_onboarding_pin,
                'qr_onboarding_url' => $qrOnboardingUrl,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $businessSetting = BusinessSetting::query()->firstOrCreate(['id' => 1]);

        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'google_review_url' => ['nullable', 'string', 'max:500'],
            'gst_number' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'ai_enabled' => ['nullable', 'boolean'],
            'ai_hub_url' => ['nullable', 'string', 'max:255'],
            'ai_api_key' => ['nullable', 'string', 'max:255'],
            'ai_voice_enabled' => ['nullable', 'boolean'],
            'ai_voice_name' => ['nullable', 'string', 'max:50'],
            'qr_onboarding_enabled' => ['nullable', 'boolean'],
            'qr_onboarding_token' => ['nullable', 'string', 'max:64'],
            'qr_onboarding_pin' => ['nullable', 'string', 'max:10'],
            'regenerate_qr_token' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('regenerate_qr_token')) {
            $validated['qr_onboarding_token'] = BusinessSetting::generateQrOnboardingToken();
        }

        if ($request->boolean('remove_logo') && $businessSetting->logo_path) {
            Storage::disk('public')->delete($businessSetting->logo_path);
            $validated['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            if ($businessSetting->logo_path) {
                Storage::disk('public')->delete($businessSetting->logo_path);
            }

            $validated['logo_path'] = $request->file('logo')->store('business-settings', 'public');
        }

        unset($validated['logo'], $validated['remove_logo'], $validated['regenerate_qr_token']);

        $businessSetting->update($validated);

        return to_route('business-settings.edit')->with('success', 'Business profile updated successfully.');
    }

    public function printStandee()
    {
        $businessSetting = BusinessSetting::firstOrCreate(['id' => 1]);
        $googleReviewUrl = $businessSetting->google_review_url;
        $qrCodeBase64 = $googleReviewUrl ? $this->generatePaddedQrCode($googleReviewUrl) : null;

        return view('print.standee', [
            'business' => $businessSetting,
            'googleReviewUrl' => $googleReviewUrl,
            'qrCodeBase64' => $qrCodeBase64,
            'qrSvg' => null,
        ]);
    }

    public function printOnboardingStandee()
    {
        $businessSetting = BusinessSetting::firstOrCreate(['id' => 1]);
        if (! $businessSetting->qr_onboarding_token || str_starts_with((string) $businessSetting->qr_onboarding_token, 'mani_join_')) {
            $businessSetting->qr_onboarding_token = BusinessSetting::generateQrOnboardingToken();
            $businessSetting->save();
        }

        $websiteUrl = trim((string) ($businessSetting->website ?: config('app.url')));
        $websiteUrl = rtrim($websiteUrl !== '' ? $websiteUrl : 'http://localhost:8000', '/');
        $joinUrl = "{$websiteUrl}/join?code=" . urlencode((string) $businessSetting->qr_onboarding_token);
        if ($businessSetting->qr_onboarding_pin) {
            $joinUrl .= '&pin=' . urlencode((string) $businessSetting->qr_onboarding_pin);
        }

        $qrCodeBase64 = $this->generatePaddedQrCode($joinUrl);

        return view('print.onboarding-standee', [
            'business' => $businessSetting,
            'joinUrl' => $joinUrl,
            'qrCodeBase64' => $qrCodeBase64,
            'qrSvg' => null,
        ]);
    }

    /**
     * Generate high-contrast, camera-scannable QR code with standard white quiet-zone padding.
     */
    private function generatePaddedQrCode(string $url): ?string
    {
        if (! class_exists(\TCPDF2DBarcode::class)) {
            return null;
        }

        try {
            $barcode = new \TCPDF2DBarcode($url, 'QRCODE,M');
            $pngData = $barcode->getBarcodePngData(8, 8);

            if ($pngData && extension_loaded('gd')) {
                $srcImg = @imagecreatefromstring($pngData);
                if ($srcImg) {
                    $w = imagesx($srcImg);
                    $h = imagesy($srcImg);
                    $margin = 32; // Standard 4-module white quiet zone
                    $newW = $w + ($margin * 2);
                    $newH = $h + ($margin * 2);

                    $destImg = imagecreatetruecolor($newW, $newH);
                    $white = imagecolorallocate($destImg, 255, 255, 255);
                    imagefilledrectangle($destImg, 0, 0, $newW, $newH, $white);
                    imagecopy($destImg, $srcImg, $margin, $margin, 0, 0, $w, $h);

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
            \Illuminate\Support\Facades\Log::warning('Failed generating QR code: ' . $e->getMessage());
            return null;
        }
    }
}


