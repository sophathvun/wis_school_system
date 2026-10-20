<?php

use App\Models\Family;
use App\Services\FamilyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutMiddleware();
    $this->actingAs(new class extends \App\Models\User {
        public function isSuperAdmin(): bool { return true; }
    });
    Storage::fake('public');
    (require database_path('migrations/2026_10_07_000001_create_student_number_sequence.php'))->up();
    Schema::create('tb_student', function (Blueprint $table) {
        $table->id();
        foreach (['student_no', 'student_id', 'full_name_en', 'family_number', 'photo_path'] as $column) {
            $table->string($column)->nullable();
        }
        $table->boolean('status')->default(true);
        $table->timestamps();
    });
    foreach (['school_info', 'class', 'session', 'academic_track', 'group'] as $lookup) {
        Schema::create('tb_'.$lookup, function (Blueprint $table) {
            $table->id();
            $table->softDeletes();
        });
    }
    Schema::create('tb_grade', function (Blueprint $table) {
        $table->id();
        $table->string('grade');
        $table->softDeletes();
    });
    Schema::create('tb_academic_year', function (Blueprint $table) {
        $table->id();
        $table->string('lifecycle_status');
        $table->softDeletes();
    });
    Schema::create('tb_student_enrollment', function (Blueprint $table) {
        $table->id();
        foreach (['student_id', 'campus_id', 'academic_year_id', 'grade_id', 'class_id', 'academic_track_id', 'group_id', 'session_id'] as $column) {
            $table->integer($column)->nullable();
        }
        foreach (['student_type', 'enrollment_status', 'enrolled_on', 'ended_on', 'exit_reason', 'notes'] as $column) {
            $table->string($column)->nullable();
        }
        $table->boolean('status')->default(true);
        $table->timestamps();
    });
    Schema::create('tb_student_enrollment_history', function (Blueprint $table) {
        $table->id();
        foreach (['enrollment_id', 'student_id', 'campus_id', 'academic_year_id', 'grade_id', 'class_id', 'academic_track_id', 'session_id', 'changed_by'] as $column) {
            $table->integer($column)->nullable();
        }
        foreach (['action_type', 'enrollment_status', 'student_type', 'effective_on', 'reason', 'notes'] as $column) {
            $table->string($column)->nullable();
        }
        $table->timestamps();
    });
    DB::table('tb_academic_year')->insert(['id' => 1, 'lifecycle_status' => 'started']);
    DB::table('tb_grade')->insert(['id' => 1, 'grade' => '4']);
    foreach (['school_info', 'class', 'session'] as $lookup) {
        DB::table('tb_'.$lookup)->insert(['id' => 1]);
    }
    $this->oldPhoto = 'student_photos/SAMPLE_STUDENT_TEST001.jpg';
    Storage::disk('public')->put($this->oldPhoto, 'old-photo-bytes');
    DB::table('tb_student')->insert([
        'id' => 1, 'student_no' => '00000001', 'student_id' => 'TEST001',
        'full_name_en' => 'SAMPLE STUDENT', 'family_number' => 'FTEST001', 'photo_path' => $this->oldPhoto,
    ]);
    $this->assignment = ['campus_id' => 1, 'academic_year_id' => 1, 'grade_id' => 1, 'class_id' => 1, 'academic_track_id' => null, 'session_id' => 1];
    DB::table('tb_student_enrollment')->insert([
        'id' => 1, 'student_id' => 1, ...$this->assignment,
        'student_type' => 'new', 'enrollment_status' => 'active',
    ]);
    $this->payload = [
        'enrollment_id' => 1, 'student_record_id' => 1, 'student_id' => 'TEST001',
        'full_name_en' => 'SAMPLE STUDENT', 'family_number' => 'FTEST001', 'existing_family_number' => '',
        ...$this->assignment, 'status' => 1,
        'mother_name_en' => 'Mother', 'mother_phone' => '012345678',
        'father_name_en' => 'Father', 'father_phone' => '012345679',
    ];
    $this->mock(FamilyService::class, function ($mock) {
        $mock->shouldReceive('syncStudentFamily')->andReturn(new Family);
        $mock->shouldReceive('syncEnrollmentMember')->andReturnNull();
    });
});

