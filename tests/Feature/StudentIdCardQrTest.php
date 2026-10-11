<?php

use App\Http\Controllers\StudentIdCardQrController;
use App\Models\StudentIdCardTemplate;
use App\Models\User;
use App\Support\StudentIdCardLayout;
use App\Support\StudentIdCardPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Schema::create('tb_student_id_card_template', function (Blueprint $table) {
        $table->id(); $table->json('layout')->nullable(); $table->unsignedInteger('version')->default(0); $table->timestamps();
    });
    DB::table('tb_student_id_card_template')->insert(['id' => 1, 'version' => 0]);
    Schema::create('tb_academic_year', function (Blueprint $table) {
        $table->id();
        $table->string('academic_year');
        $table->string('period_type')->default('regular');
        $table->softDeletes();
    });
    Schema::create('tb_school_info', function (Blueprint $table) {
        $table->id();
        $table->string('campus_name_en');
        $table->string('campus_name_kh')->nullable();
        $table->softDeletes();
    });
    Schema::create('tb_grade', function (Blueprint $table) {
        $table->id();
        $table->string('grade');
        $table->string('grade_short_name')->nullable();
        $table->integer('grade_order');
        $table->softDeletes();
    });
    Schema::create('tb_class', function (Blueprint $table) {
        $table->id();
        $table->string('class_name');
        $table->integer('class_order')->default(1);
        $table->softDeletes();
    });
    Schema::create('tb_student', function (Blueprint $table) {
        $table->id();
        foreach (['student_id', 'student_no', 'full_name_en', 'full_name_kh', 'photo_path', 'home_phone', 'date_of_birth', 'gender'] as $column) {
            $table->string($column)->nullable();
        }
    });
    Schema::create('tb_student_enrollment', function (Blueprint $table) {
        $table->id();
        foreach (['student_id', 'academic_year_id', 'campus_id', 'grade_id', 'class_id'] as $column) {
            $table->integer($column);
        }
    });

    DB::table('tb_academic_year')->insert([
        ['id' => 1, 'academic_year' => '2025-2026'],
        ['id' => 2, 'academic_year' => '2026-2027'],
    ]);
    DB::table('tb_school_info')->insert([
        ['id' => 1, 'campus_name_en' => 'BCH'],
        ['id' => 2, 'campus_name_en' => 'BKK'],
    ]);
    DB::table('tb_class')->insert(['id' => 1, 'class_name' => 'A']);
    foreach (['N-', 'K1-', 'K2-', 'K3-', '1', '2', '3'] as $index => $code) {
        $id = $index + 1;
        DB::table('tb_grade')->insert([
            'id' => $id, 'grade' => $code, 'grade_short_name' => $code, 'grade_order' => $id,
        ]);
        DB::table('tb_student')->insert(['id' => $id, 'student_id' => 'TEST-' . $id, 'full_name_en' => 'STUDENT ' . $id]);
        DB::table('tb_student_enrollment')->insert([
            'student_id' => $id, 'academic_year_id' => 1, 'campus_id' => 1, 'grade_id' => $id, 'class_id' => 1,
        ]);
    }
});

function idCardRequest(string $uri, string $method = 'GET', array $parameters = [], array $denied = []): Request
{
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('hasPermission')->andReturnUsing(fn ($code) => !in_array($code, $denied, true));
    $request = Request::create($uri, $method, $parameters);
    $request->setUserResolver(fn () => $user);
    return $request;
}

function idCardTemplateRequest(array $layout, int $version = 0, bool $allowed = true): Request
{
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('hasPermission')->andReturnUsing(fn ($code) => $code !== StudentIdCardLayout::EDIT_PERMISSION || $allowed);
    $request = Request::create('/students/id-card-qr/template', 'POST', [
        'template_data' => json_encode($layout), 'template_version' => $version,
        'academic_year_id' => 1, 'campus_id' => 1, 'grade_id' => 2, 'class_id' => 1, 'student_id' => 2, 'print_scope' => 'class',
    ]);
    $request->setUserResolver(fn () => $user);
    return $request;
}

