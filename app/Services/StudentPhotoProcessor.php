<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class StudentPhotoProcessor
{
    public const MAX_BYTES = 40 * 1024;

    public function webp(UploadedFile $file): string
    {
        $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $image) {
            throw ValidationException::withMessages(['photo' => 'The photo cannot be read. Please upload another image.']);
        }
        try {
            // Retain the largest portrait possible, flatten transparency and strip EXIF.
            foreach ([600, 540, 480, 420, 360, 300] as $width) {
                $height = (int) ($width * 4 / 3);
                $canvas = imagecreatetruecolor($width, $height);
                imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
                imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
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