it('preserves enrollment remarks and rejects attempts to change them through profile edits', function ($mode) {
    $remark='Skipped from 3B | Approval No: SG2627-003';
    DB::table('tb_student_enrollment')->where('id',1)->update(['notes'=>$remark]);
    $payload=$this->payload;
    if ($mode==='replace') $payload['enrollment_notes']='Updated enrollment remark';
    if ($mode==='clear') $payload['enrollment_notes']=null;
    if ($mode === 'omit') {
        $this->postJson(route('student-enrollments.save'), $payload)->assertOk()->assertJsonPath('data.notes',$remark);
        expect(DB::table('tb_student_enrollment_history')->latest('id')->value('notes'))->toBe($remark);
    } else {
        $this->postJson(route('student-enrollments.save'), $payload)->assertUnprocessable()->assertJsonValidationErrors('enrollment_id');
    }
    expect(DB::table('tb_student_enrollment')->where('id',1)->value('notes'))->toBe($remark);
})->with(['omit','replace','clear']);

it('changes the photo URL on every upload and deletes each replaced file after saving', function () {
    $previousPath = $this->oldPhoto;
    for ($upload = 0; $upload < 2; $upload++) {
        $photo = UploadedFile::fake()->image('same-photo.jpg', 600, 800);
        $response = $this->postJson(route('student-enrollments.save'), [...$this->payload, 'photo' => $photo])->assertOk();
        $newPath = $response->json('data.student.photo_path');
        expect($newPath)->not->toBe($previousPath)
            ->and($newPath)->toStartWith('student_photos/SAMPLE_STUDENT_TEST001_')
            ->and($newPath)->toEndWith('.webp')
            ->and(strlen(Storage::disk('public')->get($newPath)))->toBeLessThanOrEqual(40960)
            ->and(getimagesizefromstring(Storage::disk('public')->get($newPath))['mime'])->toBe('image/webp')
            ->and(DB::table('tb_student')->value('photo_path'))->toBe($newPath);
        Storage::disk('public')->assertMissing($previousPath);
        expect(Storage::disk('public')->allFiles('student_photos'))->toBe([$newPath]);
        $previousPath = $newPath;
    }
});

it('keeps the original photo and removes the new upload if the enrollment transaction fails', function () {
    DB::table('tb_class')->insert(['id' => 2]);
    $this->postJson(route('student-enrollments.save'), [
        ...$this->payload, 'class_id' => 2, 'photo' => UploadedFile::fake()->image('new.jpg', 600, 800),
    ])->assertUnprocessable()->assertJsonValidationErrors('enrollment_id');
    expect(DB::table('tb_student')->value('photo_path'))->toBe($this->oldPhoto)
        ->and(Storage::disk('public')->get($this->oldPhoto))->toBe('old-photo-bytes')
        ->and(Storage::disk('public')->allFiles('student_photos'))->toBe([$this->oldPhoto]);
});

it('keeps the photo when updating enrollment without an upload', function () {
    $this->postJson(route('student-enrollments.save'), $this->payload)->assertOk()
        ->assertJsonPath('data.student.photo_path', $this->oldPhoto);
    Storage::disk('public')->assertExists($this->oldPhoto);
});

it('saves profile edits in the original completed academic year without changing its assignment', function () {
    DB::table('tb_academic_year')->where('id',1)->update(['lifecycle_status'=>'finished']);
    DB::table('tb_student_enrollment')->where('id',1)->update(['enrollment_status'=>'completed']);
    $this->postJson(route('student-enrollments.save'), [...$this->payload,'full_name_en'=>'UPDATED STUDENT'])
        ->assertOk()->assertJsonPath('data.academic_year_id',1)
        ->assertJsonPath('data.enrollment_status','completed')->assertJsonPath('data.student.full_name_en','UPDATED STUDENT');
    expect(DB::table('tb_student_enrollment')->where('id',1)->value('academic_year_id'))->toBe(1);
});

it('does not allow new enrollments or reassignment to an unrelated completed year', function () {
    DB::table('tb_academic_year')->insert(['id'=>2,'lifecycle_status'=>'finished']);
    $this->postJson(route('student-enrollments.save'), [...$this->payload,'academic_year_id'=>2])
        ->assertUnprocessable()->assertJsonValidationErrors('enrollment_id');
    $this->postJson(route('student-enrollments.save'), [...$this->payload,'enrollment_id'=>null,'student_record_id'=>null,'student_id'=>'NEWYEARTEST','academic_year_id'=>2])
        ->assertUnprocessable()->assertJsonValidationErrors('academic_year_id');
});

