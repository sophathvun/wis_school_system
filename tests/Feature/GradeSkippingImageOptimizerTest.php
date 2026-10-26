<?php

use App\Services\GradeSkippingImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('converts supporting document images to readable WebP files below 100 KB', function (string $format) {
    if (!function_exists('imagewebp') || !(imagetypes() & IMG_WEBP)) $this->markTestSkipped('GD WebP support is unavailable.');
    Storage::fake('local');
    $base=tempnam(sys_get_temp_dir(),'grade-skip-image-'); $source=$base.'.'.$format; @unlink($base);
    $image=imagecreatetruecolor(1400,1900);
    imagefill($image,0,0,imagecolorallocate($image,248,248,245));
    for($y=40;$y<1860;$y+=35){$shade=($y*17)%180;imageline($image,35,$y,1365,$y,imagecolorallocate($image,$shade,70,140));}
    imagefilledrectangle($image,180,220,1220,1680,imagecolorallocate($image,220,205,170));
    $format==='png'?imagepng($image,$source):imagejpeg($image,$source,96); imagedestroy($image);
    $file=new UploadedFile($source,'supporting.'.$format,$format==='png'?'image/png':'image/jpeg',null,true);

    try {
        $prepared=app(GradeSkippingImageOptimizer::class)->prepare($file);
        expect($prepared['optimization_type'])->toBe('image')->and($prepared['extension'])->toBe('webp')
            ->and($prepared['mime'])->toBe('image/webp')->and($prepared['size'])->toBeLessThanOrEqual(100_000)
            ->and((@getimagesize($prepared['path']))['mime'])->toBe('image/webp');
        Storage::disk('local')->delete($prepared['temporary_path']);
    } finally { @unlink($source); }
})->with(['jpg','png']);
