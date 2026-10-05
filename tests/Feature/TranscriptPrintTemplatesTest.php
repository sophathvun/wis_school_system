<?php

use Illuminate\Support\Str;

function transcriptPrintTestRows(): \Illuminate\Support\Collection
{
    return collect([(object) [
        'student' => (object) [
            'full_name_kh' => 'សិស្ស សាកល្បង',
            'full_name_en' => 'TEST STUDENT',
            'date_of_birth' => '2012-09-13',
            'birthProvince' => (object) ['province_name_kh' => 'កំពង់ចាម'],
            'current_address_kh' => 'រាជធានីភ្នំពេញ',
            'familyMembers' => collect(),
        ],
        'campus' => (object) [
            'addressCommune' => null,
            'addressDistrict' => null,
            'addressProvince' => null,
        ],
    ]]);
}

dataset('transcript print modes', [
    'primary cover' => ['primary', 'cover', 2],
    'primary content' => ['primary', 'content', 9],
    'secondary cover' => ['secondary', 'cover', 2],
    'secondary content' => ['secondary', 'content', 11],
]);

it('renders transcript pages without legacy import files on the server', function ($level, $mode, $pageCount) {
    $originalStoragePath = $this->app->storagePath();
    $this->app->useStoragePath(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'transcript-no-imports-' . Str::uuid());

    try {
        expect(is_dir(storage_path('app/imports')))->toBeFalse();
        $html = view('reports._moeys-transcript-template-book', [
            'filters' => ['transcript_level' => $level, 'print_mode' => $mode, 'report_date' => '2026-10-05'],
            'enrollments' => transcriptPrintTestRows(),
        ])->render();

        expect(substr_count($html, '<section class="transcript-template-page '))->toBe($pageCount)
            ->and(substr_count($html, 'data:image/jpeg;base64,'))->toBe($pageCount)
            ->and($html)->toContain('សិស្ស សាកល្បង')
            ->not->toContain('data-report-print-unavailable');
    } finally {
        $this->app->useStoragePath($originalStoragePath);
    }
})->with('transcript print modes');

it('shows a visible message instead of blank pages when bundled templates are missing', function ($level, $mode) {
    $originalBasePath = $this->app->basePath();
    $this->app->setBasePath(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'transcript-no-assets-' . Str::uuid());

    try {
        $html = view('reports._moeys-transcript-template-book', [
            'filters' => ['transcript_level' => $level, 'print_mode' => $mode],
            'enrollments' => transcriptPrintTestRows(),
        ])->render();

        expect($html)->toContain('data-report-print-unavailable', 'Transcript Book templates are unavailable.')
            ->not->toContain('<section class="transcript-template-page ');
    } finally {
        $this->app->setBasePath($originalBasePath);
    }
})->with('transcript print modes');

it('keeps the no-students message for an empty transcript selection', function () {
    $html = view('reports._moeys-transcript-template-book', [
        'filters' => ['transcript_level' => 'primary', 'print_mode' => 'cover'],
        'enrollments' => collect(),
    ])->render();

    expect($html)->toContain('No students found.')->not->toContain('data-report-print-unavailable');
});

it('keeps class PDF markup small by referencing the local template files', function ($level, $mode, $pageCount) {
    $studentCount = 19;
    $html = view('reports._moeys-transcript-template-book', [
        'filters' => ['transcript_level' => $level, 'print_mode' => $mode, 'report_date' => '2026-10-05'],
        'enrollments' => collect(array_fill(0, $studentCount, transcriptPrintTestRows()->first())),
        'pdfMode' => true,
    ])->render();

    expect(substr_count($html, '<section class="transcript-template-page '))->toBe($pageCount * $studentCount)
        ->and($html)->toContain('file://')->not->toContain('data:image/jpeg;base64,')
        ->and(strlen($html))->toBeLessThan(1024 * 1024);
})->with('transcript print modes');
