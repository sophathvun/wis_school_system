<?php

use App\Http\Middleware\TrackUserPresence;
use App\Http\Middleware\ResolveCampusContext;
use App\Models\Permission;
use App\Models\User;
use App\Services\StudentPhotoBackground;
use App\Services\StudentPhotoProcessor;
use App\Support\PermissionHierarchy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function standalonePhotoUser(bool $allowed): User
{
    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 1;
    $user->shouldReceive('canUsePhotoEditor')->andReturn($allowed);
    return $user;
}

it('checks photo editor permission without requiring campus resolution', function () {
    $this->withoutMiddleware(TrackUserPresence::class);
    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 1; $user->active_campus_id = 7;
    $user->shouldReceive('canUsePhotoEditor')->once()->andReturn(true);
    $this->mock(\App\Services\CampusContext::class)->shouldNotReceive('resolve');
    $this->actingAs($user);
    $this->postJson(route('photo-editor.size'), ['photo' => UploadedFile::fake()->image('crop.png', 354, 472)])->assertOk();
});

it('requires sign in for the standalone editor and its processing routes', function () {
    $this->getJson(route('photo-editor.index'))->assertUnauthorized();
    $this->postJson(route('photo-editor.background'))->assertUnauthorized();
    $this->postJson(route('photo-editor.download'))->assertUnauthorized();
    $this->postJson(route('photo-editor.size'))->assertUnauthorized();
});

it('requires photo editor permission for every standalone route', function () {
    $this->withoutMiddleware([ResolveCampusContext::class, TrackUserPresence::class]);
    $this->actingAs(standalonePhotoUser(false));
    $this->mock(StudentPhotoBackground::class)->shouldNotReceive('remove');
    $this->getJson(route('photo-editor.index'))->assertForbidden();
    $this->postJson(route('photo-editor.background'))->assertForbidden();
    $this->postJson(route('photo-editor.download'))->assertForbidden();
    $this->postJson(route('photo-editor.size'))->assertForbidden();
});

it('allows standalone background processing without enrollment editing permission', function () {
    $this->withoutMiddleware([ResolveCampusContext::class, TrackUserPresence::class]);
    $this->actingAs(standalonePhotoUser(true));
    $this->mock(StudentPhotoBackground::class)->shouldReceive('remove')->once()->andReturn('cutout');
    $this->postJson(route('photo-editor.background'), ['photo' => UploadedFile::fake()->image('portrait.png', 600, 800)])
        ->assertOk()->assertHeader('Content-Type', 'image/png')->assertContent('cutout');
});

it('downloads a real private JPEG or an exact size-limited WebP without saving photos', function (string $format) {
    Storage::fake('public');
    $this->withoutMiddleware([ResolveCampusContext::class, TrackUserPresence::class]);
    $this->actingAs(standalonePhotoUser(true));
    $photo = UploadedFile::fake()->image('edited.png', 600, 800);
    $response = $this->postJson(route('photo-editor.download'), ['photo' => $photo, 'format' => $format])
        ->assertOk()->assertDownload('edited-photo.'.$format)->assertHeader('Cache-Control', 'no-store, private');
    $bytes = $response->streamedContent();
    $size = getimagesizefromstring($bytes);
    expect($size['mime'])->toBe($format === 'jpg' ? 'image/jpeg' : 'image/webp')
        ->and($size[0])->toBe(600)->and($size[1])->toBe(800);
    if ($format === 'webp') {
        expect(strlen($bytes))->toBeLessThanOrEqual(40960)
            ->and($bytes)->toBe(app(StudentPhotoProcessor::class)->webp($photo));
    }
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
})->with(['jpg', 'webp']);

it('rejects oversized, invalid and unsupported download uploads', function () {
    $this->withoutMiddleware([ResolveCampusContext::class, TrackUserPresence::class]);
    $this->actingAs(standalonePhotoUser(true));
    $this->postJson(route('photo-editor.download'), ['photo' => UploadedFile::fake()->image('large.png', 1201, 1201), 'format' => 'webp'])
        ->assertUnprocessable()->assertJsonValidationErrors('photo');
    $this->postJson(route('photo-editor.download'), ['photo' => UploadedFile::fake()->createWithContent('broken.png', 'not an image'), 'format' => 'jpg'])
        ->assertUnprocessable()->assertJsonValidationErrors('photo');
    $this->postJson(route('photo-editor.download'), ['photo' => UploadedFile::fake()->image('photo.png'), 'format' => 'svg'])
        ->assertUnprocessable()->assertJsonValidationErrors('format');
});

it('preserves every selected photo size in JPG and WebP downloads and size measurements', function (int $width, int $height) {
    $this->withoutMiddleware([ResolveCampusContext::class, TrackUserPresence::class]);
    $this->actingAs(standalonePhotoUser(true));
    $photo = UploadedFile::fake()->image('crop.png', $width, $height);
    $measurement = $this->postJson(route('photo-editor.size'), ['photo' => $photo])->assertOk()
        ->assertJsonPath('width', $width)->assertJsonPath('height', $height)->assertJsonPath('webp_available', true)->json();
    foreach (['jpg', 'webp'] as $format) {
        $response = $this->postJson(route('photo-editor.download'), ['photo' => $photo, 'format' => $format])->assertOk();
        $bytes = $response->streamedContent();
        $dimensions = getimagesizefromstring($bytes);
        expect($dimensions[0])->toBe($width)->and($dimensions[1])->toBe($height);
        if ($format === 'webp') {
            expect(strlen($bytes))->toBe($measurement['bytes'])->toBeLessThanOrEqual(40960);
        } else {
            expect(strlen($bytes))->toBe($measurement['jpg_bytes']);
            $jfif = strpos($bytes, "JFIF\0");
            expect($jfif)->not->toBeFalse();
            expect(unpack('Cunit/nx/ny', substr($bytes, $jfif + 7, 5)))->toBe(['unit' => 1, 'x' => 300, 'y' => 300]);
        }
    }
})->with([[354, 472], [413, 531], [472, 709], [531, 531], [591, 354]]);

it('keeps JPG available if WebP cannot fit the selected dimensions under 40 KB', function () {
    $this->withoutMiddleware([ResolveCampusContext::class, TrackUserPresence::class]);
    $this->actingAs(standalonePhotoUser(true));
    $this->mock(StudentPhotoProcessor::class)->shouldReceive('sizedWebp')->once()->andThrow(
        \Illuminate\Validation\ValidationException::withMessages(['photo' => 'WebP cannot fit within 40 KB at this photo size. Choose a smaller size or download JPG.']));
    $photo = UploadedFile::fake()->image('crop.png', 1181, 1181);
    $this->postJson(route('photo-editor.size'), ['photo' => $photo])->assertOk()->assertJsonPath('webp_available', false)->assertJsonPath('bytes', null);
    $this->postJson(route('photo-editor.download'), ['photo' => $photo, 'format' => 'jpg'])->assertOk()->assertDownload('edited-photo.jpg');
});

it('includes the standalone main menu in the permission tree', function () {
    $permission = new Permission(['code' => 'photo-editor.view', 'module' => 'photo-editor', 'action' => 'view', 'name' => 'Use Photo Editor']);
    $permission->id = 123;
    $permissions = collect([$permission]);
    expect(PermissionHierarchy::tree($permissions)['photo-editor']['permission']->id)->toBe(123)
        ->and(PermissionHierarchy::visibleIds($permissions)->all())->toBe([123])
        ->and(PermissionHierarchy::normalizeIds([123], $permissions))->toBe([123]);
});
