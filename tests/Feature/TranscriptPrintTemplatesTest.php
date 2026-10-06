<?php

use Illuminate\Support\Str;
use App\Support\TranscriptPdfLayout;
use App\Support\TranscriptPdfRenderer;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\File;

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

it('uses UTF-8 and bundled Khmer fonts in all standalone transcript print modes', function ($level, $mode) {
    $html = view('reports.print', [
        'filters' => ['transcript_level' => $level, 'print_mode' => $mode, 'report_date' => '2026-10-06'],
        'enrollments' => transcriptPrintTestRows(), 'type' => 'moeys-sikkhakarik-book',
        'title' => 'Transcript test', 'academicYear' => null, 'campus' => null,
    ])->render();
    expect(mb_check_encoding($html, 'UTF-8'))->toBeTrue()
        ->and($html)->toContain('<meta charset="utf-8">', 'data:font/ttf;base64,', 'សិស្ស សាកល្បង');
    foreach (['KhmerOSsiemreap.ttf', 'KhmerOSmuollight.ttf'] as $file) {
        expect($html)->toContain(base64_encode(file_get_contents(public_path('fonts/khmer/'.$file))));
    }
})->with('transcript print modes');

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
            ->and(substr_count($html, '/reports/transcript-templates/'.$level.'/'))->toBe($pageCount)
            ->and($html)->toContain('?v='.substr(hash_file('sha256', resource_path('report-templates/transcript-book/'.$level.'/'.($mode === 'cover' ? 'page-1.jpg' : 'page-3.jpg'))), 0, 12))
            ->and($html)->not->toContain('data:image/jpeg;base64,')
            ->and($html)->toContain('សិស្ស សាកល្បង')
            ->not->toContain('data-report-print-unavailable');
    } finally {
        $this->app->useStoragePath($originalStoragePath);
    }
})->with('transcript print modes');

it('keeps a 50-student browser print request small and includes every page', function ($level, $mode, $pageCount) {
    $html = view('reports.print', [
        'filters' => ['transcript_level' => $level, 'print_mode' => $mode, 'report_date' => '2026-10-06'],
        'enrollments' => collect(array_fill(0, 50, transcriptPrintTestRows()->first())),
        'type' => 'moeys-sikkhakarik-book', 'title' => 'Transcript test', 'academicYear' => null, 'campus' => null,
    ])->render();
    expect(strlen($html))->toBeLessThan(2 * 1024 * 1024)
        ->and(substr_count($html, '<section class="transcript-template-page '))->toBe(50 * $pageCount)
        ->and(substr_count($html, '/reports/transcript-templates/'.$level.'/'))->toBe(50 * $pageCount)
        ->and($html)->not->toContain('data:image/jpeg;base64,', 'http://localhost/reports/transcript-templates');
})->with('transcript print modes');

it('serves only bundled transcript images through the authenticated image route', function () {
    $url = route('reports.transcript-template', ['level' => 'primary', 'page' => 'page-3.jpg'], false);
    $this->get($url)->assertRedirect(route('login'));
    $this->withoutMiddleware();
    $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    expect($response->baseResponse->getFile()->getPathname())->toBe(resource_path('report-templates/transcript-book/primary/page-3.jpg'))
        ->and($response->headers->get('Cache-Control'))->toContain('private', 'max-age=86400');
    $this->get('/reports/transcript-templates/invalid/page-3.jpg')->assertNotFound();
    $this->get('/reports/transcript-templates/primary/page-5.jpg')->assertNotFound();
});

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

it('exports more than 25 students through the controller fallback with every content page', function ($level, $pagesPerStudent) {
    $rows = collect(range(1, 30))->map(function ($index) {
        $row = transcriptPrintTestRows()->first();
        $row->student->full_name_kh .= ' '.$index;
        return $row;
    });
    $html = view('reports.print', [
        'filters' => ['transcript_level' => $level, 'print_mode' => 'content', 'report_date' => '2026-10-06'],
        'enrollments' => $rows, 'pdfMode' => true, 'type' => 'moeys-sikkhakarik-book',
        'title' => 'Transcript test', 'academicYear' => null, 'campus' => null,
    ])->render();
    $controller = new \App\Http\Controllers\ReportsController;
    $method = new ReflectionMethod($controller, 'makeDomPdfPath');
    $path = $method->invoke($controller, $html, 'moeys-sikkhakarik-book');
    try {
        $parser = new \setasign\Fpdi\PdfParser\PdfParser(\setasign\Fpdi\PdfParser\StreamReader::createByFile($path));
        $reader = new \setasign\Fpdi\PdfReader\PdfReader($parser);
        expect($reader->getPageCount())->toBe(30 * $pagesPerStudent)->and(filesize($path))->toBeGreaterThan(1000);
        unset($reader, $parser);
    } finally {
        @unlink($path);
    }
})->with(['primary content' => ['primary', 9], 'secondary content' => ['secondary', 11]]);

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