it('saves added and removed fields, font changes, positions and QR visibility for all years', function () {
    $layout = StudentIdCardLayout::defaults();
    unset($layout['fields']['phone']);
    $layout['fields']['logo']['x'] = 8;
    $layout['fields']['student_name']['size'] = 12;
    $layout['fields']['qr']['visible'] = false;
    $layout['fields']['campus'] = StudentIdCardLayout::textField('Campus', '{{campus}} / {{academic_year}}', 3, 60, 48, 8);
    $response = (new StudentIdCardQrController)->saveTemplate(idCardTemplateRequest($layout));

    expect($response->getTargetUrl())->toContain('print_scope=class', 'student_id=2');
    $saved = StudentIdCardTemplate::find(1);
    expect($saved->version)->toBe(1)->and($saved->layout['fields'])->not->toHaveKey('phone')
        ->and($saved->layout['fields']['student_name']['size'])->toBe(12)
        ->and($saved->layout['fields']['qr']['visible'])->toBeFalse()
        ->and($saved->layout['fields']['logo']['x'])->toBe(8);
    $nextYear = (new StudentIdCardQrController)->index(idCardRequest('/students/id-card-qr', 'GET', ['academic_year_id' => 2]))->getData();
    expect($nextYear['cardLayout']['fields'])->toHaveKey('campus')->not->toHaveKey('phone')
        ->and($nextYear['cardTemplateVersion'])->toBe(1);
});

it('rejects template saves without the template permission', function () {
    expect(fn () => (new StudentIdCardQrController)->saveTemplate(idCardTemplateRequest(StudentIdCardLayout::defaults(), 0, false)))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(StudentIdCardTemplate::find(1)->version)->toBe(0);
});

it('requires the Students menu and ID Card submenu permissions before allowing any action', function (string $missing) {
    $request = idCardRequest('/students/id-card-qr', 'GET', [], [$missing]);
    foreach (array_keys(StudentIdCardPermissions::catalog()) as $action) {
        expect(StudentIdCardPermissions::allows($request->user(), $action))->toBeFalse();
    }
    expect(fn () => (new StudentIdCardQrController)->index($request))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
})->with(['students.view', 'student-id-card-qr.view']);

