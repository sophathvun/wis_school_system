<?php

namespace App\Support;

use App\Models\BrandingSetting;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Storage;

class K3CertificateQr
{
    public static function url(string $token): string
    {
        return route('k3-certificate.verify', ['token' => $token]);
    }

    public static function image(string $token, ?string $faviconPath = null): string
    {
        // Keep the QR quiet zone inside a matching rounded corner frame. Embedding
        // the complete PNG preserves this border in both browser printing and PDF.
        $png = (new Writer(new GDLibRenderer(324, 4)))->writeString(self::url($token), 'UTF-8', ErrorCorrectionLevel::H());
        $qr = imagecreatefromstring($png);
        $canvas = imagecreatetruecolor(420, 420);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 0, 0, 0);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $qr, 48, 48, 0, 0, 324, 324);
        self::addFavicon($canvas, $white, $faviconPath ?? BrandingSetting::current()->favicon_path ?? '');
        imageantialias($canvas, true);
        imagesetthickness($canvas, 4);
        foreach ([[35,35,180,270], [385,35,270,360], [35,385,90,180], [385,385,0,90]] as [$x,$y,$start,$end]) {
            imagearc($canvas, $x, $y, 50, 50, $start, $end, $black);
        }
        // GD's antialiased straight lines ignore thickness; retain even frame strokes.
        imageantialias($canvas, false);
        foreach ([[35,10,110,10], [10,35,10,110], [310,10,385,10], [410,35,410,110],
            [35,410,110,410], [10,310,10,385], [310,410,385,410], [410,310,410,385]] as [$x1,$y1,$x2,$y2]) {
            imageline($canvas, $x1, $y1, $x2, $y2, $black);
        }
        ob_start();
        try {
            imagepng($canvas);
            return 'data:image/png;base64,'.base64_encode(ob_get_contents());
        } finally {
            ob_end_clean();
            imagedestroy($qr);
            imagedestroy($canvas);
        }
    }

    private static function addFavicon(\GdImage $canvas, int $white, string $path): void
    {
        if ($path === '') return;
        $disk = Storage::disk('public');
        $root = realpath($disk->path(''));
        $file = realpath($disk->path($path));
        if (!$root || !$file || !str_starts_with($file, $root.DIRECTORY_SEPARATOR) || !is_file($file)) return;
        $dimensions = @getimagesize($file);
        if (!$dimensions || $dimensions[0] * $dimensions[1] > 16000000) return;
        $contents = @file_get_contents($file);
        $logo = $contents === false ? false : @imagecreatefromstring($contents);
        if (!$logo) return;

        try {
            $scale = min(58 / imagesx($logo), 58 / imagesy($logo));
            $width = max(1, (int) round(imagesx($logo) * $scale));
            $height = max(1, (int) round(imagesy($logo) * $scale));
            imagefilledellipse($canvas, 210, 210, 84, 84, $white);
            imagecopyresampled($canvas, $logo, (int) round(210-$width/2), (int) round(210-$height/2),
                0, 0, $width, $height, imagesx($logo), imagesy($logo));
        } finally {
            imagedestroy($logo);
        }
    }
}
