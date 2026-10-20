<?php

use App\Models\User;
use App\Services\StudentPhotoBackground;
use App\Services\StudentPhotoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

it('requires authentication for staff, user and profile photo processing', function (string $route) {
    $this->postJson(route($route))->assertUnauthorized();
})->with(['staff.photo.background', 'users.photo.background', 'profile.photo.background']);

it('measures the exact saved WebP size without storing a preview', function (string $format) {
    Storage::fake('public');
    $this->withoutMiddleware()->actingAs(photoEditorUser(false));
    $file = UploadedFile::fake()->image('edited.png', 600, 800);
    $processor = app(StudentPhotoProcessor::class);
    $bytes = $format === 'square' ? $processor->squareWebp($file) : $processor->webp($file);
    [$width, $height] = getimagesizefromstring($bytes);
    $this->postJson(route('photos.size'), ['photo' => $file, 'format' => $format])->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['bytes' => strlen($bytes), 'width' => $width, 'height' => $height]);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
})->with(['portrait', 'square']);

it('rejects invalid size-check uploads and unauthenticated requests', function () {
    $this->postJson(route('photos.size'))->assertUnauthorized();
    $this->withoutMiddleware()->actingAs(photoEditorUser(false));
    $this->postJson(route('photos.size'), ['photo' => UploadedFile::fake()->image('oversize.png', 1000, 1000), 'format' => 'square'])
        ->assertUnprocessable()->assertJsonValidationErrors('photo');
});

it('stores square staff and user photos as WebP under 40 KB', function (string $directory) {
    Storage::fake('public');
    $file = UploadedFile::fake()->image('portrait.png', 600, 800);
    $path = app(StudentPhotoProcessor::class)->storeSquare($file, $directory);
    $size = getimagesize(Storage::disk('public')->path($path));
    expect($path)->toStartWith($directory.'/')->toEndWith('.webp')
        ->and($size['mime'])->toBe('image/webp')->and($size[0])->toBe(400)->and($size[1])->toBe(400)
        ->and(Storage::disk('public')->size($path))->toBeLessThanOrEqual(40*1024);
})->with(['staff', 'users']);

it('checks staff or user editing permissions before background processing', function (string $route) {
    $user = photoEditorUser(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $this->withoutMiddleware()->actingAs($user);
    $this->mock(StudentPhotoBackground::class)->shouldNotReceive('remove');
    $this->postJson(route($route), ['photo' => UploadedFile::fake()->image('photo.jpg', 400, 400)])->assertForbidden();
})->with(['staff.photo.background', 'users.photo.background']);

it('allows authorized staff, user and own-profile background editing', function (string $route, bool $superAdmin) {
    $user = photoEditorUser(true);
    $user->shouldReceive('isSuperAdmin')->andReturn($superAdmin);
    $this->withoutMiddleware()->actingAs($user);
    $this->mock(StudentPhotoBackground::class)->shouldReceive('remove')->once()->andReturn('private-cutout');
    $this->postJson(route($route), ['photo' => UploadedFile::fake()->image('photo.jpg', 400, 400)])
        ->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'no-store, private');
})->with([['staff.photo.background', false], ['users.photo.background', true], ['profile.photo.background', false]]);

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
