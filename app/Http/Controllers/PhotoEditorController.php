<?php

namespace App\Http\Controllers;

use App\Services\StudentPhotoProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class PhotoEditorController
{
    public function index()
    {
        return view('photos.editor');
    }

    public function size(Request $request, StudentPhotoProcessor $processor)
    {
        $request->validate($this->cropRules());
        $photo = $request->file('photo');
        [$width, $height] = getimagesize($photo->getRealPath());
        try {
            $bytes = $processor->sizedWebp($photo);
            $result = ['bytes' => strlen($bytes), 'width' => $width, 'height' => $height, 'webp_available' => true];
        } catch (\Illuminate\Validation\ValidationException $error) {
            $result = ['bytes' => null, 'width' => $width, 'height' => $height, 'webp_available' => false,
                'message' => $error->errors()['photo'][0]];
        }
        $result['jpg_bytes'] = strlen($this->jpeg($photo));
        return response()->json($result, 200, ['Cache-Control' => 'private, no-store']);
    }

    private function cropRules(): array
    {
        return ['photo' => ['required', 'image', 'mimes:png', 'max:10240', 'dimensions:max_width=1200,max_height=1200']];
    }

    public function download(Request $request, StudentPhotoProcessor $processor)
    {
        $data = $request->validate($this->cropRules() + [
            'format' => ['required', 'in:jpg,webp'],
        ]);
        $photo = $request->file('photo');
        if ($data['format'] === 'webp') {
            $bytes = $processor->sizedWebp($photo);
            $mime = 'image/webp';
        } else {
            $bytes = $this->jpeg($photo);
            $mime = 'image/jpeg';
        }

        return response()->streamDownload(fn () => print($bytes), 'edited-photo.'.$data['format'], [
            'Content-Type' => $mime, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function jpeg(UploadedFile $photo): string
    {
        $image = @imagecreatefromstring(file_get_contents($photo->getRealPath()));
        if (!$image) {
            throw \Illuminate\Validation\ValidationException::withMessages(['photo' => 'The photo cannot be read. Please upload another image.']);
        }
        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
        try {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imageresolution($canvas, 300, 300);
            ob_start();
            try {
                imagejpeg($canvas, null, 95);
                return ob_get_contents();
            } finally {
                ob_end_clean();
            }
        } finally {
            imagedestroy($canvas);
            imagedestroy($image);
        }
    }
}
