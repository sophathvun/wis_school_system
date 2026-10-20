<?php

namespace App\Http\Controllers;

use App\Services\StudentPhotoBackground;
use Illuminate\Http\Request;

class StudentPhotoController
{
    public function background(Request $request, StudentPhotoBackground $background)
    {
        $user = $request->user();
        abort_unless($user && ($user->hasPermission('students.enrollment.create', $user->active_campus_id)
            || $user->hasPermission('students.enrollment.update', $user->active_campus_id)
            || $user->hasPermission('students.enrollment.manage', $user->active_campus_id)), 403);
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
