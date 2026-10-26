<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class G12NationalExamPhoto
{
    public const MAX_BYTES = 40_000;

    public function webp(string $bytes): string
    {
        if (!function_exists('imagewebp') || !(imagetypes() & IMG_WEBP)) {
            $this->fail('WebP processing is unavailable on this server. Enable GD WebP support and retry.');
        }
        $size = @getimagesizefromstring($bytes);
        if (!$size || $size[0] * $size[1] > 40_000_000 || !in_array($size['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $this->fail('Choose a readable JPG, PNG or WebP photo up to 40 megapixels.');
        }
        $image = @imagecreatefromstring($bytes);
        if (!$image) $this->fail('The student photo cannot be read. Choose another photo.');
        try {
            if ($size['mime'] === 'image/jpeg') $image = $this->orient($image, $bytes);
            $scale = min(1, 600 / imagesx($image), 800 / imagesy($image));
            // Keep aspect ratio and avoid upscaling. Reduce dimensions only if
            // quality 80 still exceeds the size limit, rather than blurring faces.
            foreach ([1, .9, .8, .7, .6, .5, .4, .3] as $factor) {
                $width = max(1, (int) round(imagesx($image) * $scale * $factor));
                $height = max(1, (int) round(imagesy($image) * $scale * $factor));
                $canvas = imagecreatetruecolor($width, $height);
                try {
                    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
                    imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
                    $best = $this->encode($canvas, 95);
                    if (strlen($best) < self::MAX_BYTES) return $best;
                    $best = $this->encode($canvas, 80);
                    if (strlen($best) >= self::MAX_BYTES) continue;
                    $low = 81; $high = 94;
                    while ($low <= $high) {
                        $quality = intdiv($low + $high, 2);
                        $candidate = $this->encode($canvas, $quality);
                        if (strlen($candidate) < self::MAX_BYTES) {$best = $candidate; $low = $quality + 1;}
                        else $high = $quality - 1;
                    }
                    return $best;
                } finally {
                    imagedestroy($canvas);
                }
            }
        } finally {
            imagedestroy($image);
        }
        $this->fail('This photo cannot fit below 40 KB at a clear quality. Choose a clearer portrait with a plain background.');
    }

    private function encode(\GdImage $image, int $quality): string
    {
        ob_start();
        try {
            if (!imagewebp($image, null, $quality)) $this->fail('The student photo could not be converted to WebP. Try another image.');
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if (!$bytes || !@getimagesizefromstring($bytes)) $this->fail('The optimized photo could not be read. Try another image.');
        return $bytes;
    }

    private function orient(\GdImage $image, string $bytes): \GdImage
    {
        if (!function_exists('exif_read_data')) return $image;
        $stream = fopen('php://temp', 'w+b');
        try {
            fwrite($stream, $bytes); rewind($stream);
            $exif = @exif_read_data($stream, 'IFD0', true);
            $orientation = (int) ($exif['IFD0']['Orientation'] ?? 1);
        } finally {
            fclose($stream);
        }
        if (in_array($orientation, [2, 5, 7], true)) imageflip($image, IMG_FLIP_HORIZONTAL);
        if ($orientation === 4) imageflip($image, IMG_FLIP_VERTICAL);
        $angle = match ($orientation) {3 => 180, 5, 8 => 90, 6, 7 => -90, default => 0};
        if ($angle) {
            $rotated = imagerotate($image, $angle, 0);
            if (!$rotated) $this->fail('The student photo could not be rotated. Choose another photo.');
            imagedestroy($image); $image = $rotated;
        }
        return $image;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['photo' => $message]);
    }
}
