<?php

namespace App\Http\Controllers;

use App\Models\BrandingSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandingSettingController
{
    public function index()
    {
        return view('branding-settings', ['branding' => BrandingSetting::current()]);
    }

    public function save(Request $request)
    {
        $validated = $request->validate([
            'sidebar_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'login_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'favicon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,ico,svg', 'max:2048'],
            'footer_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'footer_text' => ['nullable', 'string', 'max:255'],
        ]);

        $branding = BrandingSetting::current();
        foreach (['sidebar_logo', 'login_logo', 'favicon', 'footer_logo'] as $field) {
            if ($request->hasFile($field)) {
                $pathField = $field . '_path';
                if ($branding->{$pathField}) Storage::disk('public')->delete($branding->{$pathField});
                $branding->{$pathField} = $request->file($field)->store('branding', 'public');
            } elseif ($request->boolean('remove_' . $field)) {
                $pathField = $field . '_path';
                if ($branding->{$pathField}) Storage::disk('public')->delete($branding->{$pathField});
                $branding->{$pathField} = null;
            }
        }
        $branding->footer_text = $validated['footer_text'] ?? null;
        $branding->save();

        return back()->with('success', 'Branding settings saved successfully.');
    }
}