it('rejects every enrollment field change before applying student or family edits', function ($field, $value) {
    $this->postJson(route('student-enrollments.save'), [...$this->payload, 'full_name_en'=>'SHOULD NOT SAVE', $field=>$value])
        ->assertUnprocessable()->assertJsonValidationErrors('enrollment_id');
    expect(DB::table('tb_student')->where('id',1)->value('full_name_en'))->toBe('SAMPLE STUDENT');
})->with([
    ['campus_id',2], ['academic_year_id',2], ['grade_id',2], ['class_id',2], ['academic_track_id',2], ['session_id',2],
    ['group_id',2], ['status',0], ['enrollment_status','withdrawn'], ['student_type','old'],
    ['enrolled_on','2026-01-01'], ['ended_on','2026-12-31'], ['exit_reason','Changed reason'],
    ['enrollment_notes','Changed notes'], ['student_record_id',2],
]);

it('updates student and family data without changing any stored enrollment fields or student status', function () {
    $stored = ['enrollment_status'=>'withdrawn', 'status'=>false, 'student_type'=>'old', 'group_id'=>9,
        'enrolled_on'=>'2024-10-01', 'ended_on'=>'2025-01-20', 'exit_reason'=>'Moved school', 'notes'=>'Keep history'];
    DB::table('tb_student_enrollment')->where('id',1)->update($stored);
    $before=DB::table('tb_student_enrollment')->where('id',1)->first();
    $payload=collect($this->payload)->except(['campus_id','academic_year_id','grade_id','class_id','academic_track_id','session_id','status'])->all();
    $this->postJson(route('student-enrollments.save'), [...$payload,'full_name_en'=>'UPDATED PROFILE','mother_name_en'=>'Updated Mother'])
        ->assertOk()->assertJsonPath('data.student.full_name_en','UPDATED PROFILE')->assertJsonPath('data.enrollment_status','withdrawn');
    expect((array) DB::table('tb_student_enrollment')->where('id',1)->first())->toBe((array) $before)
        ->and((bool) DB::table('tb_student')->where('id',1)->value('status'))->toBeTrue();
    $history=DB::table('tb_student_enrollment_history')->latest('id')->first();
    expect($history->notes)->toBe('Keep history')->and($history->enrollment_status)->toBe('withdrawn');
});

it('rejects invalid photos without deleting the existing photo', function () {
    $this->postJson(route('student-enrollments.save'), [
        ...$this->payload, 'photo' => UploadedFile::fake()->image('invalid.jpg', 200, 200),
    ])->assertUnprocessable()->assertJsonValidationErrors('photo');
    expect(DB::table('tb_student')->value('photo_path'))->toBe($this->oldPhoto)
        ->and(Storage::disk('public')->allFiles('student_photos'))->toBe([$this->oldPhoto]);
});

it('does not delete an old file still used by another student', function () {
    DB::table('tb_student')->insert(['id' => 2, 'student_id' => 'TEST002', 'photo_path' => $this->oldPhoto]);
    $response = $this->postJson(route('student-enrollments.save'), [
        ...$this->payload, 'photo' => UploadedFile::fake()->image('new.jpg', 600, 800),
    ])->assertOk();
    Storage::disk('public')->assertExists($this->oldPhoto);
    Storage::disk('public')->assertExists($response->json('data.student.photo_path'));
});

it('returns all missing required fields as JSON for enrollment form submissions', function (string $userAgent) {
    $this->post(route('student-enrollments.save'), ['student_id' => 'NEW001'], [
        'Accept' => 'application/json', 'User-Agent' => $userAgent,
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'full_name_en', 'academic_year_id', 'campus_id', 'grade_id', 'class_id', 'session_id', 'status',
        'mother_name_en', 'mother_phone', 'father_name_en', 'father_phone',
    ]);
})->with([
    'iPhone Safari' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1',
    'Android Chrome' => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/143.0.0.0 Mobile Safari/537.36',
    'desktop Chrome' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/143.0.0.0 Safari/537.36',
]);

