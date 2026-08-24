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
            ]
        );

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
        ]);

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

        unset($validated['logo'], $validated['remove_logo']);

        $businessSetting->update($validated);

        return to_route('business-settings.edit')->with('success', 'Business profile updated successfully.');
    }

    public function printStandee()
    {
        $businessSetting = BusinessSetting::firstOrCreate(['id' => 1]);
        $googleReviewUrl = $businessSetting->google_review_url;

        $qrCodeBase64 = null;
        $qrSvg = null;

        if ($googleReviewUrl) {
            try {
                $reviewBarcode = new \TCPDF2DBarcode($googleReviewUrl, 'QRCODE,H');
                $rawReviewSvg = $reviewBarcode->getBarcodeSVGcode(4.5, 4.5, 'black');
                $qrSvg = preg_replace('/^<\?xml[^>]*\?>\s*(<!DOCTYPE[^>]*>)?\s*/i', '', (string) $rawReviewSvg);
                $qrSvg = preg_replace('/(<svg[^>]*>)/i', '$1<rect width="100%" height="100%" fill="#ffffff"/>', (string) $qrSvg);

                $reviewPngData = $reviewBarcode->getBarcodePngData(8, 8);
                if ($reviewPngData) {
                    $qrCodeBase64 = 'data:image/png;base64,' . base64_encode($reviewPngData);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed generating Google Review Standee QR code: ' . $e->getMessage());
            }
        }

        return view('print.standee', [
            'business' => $businessSetting,
            'googleReviewUrl' => $googleReviewUrl,
            'qrCodeBase64' => $qrCodeBase64,
            'qrSvg' => $qrSvg,
        ]);
    }
}
