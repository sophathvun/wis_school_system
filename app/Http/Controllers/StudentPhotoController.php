<?php

namespace App\Http\Controllers;

use App\Services\StudentPhotoBackground;
use Illuminate\Http\Request;

class StudentPhotoController
{
    public function size(Request $request, \App\Services\StudentPhotoProcessor $processor)
    {
        abort_unless($request->user(), 403);
        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:png', 'max:10240', 'dimensions:max_width=800,max_height=800'],
            'format' => ['required', 'in:portrait,square'],
        ]);
        $photo = $request->file('photo');
        $bytes = $data['format'] === 'square' ? $processor->squareWebp($photo) : $processor->webp($photo);
        [$width, $height] = getimagesizefromstring($bytes);
        // Measure only; no public or temporary preview file is stored.
        return response()->json(['bytes' => strlen($bytes), 'width' => $width, 'height' => $height], 200,
            ['Cache-Control' => 'private, no-store']);
    }

    public function background(Request $request, StudentPhotoBackground $background)
    {
        abort_unless(\App\Support\StudentEnrollmentPermissions::current()->canUseForm(), 403);
        return $this->process($request, $background);
    }

    public function staffBackground(Request $request, StudentPhotoBackground $background)
    {
        abort_unless(\App\Support\HrmPermissions::allows($request->user(), 'staff', 'create')
            || \App\Support\HrmPermissions::allows($request->user(), 'staff', 'edit'), 403);
        return $this->process($request, $background);
    }

    public function userBackground(Request $request, StudentPhotoBackground $background)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        return $this->process($request, $background);
    }

    public function profileBackground(Request $request, StudentPhotoBackground $background)
    {
        abort_unless($request->user(), 403);
        return $this->process($request, $background);
    }

    private function process(Request $request, StudentPhotoBackground $background)
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:max_width=6000,max_height=6000'],
        ]);
        $photo = $request->file('photo');
        [$width, $height] = getimagesize($photo->getRealPath());
        abort_if($width * $height > 24000000, 422, 'Please use a photo smaller than 24 megapixels.');

        return response($background->remove($photo), 200, [
            'Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