it('returns optional field validation errors without redirecting the mobile form', function () {
    $this->post(route('student-enrollments.save'), [...$this->payload, 'email' => 'invalid-email'], [
        'Accept' => 'application/json', 'User-Agent' => 'iPhone Safari',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('returns a clear duplicate ID error for mobile enrollment', function () {
    $this->post(route('student-enrollments.save'), [
        ...$this->payload, 'enrollment_id' => null, 'student_record_id' => null,
    ], ['Accept' => 'application/json', 'User-Agent' => 'iPhone Safari'])
        ->assertUnprocessable()->assertJsonPath('errors.student_id.0', 'Student ID TEST001 already exists. Please enter a different Student ID.');
});

it('creates a student from a valid mobile form submission', function () {
    $response = $this->post(route('student-enrollments.save'), [
        ...$this->payload, 'enrollment_id' => null, 'student_record_id' => null, 'student_id' => 'NEW001',
    ], ['Accept' => 'application/json', 'User-Agent' => 'iPhone Safari'])->assertCreated()
        ->assertJsonPath('data.student.student_id', 'NEW001');
    expect(DB::table('tb_student_enrollment')->where('student_id', $response->json('data.student.id'))->exists())->toBeTrue();
});

it('ignores stale or user-supplied student numbers and assigns the next number only on save', function () {
    $this->getJson('/student-enrollments/quick-options')->assertOk()->assertJsonMissingPath('nextStudentNo');
    expect(DB::table('tb_student_number_sequence')->value('last_number'))->toBe(0);
    foreach (['NEW001' => '00000002', 'NEW002' => '00000003'] as $studentId => $studentNo) {
        $this->postJson(route('student-enrollments.save'), [
            ...$this->payload, 'enrollment_id' => null, 'student_record_id' => null,
            'student_id' => $studentId, 'student_no' => '00000003',
        ])->assertCreated()->assertJsonPath('data.student.student_no', $studentNo);
    }
});

it('preserves the student number when updating an existing enrollment', function () {
    $this->postJson(route('student-enrollments.save'), [...$this->payload, 'student_no' => '99999999'])
        ->assertOk()->assertJsonPath('data.student.student_no', '00000001');
    expect(DB::table('tb_student_number_sequence')->value('last_number'))->toBe(0);
});

it('rolls back the number allocation if enrollment creation fails after the student insert', function () {
    $this->mock(FamilyService::class, function ($mock) {
        $mock->shouldReceive('syncStudentFamily')->andThrow(new \RuntimeException('Test save failure'));
    });
    $this->withoutExceptionHandling();
    try {
        $this->postJson(route('student-enrollments.save'), [
            ...$this->payload, 'enrollment_id' => null, 'student_record_id' => null, 'student_id' => 'NEW001',
        ]);
        $this->fail('The failed enrollment should throw.');
    } catch (\RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Test save failure');
    }
    expect(DB::table('tb_student_number_sequence')->value('last_number'))->toBe(0)
        ->and(DB::table('tb_student')->where('student_id', 'NEW001')->exists())->toBeFalse();
});

it('allows a create-only campus user to enroll a new student at another campus', function () {
    $this->actingAs(enrollmentCreateOnlyUser());
    DB::table('tb_school_info')->insert(['id'=>2]);
    $this->postJson(route('student-enrollments.save'), [
        ...$this->payload, 'enrollment_id'=>null, 'student_record_id'=>null,
        'student_id'=>'NEW001', 'campus_id'=>2,
    ])->assertCreated()->assertJsonPath('data.campus_id',2)->assertJsonPath('data.student.student_id','NEW001');
});

it('does not let creation overwrite an existing student profile without edit permission', function () {
    $user = enrollmentCreateOnlyUser();
    $user->is_global = true;
    $this->actingAs($user);
    $this->mock(FamilyService::class)->shouldNotReceive('syncStudentFamily', 'syncEnrollmentMember');
    DB::table('tb_school_info')->insert(['id'=>2]);
    DB::table('tb_academic_year')->insert(['id'=>2,'lifecycle_status'=>'started']);
    $original = (array) DB::table('tb_student')->where('id',1)->first();
    $this->postJson(route('student-enrollments.save'), [
        ...$this->payload, 'enrollment_id'=>null, 'academic_year_id'=>2, 'campus_id'=>2,
        'full_name_en'=>'UNAUTHORIZED CHANGE', 'family_number'=>'OTHER FAMILY',
        'photo'=>UploadedFile::fake()->image('replacement.png',600,800),
    ])->assertCreated()->assertJsonPath('data.campus_id',2)->assertJsonPath('data.student.full_name_en','SAMPLE STUDENT');
    expect((array) DB::table('tb_student')->where('id',1)->first())->toBe($original);
    Storage::disk('public')->assertExists($this->oldPhoto);
});

function enrollmentCreateOnlyUser(): \App\Models\User
{
    return new class extends \App\Models\User {
        public function isSuperAdmin(): bool { return false; }
        public function hasPermission(string $permission, ?int $campusId = null): bool
        {
            return in_array($permission, ['students.view', 'students.enrollment.view', 'students.enrollment.create'], true);
        }
    };
}
