<?php

use App\Services\G12NationalExamPhoto;
use Illuminate\Validation\ValidationException;

it('converts accepted portrait formats to small readable WebP without enlarging or stretching', function (string $format) {
    $image = imagecreatetruecolor(240, 320);
    imagefill($image, 0, 0, imagecolorallocate($image, 245, 245, 245));
    imagefilledellipse($image, 120, 130, 85, 120, imagecolorallocate($image, 190, 145, 110));
    imagefilledrectangle($image, 60, 230, 180, 319, imagecolorallocate($image, 40, 70, 130));
    ob_start();
    match ($format) {'jpg' => imagejpeg($image), 'png' => imagepng($image), 'webp' => imagewebp($image)};
    $input = ob_get_clean(); imagedestroy($image);
    $output = app(G12NationalExamPhoto::class)->webp($input);
    $size = getimagesizefromstring($output);
    expect($size['mime'])->toBe('image/webp')->and([$size[0], $size[1]])->toBe([240, 320])
        ->and(strlen($output))->toBeGreaterThan(0)->toBeLessThan(40_000);
    $decoded = imagecreatefromstring($output);
    $pixel = imagecolorsforindex($decoded, imagecolorat($decoded, 120, 130));
    expect(abs($pixel['red'] - 190))->toBeLessThan(8)->and(abs($pixel['green'] - 145))->toBeLessThan(8);
    imagedestroy($decoded);
})->with(['jpg', 'png', 'webp']);

it('fits a highly detailed portrait below the strict size limit and preserves proportions', function () {
    $image = imagecreatetruecolor(600, 800);
    for ($y = 0; $y < 800; $y++) for ($x = 0; $x < 600; $x++) {
        imagesetpixel($image, $x, $y, (($x * 971 + $y * 151) ^ ($x * $y * 13)) & 0xffffff);
    }
    ob_start(); imagepng($image); $input = ob_get_clean(); imagedestroy($image);
    $output = app(G12NationalExamPhoto::class)->webp($input); $size = getimagesizefromstring($output);
    expect(strlen($output))->toBeLessThan(40_000)->and($size['mime'])->toBe('image/webp')
        ->and($size[0] / $size[1])->toBe(.75)->and($size[0])->toBeGreaterThanOrEqual(180);
});

it('applies JPEG camera orientation before stripping metadata', function () {
    if (!function_exists('exif_read_data')) $this->markTestSkipped('EXIF extension unavailable.');
    $image = imagecreatetruecolor(800, 600);
    imagefill($image, 0, 0, imagecolorallocate($image, 230, 40, 40));
    imagefilledrectangle($image, 400, 0, 799, 599, imagecolorallocate($image, 40, 40, 230));
    ob_start(); imagejpeg($image); $jpeg = ob_get_clean(); imagedestroy($image);
    $exif = "Exif\0\0II".pack('v', 42).pack('V', 8).pack('v', 1).pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', 6)."\0\0".pack('V', 0);
    $input = substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
    $output = app(G12NationalExamPhoto::class)->webp($input); $size = getimagesizefromstring($output);
    expect([$size[0], $size[1]])->toBe([600, 800])->and($output)->not->toContain('Exif');
    $decoded = imagecreatefromstring($output);
    $top = imagecolorsforindex($decoded, imagecolorat($decoded, 300, 150));
    $bottom = imagecolorsforindex($decoded, imagecolorat($decoded, 300, 650));
    expect($top['red'])->toBeGreaterThan($top['blue'])->and($bottom['blue'])->toBeGreaterThan($bottom['red']);
    imagedestroy($decoded);
});

it('flattens PNG transparency onto white while retaining a nonstandard aspect ratio', function () {
    $image = imagecreatetruecolor(200, 100); imagealphablending($image, false); imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagefilledrectangle($image, 80, 30, 120, 70, imagecolorallocatealpha($image, 30, 70, 120, 0));
    ob_start(); imagepng($image); $input = ob_get_clean(); imagedestroy($image);
    $output = app(G12NationalExamPhoto::class)->webp($input); $size = getimagesizefromstring($output);
    expect([$size[0], $size[1]])->toBe([200, 100]);
    $decoded = imagecreatefromstring($output); $corner = imagecolorsforindex($decoded, imagecolorat($decoded, 5, 5));
    expect(min($corner['red'], $corner['green'], $corner['blue']))->toBeGreaterThan(248);
    imagedestroy($decoded);
});

it('rejects unreadable portraits before any photo can be saved', function () {
    app(G12NationalExamPhoto::class)->webp('invalid image bytes');
})->throws(ValidationException::class);

it('optimizes the supplied MoEYS sample for a local quality check', function () {
    $path = storage_path('app/imports/120804049559121000001.jpg');
    if (!is_file($path)) $this->markTestSkipped('Local MoEYS photo sample not present.');
    $input = file_get_contents($path); $output = app(G12NationalExamPhoto::class)->webp($input);
    $size = getimagesizefromstring($output); $source = getimagesizefromstring($input);
    expect(strlen($output))->toBeLessThan(40_000)->and($size[0])->toBe(600)
        ->and($size[1])->toBeLessThanOrEqual(800)
        ->and(abs($size[0] / $size[1] - $source[0] / $source[1]))->toBeLessThan(.002);
    $directory = storage_path('app/testing'); if (!is_dir($directory)) mkdir($directory, 0775, true);
    file_put_contents($directory.'/g12-portrait-optimized.webp', $output);
});
