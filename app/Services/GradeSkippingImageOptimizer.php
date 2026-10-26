<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GradeSkippingImageOptimizer
{
    public function prepare(UploadedFile $file): array
    {
        if (!function_exists('imagewebp') || !(imagetypes() & IMG_WEBP)) {
            $this->fail('WebP processing is unavailable on this server. Enable PHP GD WebP support and retry.');
        }
        $source = $file->getRealPath();
        $bytes = $source && is_file($source) ? @file_get_contents($source) : false;
        if ($bytes === false || $bytes === '') $this->fail('The image document cannot be read. Choose another file.');
        $originalSize = strlen($bytes);
        $size = @getimagesizefromstring($bytes);
        $maximumPixels = (int) config('student_skipping_grade.image_optimization.maximum_pixels', 40_000_000);
        if (!$size || $size[0] * $size[1] > $maximumPixels || !in_array($size['mime']??null, ['image/jpeg','image/png','image/webp'], true)) {
            $this->fail('Choose a readable JPG, PNG or WebP document up to 40 megapixels.');
        }

        $maximumBytes = (int) config('student_skipping_grade.image_optimization.maximum_bytes', 100_000);
        if (($size['mime']??null) === 'image/webp' && $originalSize <= $maximumBytes) {
            return ['path'=>$source,'temporary_path'=>null,'original_size'=>$originalSize,'size'=>$originalSize,'compressed'=>false,'optimization_type'=>'image','extension'=>'webp','mime'=>'image/webp'];
        }

        $optimized = $this->webp($bytes, $size);
        $disk = Storage::disk('local');
        $temporaryPath = 'tmp/grade-skipping-image/'.Str::uuid().'.webp';
        if (!$disk->put($temporaryPath, $optimized)) $this->fail('The optimized image could not be prepared. Please try again.');
        return [
            'path'=>$disk->path($temporaryPath),'temporary_path'=>$temporaryPath,'original_size'=>$originalSize,'size'=>strlen($optimized),
            'compressed'=>strlen($optimized)<$originalSize,'optimization_type'=>'image','extension'=>'webp','mime'=>'image/webp',
        ];
    }

    private function webp(string $bytes, array $size): string
    {
        $image = @imagecreatefromstring($bytes);
        if (!$image) $this->fail('The image document cannot be decoded. Choose another file.');
        try {
            if (($size['mime']??null) === 'image/jpeg') $image = $this->orient($image, $bytes);
            $baseScale = min(
                1,
                (int) config('student_skipping_grade.image_optimization.maximum_width', 1800) / imagesx($image),
                (int) config('student_skipping_grade.image_optimization.maximum_height', 2400) / imagesy($image),
            );
            $limit = (int) config('student_skipping_grade.image_optimization.maximum_bytes', 100_000);
            foreach ([1,.9,.8,.7,.6,.5,.4,.3] as $factor) {
                $width = max(1, (int) round(imagesx($image) * $baseScale * $factor));
                $height = max(1, (int) round(imagesy($image) * $baseScale * $factor));
                $canvas = imagecreatetruecolor($width, $height);
                try {
                    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
                    imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
                    $best = $this->encode($canvas, 35);
                    if (strlen($best) > $limit) continue;
                    $low = 36; $high = 92;
                    while ($low <= $high) {
                        $quality = intdiv($low + $high, 2);
                        $candidate = $this->encode($canvas, $quality);
                        if (strlen($candidate) <= $limit) { $best = $candidate; $low = $quality + 1; }
                        else $high = $quality - 1;
                    }
                    return $best;
                } finally { imagedestroy($canvas); }
            }
        } finally { imagedestroy($image); }
        $this->fail('This image cannot fit below 100 KB while remaining readable. Choose a clearer or smaller image.');
    }

    private function encode(\GdImage $image, int $quality): string
    {
        ob_start();
        try {
            if (!imagewebp($image, null, $quality)) $this->fail('The image could not be converted to WebP.');
            $bytes = ob_get_contents();
        } finally { ob_end_clean(); }
        if (!$bytes || !@getimagesizefromstring($bytes)) $this->fail('The optimized WebP image cannot be read.');
        return $bytes;
    }

    private function orient(\GdImage $image, string $bytes): \GdImage
    {
        if (!function_exists('exif_read_data')) return $image;
        $stream = fopen('php://temp', 'w+b');
        try { fwrite($stream, $bytes); rewind($stream); $exif = @exif_read_data($stream, 'IFD0', true); }
        finally { fclose($stream); }
        $orientation = (int) ($exif['IFD0']['Orientation']??1);
        if (in_array($orientation,[2,5,7],true)) imageflip($image,IMG_FLIP_HORIZONTAL);
        if ($orientation===4) imageflip($image,IMG_FLIP_VERTICAL);
        $angle=match($orientation){3=>180,5,8=>90,6,7=>-90,default=>0};
        if ($angle) {
            $rotated=imagerotate($image,$angle,0);
            if (!$rotated) $this->fail('The image could not be rotated.');
            imagedestroy($image); $image=$rotated;
        }
        return $image;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['documents'=>$message]);
    }
}
