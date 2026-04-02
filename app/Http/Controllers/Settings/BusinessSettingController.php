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
            ]
        );

        return Inertia::render('settings/BusinessProfile', [
            'businessSetting' => [
                'store_name' => $businessSetting->store_name,
                'address' => $businessSetting->address,
                'phone' => $businessSetting->phone,
                'email' => $businessSetting->email,
                'website' => $businessSetting->website,
                'logo_path' => $businessSetting->logo_path,
                'logo_url' => $businessSetting->logo_url,
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
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
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
}