function transcriptPdfLayoutTestDirectory(): string
{
    static $path;
    return $path ??= dirname(__DIR__, 2) . '/storage/framework/testing/transcript-pdf-' . Str::uuid();
}

afterAll(function () {
    (new \Illuminate\Filesystem\Filesystem)->deleteDirectory(transcriptPdfLayoutTestDirectory());
});

it('shapes Khmer in the transcript fallback PDF while keeping page counts and field bounds', function ($level, $mode, $pageCount) {
    $rows = transcriptPrintTestRows();
    $rows->first()->student->full_name_kh = 'ងួន ពិចិត្រ';
    $rows->first()->student->full_name_en = str_repeat('LONG STUDENT NAME ', 6);
    $rows->first()->student->familyMembers = collect([
        (object) ['relationship_type' => 'father', 'full_name_kh' => 'ឈឿន ស៊ីយ៉ា', 'occupation_kh' => 'មន្ត្រីរាជការ'],
        (object) ['relationship_type' => 'mother', 'full_name_kh' => 'លុន សាវេត', 'occupation_kh' => 'មន្ត្រីរាជការ'],
    ]);
    $html = view('reports.print', [
        'filters' => ['transcript_level' => $level, 'print_mode' => $mode, 'report_date' => '2026-10-06'],
        'enrollments' => collect([$rows->first(), $rows->first()]), 'pdfMode' => true,
        'type' => 'moeys-sikkhakarik-book', 'title' => 'Transcript test', 'academicYear' => null, 'campus' => null,
    ])->render();
    $html = str_replace('</body>', '<style>.transcript-template-page{width:297mm;height:210mm;page-break-inside:avoid}.transcript-template-page img{position:absolute;left:0;top:0;width:297mm;height:210mm}</style></body>', $html);
    if (PHP_OS_FAMILY === 'Windows') $html = preg_replace('~file:///([A-Za-z]:/)~', 'file://$1', $html);
    $temp = transcriptPdfLayoutTestDirectory();
    File::ensureDirectoryExists($temp);
    $layout = new Dompdf(['chroot' => [public_path(), resource_path('report-templates/transcript-book'), $temp],
        'tempDir' => $temp, 'fontDir' => $temp, 'fontCache' => $temp]);
    $layout->loadHtml($html, 'UTF-8');
    $layout->setPaper('a4', 'landscape');
    $path = $temp.'/'.$level.'-'.$mode.'.pdf';
    $rendered = TranscriptPdfRenderer::save($layout, $path, $temp.'/shaped');
    expect($layout->getCanvas()->get_page_count())->toBe(2)
        ->and(array_keys($rendered))->toBe([1, $pageCount + 1]);
    $parser = new \setasign\Fpdi\PdfParser\PdfParser(\setasign\Fpdi\PdfParser\StreamReader::createByFile($path));
    $reader = new \setasign\Fpdi\PdfReader\PdfReader($parser);
    expect($reader->getPageCount())->toBe(2 * $pageCount)->and($rendered)->toHaveCount(2);
    foreach ($rendered as $fields) {
        $name = $fields[$mode === 'cover' ? 'transcript-cover-student-name' : 'transcript-content-student-name'];
        expect($name['text'])->toBe('ងួន ពិចិត្រ')->and($name['shaped'])->not->toBe($name['text'])->not->toContain("\u{25CC}");
        foreach ($fields as $field) expect($field['rendered_width'])->toBeLessThanOrEqual($field['width'] + 0.1)
            ->and($field['baseline'])->toBeGreaterThan(0)->toBeLessThan(595.28);
    }
    expect(file_get_contents($path))->toContain('/Subtype /Type0');
})->with('transcript print modes');