it('blocks direct QR generation and print routes for denied actions', function (string $action, string $method, string $scope) {
    $request = idCardRequest('/students/id-card-qr', $method === 'generateQr' ? 'POST' : 'GET', [
        'academic_year_id' => 1, 'campus_id' => 1, 'grade_id' => 2, 'class_id' => 1, 'student_id' => 2, 'print_scope' => $scope,
    ], ['student-id-card-qr.' . $action]);
    try {
        (new StudentIdCardQrController)->{$method}($request);
        throw new RuntimeException('Denied action was allowed.');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
})->with([
    ['generate-qr', 'generateQr', 'student'],
    ['print-card', 'printCards', 'student'],
    ['print-class', 'printCards', 'class'],
]);

it('generates QR tokens only through an authorized action and preserves existing tokens on repeat', function () {
    Schema::table('tb_student', function (Blueprint $table) {
        $table->string('id_card_qr_code')->nullable(); $table->timestamp('id_card_qr_generated_at')->nullable(); $table->timestamps();
    });
    $controller = new StudentIdCardQrController;
    $filters = ['academic_year_id' => 1, 'campus_id' => 1, 'grade_id' => 2, 'class_id' => 1, 'student_id' => 2];
    $view = $controller->index(idCardRequest('/students/id-card-qr', 'GET', $filters));
    expect(DB::table('tb_student')->where('id', 2)->value('id_card_qr_code'))->toBeNull();
    try {
        $controller->qr(idCardRequest('/students/id-card-qr/2/qr.svg'), \App\Models\Student::find(2));
        throw new RuntimeException('QR preview must not generate a token.');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        expect($exception->getStatusCode())->toBe(404);
    }
    $request = idCardRequest('/students/id-card-qr/generate', 'POST', $filters);
    $response = $controller->generateQr($request);
    $token = DB::table('tb_student')->where('id', 2)->value('id_card_qr_code');
    expect($token)->toStartWith('SID-')->and($response->getTargetUrl())->toContain('student_id=2');
    $controller->generateQr($request);
    expect(DB::table('tb_student')->where('id', 2)->value('id_card_qr_code'))->toBe($token);
});

it('renders the permitted individual print and whole class print with the saved layout', function () {
    $this->withoutVite();
    DB::table('tb_student')->insert(['id' => 8, 'student_id' => 'TEST-8', 'full_name_en' => 'STUDENT 8']);
    DB::table('tb_student_enrollment')->insert([
        'student_id' => 8, 'academic_year_id' => 1, 'campus_id' => 1, 'grade_id' => 2, 'class_id' => 1,
    ]);
    $controller = new StudentIdCardQrController;
    $filters = ['academic_year_id' => 1, 'campus_id' => 1, 'grade_id' => 2, 'class_id' => 1, 'student_id' => 2];
    foreach (['student' => 1, 'class' => 2] as $scope => $count) {
        $view = $controller->printCards(idCardRequest('/students/id-card-qr/print', 'GET', $filters + ['print_scope' => $scope]));
        expect($view->name())->toBe('student-id-card.print');
        $document = new DOMDocument;
        @$document->loadHTML($view->render());
        $xpath = new DOMXPath($document);
        expect($xpath->query('//*[@data-id-card]')->length)->toBe($count);
    }
});

it('hides denied ID Card buttons while retaining student preview and filtering', function () {
    $request = idCardRequest('/students/id-card-qr', 'GET', [
        'academic_year_id' => 1, 'campus_id' => 1, 'grade_class' => '2:1',
    ], array_map(fn ($action) => 'student-id-card-qr.' . $action, ['generate-qr', 'print-card', 'print-class', 'copy-link', 'edit-template']));
    $data = (new StudentIdCardQrController)->index($request)->getData();
    $source = file_get_contents(resource_path('views/student-id-card-qr.blade.php'));
    $start = strpos($source, "@section('content')") + strlen("@section('content')");
    $source = substr($source, $start, strrpos($source, '@endsection') - $start);
    $source = preg_replace('/@vite\([^\n]+\)/', '', $source);
    $html = \Illuminate\Support\Facades\Blade::render($source, $data, deleteCachedView: true);
    expect($html)->toContain('Filter Students', 'STUDENT 2', 'data-id-card')
        ->not->toContain('Generate QR', 'data-id-print>', 'title="Copy link"', 'data-id-template-editor', 'student-id-print-sheet');
});

it('checks individual print permission when a QR lookup resolves a class request to one student', function () {
    Schema::table('tb_student', fn (Blueprint $table) => $table->string('id_card_qr_code')->nullable());
    DB::table('tb_student')->where('id', 2)->update(['id_card_qr_code' => 'SID-TEST']);
    $request = idCardRequest('/students/id-card-qr/print', 'GET', [
        'print_scope' => 'class', 'qr' => 'SID-TEST',
    ], ['student-id-card-qr.print-card']);
    try {
        (new StudentIdCardQrController)->printCards($request);
        throw new RuntimeException('Denied individual print was allowed.');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
});

it('shows editor controls only to users with the template permission', function () {
    $controller = new StudentIdCardQrController;
    $allowed = $controller->index(idCardTemplateRequest(StudentIdCardLayout::defaults()))->getData();
    $denied = $controller->index(idCardTemplateRequest(StudentIdCardLayout::defaults(), 0, false))->getData();
    expect($allowed['canEditCardTemplate'])->toBeTrue()->and($denied['canEditCardTemplate'])->toBeFalse();
    expect(view('student-id-card._editor', $allowed)->render())->toContain('Edit Template', 'Remove Block', 'Show QR code', 'Student Name (Khmer)')
        ->and(view('student-id-card._editor', $denied)->render())->not->toContain('data-id-template-editor');
});

it('prevents an outdated editor from overwriting a saved template', function () {
    $controller = new StudentIdCardQrController;
    $controller->saveTemplate(idCardTemplateRequest(StudentIdCardLayout::defaults()));
    $stale = StudentIdCardLayout::defaults();
    $stale['fields']['logo']['x'] = 1;
    expect(fn () => $controller->saveTemplate(idCardTemplateRequest($stale)))
        ->toThrow(ValidationException::class);
    expect(StudentIdCardTemplate::find(1)->layout['fields']['logo']['x'])->toBe(6);
});

it('rejects unsupported placeholders, invalid styles, unknown field types and duplicate media', function (string $property, mixed $value) {
    $layout = StudentIdCardLayout::defaults();
    $layout['fields']['student_name'][$property] = $value;
    expect(fn () => (new StudentIdCardQrController)->saveTemplate(idCardTemplateRequest($layout)))
        ->toThrow(ValidationException::class);
    expect(StudentIdCardTemplate::find(1)->version)->toBe(0);
})->with([
    ['text', '{{password}}'], ['size', 200], ['color', 'red;display:none'],
    ['font', 'url(https://example.com/font)'], ['type', 'html'], ['width', 55],
    ['type', 'photo'],
]);

it('bounds media within the card and keeps the QR code square', function () {
    $layout = StudentIdCardLayout::defaults();
    $layout['fields']['qr'] = array_replace($layout['fields']['qr'], ['x' => 53, 'y' => 89, 'width' => 20, 'height' => 5]);
    (new StudentIdCardQrController)->saveTemplate(idCardTemplateRequest($layout));
    expect(StudentIdCardTemplate::find(1)->layout['fields']['qr'])->toMatchArray(['x' => 34, 'y' => 70, 'width' => 20, 'height' => 20]);
});

it('fills custom fields from the selected enrollment and escapes text in printed cards', function () {
    DB::table('tb_student')->where('id', 2)->update(['full_name_en' => '<script>TEST</script>', 'home_phone' => '+85512676787']);
    DB::table('tb_student_enrollment')->insert([
        'student_id' => 2, 'academic_year_id' => 2, 'campus_id' => 2, 'grade_id' => 7, 'class_id' => 1,
    ]);
    $layout = StudentIdCardLayout::defaults();
    $layout['fields']['qr']['visible'] = false;
    $layout['fields']['details'] = StudentIdCardLayout::textField('Details', '{{class}} / {{campus}} / {{academic_year}}', 3, 60, 48, 8);
    StudentIdCardTemplate::find(1)->update(['layout' => $layout]);
    $data = (new StudentIdCardQrController)->index(idCardRequest('/students/id-card-qr', 'GET', [
        'academic_year_id' => 1, 'campus_id' => 1, 'grade_class' => '2:1',
    ]))->getData();
    $html = view('student-id-card._card', $data + ['cardStudent' => $data['selectedStudent'], 'logoPath' => null])->render();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($html)->toContain('K1-A / BCH / 2025-2026', '+855 12 676 787', '&lt;script&gt;TEST&lt;/script&gt;')
        ->and($xpath->query('//script')->length)->toBe(0)
        ->and($xpath->query('//*[@data-id-field="qr" and @hidden]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-id-field="scan" and @hidden]')->length)->toBe(1);
});

it('allows all blocks to be removed and restored through a later save', function () {
    $controller = new StudentIdCardQrController;
    $controller->saveTemplate(idCardTemplateRequest(['fields' => []]));
    expect(StudentIdCardLayout::resolve(StudentIdCardTemplate::find(1)->layout)['fields'])->toBeEmpty();
    $controller->saveTemplate(idCardTemplateRequest(StudentIdCardLayout::defaults(), 1));
    expect(StudentIdCardTemplate::find(1)->layout['fields'])->toHaveCount(7);
});

it('lists only regular academic years with enrollments', function () {
    DB::table('tb_academic_year')->insert([
        'id' => 3, 'academic_year' => 'Summer 2025-2026', 'period_type' => 'summer',
    ]);
    foreach ([2, 3] as $yearId) {
        DB::table('tb_student_enrollment')->insert([
            'student_id' => 1, 'academic_year_id' => $yearId, 'campus_id' => 1, 'grade_id' => 1, 'class_id' => 1,
        ]);
    }
    $data = (new StudentIdCardQrController)->index(idCardRequest('/students/id-card-qr'))->getData();

    expect($data['academicYears']->pluck('id')->all())->toBe([2, 1])
        ->and($data['academicYears']->pluck('period_type')->unique()->all())->toBe(['regular']);
});

it('keeps nursery and kindergarten distinct from numbered grades and lists each enrolled pair once', function () {
    DB::table('tb_student_enrollment')->insert([
        'student_id' => 1, 'academic_year_id' => 1, 'campus_id' => 1, 'grade_id' => 1, 'class_id' => 1,
    ]);
    $data = (new StudentIdCardQrController)->index(idCardRequest('/students/id-card-qr', 'GET', [
        'academic_year_id' => 1, 'campus_id' => 1,
    ]))->getData();

    expect($data['classes']->pluck('label')->all())->toBe(['N-A', 'K1-A', 'K2-A', 'K3-A', '1A', '2A', '3A']);
});

it('filters kindergarten students by the original grade and class IDs', function () {
    $data = (new StudentIdCardQrController)->index(idCardRequest('/students/id-card-qr', 'GET', [
        'academic_year_id' => 1, 'campus_id' => 1, 'grade_class' => '2:1',
    ]))->getData();

    expect($data['students']->pluck('student_id')->all())->toBe(['TEST-2'])
        ->and($data['selectedStudent']->student_id)->toBe('TEST-2')
        ->and($data['classes']->pluck('label')->all())->toContain('K1-A', '1A');
});

it('keeps grade options scoped to the selected year and campus', function () {
    DB::table('tb_student_enrollment')->insert([
        'student_id' => 1, 'academic_year_id' => 2, 'campus_id' => 2, 'grade_id' => 1, 'class_id' => 1,
    ]);
    $controller = new StudentIdCardQrController;
    $selected = $controller->index(idCardRequest('/students/id-card-qr', 'GET', [
        'academic_year_id' => 2, 'campus_id' => 2,
    ]))->getData();
    $empty = $controller->index(idCardRequest('/students/id-card-qr', 'GET', [
        'academic_year_id' => 2, 'campus_id' => 1,
    ]))->getData();

    expect($selected['classes']->pluck('label')->all())->toBe(['N-A'])
        ->and($empty['classes'])->toBeEmpty();
});

it('avoids repeating a grade already included in a class name', function (string $code, string $class, string $expected) {
    DB::table('tb_grade')->where('id', 1)->update(['grade' => $code, 'grade_short_name' => $code]);
    DB::table('tb_class')->where('id', 1)->update(['class_name' => $class]);
    $data = (new StudentIdCardQrController)->index(idCardRequest('/students/id-card-qr', 'GET', [
        'academic_year_id' => 1, 'campus_id' => 1,
    ]))->getData();

    expect($data['classes']->first()->label)->toBe($expected);
})->with([
    ['K1-', 'K1-A', 'K1-A'],
    ['N-', 'N-A', 'N-A'],
    ['1', '1A', '1A'],
    ['G10', 'A', '10A'],
    ['Grade 1', 'A', '1A'],
    ['Nursery', 'A', 'N-A'],
]);
