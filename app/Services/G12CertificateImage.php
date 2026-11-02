<?php

namespace App\Services;

use GdImage;
use Illuminate\Validation\ValidationException;

class G12CertificateImage
{
    public const MAX_MASTER_BYTES = 819_200;
    public const MAX_THUMBNAIL_BYTES = 71_680;
    public const MAX_MASTER_WIDTH = 2_480;
    public const MAX_MASTER_HEIGHT = 3_508;
    public const MAX_THUMBNAIL_WIDTH = 520;

    /**
     * Build a high-quality archive master and a compact list thumbnail.
     * Both derivatives use WebP and retain the source aspect ratio.
     */
    public function optimize(string $bytes): array
    {
        $this->assertWebpSupport();
        $source = $this->read($bytes);

        try {
            $master = $this->fitWebp(
                $source,
                self::MAX_MASTER_WIDTH,
                self::MAX_MASTER_HEIGHT,
                self::MAX_MASTER_BYTES,
                90,
                62
            );

            $masterImage = $this->read($master['bytes']);
            try {
                $thumbnail = $this->fitWebp(
                    $masterImage,
                    self::MAX_THUMBNAIL_WIDTH,
                    900,
                    self::MAX_THUMBNAIL_BYTES,
                    82,
                    54
                );
            } finally {
                imagedestroy($masterImage);
            }

            return [
                'master' => $master['bytes'],
                'thumbnail' => $thumbnail['bytes'],
                'width' => $master['width'],
                'height' => $master['height'],
            ];
        } finally {
            imagedestroy($source);
        }
    }

    public function jpeg(string $bytes, int $quality = 94): string
    {
        $image = $this->read($bytes);
        $quality = max(70, min(96, $quality));

        try {
            $canvas = $this->resample($image, imagesx($image), imagesy($image));
            try {
                ob_start();
                if (!imagejpeg($canvas, null, $quality)) {
                    $this->fail('The certificate could not be prepared as a JPG.');
                }
                $jpeg = ob_get_contents();
            } finally {
                ob_end_clean();
                imagedestroy($canvas);
            }
        } finally {
            imagedestroy($image);
        }

        if (!$jpeg || !@getimagesizefromstring($jpeg)) {
            $this->fail('The generated JPG could not be read.');
        }

        return $jpeg;
    }

    private function fitWebp(
        GdImage $source,
        int $maxWidth,
        int $maxHeight,
        int $maxBytes,
        int $maximumQuality,
        int $minimumQuality
    ): array {
        $baseScale = min(1, $maxWidth / imagesx($source), $maxHeight / imagesy($source));

        foreach ([1, .92, .84, .76, .68, .60, .52, .44] as $factor) {
            $width = max(1, (int) round(imagesx($source) * $baseScale * $factor));
            $height = max(1, (int) round(imagesy($source) * $baseScale * $factor));
            $canvas = $this->resample($source, $width, $height);

            try {
                $maximum = $this->webp($canvas, $maximumQuality);
                if (strlen($maximum) <= $maxBytes) {
                    return ['bytes' => $maximum, 'width' => $width, 'height' => $height];
                }

                $best = null;
                $low = $minimumQuality;
                $high = $maximumQuality - 1;

                while ($low <= $high) {
                    $quality = intdiv($low + $high, 2);
                    $candidate = $this->webp($canvas, $quality);

                    if (strlen($candidate) <= $maxBytes) {
                        $best = $candidate;
                        $low = $quality + 1;
                    } else {
                        $high = $quality - 1;
                    }
                }

                if ($best !== null) {
                    return ['bytes' => $best, 'width' => $width, 'height' => $height];
                }
            } finally {
                imagedestroy($canvas);
            }
        }

        $this->fail('This certificate image cannot be compressed clearly. Choose a sharper image with less background area.');
    }

    private function read(string $bytes): GdImage
    {
        $size = @getimagesizefromstring($bytes);
        if (!$size || $size[0] * $size[1] > 40_000_000 || !in_array($size['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $this->fail('Choose a readable JPG, PNG or WebP certificate image up to 40 megapixels.');
        }

        $image = @imagecreatefromstring($bytes);
        if (!$image) {
            $this->fail('The certificate image cannot be read. Choose another image.');
        }

        return $size['mime'] === 'image/jpeg' ? $this->orient($image, $bytes) : $image;
    }

    private function resample(GdImage $source, int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $width,
            $height,
            imagesx($source),
            imagesy($source)
        );

        return $canvas;
    }

    private function webp(GdImage $image, int $quality): string
    {
        ob_start();
        try {
            if (!imagewebp($image, null, $quality)) {
                $this->fail('The certificate image could not be converted to WebP.');
            }
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        if (!$bytes || !@getimagesizefromstring($bytes)) {
            $this->fail('The optimized certificate image could not be read.');
        }

        return $bytes;
    }

    private function orient(GdImage $image, string $bytes): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $stream = fopen('php://temp', 'w+b');
        try {
            fwrite($stream, $bytes);
            rewind($stream);
            $exif = @exif_read_data($stream, 'IFD0', true);
            $orientation = (int) ($exif['IFD0']['Orientation'] ?? 1);
        } finally {
            fclose($stream);
        }

        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        if ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        $angle = match ($orientation) {
            3 => 180,
            5, 8 => 90,
            6, 7 => -90,
            default => 0,
        };

        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            if (!$rotated) {
                imagedestroy($image);
                $this->fail('The certificate image could not be rotated.');
            }
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    private function assertWebpSupport(): void
    {
        if (!function_exists('imagewebp') || !(imagetypes() & IMG_WEBP)) {
            $this->fail('WebP processing is unavailable on this server. Enable GD WebP support and retry.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
