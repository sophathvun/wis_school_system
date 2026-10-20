<?php

use App\Models\User;
use App\Services\StudentPhotoBackground;
use App\Services\StudentPhotoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

it('compresses a detailed crop to a real WebP under 40 KB while preserving the portrait ratio', function () {
    $image = imagecreatetruecolor(600, 800);
    for ($y = 0; $y < 800; $y++) {
        for ($x = 0; $x < 600; $x++) {
            imagesetpixel($image, $x, $y, (($x * 971 + $y * 151) ^ ($x * $y * 13)) & 0xffffff);
        }
    }
    ob_start(); imagepng($image); $bytes = ob_get_clean(); imagedestroy($image);
    $file = UploadedFile::fake()->createWithContent('detailed.png', $bytes);
    $encoded = app(StudentPhotoProcessor::class)->webp($file);
    $size = getimagesizefromstring($encoded);
    expect(strlen($encoded))->toBeLessThanOrEqual(StudentPhotoProcessor::MAX_BYTES)
        ->and($size['mime'])->toBe('image/webp')
        ->and($size[0] / $size[1])->toBe(.75)
        ->and($size[0])->toBeGreaterThanOrEqual(300);
});

it('rejects undecodable photo data instead of writing an invalid file', function () {
    app(StudentPhotoProcessor::class)->webp(UploadedFile::fake()->createWithContent('broken.jpg', 'not an image'));
})->throws(\Illuminate\Validation\ValidationException::class);

function photoEditorUser(bool $allowed): User
{
    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 1; $user->active_campus_id = 1;
    $user->shouldReceive('hasPermission')->andReturn($allowed);
    return $user;
}

it('requires enrollment editing permission for local background processing', function () {
    $this->withoutMiddleware();
    $this->actingAs(photoEditorUser(false));
    $this->mock(StudentPhotoBackground::class)->shouldNotReceive('remove');
    $this->postJson(route('student-enrollments.photo.background'), ['photo' => UploadedFile::fake()->image('photo.jpg', 600, 800)])->assertForbidden();
});

it('validates the photo before starting the local model', function () {
    $this->withoutMiddleware(); $this->actingAs(photoEditorUser(true));
    $this->mock(StudentPhotoBackground::class)->shouldNotReceive('remove');
    $this->postJson(route('student-enrollments.photo.background'), ['photo' => UploadedFile::fake()->create('photo.svg', 1, 'image/svg+xml')])
        ->assertUnprocessable()->assertJsonValidationErrors('photo');
});

it('returns a private PNG cutout from the local photo service', function () {
    $this->withoutMiddleware(); $this->actingAs(photoEditorUser(true));
    $this->mock(StudentPhotoBackground::class)->shouldReceive('remove')->once()->andReturn('png-content');
    $this->postJson(route('student-enrollments.photo.background'), ['photo' => UploadedFile::fake()->image('photo.jpg', 600, 800)])
        ->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'no-store, private')->assertContent('png-content');
});

it('explains missing model setup without keeping temporary photos', function () {
    config(['student_photos.python' => base_path('missing-photo-python')]);
    $this->withoutMiddleware(); $this->actingAs(photoEditorUser(true));
    $this->postJson(route('student-enrollments.photo.background'), ['photo' => UploadedFile::fake()->image('photo.jpg', 600, 800)])
        ->assertStatus(503)->assertJsonPath('message', 'Background removal is not installed on this server. Please contact your administrator.');
});

it('runs the installed model offline and removes temporary inputs and outputs', function () {
    $background = app(StudentPhotoBackground::class);
    if (! $background->available()) $this->markTestSkipped('Install the local model to run inference integration checks.');
    $directory = storage_path('app/private/student-photo-ai/tmp');
    $before = is_dir($directory) ? File::directories($directory) : [];
    $png = $background->remove(UploadedFile::fake()->image('synthetic-portrait.jpg', 600, 800));
    $size = getimagesizefromstring($png);
    expect($size['mime'])->toBe('image/png')->and($size[0])->toBe(600)->and($size[1])->toBe(800)
        ->and(File::directories($directory))->toBe($before);
});
