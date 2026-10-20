<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class StudentPhotoProcessor
{
    public const MAX_BYTES = 40 * 1024;

    public function webp(UploadedFile $file): string
    {
        return $this->encode($file, false);
    }

    public function storeSquare(UploadedFile $file, string $directory): string
    {
        $bytes = $this->squareWebp($file);
        $path = $directory.'/'.\Illuminate\Support\Str::uuid().'.webp';
        if (! \Illuminate\Support\Facades\Storage::disk('public')->put($path, $bytes)) {
            throw ValidationException::withMessages(['photo' => 'The photo could not be saved. Please try again.']);
        }
        return $path;
    }

    public function squareWebp(UploadedFile $file): string
    {
        return $this->encode($file, true);
    }

    private function encode(UploadedFile $file, bool $square): string
    {
        $size = @getimagesize($file->getRealPath());
        if (! $size || $size[0] * $size[1] > 24000000) {
            throw ValidationException::withMessages(['photo' => 'Please use a readable photo smaller than 24 megapixels.']);
        }
        $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $image) {
            throw ValidationException::withMessages(['photo' => 'The photo cannot be read. Please upload another image.']);
        }
        try {
            // Retain the largest portrait possible, flatten transparency and strip EXIF.
            foreach ($square ? [400, 360, 320, 280, 240, 200] : [600, 540, 480, 420, 360, 300] as $width) {
                $height = $square ? $width : (int) ($width * 4 / 3);
                $canvas = imagecreatetruecolor($width, $height);
                imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
                $sourceWidth = $square ? min(imagesx($image), imagesy($image)) : imagesx($image);
                $sourceHeight = $square ? $sourceWidth : imagesy($image);
                imagecopyresampled($canvas, $image, 0, 0,
                    (int) ((imagesx($image) - $sourceWidth) / 2), (int) ((imagesy($image) - $sourceHeight) / 2),
                    $width, $height, $sourceWidth, $sourceHeight);
                try {
                    foreach ([90, 85, 80, 75, 70, 65, 60, 50, 40] as $quality) {
                        ob_start();
                        try {
                            $ok = imagewebp($canvas, null, $quality);
                            $bytes = ob_get_contents();
                        } finally {
                            ob_end_clean();
                        }
                        if ($ok && $bytes !== false && strlen($bytes) > 0 && strlen($bytes) <= self::MAX_BYTES) {
                            return $bytes;
                        }
                    }
                } finally {
                    imagedestroy($canvas);
                }
            }
        } finally {
            imagedestroy($image);
        }
        throw ValidationException::withMessages(['photo' => 'This photo cannot fit within 40 KB. Please use a clearer portrait with a plain background.']);
    }
}