it('renders transcript values inside their template rows in the fallback PDF', function ($level, $mode, $pageCount) {
    $rows = transcriptPrintTestRows();
    $rows->first()->student->full_name_kh = str_repeat('សិស្ស សាកល្បង ', 4);
    $rows->first()->student->full_name_en = str_repeat('LONG STUDENT NAME ', 8);
    $rows->first()->student->current_address_kh = str_repeat('រាជធានីភ្នំពេញ ', 12);
    $rows->first()->student->familyMembers = collect([
        (object) ['relationship_type' => 'father', 'full_name_kh' => 'សុខ ដារ៉ា', 'occupation_kh' => 'បុគ្គលិកក្រុមហ៊ុន'],
        (object) ['relationship_type' => 'mother', 'full_name_kh' => 'សុខ សុភា', 'occupation_kh' => 'មន្ត្រីរាជការ'],
    ]);
    $html = view('reports.print', [
        'filters' => ['transcript_level' => $level, 'print_mode' => $mode, 'report_date' => '2026-10-05'],
        'enrollments' => collect([$rows->first(), $rows->first()]),
        'type' => 'moeys-sikkhakarik-book', 'title' => 'Transcript test',
        'academicYear' => null, 'campus' => null, 'pdfMode' => true,
    ])->render();
    $html = str_replace('</body>', '<style>
        .transcript-template-page{width:297mm;height:210mm;page-break-inside:avoid}
        .transcript-template-page img{position:absolute;left:0;top:0;width:297mm;height:210mm}
        </style></body>', $html);
    if (PHP_OS_FAMILY === 'Windows') $html = preg_replace('~file:///([A-Za-z]:/)~', 'file://$1', $html);

    // DomPDF caches registered fonts across instances in the same PHP process.
    $temp = transcriptPdfLayoutTestDirectory();
    File::ensureDirectoryExists($temp);
    $dompdf = new Dompdf([
        'chroot' => [public_path(), resource_path('report-templates/transcript-book'), $temp],
        'tempDir' => $temp, 'fontDir' => $temp, 'fontCache' => $temp,
    ]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('a4', 'landscape');
    TranscriptPdfLayout::configure($dompdf);
    $prepare = $dompdf->getCallbacks()['begin_page_reflow'][0];
    $rendered = [];
    $dompdf->setCallbacks([
        ['event' => 'begin_page_reflow', 'f' => $prepare],
        ['event' => 'begin_frame', 'f' => static function ($frame, $canvas, $fontMetrics) use (&$rendered): void {
            if (!$frame->is_text_node() || trim($frame->get_node()->textContent) === '') return;
            $parent = $frame->get_parent();
            $node = $parent->get_node();
            if (!$node instanceof DOMElement) return;
            $classes = preg_split('/\s+/', $node->getAttribute('class'));
            if (!array_intersect($classes, ['transcript-cover-field', 'transcript-content-field'])) return;
            $style = $frame->get_style();
            $field = $parent->get_style();
            $key = end($classes);
            $rendered[$canvas->get_page_number()][$key][] = [
                'baseline' => $frame->get_position('y') + $fontMetrics->getFontBaseline($style->font_family, $style->font_size),
                'width' => $frame->get_margin_width(),
                'available' => (float) $field->length_in_pt($field->width, $canvas->get_width()),
            ];
        }],
    ]);
    $dompdf->render();
    expect($dompdf->getCanvas()->get_page_count())->toBe(2 * $pageCount)
        ->and(count($rendered))->toBe(2);

    // Bounds come from the dotted rows on the templates, in A4 PDF points.
    $bounds = $mode === 'cover'
        ? ($level === 'primary' ? [
            'transcript-cover-school' => [428, 447],
            'transcript-cover-student-name' => [480, 496],
            'transcript-cover-dob' => [500, 516],
            'transcript-cover-birth-place' => [522, 537],
        ] : [
            'transcript-cover-school' => [433, 453],
            'transcript-cover-student-name' => [461, 477],
            'transcript-cover-dob' => [485, 502],
            'transcript-cover-birth-place' => [507, 525],
        ])
        : ($level === 'primary' ? [
            'transcript-content-student-name' => [134, 150],
            'transcript-content-dob' => [158, 175],
            'transcript-content-birth-place' => [180, 195],
            'transcript-content-father-name' => [202, 216],
            'transcript-content-father-occupation' => [221, 236],
            'transcript-content-mother-name' => [244, 259],
            'transcript-content-mother-occupation' => [263, 278],
            'transcript-content-current-address' => [285, 303],
            'transcript-content-print-day' => [319, 336],
            'transcript-content-print-month' => [319, 336],
            'transcript-content-print-year' => [319, 336],
        ] : [
            'transcript-content-student-name' => [205, 221],
            'transcript-content-student-name-en' => [205, 221],
            'transcript-content-dob' => [229, 245],
            'transcript-content-birth-place' => [251, 266],
            'transcript-content-current-address' => [273, 291],
            'transcript-content-father-name' => [298, 311],
            'transcript-content-father-occupation' => [319, 335],
            'transcript-content-mother-name' => [343, 358],
            'transcript-content-mother-occupation' => [365, 381],
        ]);
    foreach ($rendered as $fields) foreach ($bounds as $key => [$top, $bottom]) {
        expect($fields)->toHaveKey($key);
        expect($fields[$key])->toHaveCount(1);
        $value = $fields[$key][0];
        expect($value['baseline'])->toBeGreaterThanOrEqual($top)->toBeLessThanOrEqual($bottom)
            ->and($value['width'])->toBeLessThanOrEqual($value['available'] + 0.1);
    }
})->with('transcript print modes');
