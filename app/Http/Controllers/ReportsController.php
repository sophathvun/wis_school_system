<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\BrandingSetting;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolInfo;
use App\Models\Session;
use App\Models\StudentEnrollment;
use App\Models\StudentEnrollmentHistory;
use App\Support\K3CertificatePdfLayout;
use App\Services\K3CertificateReport;
use App\Services\G9CertificateReport;
use App\Support\G9CertificatePdfLayout;
use App\Services\G12CertificateReport;
use App\Services\StudentPhotoReport;
use App\Support\G12CertificatePdfLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;
use Illuminate\Support\Str;
use App\Support\AcademicReportPermissions;

class ReportsController
{
    private const PREVIEW_LIMIT = 500;
    private const GET_STUDENT_LIST_PREVIEW_DEFAULT_SIZE = 25;
    private const GET_STUDENT_LIST_PREVIEW_SIZES = ['all', '25', '50', '75', '100'];

    public function index(Request $request)
    {
        $reportTypes = AcademicReportPermissions::visibleTypes($request->user());
        abort_unless($reportTypes, 403);
        $type = $request->query('type', array_key_first($reportTypes));
        abort_unless(is_string($type) && isset(AcademicReportPermissions::REPORTS[$type]), 404);
        abort_unless(isset($reportTypes[$type]), 403);
        if ($type === 'moeys-id-number-book' && $request->boolean('filter_options')) {
            return $this->customStudentListFilterOptions($request);
        }
        $payload = $this->reportPayload($request, $type, true);

        return view('reports.index', $payload + [
            'type' => $type,
            'reportTypes' => $reportTypes,
            'isReportStub' => $this->isReportStub($type),
            'academicYears' => $this->academicYears($payload['filters'], $type),
            'campuses' => $payload['photoCampuses'] ?? $this->campuses($request, $payload['filters'], $type),
            'grades' => Grade::where('status', 1)->orderByRaw('CAST(grade_order AS UNSIGNED)')->get(['id', 'grade']),
            'gradeClassOptions' => $payload['photoGradeClassOptions'] ?? (($this->isStudentListReport($type) || $this->isStudentContactListReport($type) || $type === 'attendance-list' || $type === 'score-list' || $type === 'moeys-id-number-book' || $type === 'moeys-sikkhakarik-book') ? $this->gradeClassOptions($request, $payload['filters'], $type) : ($type === 'withdrawn-students' ? $this->withdrawalGradeClassOptions($request, $payload['filters']) : collect())),
            'groupOptions' => ($this->isStudentListReport($type) || $this->isStudentContactListReport($type) || $type === 'moeys-sikkhakarik-book') ? $this->groupOptions($request, $payload['filters']) : ($type === 'withdrawn-students' ? $this->withdrawalGroupOptions($request, $payload['filters']) : collect()),
        ]);
    }

    private function customStudentListFilterOptions(Request $request)
    {
        $filters = $request->validate([
            'period_type' => ['nullable', 'in:all,regular,summer'],
            'academic_year_id' => ['nullable', 'integer'],
            'campus_id' => ['nullable', 'integer'],
        ]);
        $filters['period_type'] = $filters['period_type'] ?? 'all';
        if (!empty($filters['academic_year_id']) && $filters['period_type'] !== 'all') {
            $period = AcademicYear::whereKey($filters['academic_year_id'])->value('period_type');
            if ($period !== $filters['period_type']) {
                unset($filters['academic_year_id']);
            }
        }

        return response()->json([
            'academicYearId' => (string) ($filters['academic_year_id'] ?? ''),
            'academicYears' => $this->academicYears($filters, 'moeys-id-number-book')
                ->map(fn ($year) => ['value' => (string) $year->id, 'label' => $year->academic_year])->values(),
            'gradeClassOptions' => $this->gradeClassOptions($request, $filters, 'moeys-id-number-book')->values(),
        ]);
    }

    public function excel(Request $request, string $type)
    {
        abort_unless(isset(AcademicReportPermissions::labels()[$type]), 404);
        abort_if(in_array($type, ['k3-certificate-wis', 'g9-certificate-wis', 'g12-certificate-wis', 'student-photo'], true), 404);

        $payload = $this->reportPayload($request, $type);
        if ($type === 'student-id-books-moeys' && $request->query('print_mode') === 'cover') {
            $academicYear = $request->filled('academic_year_id') ? AcademicYear::find($request->integer('academic_year_id')) : null;
            $campus = $request->filled('campus_id') ? SchoolInfo::find($request->integer('campus_id')) : null;
            $filename = 'student-id-book-cover-' . now('Asia/Phnom_Penh')->format('Ymd-His') . '.xlsx';
            $path = $this->studentIdBookCoverExcelPath($this->studentIdBookCoverData($payload, $academicYear, $campus));
        } else {
            $filename = str_replace(' ', '-', strtolower(AcademicReportPermissions::labels()[$type])) . '-' . now('Asia/Phnom_Penh')->format('Ymd-His') . '.xlsx';
            $path = $this->reportExcelPath($payload, $type);
        }

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }
    public function pdf(Request $request, string $type)
    {
        abort_unless(isset(AcademicReportPermissions::labels()[$type]), 404);
        abort_if($type === 'student-photo', 404);

        $payload = $this->reportPayload($request, $type);
        $mode = $request->query('pdf_mode', 'combined');

        if (($this->isStudentListReport($type) || $this->isStudentContactListReport($type) || $type === 'attendance-list' || $type === 'score-list') && $mode === 'separate') {
            return $this->separateReportPdfZip($request, $payload, $type);
        }

        $filename = $type === 'student-id-books-moeys' && $request->query('print_mode') === 'cover'
            ? 'student-id-book-cover-' . now('Asia/Phnom_Penh')->format('Ymd-His') . '.pdf'
            : $this->reportPdfFilename($payload['enrollments'] ?? collect(), $type, false);
        $path = $this->makeReportPdfPath($request, $payload, $type);

        if ($type === 'moeys-sikkhakarik-book' && $request->boolean('inline')) {
            return response()
                ->file($path, ['Content-Type' => 'application/pdf'])
                ->setContentDisposition('inline', $filename)
                ->deleteFileAfterSend(true);
        }

        return response()
            ->download($path, $filename, ['Content-Type' => 'application/pdf'])
            ->deleteFileAfterSend(true);
    }

    public function transcriptTemplate(string $level, string $page)
    {
        abort_unless(in_array($level, ['primary', 'secondary'], true) && preg_match('/^page-[1-4]\.jpg$/', $page), 404);
        $path = resource_path('report-templates/transcript-book/'.$level.'/'.$page);
        abort_unless(is_file($path) && is_readable($path), 404);
        return response()->file($path, ['Content-Type' => 'image/jpeg'])->setPrivate()->setMaxAge(86400);
    }

    public function show(Request $request, string $type)
    {
        abort_unless(isset(AcademicReportPermissions::labels()[$type]), 404);
        $payload = $this->reportPayload($request, $type);
        if ($type === 'student-photo') {
            return view('reports.student-photo-print', $payload);
        }
        if ($type === 'k3-certificate-wis') {
            abort_unless($payload['certificateReady'], 422, 'Save the Given Date and assign certificate numbers before printing.');
            return view('reports.k3-certificate-print', $payload);
        }
        if ($type === 'g9-certificate-wis') {
            abort_unless($payload['certificateReady'], 422, 'Save the Given Date and assign certificate numbers before printing.');
            return view('reports.g9-certificate-print', $payload);
        }
        if ($type === 'g12-certificate-wis') {
            abort_unless($payload['certificateReady'], 422, 'Save the Given Date and assign certificate numbers before printing.');
            return view('reports.g12-certificate-print', $payload);
        }
        $academicYear = $request->filled('academic_year_id') ? AcademicYear::find($request->integer('academic_year_id')) : null;
        $campus = $request->filled('campus_id') ? SchoolInfo::find($request->integer('campus_id')) : null;

        if ($type === 'student-id-books-moeys' && $request->query('print_mode') === 'cover') {
            return view('reports.student-id-book-cover', $payload + [
                'type' => $type,
                'title' => 'Student ID Book Cover',
                'academicYear' => $academicYear,
                'campus' => $campus,
                'cover' => $this->studentIdBookCoverData($payload, $academicYear, $campus),
            ]);
        }

        $printView = view('reports.print', $payload + [
            'type' => $type,
            'title' => AcademicReportPermissions::labels()[$type],
            'academicYears' => $this->academicYears($payload['filters'] ?? []),
            'academicYear' => $academicYear,
            'campus' => $campus,
        ]);
        return $type === 'moeys-sikkhakarik-book'
            ? response($printView->render())->header('Content-Type', 'text/html; charset=UTF-8')
            : $printView;
    }

    public function saveK3Certificates(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('tb_academic_year', 'id')->where('period_type', 'regular')->whereNull('deleted_at')],
            'action' => ['required', 'in:save_date,assign,update_prefix,save_style,reset_style'],
        ]);
        if ($data['action'] === 'save_style') {
            $data += $request->validate(\App\Support\K3CertificateTypography::rules());
        } elseif ($data['action'] !== 'reset_style') {
            $data += $request->validate([
                'given_date'=>[$data['action'] === 'save_date' ? 'required' : 'nullable','date_format:Y-m-d'],
                'number_prefix'=>[$data['action'] === 'update_prefix' ? 'required' : 'nullable','string','max:16','regex:/^[A-Za-z0-9-]+$/'],
            ]);
        }
        $count = app(K3CertificateReport::class)->save($request, $data);
        $message = match ($data['action']) {
            'save_style' => 'Certificate fonts saved for this academic year.',
            'reset_style' => 'Certificate fonts restored to the original style for this academic year.',
            'assign' => "Assigned {$count} new certificate numbers. Given Date saved.",
            'update_prefix' => $count ? "Prefix updated on {$count} certificates. Student sequence numbers and Given Date kept unchanged." : 'Certificate Prefix saved for this academic year.',
            default => 'Given Date saved for this academic year.',
        };
        return redirect()->route('reports.index', ['type' => 'k3-certificate-wis', 'academic_year_id' => $data['academic_year_id']] + $request->only(['campus_id', 'grade_class', 'certificate_student_id', 'certificate_show_qr']))
            ->with('success', $message)->with('k3_action', $data['action']);
    }

    public function saveK3CertificateTemplate(Request $request)
    {
        $data = $request->validate(['template_data'=>['required','json','max:30000'],'template_version'=>['required','integer','min:0']]);
        $layout = json_decode($data['template_data'],true);
        $validated = \Illuminate\Support\Facades\Validator::make(is_array($layout)?$layout:[],\App\Support\K3CertificateLayout::rules())->validate();
        app(K3CertificateReport::class)->saveTemplate($request,$validated,(int)$data['template_version']);
        return redirect()->route('reports.index',['type'=>'k3-certificate-wis']+$request->only(['academic_year_id','campus_id','grade_class','certificate_student_id','certificate_show_qr']))
            ->with('success','K3 certificate template saved. It will be used for certificates in all academic years.')->with('k3_action','save_template');
    }

    public function generateIdBookListCodes(Request $request, string $type)
    {
        abort_unless($type === 'student-id-books-moeys', 404);

        $filters = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:tb_academic_year,id'],
            'period_type' => ['nullable', 'in:all,regular,summer'],
            'campus_id' => ['required', 'integer', 'exists:tb_school_info,id'],
            'id_book_level' => ['required', 'in:kindergarten,primary,secondary'],
            'id_book_start_number' => ['required', 'integer', 'min:1', 'max:99999'],
        ]);
        $filters['period_type'] = $filters['period_type'] ?? 'all';

        $rows = $this->enrollments($request, $filters, 'student-id-books-moeys')->get();
        $codedCount = $rows->filter(fn ($row) => filled($row->id_book_list_no))->count();
        if ($codedCount > 0) {
            $message = "List codes already generated for this Academic Year, Book Level, and Campus.";

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'count' => $codedCount,
                ], 409);
            }

            return redirect()
                ->route('reports.index', ['type' => $type] + $request->only(['period_type', 'academic_year_id', 'id_book_level', 'campus_id']))
                ->with('error', $message);
        }

        $updated = DB::transaction(function () use ($rows, $filters) {
            $number = (int) $filters['id_book_start_number'];

            foreach ($rows as $row) {
                if ($number > 99999) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'id_book_start_number' => 'The generated number cannot be more than 99999.',
                    ]);
                }

                StudentEnrollment::whereKey($row->id)->update([
                    'id_book_list_no' => str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                ]);
                $number++;
            }

            return $rows->count();
        });

        $message = "Generated {$updated} list codes.";
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'count' => $updated,
            ]);
        }

        return redirect()
            ->route('reports.index', ['type' => $type] + $request->only(['period_type', 'academic_year_id', 'id_book_level', 'campus_id']))
            ->with('success', $message);
    }

    private function makeReportPdfPath(Request $request, array $payload, string $type): string
    {
        if ($type === 'g9-certificate-wis') {
            abort_unless($payload['certificateReady'], 422, 'Save the Given Date and assign certificate numbers before exporting.');
            $html = view('reports.g9-certificate-print', $payload + ['pdfMode' => true])->render();
            return $this->makeDomPdfPath(G9CertificatePdfLayout::prepareHtml($html), $type);
        }
        if ($type === 'g12-certificate-wis') {
            abort_unless($payload['certificateReady'], 422, 'Save the Given Date and assign certificate numbers before exporting.');
            $html = view('reports.g12-certificate-print', $payload + ['pdfMode' => true])->render();
            return $this->makeDomPdfPath(G12CertificatePdfLayout::prepareHtml($html), $type);
        }
        if ($type === 'k3-certificate-wis') {
            abort_unless($payload['certificateReady'], 422, 'Save the Given Date and assign certificate numbers before exporting.');
            $html = view('reports.k3-certificate-print', $payload + ['pdfMode' => true])->render();
            return $this->makeDomPdfPath(K3CertificatePdfLayout::prepareHtml($html), $type);
        }
        $first = ($payload['enrollments'] ?? collect())->first();
        [$pdfKhmerLunarDate, $pdfKhmerSolarDate] = $this->moeysKhmerDateLines($payload['filters']['report_date'] ?? null, $first?->campus);
        $academicYear = $request->filled('academic_year_id') ? AcademicYear::find($request->integer('academic_year_id')) : null;
        $campus = $request->filled('campus_id') ? SchoolInfo::find($request->integer('campus_id')) : null;
        $html = ($type === 'student-id-books-moeys' && $request->query('print_mode') === 'cover'
            ? view('reports.student-id-book-cover', $payload + [
                'type' => $type,
                'title' => 'Student ID Book Cover',
                'academicYear' => $academicYear,
                'campus' => $campus,
                'cover' => $this->studentIdBookCoverData($payload, $academicYear, $campus),
                'pdfMode' => true,
            ])
            : view('reports.print', $payload + [
                'type' => $type,
                'title' => AcademicReportPermissions::labels()[$type],
                'academicYears' => $this->academicYears($payload['filters'] ?? []),
                'academicYear' => $academicYear,
                'campus' => $campus,
                'pdfMode' => true,
                'pdfLogoSrc' => $this->reportPdfLogoDataUri(),
                'pdfKhmerLunarDate' => $pdfKhmerLunarDate,
                'pdfKhmerSolarDate' => $pdfKhmerSolarDate,
            ]))->render();

        $htmlPath = $this->reportTempPath('html-', '.html');
        $pdfPath = $this->reportTempPath('pdf-', '.pdf');
        file_put_contents($htmlPath, $html);
        @unlink($pdfPath);

        $chrome = $this->chromeExecutablePath();
        if ($chrome === null) {
            @unlink($htmlPath);
            return $this->makeDomPdfPath($html, $type);
        }

        if (!function_exists('proc_open')) {
            @unlink($htmlPath);
            @unlink($pdfPath);
            return $this->makeDomPdfPath($html, $type);
        }

        $chromeProfilePath = $this->reportTempDirectory('chrome-profile-' . (string) Str::uuid());
        $chromeCrashPath = $this->reportTempDirectory('chrome-crashes');
        $fileUrl = $this->localFileUrl($htmlPath);
        $command = [
            $chrome,
            '--headless',
            '--disable-gpu',
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--allow-file-access-from-files',
            '--disable-dev-shm-usage',
            '--no-zygote',
            '--disable-software-rasterizer',
            '--font-render-hinting=none',
            '--user-data-dir=' . $chromeProfilePath,
            '--crash-dumps-dir=' . $chromeCrashPath,
            '--run-all-compositor-stages-before-draw',
            '--virtual-time-budget=1000',
            '--print-to-pdf=' . $pdfPath,
            '--no-pdf-header-footer',
            '--print-to-pdf-no-header',
            $fileUrl,
        ];
        try {
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        } catch (\Throwable $exception) {
            Log::warning('Chrome PDF export could not start.', [
                'type' => $type,
                'message' => $exception->getMessage(),
            ]);
            @unlink($htmlPath);
            @unlink($pdfPath);
            $this->removeDirectory($chromeProfilePath);

            return $this->makeDomPdfPath($html, $type);
        }
        if (!is_resource($process)) {
            Log::warning('Chrome PDF export returned no process resource.', ['type' => $type]);
            @unlink($htmlPath);
            @unlink($pdfPath);
            $this->removeDirectory($chromeProfilePath);

            return $this->makeDomPdfPath($html, $type);
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        @unlink($htmlPath);
        $this->removeDirectory($chromeProfilePath);

        if ($exitCode !== 0 || !is_file($pdfPath) || filesize($pdfPath) < 1000) {
            Log::warning('Chrome PDF export failed, falling back to DomPDF.', [
                'type' => $type,
                'exit_code' => $exitCode,
                'stdout' => Str::limit(trim($stdout), 1000),
                'stderr' => Str::limit(trim($stderr), 1000),
                'pdf_exists' => is_file($pdfPath),
                'pdf_size' => is_file($pdfPath) ? filesize($pdfPath) : 0,
            ]);
            @unlink($pdfPath);
            return $this->makeDomPdfPath($html, $type);
        }

        return $pdfPath;
    }

    private function makeDomPdfPath(string $html, string $type): string
    {
        if ($type === 'moeys-sikkhakarik-book') {
            // DomPDF has no aspect-ratio support; anchor each template to one A4 page.
            $html = str_replace('</body>', '<style>
                .transcript-template-page{width:297mm;height:210mm;page-break-inside:avoid}
                .transcript-template-page img{position:absolute;left:0;top:0;width:297mm;height:210mm}
                </style></body>', $html);
        }
        if (PHP_OS_FAMILY === 'Windows') {
            // DomPDF resolves drive-letter paths without the browser's extra slash.
            $html = preg_replace('~file:///([A-Za-z]:/)~', 'file://$1', $html);
        }
        $paper = in_array($type, ['student-list', 'student-contact-list', 'attendance-list', 'score-list', 'student-id-books-moeys', 'moeys-sikkhakarik-book', 'k3-certificate-wis', 'g9-certificate-wis', 'g12-certificate-wis'], true)
            ? 'landscape'
            : 'portrait';
        $pdfPath = $this->reportTempPath('dompdf-', '.pdf');
        $domPdfTempPath = $this->reportTempDirectory('dompdf-temp');
        $domPdfFontPath = $this->reportTempDirectory('dompdf-fonts');

        try {
            $pdf = Pdf::loadHTML($html, 'UTF-8')
                ->setPaper('a4', $paper)
                ->setOptions([
                    'isRemoteEnabled' => true,
                    'isHtml5ParserEnabled' => true,
                    'isFontSubsettingEnabled' => true,
                    'tempDir' => $domPdfTempPath,
                    'fontDir' => $domPdfFontPath,
                    'fontCache' => $domPdfFontPath,
                    'chroot' => [
                        public_path(),
                        resource_path('css'),
                        resource_path('report-templates/transcript-book'),
                        storage_path('app/public'),
                        storage_path('app/report-pdf-temp'),
                    ],
                ]);
            if ($type === 'moeys-sikkhakarik-book') {
                \App\Support\TranscriptPdfRenderer::save($pdf->getDomPDF(), $pdfPath, $this->reportTempDirectory('transcript-mpdf'));
            } elseif ($type === 'k3-certificate-wis') {
                K3CertificatePdfLayout::configure($pdf->getDomPDF());
            } elseif ($type === 'g9-certificate-wis') {
                G9CertificatePdfLayout::configure($pdf->getDomPDF());
            } elseif ($type === 'g12-certificate-wis') {
                G12CertificatePdfLayout::configure($pdf->getDomPDF());
            }
            if ($type !== 'moeys-sikkhakarik-book') $pdf->save($pdfPath);
        } catch (\Throwable $exception) {
            @unlink($pdfPath);
            Log::error('DomPDF report export failed.', [
                'type' => $type,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if (!is_file($pdfPath) || filesize($pdfPath) < 1000) {
            @unlink($pdfPath);
            throw new RuntimeException('PDF export failed.');
        }

        return $pdfPath;
    }

    private function reportTempDirectory(?string $subdirectory = null): string
    {
        $path = storage_path('app/report-pdf-temp' . ($subdirectory ? DIRECTORY_SEPARATOR . $subdirectory : ''));
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create report PDF temporary directory.');
        }

        return $path;
    }

    private function reportTempPath(string $prefix, string $extension = ''): string
    {
        $path = tempnam($this->reportTempDirectory(), $prefix);
        if ($path === false) {
            throw new RuntimeException('Unable to create report PDF temporary file.');
        }

        if ($extension === '') {
            return $path;
        }

        $target = $path . $extension;
        if (!rename($path, $target)) {
            @unlink($path);
            throw new RuntimeException('Unable to prepare report PDF temporary file.');
        }

        return $target;
    }

    private function localFileUrl(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return preg_match('/^[A-Za-z]:\//', $path) ? 'file:///' . $path : 'file://' . $path;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) return;

        $items = scandir($path);
        if ($items === false) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath) && !is_link($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                @unlink($itemPath);
            }
        }

        @rmdir($path);
    }

    private function chromeExecutablePath(): ?string
    {
        $candidates = [
            env('CHROME_PATH'),
            env('CHROMIUM_PATH'),
            env('PUPPETEER_EXECUTABLE_PATH'),
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/snap/bin/chromium',
            '/opt/google/chrome/chrome',
            '/opt/chromium.org/chromium/chromium',
        ];

        foreach ($candidates as $path) {
            if ($path && is_file($path)) return $path;
        }

        if (!function_exists('shell_exec')) {
            return null;
        }

        foreach (['google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser'] as $binary) {
            $path = trim((string) @shell_exec(PHP_OS_FAMILY === 'Windows' ? "where {$binary} 2>NUL" : "command -v {$binary} 2>/dev/null"));
            if ($path !== '') {
                $firstPath = strtok($path, PHP_EOL);
                if ($firstPath && is_file($firstPath)) return $firstPath;
            }
        }

        return null;
    }

    private function reportPdfLogoDataUri(): ?string
    {
        $path = $this->reportExcelLogoPath();
        if (!$path || !is_file($path)) return null;

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }
    private function separateReportPdfZip(Request $request, array $payload, string $type)
    {
        $enrollments = $payload['enrollments'] ?? collect();
        $groups = $enrollments
            ->groupBy(fn (StudentEnrollment $row) => ($row->grade_id ?: 0) . ':' . ($row->class_id ?: 0))
            ->sortBy(function ($rows) {
                $first = $rows->first();

                return sprintf(
                    '%06d-%06d-%s',
                    (int) ($first?->grade?->grade_order ?? 999999),
                    (int) ($first?->schoolClass?->class_order ?? 999999),
                    $this->gradeClassLabel($first),
                );
            });

        $zipPath = $this->reportTempPath('zip-', '.zip');
        $entries = [];
        $usedNames = [];

        foreach ($groups as $rows) {
            $classPayload = $payload;
            $classPayload['enrollments'] = $rows->values();
            $pdfPath = $this->makeReportPdfPath($request, $classPayload, $type);
            $filename = $this->uniqueArchiveName($this->reportPdfFilename($rows->values(), $type, true), $usedNames);
            $entries[$filename] = file_get_contents($pdfPath);
            @unlink($pdfPath);
        }

        if (!$entries) {
            $pdfPath = $this->makeReportPdfPath($request, $payload, $type);
            $entries[$this->reportPdfFilename(collect(), $type, true)] = file_get_contents($pdfPath);
            @unlink($pdfPath);
        }

        $this->writeZipArchive($zipPath, $entries);
        $zipFilename = str_replace(' ', '-', strtolower(AcademicReportPermissions::labels()[$type])) . '-pdf-' . now('Asia/Phnom_Penh')->format('Ymd-His') . '.zip';

        return response()
            ->download($zipPath, $zipFilename, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    private function reportPdfFilename($enrollments, string $type, bool $classSpecific = false): string
    {
        $first = $enrollments instanceof Collection ? $enrollments->first() : null;
        $grade = $classSpecific && $first ? $this->gradeClassLabel($first) : AcademicReportPermissions::labels()[$type];
        $academicYear = $first?->academicYear?->academic_year ?: 'All-Academic-Years';
        $reportName = $this->isStudentContactListReport($type) ? 'Student-Contact-List' : ($type === 'student-profile-label' ? 'Student-Profile-Label' : ($this->isStudentListReport($type) ? 'Student-List' : ($type === 'attendance-list' ? 'Attendance-List' : ($type === 'score-list' ? 'Score-List' : AcademicReportPermissions::labels()[$type]))));
        $parts = $classSpecific ? [$grade, $academicYear, $reportName] : [$reportName, $academicYear, now('Asia/Phnom_Penh')->format('Ymd-His')];

        return $this->safeReportFilename(collect($parts)->filter()->join('-')) . '.pdf';
    }

    private function safeReportFilename(string $filename): string
    {
        $filename = Str::ascii($filename);
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename);
        $filename = trim($filename, '-_.');

        return $filename ?: 'report';
    }

    private function uniqueArchiveName(string $filename, array &$usedNames): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'pdf';
        $candidate = $filename;
        $counter = 2;

        while (isset($usedNames[strtolower($candidate)])) {
            $candidate = $base . '-' . $counter . '.' . $extension;
            $counter++;
        }

        $usedNames[strtolower($candidate)] = true;

        return $candidate;
    }
    private function isStudentListReport(string $type): bool
    {
        return in_array($type, ['student-list', 'student-profile-label'], true);
    }

    private function isStudentContactListReport(string $type): bool
    {
        return $type === 'student-contact-list';
    }

    private function isStudentStatisticsReport(string $type): bool
    {
        return in_array($type, ['student-statistics', 'student-statistics-detail'], true);
    }

    private function isReportStub(string $type): bool
    {
        return false;
    }

    private function classGroups($enrollments): Collection
    {
        return collect($enrollments)
            ->groupBy(fn (StudentEnrollment $row) => ($row->grade_id ?: 0) . ':' . ($row->class_id ?: 0))
            ->sortBy(function ($rows) {
                $first = $rows->first();

                return sprintf(
                    '%06d-%06d-%s',
                    (int) ($first?->grade?->grade_order ?? 999999),
                    (int) ($first?->schoolClass?->class_order ?? 999999),
                    $this->gradeClassLabel($first),
                );
            });
    }

    private function reportExcelPath(array $payload, string $type): string
    {
        $path = tempnam(sys_get_temp_dir(), 'report-excel-');
        $xlsxPath = $path . '.xlsx';
        rename($path, $xlsxPath);

        $logoPath = $this->reportExcelLogoPath($type);
        if ($type === 'student-id-books-moeys') {
            $logoPath = null;
        }
        $sheets = $this->reportExcelSheets($payload, $type, (bool) $logoPath);
        $logoExtension = $logoPath ? $this->xlsxImageExtension($logoPath) : null;
        $drawingSheetNumbers = [];
        $imageExtensions = [];

        if ($logoExtension) {
            $imageExtensions[] = $logoExtension;
            $drawingSheetNumbers = range(1, max(1, count($sheets)));
        }

        foreach ($sheets as $index => $sheet) {
            if (!empty($sheet['photos'])) {
                $drawingSheetNumbers[] = $index + 1;
                foreach ($sheet['photos'] as $photo) {
                    $imageExtensions[] = $photo['extension'] ?? 'png';
                }
            }
        }

        $drawingSheetNumbers = collect($drawingSheetNumbers)->unique()->values()->all();
        $imageExtensions = collect($imageExtensions)->filter()->unique()->values()->all();
        $entries = [
            '[Content_Types].xml' => $this->xlsxContentTypes(count($sheets), $drawingSheetNumbers, $imageExtensions),
            '_rels/.rels' => $this->xlsxRootRels(),
            'xl/workbook.xml' => $this->xlsxWorkbook($sheets),
            'xl/_rels/workbook.xml.rels' => $this->xlsxWorkbookRels(count($sheets)),
            'xl/styles.xml' => $this->xlsxStyles(),
        ];

        foreach ($sheets as $index => $sheet) {
            $sheetNumber = $index + 1;
            $entries['xl/worksheets/sheet' . $sheetNumber . '.xml'] = $sheet['xml'];
            if ($logoPath && $logoExtension) {
                $entries['xl/worksheets/_rels/sheet' . $sheetNumber . '.xml.rels'] = $this->xlsxWorksheetRels($sheetNumber);
                $entries['xl/drawings/drawing' . $sheetNumber . '.xml'] = $this->xlsxLogoDrawing($sheetNumber, $sheet['tacteing_col'] ?? 4, $sheet['show_tacteing'] ?? true, $sheet['logo_cx'] ?? 931560, $sheet['logo_cy'] ?? 950000, $sheet['logo_row'] ?? 0, $sheet['logo_row_off'] ?? 217160, $sheet['logo_col_off'] ?? 97140);
                $entries['xl/drawings/_rels/drawing' . $sheetNumber . '.xml.rels'] = $this->xlsxLogoDrawingRels($logoExtension);
            } elseif (!empty($sheet['photos'])) {
                $entries['xl/worksheets/_rels/sheet' . $sheetNumber . '.xml.rels'] = $this->xlsxWorksheetRels($sheetNumber);
                $entries['xl/drawings/drawing' . $sheetNumber . '.xml'] = $this->xlsxStudentIdBookPhotoDrawing($sheetNumber, $sheet['photos']);
                $entries['xl/drawings/_rels/drawing' . $sheetNumber . '.xml.rels'] = $this->xlsxStudentIdBookPhotoDrawingRels($sheetNumber, $sheet['photos']);

                foreach ($sheet['photos'] as $photoIndex => $photo) {
                    $entries['xl/media/' . $this->studentIdBookPhotoMediaName($sheetNumber, $photoIndex, $photo['extension'] ?? 'png')] = file_get_contents($photo['path']);
                }
            }
        }

        if ($logoPath && $logoExtension) {
            $entries['xl/media/report-logo.' . $logoExtension] = file_get_contents($logoPath);
        }

        $this->writeZipArchive($xlsxPath, $entries);

        return $xlsxPath;
    }

    private function studentIdBookCoverExcelPath(array $cover): string
    {
        $path = tempnam(sys_get_temp_dir(), 'student-id-book-cover-');
        $xlsxPath = $path . '.xlsx';
        rename($path, $xlsxPath);

        $sheets = [[
            'name' => 'Cover',
            'xml' => $this->studentIdBookCoverWorksheetXml($cover),
        ]];
        $entries = [
            '[Content_Types].xml' => $this->xlsxContentTypes(1),
            '_rels/.rels' => $this->xlsxRootRels(),
            'xl/workbook.xml' => $this->xlsxWorkbook($sheets),
            'xl/_rels/workbook.xml.rels' => $this->xlsxWorkbookRels(1),
            'xl/styles.xml' => $this->xlsxStyles(),
            'xl/worksheets/sheet1.xml' => $sheets[0]['xml'],
        ];

        $this->writeZipArchive($xlsxPath, $entries);

        return $xlsxPath;
    }

    private function reportExcelSheets(array $payload, string $type, bool $hasLogo = false): array
    {
        $isMoeys = ($payload['filters']['print_format'] ?? 'internal') === 'moeys';

        if ($type === 'student-statistics-detail') {
            $detailReportMonth = $payload['filters']['month'] ?? now('Asia/Phnom_Penh')->format('Y-m');
            $detailSheetName = strtoupper(\Carbon\Carbon::createFromFormat('Y-m-d', $detailReportMonth . '-01')->format('M'));

            return [[
                'name' => $detailSheetName,
                'xml' => $this->statisticsDetailWorksheetXml($payload['statistics'] ?? $this->emptyStatisticsDetail(), $hasLogo, $payload['filters'] ?? []),
                'tacteing_col' => 7,
                'show_tacteing' => false,
            ]];
        }

        if ($type === 'student-statistics') {
            return [[
                'name' => AcademicReportPermissions::labels()[$type],
                'xml' => $this->statisticsWorksheetXml($payload['statistics'] ?? $this->emptyStatistics(), $hasLogo, $payload['filters'] ?? []),
                'tacteing_col' => 7,
                'show_tacteing' => false,
            ]];
        }

        if ($type === 'withdrawn-students') {
            return [[
                'name' => 'List of Withdrawn Students',
                'xml' => $this->withdrawnStudentsWorksheetXml($payload['withdrawals'] ?? collect(), $payload['filters'] ?? []),
                'tacteing_col' => 7,
                'show_tacteing' => false,
            ]];
        }

        $enrollments = $payload['enrollments'] ?? collect();
        if ($type === 'moeys-sikkhakarik-book') {
            return [[
                'name' => 'MoEYS Transcript Book',
                'xml' => $this->enrollmentsWorksheetXml($enrollments, AcademicReportPermissions::labels()[$type], $hasLogo, false, $payload['filters']['report_date'] ?? null, $type),
                'tacteing_col' => 7,
                'show_tacteing' => false,
            ]];
        }
        if ($type === 'moeys-id-number-book') {
            return [[
                'name' => 'Get Student List',
                'xml' => $this->getStudentListWorksheetXml($enrollments, $payload['selectedColumns'] ?? []),
                'tacteing_col' => 7,
                'show_tacteing' => false,
            ]];
        }
        if ($type === 'student-profile-label') {
            return [[
                'name' => 'Profile Labels',
                'xml' => $this->studentProfileLabelWorksheetXml($enrollments, $payload['filters'] ?? []),
                'tacteing_col' => 7,
                'show_tacteing' => false,
            ]];
        }
        if ($type === 'student-id-books-moeys') {
            $groups = $this->classGroups($enrollments);
            if ($groups->isEmpty()) {
                return [[
                    'name' => 'Student ID Book',
                    'xml' => $this->studentIdBookWorksheetXml(collect()),
                    'photos' => [],
                    'tacteing_col' => 7,
                    'show_tacteing' => false,
                ]];
            }

            $usedNames = [];
            return $groups->map(function ($rows) use (&$usedNames) {
                $rows = $rows->values();
                $photos = $this->studentIdBookExcelPhotos($rows);
                $gradeClass = $this->gradeClassLabel($rows->first()) ?: 'Class';
                return [
                    'name' => $this->uniqueSheetName($gradeClass, $usedNames),
                    'xml' => $this->studentIdBookWorksheetXml($rows, $photos->isNotEmpty()),
                    'photos' => $photos->all(),
                    'tacteing_col' => 7,
                    'show_tacteing' => false,
                ];
            })->values()->all();
        }
        if ($type === 'score-list') {
            $groups = $this->classGroups($enrollments);
            if ($groups->isEmpty()) {
                return [[
                    'name' => 'Score List',
                    'xml' => $this->scoreListWorksheetXml(collect(), $payload['filters'] ?? [], $hasLogo),
                    'tacteing_col' => 7,
                    'show_tacteing' => false,
                    'logo_cx' => 2880000,
                    'logo_cy' => 1100000,
                    'logo_row' => 1,
                    'logo_row_off' => 45720,
                    'logo_col_off' => 0,
                ]];
            }
            $usedNames = [];
            return $groups->map(function ($rows) use (&$usedNames, $payload, $hasLogo) {
                $gradeClass = $this->gradeClassLabel($rows->first()) ?: 'Class';
                return [
                    'name' => $this->uniqueSheetName('Score-' . $gradeClass, $usedNames),
                    'xml' => $this->scoreListWorksheetXml($rows->values(), $payload['filters'] ?? [], $hasLogo),
                    'tacteing_col' => 7,
                    'show_tacteing' => false,
                    'logo_cx' => 2880000,
                    'logo_cy' => 1100000,
                    'logo_row' => 1,
                    'logo_row_off' => 45720,
                    'logo_col_off' => 0,
                ];
            })->values()->all();
        }

        if ($type === 'attendance-list') {
            $attendanceMonth = $payload['filters']['month'] ?? now('Asia/Phnom_Penh')->format('Y-m');
            $monthName = strtoupper(\Carbon\Carbon::createFromFormat('Y-m-d', $attendanceMonth . '-01')->format('M'));
            $groups = $this->classGroups($enrollments);

            if ($groups->isEmpty()) {
                return [[
                    'name' => $monthName,
                    'xml' => $this->attendanceWorksheetXml(collect(), $payload['filters'] ?? [], $hasLogo),
                    'tacteing_col' => 7,
                    'show_tacteing' => false,
                    'logo_cx' => 2781300,
                    'logo_cy' => 944880,
                    'logo_row' => 2,
                    'logo_row_off' => 45720,
                    'logo_col_off' => 0,
                ]];
            }

            $usedNames = [];
            return $groups->map(function ($rows) use (&$usedNames, $payload, $hasLogo, $monthName) {
                $gradeClass = $this->gradeClassLabel($rows->first()) ?: 'Class';
                return [
                    'name' => $this->uniqueSheetName($monthName . '-' . $gradeClass, $usedNames),
                    'xml' => $this->attendanceWorksheetXml($rows->values(), $payload['filters'] ?? [], $hasLogo),
                    'tacteing_col' => 7,
                    'show_tacteing' => false,
                    'logo_cx' => 2781300,
                    'logo_cy' => 944880,
                    'logo_row' => 2,
                    'logo_row_off' => 45720,
                    'logo_col_off' => 0,
                ];
            })->values()->all();
        }

        if (!($this->isStudentListReport($type) || $this->isStudentContactListReport($type))) {
            return [[
                'name' => AcademicReportPermissions::labels()[$type],
                'xml' => $this->enrollmentsWorksheetXml($enrollments, AcademicReportPermissions::labels()[$type], $hasLogo, false, $payload['filters']['report_date'] ?? null, $type),
                'tacteing_col' => 7,
            ]];
        }

        $groups = $this->classGroups($enrollments);

        if ($groups->isEmpty()) {
            return [[
                'name' => AcademicReportPermissions::labels()[$type],
                'xml' => $this->enrollmentsWorksheetXml(collect(), $isMoeys ? ($this->isStudentContactListReport($type) ? 'បញ្ជីទំនាក់ទំនងសិស្ស' : 'បញ្ជីឈ្មោះសិស្ស') : AcademicReportPermissions::labels()[$type], $hasLogo, $isMoeys, $payload['filters']['report_date'] ?? null, $type),
                'tacteing_col' => $isMoeys ? ($this->isStudentContactListReport($type) ? 5 : 4) : ($this->isStudentContactListReport($type) ? 8 : 6),
            ]];
        }

        $usedNames = [];
        return $groups->map(function ($rows) use (&$usedNames, $hasLogo, $isMoeys, $payload, $type) {
            $gradeClass = $this->gradeClassLabel($rows->first());
            $sheetName = $this->uniqueSheetName($gradeClass ?: 'Class', $usedNames);

            return [
                'name' => $sheetName,
                'xml' => $this->enrollmentsWorksheetXml(
                    $rows->values(),
                    $isMoeys ? (($this->isStudentContactListReport($type) ? 'បញ្ជីទំនាក់ទំនងសិស្សថ្នាក់ទី ' : 'បញ្ជីឈ្មោះសិស្សថ្នាក់ទី ') . $gradeClass) : (AcademicReportPermissions::labels()[$type] . ' for Grade ' . $gradeClass),
                    $hasLogo,
                    $isMoeys,
                    $payload['filters']['report_date'] ?? null,
                    $type,
                ),
                'tacteing_col' => $isMoeys ? ($this->isStudentContactListReport($type) ? 5 : 4) : ($this->isStudentContactListReport($type) ? 8 : 6),
            ];
        })->values()->all();
    }

    private function studentProfileLabelWorksheetXml($enrollments, array $filters = []): string
    {
        $rows = [];
        $rowNumber = 1;
        $chunks = collect($enrollments)->values()->chunk(3);
        foreach ($chunks as $chunk) {
            $nameCells = [];
            $idCells = [];
            foreach ([0, 1, 2] as $index) {
                $column = $this->xlsxColumnName(($index * 2) + 1);
                $row = $chunk->get($index);
                $nameCells[] = [$column, $row?->student?->full_name_en ? strtoupper($row->student->full_name_en) : '', 3];
                $idCells[] = [$column, $row?->student?->student_id ?: '', 3];
            }
            $rows[] = $this->xlsxRow($rowNumber, $nameCells, 24);
            $rows[] = $this->xlsxRow($rowNumber + 1, $idCells, 22);
            $rows[] = $this->xlsxRow($rowNumber + 2, [], 8);
            $rowNumber += 3;
        }

        $first = collect($enrollments)->first();
        $footerStart = max($rowNumber + 1, 4);
        $rows[] = $this->xlsxRow($footerStart, [['A', 'Grade', 2], ['B', ':  ' . ($first ? $this->gradeClassLabel($first) : ''), 3]], 18);
        $rows[] = $this->xlsxRow($footerStart + 1, [['A', 'Campus', 2], ['B', ':  ' . ($first?->campus?->campus_name_en ?: ''), 3]], 18);

        $lastRow = $footerStart + 1;
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:F' . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="20"/>'
            . '<cols><col min="1" max="6" width="18" customWidth="1"/></cols>'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . '<pageMargins left="0.25" right="0.25" top="0.25" bottom="0.25" header="0.1" footer="0.1"/>'
            . '<pageSetup paperSize="9" orientation="portrait" fitToWidth="1" fitToHeight="0"/>'
            . '</worksheet>';
    }
    private function getStudentListColumnDefinitions(): array
    {
        return [
            'student_id' => ['key' => 'student_id', 'label' => 'Student ID', 'group' => 'Student Information'],
            'student_no' => ['key' => 'student_no', 'label' => 'Student No.', 'group' => 'Student Information'],
            'family_number' => ['key' => 'family_number', 'label' => 'Family Number', 'group' => 'Student Information'],
            'full_name_en' => ['key' => 'full_name_en', 'label' => 'Student Name English', 'group' => 'Student Information'],
            'full_name_kh' => ['key' => 'full_name_kh', 'label' => 'Student Name Khmer', 'group' => 'Student Information'],
            'gender' => ['key' => 'gender', 'label' => 'Gender English', 'group' => 'Student Information'],
            'gender_kh' => ['key' => 'gender_kh', 'label' => 'Gender Khmer', 'group' => 'Student Information'],
            'date_of_birth' => ['key' => 'date_of_birth', 'label' => 'Date of Birth English', 'group' => 'Student Information'],
            'date_of_birth_kh' => ['key' => 'date_of_birth_kh', 'label' => 'Date of Birth Khmer', 'group' => 'Student Information'],
            'nationality' => ['key' => 'nationality', 'label' => 'Nationality', 'group' => 'Student Information'],
            'nationality_en' => ['key' => 'nationality_en', 'label' => 'Nationality English', 'group' => 'Student Information'],
            'nationality_kh' => ['key' => 'nationality_kh', 'label' => 'Nationality Khmer', 'group' => 'Student Information'],
            'home_phone' => ['key' => 'home_phone', 'label' => 'Home Phone', 'group' => 'Student Information'],
            'email' => ['key' => 'email', 'label' => 'Email', 'group' => 'Student Information'],
            'birth_place_en' => ['key' => 'birth_place_en', 'label' => 'Birth Place English', 'group' => 'Student Information'],
            'birth_place_kh' => ['key' => 'birth_place_kh', 'label' => 'Birth Place Khmer', 'group' => 'Student Information'],
            'birth_village_en' => ['key' => 'birth_village_en', 'label' => 'Birth Village English', 'group' => 'Student Information'],
            'birth_village_kh' => ['key' => 'birth_village_kh', 'label' => 'Birth Village Khmer', 'group' => 'Student Information'],
            'birth_commune_en' => ['key' => 'birth_commune_en', 'label' => 'Birth Commune English', 'group' => 'Student Information'],
            'birth_commune_kh' => ['key' => 'birth_commune_kh', 'label' => 'Birth Commune Khmer', 'group' => 'Student Information'],
            'birth_district_en' => ['key' => 'birth_district_en', 'label' => 'Birth District/Khan English', 'group' => 'Student Information'],
            'birth_district_kh' => ['key' => 'birth_district_kh', 'label' => 'Birth District/Khan Khmer', 'group' => 'Student Information'],
            'birth_province_en' => ['key' => 'birth_province_en', 'label' => 'Birth Province/City English', 'group' => 'Student Information'],
            'birth_province_kh' => ['key' => 'birth_province_kh', 'label' => 'Birth Province/City Khmer', 'group' => 'Student Information'],
            'birth_country_en' => ['key' => 'birth_country_en', 'label' => 'Birth Country English', 'group' => 'Student Information'],
            'birth_country_kh' => ['key' => 'birth_country_kh', 'label' => 'Birth Country Khmer', 'group' => 'Student Information'],
            'current_address_en' => ['key' => 'current_address_en', 'label' => 'Current Address English', 'group' => 'Student Information'],
            'current_address_kh' => ['key' => 'current_address_kh', 'label' => 'Current Address Khmer', 'group' => 'Student Information'],
            'address_house_no_en' => ['key' => 'address_house_no_en', 'label' => 'House No. English', 'group' => 'Student Information'],
            'address_house_no_kh' => ['key' => 'address_house_no_kh', 'label' => 'House No. Khmer', 'group' => 'Student Information'],
            'address_street_en' => ['key' => 'address_street_en', 'label' => 'Street English', 'group' => 'Student Information'],
            'address_street_kh' => ['key' => 'address_street_kh', 'label' => 'Street Khmer', 'group' => 'Student Information'],
            'address_village_en' => ['key' => 'address_village_en', 'label' => 'Address Village English', 'group' => 'Student Information'],
            'address_village_kh' => ['key' => 'address_village_kh', 'label' => 'Address Village Khmer', 'group' => 'Student Information'],
            'address_commune_en' => ['key' => 'address_commune_en', 'label' => 'Address Commune English', 'group' => 'Student Information'],
            'address_commune_kh' => ['key' => 'address_commune_kh', 'label' => 'Address Commune Khmer', 'group' => 'Student Information'],
            'address_district_en' => ['key' => 'address_district_en', 'label' => 'Address District/Khan English', 'group' => 'Student Information'],
            'address_district_kh' => ['key' => 'address_district_kh', 'label' => 'Address District/Khan Khmer', 'group' => 'Student Information'],
            'address_province_en' => ['key' => 'address_province_en', 'label' => 'Address Province/City English', 'group' => 'Student Information'],
            'address_province_kh' => ['key' => 'address_province_kh', 'label' => 'Address Province/City Khmer', 'group' => 'Student Information'],
            'address_country_en' => ['key' => 'address_country_en', 'label' => 'Address Country English', 'group' => 'Student Information'],
            'address_country_kh' => ['key' => 'address_country_kh', 'label' => 'Address Country Khmer', 'group' => 'Student Information'],
            'previous_school' => ['key' => 'previous_school', 'label' => 'Previous School', 'group' => 'Student Information'],
            'experienced_english' => ['key' => 'experienced_english', 'label' => 'Experienced English', 'group' => 'Student Information'],
            'test_result' => ['key' => 'test_result', 'label' => 'Test Result', 'group' => 'Student Information'],
            'tested_by' => ['key' => 'tested_by', 'label' => 'Tested By', 'group' => 'Student Information'],
            'remarks' => ['key' => 'remarks', 'label' => 'Remarks', 'group' => 'Student Information'],
            'academic_year' => ['key' => 'academic_year', 'label' => 'Academic Year', 'group' => 'Enrollment'],
            'period_type' => ['key' => 'period_type', 'label' => 'Period', 'group' => 'Enrollment'],
            'campus' => ['key' => 'campus', 'label' => 'Campus', 'group' => 'Enrollment'],
            'grade_class' => ['key' => 'grade_class', 'label' => 'Grade/Class', 'group' => 'Enrollment'],
            'grade' => ['key' => 'grade', 'label' => 'Grade', 'group' => 'Enrollment'],
            'class' => ['key' => 'class', 'label' => 'Class', 'group' => 'Enrollment'],
            'group' => ['key' => 'group', 'label' => 'Group', 'group' => 'Enrollment'],
            'academic_track' => ['key' => 'academic_track', 'label' => 'Academic Track', 'group' => 'Enrollment'],
            'student_type' => ['key' => 'student_type', 'label' => 'Student Type', 'group' => 'Enrollment'],
            'enrollment_status' => ['key' => 'enrollment_status', 'label' => 'Enrollment Status', 'group' => 'Enrollment'],
            'enrolled_on' => ['key' => 'enrolled_on', 'label' => 'Enrolled On', 'group' => 'Enrollment'],
            'ended_on' => ['key' => 'ended_on', 'label' => 'Ended On', 'group' => 'Enrollment'],
            'id_book_list_no' => ['key' => 'id_book_list_no', 'label' => 'List Code', 'group' => 'Enrollment'],
            'mother_name_en' => ['key' => 'mother_name_en', 'label' => 'Mother Name English', 'group' => 'Family'],
            'mother_name_kh' => ['key' => 'mother_name_kh', 'label' => 'Mother Name Khmer', 'group' => 'Family'],
            'mother_phone' => ['key' => 'mother_phone', 'label' => 'Mother Phone', 'group' => 'Family'],
            'mother_email' => ['key' => 'mother_email', 'label' => 'Mother Email', 'group' => 'Family'],
            'mother_occupation' => ['key' => 'mother_occupation', 'label' => 'Mother Occupation', 'group' => 'Family'],
            'mother_workplace' => ['key' => 'mother_workplace', 'label' => 'Mother Workplace', 'group' => 'Family'],
            'father_name_en' => ['key' => 'father_name_en', 'label' => 'Father Name English', 'group' => 'Family'],
            'father_name_kh' => ['key' => 'father_name_kh', 'label' => 'Father Name Khmer', 'group' => 'Family'],
            'father_phone' => ['key' => 'father_phone', 'label' => 'Father Phone', 'group' => 'Family'],
            'father_email' => ['key' => 'father_email', 'label' => 'Father Email', 'group' => 'Family'],
            'father_occupation' => ['key' => 'father_occupation', 'label' => 'Father Occupation', 'group' => 'Family'],
            'father_workplace' => ['key' => 'father_workplace', 'label' => 'Father Workplace', 'group' => 'Family'],
            'guardian_name_en' => ['key' => 'guardian_name_en', 'label' => 'Guardian Name English', 'group' => 'Family'],
            'guardian_name_kh' => ['key' => 'guardian_name_kh', 'label' => 'Guardian Name Khmer', 'group' => 'Family'],
            'guardian_phone' => ['key' => 'guardian_phone', 'label' => 'Guardian Phone', 'group' => 'Family'],
            'guardian_email' => ['key' => 'guardian_email', 'label' => 'Guardian Email', 'group' => 'Family'],
            'guardian_occupation' => ['key' => 'guardian_occupation', 'label' => 'Guardian Occupation', 'group' => 'Family'],
            'guardian_workplace' => ['key' => 'guardian_workplace', 'label' => 'Guardian Workplace', 'group' => 'Family'],
        ];
    }

    private function getStudentListFamilyMember($student, string $relationship)
    {
        return $student?->familyMembers?->first(fn ($member) => ($member->relationship_type ?? $member->pivot?->relationship_type) === $relationship);
    }

    private function getStudentListLocation($student, string $type, string $language = 'en'): string
    {
        $suffix = $language === 'kh' ? 'kh' : 'en';
        $parts = $type === 'birth'
            ? [$student?->birthVillage?->{'village_name_' . $suffix}, $student?->birthCommune?->{'commune_name_' . $suffix}, $student?->birthDistrict?->{'district_name_' . $suffix}, $student?->birthProvince?->{'province_name_' . $suffix}, $student?->birthCountry?->{'country_name_' . $suffix}]
            : [$language === 'kh' ? $student?->address_house_no_kh : $student?->address_house_no_en, $language === 'kh' ? $student?->address_street_kh : $student?->address_street_en, $student?->addressVillage?->{'village_name_' . $suffix}, $student?->addressCommune?->{'commune_name_' . $suffix}, $student?->addressDistrict?->{'district_name_' . $suffix}, $student?->addressProvince?->{'province_name_' . $suffix}, $student?->addressCountry?->{'country_name_' . $suffix}];

        return collect($parts)->map(fn ($value) => trim((string) $value))->filter()->join(', ');
    }

    private function getStudentListLocationPart($student, string $type, string $part, string $language = 'en'): string
    {
        $suffix = $language === 'kh' ? 'kh' : 'en';
        return match ($type . '_' . $part) {
            'birth_village' => (string) $student?->birthVillage?->{'village_name_' . $suffix},
            'birth_commune' => (string) $student?->birthCommune?->{'commune_name_' . $suffix},
            'birth_district' => (string) $student?->birthDistrict?->{'district_name_' . $suffix},
            'birth_province' => (string) $student?->birthProvince?->{'province_name_' . $suffix},
            'birth_country' => (string) $student?->birthCountry?->{'country_name_' . $suffix},
            'address_house_no' => (string) ($language === 'kh' ? $student?->address_house_no_kh : $student?->address_house_no_en),
            'address_street' => (string) ($language === 'kh' ? $student?->address_street_kh : $student?->address_street_en),
            'address_village' => (string) $student?->addressVillage?->{'village_name_' . $suffix},
            'address_commune' => (string) $student?->addressCommune?->{'commune_name_' . $suffix},
            'address_district' => (string) $student?->addressDistrict?->{'district_name_' . $suffix},
            'address_province' => (string) $student?->addressProvince?->{'province_name_' . $suffix},
            'address_country' => (string) $student?->addressCountry?->{'country_name_' . $suffix},
            default => '',
        };
    }

    private function getStudentListKhmerDate($date): string
    {
        return $this->studentIdBookKhmerDate($date);
    }

    private function getStudentListColumnValue($row, string $key): string
    {
        $student = $row->student;
        $mother = $this->getStudentListFamilyMember($student, 'mother');
        $father = $this->getStudentListFamilyMember($student, 'father');
        $guardian = $this->getStudentListFamilyMember($student, 'guardian');
        $occupation = static fn ($member) => trim((string) ($member?->occupation_en ?: $member?->occupation_kh ?: $member?->occupation));

        $value = match ($key) {
            'student_id' => $student?->student_id,
            'student_no' => $student?->student_no,
            'family_number' => $student?->family_number,
            'full_name_en' => $student?->full_name_en,
            'full_name_kh' => $student?->full_name_kh,
            'gender' => $student?->gender,
            'gender_kh' => $student?->gender_kh,
            'date_of_birth' => $this->displayDate($student?->date_of_birth, 'd-M-Y'),
            'date_of_birth_kh' => $this->getStudentListKhmerDate($student?->date_of_birth),
            'nationality' => $student?->nationalityCountry?->nationality_name_en ?: $student?->nationalityCountry?->country_name_en,
            'nationality_en' => $student?->nationalityCountry?->nationality_name_en ?: $student?->nationalityCountry?->country_name_en,
            'nationality_kh' => $student?->nationalityCountry?->nationality_name_kh ?: $student?->nationalityCountry?->country_name_kh,
            'home_phone' => $student?->home_phone,
            'email' => $student?->email,
            'birth_place_en' => $this->getStudentListLocation($student, 'birth', 'en'),
            'birth_place_kh' => $this->getStudentListLocation($student, 'birth', 'kh'),
            'birth_village_en' => $this->getStudentListLocationPart($student, 'birth', 'village', 'en'),
            'birth_village_kh' => $this->getStudentListLocationPart($student, 'birth', 'village', 'kh'),
            'birth_commune_en' => $this->getStudentListLocationPart($student, 'birth', 'commune', 'en'),
            'birth_commune_kh' => $this->getStudentListLocationPart($student, 'birth', 'commune', 'kh'),
            'birth_district_en' => $this->getStudentListLocationPart($student, 'birth', 'district', 'en'),
            'birth_district_kh' => $this->getStudentListLocationPart($student, 'birth', 'district', 'kh'),
            'birth_province_en' => $this->getStudentListLocationPart($student, 'birth', 'province', 'en'),
            'birth_province_kh' => $this->getStudentListLocationPart($student, 'birth', 'province', 'kh'),
            'birth_country_en' => $this->getStudentListLocationPart($student, 'birth', 'country', 'en'),
            'birth_country_kh' => $this->getStudentListLocationPart($student, 'birth', 'country', 'kh'),
            'current_address_en' => $student?->current_address_en ?: $this->getStudentListLocation($student, 'address', 'en'),
            'current_address_kh' => $student?->current_address_kh ?: $this->getStudentListLocation($student, 'address', 'kh'),
            'address_house_no_en' => $this->getStudentListLocationPart($student, 'address', 'house_no', 'en'),
            'address_house_no_kh' => $this->getStudentListLocationPart($student, 'address', 'house_no', 'kh'),
            'address_street_en' => $this->getStudentListLocationPart($student, 'address', 'street', 'en'),
            'address_street_kh' => $this->getStudentListLocationPart($student, 'address', 'street', 'kh'),
            'address_village_en' => $this->getStudentListLocationPart($student, 'address', 'village', 'en'),
            'address_village_kh' => $this->getStudentListLocationPart($student, 'address', 'village', 'kh'),
            'address_commune_en' => $this->getStudentListLocationPart($student, 'address', 'commune', 'en'),
            'address_commune_kh' => $this->getStudentListLocationPart($student, 'address', 'commune', 'kh'),
            'address_district_en' => $this->getStudentListLocationPart($student, 'address', 'district', 'en'),
            'address_district_kh' => $this->getStudentListLocationPart($student, 'address', 'district', 'kh'),
            'address_province_en' => $this->getStudentListLocationPart($student, 'address', 'province', 'en'),
            'address_province_kh' => $this->getStudentListLocationPart($student, 'address', 'province', 'kh'),
            'address_country_en' => $this->getStudentListLocationPart($student, 'address', 'country', 'en'),
            'address_country_kh' => $this->getStudentListLocationPart($student, 'address', 'country', 'kh'),
            'previous_school' => $student?->previous_school ?: $row->previous_school,
            'experienced_english' => $student?->experienced_english,
            'test_result' => $student?->test_result,
            'tested_by' => $student?->tested_by,
            'remarks' => $student?->remarks ?: $row->notes,
            'academic_year' => $row->academicYear?->academic_year,
            'period_type' => $row->academicYear?->period_type ? Str::headline($row->academicYear->period_type) : null,
            'campus' => $row->campus?->campus_name_en,
            'grade_class' => $this->gradeClassLabel($row),
            'grade' => $row->grade?->grade_short_name ?: $row->grade?->grade,
            'class' => $row->schoolClass?->class_name,
            'group' => $row->session?->session_short_name ?: $row->session?->session_name,
            'academic_track' => $row->academicTrack?->name_en,
            'student_type' => $row->student_type ? Str::headline($row->student_type) : null,
            'enrollment_status' => $row->enrollment_status ? Str::headline($row->enrollment_status) : null,
            'enrolled_on' => $this->displayDate($row->enrolled_on, 'd-M-Y'),
            'ended_on' => $this->displayDate($row->ended_on, 'd-M-Y'),
            'id_book_list_no' => $row->id_book_list_no,
            'mother_name_en' => $mother?->full_name_en,
            'mother_name_kh' => $mother?->full_name_kh,
            'mother_phone' => $mother?->phone,
            'mother_email' => $mother?->email,
            'mother_occupation' => $occupation($mother),
            'mother_workplace' => $mother?->workplace,
            'father_name_en' => $father?->full_name_en,
            'father_name_kh' => $father?->full_name_kh,
            'father_phone' => $father?->phone,
            'father_email' => $father?->email,
            'father_occupation' => $occupation($father),
            'father_workplace' => $father?->workplace,
            'guardian_name_en' => $guardian?->full_name_en,
            'guardian_name_kh' => $guardian?->full_name_kh,
            'guardian_phone' => $guardian?->phone,
            'guardian_email' => $guardian?->email,
            'guardian_occupation' => $occupation($guardian),
            'guardian_workplace' => $guardian?->workplace,
            default => null,
        };

        $value = trim((string) $value);
        return $value === '' ? '-' : $value;
    }

    private function getStudentListSortedIds(iterable $rows, string $column, string $direction): Collection
    {
        $values = [];
        foreach ($rows as $row) {
            // Use the actual date for both language columns, rather than sorting formatted dates.
            $value = match ($column) {
                'row_no' => count($values),
                'date_of_birth', 'date_of_birth_kh' => $row->student?->date_of_birth?->format('Y-m-d') ?? '',
                'enrolled_on', 'ended_on' => $row->{$column}?->format('Y-m-d') ?? '',
                default => $this->getStudentListColumnValue($row, $column),
            };
            $values[$row->id] = mb_strtolower((string) $value);
        }

        $sorted = collect($values)->sort(SORT_NATURAL | SORT_FLAG_CASE);
        return ($direction === 'desc' ? $sorted->reverse() : $sorted)->keys();
    }

    private function getStudentListWorksheetXml($enrollments, array $columns): string
    {
        $enrollments = collect($enrollments)->values();
        if (empty($columns)) {
            $columns = collect($this->getStudentListColumnDefinitions())->take(8)->values()->all();
        }

        $headers = collect($columns)->map(fn ($column) => $column['label'] ?? $column['key'] ?? 'Column')->values();
        $lastColumn = $this->xlsxColumnName(max(1, $headers->count() + 1));
        $lastRow = max(5, $enrollments->count() + 4);
        $rows = [
            $this->xlsxRow(1, [['A', 'Get Student List', 1]], 24),
            $this->xlsxRow(2, [['A', 'Generated: ' . now('Asia/Phnom_Penh')->format('d-M-Y H:i'), 2]], 18),
            $this->xlsxRow(3, [], 6),
        ];
        $headerCells = [['A', 'No.', 3]];
        foreach ($headers as $index => $header) {
            $headerCells[] = [$this->xlsxColumnName($index + 2), $header, 3];
        }
        $rows[] = $this->xlsxRow(4, $headerCells, 24);

        foreach ($enrollments as $index => $row) {
            $cells = [['A', (string) ($index + 1), 5]];
            foreach ($columns as $columnIndex => $column) {
                $cells[] = [$this->xlsxColumnName($columnIndex + 2), $this->getStudentListColumnValue($row, (string) ($column['key'] ?? '')), 5];
            }
            $rows[] = $this->xlsxRow($index + 5, $cells, 22);
        }

        if ($enrollments->isEmpty()) {
            $rows[] = $this->xlsxRow(5, [['A', 'No students found.', 5]], 26);
        }

        $mergeRefs = ['A1:' . $lastColumn . '1', 'A2:' . $lastColumn . '2'];
        if ($enrollments->isEmpty()) {
            $mergeRefs[] = 'A5:' . $lastColumn . '5';
        }
        $mergeCells = '<mergeCells count="' . count($mergeRefs) . '">' . collect($mergeRefs)->map(fn ($ref) => '<mergeCell ref="' . $ref . '"/>')->implode('') . '</mergeCells>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols><col min="1" max="1" width="6" customWidth="1"/><col min="2" max="' . max(2, $headers->count() + 1) . '" width="22" customWidth="1"/></cols>'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . $mergeCells
            . '<pageMargins left="0.25" right="0.25" top="0.35" bottom="0.35" header="0.1" footer="0.1"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            . '</worksheet>';
    }

    private function enrollmentsWorksheetXml($enrollments, string $title, bool $hasLogo = false, bool $isMoeys = false, ?string $reportDate = null, string $type = 'student-list'): string
    {
        $isContactList = $this->isStudentContactListReport($type);
        $headers = $isMoeys
            ? ($isContactList
                ? ['ល.រ', 'អត្តលេខសិស្ស', 'នាមត្រកូល-នាមខ្លួន', 'ភេទ', 'ក្រុម', 'លទូរសព្ទផ្ទះ', 'លទូរសព្ទម្ដាយ', 'លទូរសព្ទឪពុក']
                : ['ល.រ', 'អត្តលេខសិស្ស', 'នាមត្រកូល-នាមខ្លួន', 'ភេទ', 'ថ្នាក់ទី', 'ក្រុម', 'ផ្សងៗ'])
            : ($isContactList
                ? ['No.', 'Student ID', 'នាមត្រកូល-នាមខ្លួន', 'Full-Name', 'Gender', 'Campus', 'Grade', 'Group', 'Home Phone', "Mother's Phone", "Father's Phone"]
                : ['No.', 'Student ID', 'នាមត្រកូល-នាមខ្លួន', 'Full-Name', 'Gender', 'Campus', 'Grade', 'Group', 'Remarks']);
        $lastColumn = $this->xlsxColumnName(count($headers));
        $first = $enrollments->first();
        $academicYear = $first?->academicYear?->academic_year ?: '-';
        $campus = $isMoeys
            ? ($first?->campus?->campus_name_kh ?: $first?->campus?->campus_name_en ?: '-')
            : ($first?->campus?->campus_name_en ?: '-');
        $lastDataRow = $enrollments->count() + 9;
        $footerStartRow = $lastDataRow + 2;
        $lastRow = $isMoeys ? max(13, $footerStartRow + 2) : max(10, $lastDataRow + 1);
        $titleStyle = $isMoeys ? 8 : 1;
        $titleMergeStart = $isMoeys ? ($isContactList ? 'F' : 'E') : ($isContactList ? 'I' : 'G');
        $rows = [
            $this->xlsxRow(1, [[$titleMergeStart, 'ព្រះរាជាណាចក្រកម្ពុជា', 6]], $isMoeys ? 22.05 : 22),
            $this->xlsxRow(2, [[$titleMergeStart, 'ជាតិ សាសនា ព្រះមហាក្សត្រ', 6]], $isMoeys ? 19.8 : 22),
            $this->xlsxRow(3, [[$titleMergeStart, 'KINGDOM OF CAMBODIA', 6]], $isMoeys ? 15.6 : 18),
            $this->xlsxRow(4, [[$titleMergeStart, 'NATION RELIGION KING', 6]], $isMoeys ? 15.6 : 18),
            $this->xlsxRow(5, [['A', $title, $titleStyle]], $isMoeys ? 31.8 : 23),
            $this->xlsxRow(6, [['A', ($isMoeys ? 'ឆ្នាំសិក្សា៖ ' : 'Academic Year: ') . $academicYear, $isMoeys ? 11 : 2]], $isMoeys ? 16.5 : 20),
            $this->xlsxRow(7, [['A', $isMoeys ? $campus : ('Campus: ' . $campus), $isMoeys ? 11 : 2]], $isMoeys ? 16.5 : 18),
            $this->xlsxRow(8, [], $isMoeys ? 3 : 8),
            $this->xlsxRow(9, collect($headers)->map(fn ($header, $index) => [
                $this->xlsxColumnName($index + 1),
                $header,
                (!$isMoeys && $index === 2) || $isMoeys ? 7 : 3,
            ])->all(), 24),
        ];

        foreach ($enrollments as $index => $row) {
            if ($isMoeys && $isContactList) {
                $cells = [
                    ['A', (string) ($index + 1), 9],
                    ['B', $row->student?->student_id ?: '-', 9],
                    ['C', $row->student?->full_name_kh ?: $row->student?->full_name_en ?: '-', 4],
                    ['D', strtoupper(substr((string) $row->student?->gender, 0, 1)) === 'F' ? 'ស្រី' : 'ប្រុស', 10],
                    ['E', $row->session?->session_short_name ?: '-', 9],
                    ['F', $row->student?->home_phone ?: '-', 9],
                    ['G', $this->studentFamilyPhone($row->student, 'mother'), 9],
                    ['H', $this->studentFamilyPhone($row->student, 'father'), 9],
                ];
            } elseif ($isMoeys) {
                $cells = [
                    ['A', (string) ($index + 1), 9],
                    ['B', $row->student?->student_id ?: '-', 9],
                    ['C', $row->student?->full_name_kh ?: $row->student?->full_name_en ?: '-', 4],
                    ['D', strtoupper(substr((string) $row->student?->gender, 0, 1)) === 'F' ? 'ស្រី' : 'ប្រុស', 10],
                    ['E', $this->gradeClassLabel($row), 9],
                    ['F', $row->session?->session_short_name ?: '-', 9],
                    ['G', '', 9],
                ];
            } elseif ($isContactList) {
                $cells = [
                    ['A', (string) ($index + 1), 5],
                    ['B', $row->student?->student_id ?: '-', 5],
                    ['C', $row->student?->full_name_kh ?: '-', 4],
                    ['D', $row->student?->full_name_en ?: '-', 5],
                    ['E', strtoupper(substr((string) $row->student?->gender, 0, 1)) === 'F' ? 'F' : 'M', 5],
                    ['F', $row->campus?->campus_name_en ?: '-', 5],
                    ['G', $this->gradeClassLabel($row), 5],
                    ['H', $row->session?->session_short_name ?: '-', 5],
                    ['I', $row->student?->home_phone ?: '-', 5],
                    ['J', $this->studentFamilyPhone($row->student, 'mother'), 5],
                    ['K', $this->studentFamilyPhone($row->student, 'father'), 5],
                ];
            } else {
                $cells = [
                    ['A', (string) ($index + 1), 5],
                    ['B', $row->student?->student_id ?: '-', 5],
                    ['C', $row->student?->full_name_kh ?: '-', 4],
                    ['D', $row->student?->full_name_en ?: '-', 5],
                    ['E', strtoupper(substr((string) $row->student?->gender, 0, 1)) === 'F' ? 'F' : 'M', 5],
                    ['F', $row->campus?->campus_name_en ?: '-', 5],
                    ['G', $this->gradeClassLabel($row), 5],
                    ['H', $row->session?->session_short_name ?: '-', 5],
                    ['I', '', 5],
                ];
            }

            $rows[] = $this->xlsxRow($index + 10, $cells, $isMoeys ? 22.05 : 22);
        }

        $footerMerges = '';
        if ($isMoeys) {
            [$khmerLunarDate, $khmerSolarDate] = $this->moeysKhmerDateLines($reportDate, $first?->campus);
            $rows[] = $this->xlsxRow($footerStartRow, [['C', $khmerLunarDate, 12]], 19.5);
            $rows[] = $this->xlsxRow($footerStartRow + 1, [['C', $khmerSolarDate, 12]], 19.5);
            $rows[] = $this->xlsxRow($footerStartRow + 2, [['C', 'នាយកសាលា', 13]], 22);
            $footerMerges = '<mergeCell ref="C' . $footerStartRow . ':' . $lastColumn . $footerStartRow . '"/><mergeCell ref="C' . ($footerStartRow + 1) . ':' . $lastColumn . ($footerStartRow + 1) . '"/><mergeCell ref="C' . ($footerStartRow + 2) . ':' . $lastColumn . ($footerStartRow + 2) . '"/>';
        } elseif ($reportDate) {
            $rows[] = $this->xlsxRow($lastDataRow + 1, [['A', \Carbon\Carbon::parse($reportDate)->format('F j, Y'), 2]], 18);
            $footerMerges = '<mergeCell ref="A' . ($lastDataRow + 1) . ':' . $lastColumn . ($lastDataRow + 1) . '"/>';
        }

        $mergeCells = $isMoeys
            ? '<mergeCells count="11"><mergeCell ref="B8:F8"/><mergeCell ref="A5:' . $lastColumn . '5"/><mergeCell ref="A6:' . $lastColumn . '6"/><mergeCell ref="A7:' . $lastColumn . '7"/><mergeCell ref="' . $titleMergeStart . '1:' . $lastColumn . '1"/><mergeCell ref="' . $titleMergeStart . '2:' . $lastColumn . '2"/><mergeCell ref="' . $titleMergeStart . '3:' . $lastColumn . '3"/><mergeCell ref="' . $titleMergeStart . '4:' . $lastColumn . '4"/>' . $footerMerges . '</mergeCells>'
            : ($isContactList
                ? '<mergeCells count="9"><mergeCell ref="I1:K1"/><mergeCell ref="A7:K7"/><mergeCell ref="A6:K6"/><mergeCell ref="A5:K5"/><mergeCell ref="I4:K4"/><mergeCell ref="I2:K2"/><mergeCell ref="I3:K3"/><mergeCell ref="B8:F8"/>' . $footerMerges . '</mergeCells>'
                : '<mergeCells count="9"><mergeCell ref="G1:I1"/><mergeCell ref="A7:I7"/><mergeCell ref="A6:I6"/><mergeCell ref="A5:I5"/><mergeCell ref="G4:I4"/><mergeCell ref="G2:I2"/><mergeCell ref="G3:I3"/><mergeCell ref="B8:F8"/>' . $footerMerges . '</mergeCells>');
        $columnsXml = $isMoeys
            ? ($isContactList
                ? '<cols><col min="1" max="1" width="5.296875" customWidth="1"/><col min="2" max="2" width="11.69921875" customWidth="1"/><col min="3" max="3" width="30" customWidth="1"/><col min="4" max="4" width="8" customWidth="1"/><col min="5" max="5" width="7" customWidth="1"/><col min="6" max="8" width="14" customWidth="1"/></cols>'
                : '<cols><col min="1" max="1" width="5.296875" customWidth="1"/><col min="2" max="2" width="11.69921875" customWidth="1"/><col min="3" max="3" width="32" customWidth="1"/><col min="4" max="4" width="9.19921875" customWidth="1"/><col min="5" max="5" width="9.19921875" customWidth="1"/><col min="6" max="6" width="7.69921875" customWidth="1"/><col min="7" max="7" width="12.3984375" customWidth="1"/></cols>')
            : ($isContactList
                ? '<cols><col min="1" max="1" width="5.296875" customWidth="1"/><col min="2" max="2" width="10.5" customWidth="1"/><col min="3" max="3" width="17" customWidth="1"/><col min="4" max="4" width="24" customWidth="1"/><col min="5" max="5" width="7" customWidth="1"/><col min="6" max="6" width="9" customWidth="1"/><col min="7" max="7" width="8" customWidth="1"/><col min="8" max="8" width="7" customWidth="1"/><col min="9" max="11" width="14" customWidth="1"/></cols>'
                : '<cols><col min="1" max="1" width="5.296875" customWidth="1"/><col min="2" max="2" width="11.69921875" customWidth="1"/><col min="3" max="3" width="16.3984375" customWidth="1"/><col min="4" max="4" width="28.5" customWidth="1"/><col min="5" max="5" width="7.8984375" customWidth="1"/><col min="6" max="6" width="9.19921875" customWidth="1"/><col min="7" max="7" width="7.69921875" customWidth="1"/><col min="8" max="8" width="7.296875" customWidth="1"/><col min="9" max="9" width="12.3984375" customWidth="1"/></cols>');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="9" topLeftCell="A10" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="13.8"/>'
            . $columnsXml
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . $mergeCells
            . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            . '<pageSetup paperSize="9" scale="85" fitToHeight="0" orientation="portrait"/>'
            . ($hasLogo ? '<drawing r:id="rId1"/>' : '')
            . '</worksheet>';
    }
    private function studentFamilyPhone($student, string $relationship): string
    {
        $member = $student?->familyMembers?->first(function ($familyMember) use ($relationship) {
            return ($familyMember->relationship_type ?? null) === $relationship
                || ($familyMember->pivot?->relationship_type ?? null) === $relationship;
        });

        return $member?->phone ?: '-';
    }
    private function scorePrintTypeLabel(array $filters): string
    {
        return match ($filters['print_type'] ?? 'quarter_1') {
            'quarter_2' => 'Quarter 2',
            'quarter_3' => 'Quarter 3',
            'quarter_4' => 'Quarter 4',
            default => 'Quarter 1',
        };
    }

    private function withdrawnStudentsWorksheetXml($withdrawals, array $filters = []): string
    {
        $withdrawals = collect($withdrawals)->values();
        $headers = ['No.', 'Photo', 'Student Name', 'Grade', 'Withdrawal Date', 'Requested By', 'Reason', 'Status'];
        $lastColumn = $this->xlsxColumnName(count($headers));
        $lastRow = max(6, $withdrawals->count() + 5);
        $rows = [
            $this->xlsxRow(1, [['A', 'List of Withdrawn Students', 1]], 24),
            $this->xlsxRow(2, [['A', 'Status: ' . $this->withdrawalStatusLabel($filters['withdrawal_status'] ?? 'approved'), 2]], 18),
            $this->xlsxRow(3, [['A', 'Report Date: ' . \Carbon\Carbon::parse($filters['report_date'] ?? now()->format('Y-m-d'))->format('F j, Y'), 2]], 18),
            $this->xlsxRow(4, [], 6),
            $this->xlsxRow(5, collect($headers)->map(fn ($header, $index) => [$this->xlsxColumnName($index + 1), $header, $index === 2 ? 7 : 3])->all(), 24),
        ];

        foreach ($withdrawals as $index => $row) {
            $rows[] = $this->xlsxRow($index + 6, [
                ['A', (string) ($index + 1), 5],
                ['B', $row->student?->photo_path ? asset('storage/' . ltrim($row->student->photo_path, '/')) : '', 5],
                ['C', trim('ID: ' . ($row->student?->student_id ?: $row->student?->student_no ?: '-') . "\n" . ($row->student?->full_name_kh ?: '-') . "\n" . ($row->student?->full_name_en ?: '-') . "\nGender: " . $this->shortGender($row->student?->gender)), 4],
                ['D', $this->withdrawalGradeGroupLabel($row), 5],
                ['E', $this->displayDate($row->effective_on), 5],
                ['F', trim(($row->requested_by_name ?: '-') . "\n" . ($row->requested_by_phone ?: '-')), 5],
                ['G', $this->withdrawalReasonText($row), 4],
                ['H', $this->withdrawalStatusLabel($row->withdrawal_status), 5],
            ], 28);
        }

        $mergeRefs = ['A1:' . $lastColumn . '1', 'A2:' . $lastColumn . '2', 'A3:' . $lastColumn . '3'];
        if ($withdrawals->isEmpty()) {
            $rows[] = $this->xlsxRow(6, [['A', 'No withdrawn students found.', 5]], 28);
            $mergeRefs[] = 'A6:' . $lastColumn . '6';
        }
        $mergeCells = '<mergeCells count="' . count($mergeRefs) . '">' . collect($mergeRefs)->map(fn ($ref) => '<mergeCell ref="' . $ref . '"/>')->implode('') . '</mergeCells>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols><col min="1" max="1" width="5" customWidth="1"/><col min="2" max="2" width="18" customWidth="1"/><col min="3" max="3" width="30" customWidth="1"/><col min="4" max="4" width="22" customWidth="1"/><col min="5" max="6" width="14" customWidth="1"/><col min="7" max="7" width="54" customWidth="1"/><col min="8" max="8" width="14" customWidth="1"/></cols>'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . $mergeCells
            . '<pageMargins left="0.25" right="0.25" top="0.35" bottom="0.35" header="0.1" footer="0.1"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            . '</worksheet>';
    }

    private function studentIdBookWorksheetXml($enrollments, bool $hasPhotos = false): string
    {
        $enrollments = collect($enrollments)->values();
        $lastRow = max(2, ($enrollments->count() * 6) + 1);
        $rows = [
            $this->xlsxRow(1, [
                ['A', "លកូដក្នុង\nបញ្ជី", 10],
                ['B', "អ្ល\nនៅWIS", 10],
                ['C', "ឈ្មោះសិស្ស\nរូប", 10],
                ['D', 'ភេទ', 10],
                ['E', '្នាក់', 10],
                ['F', "្ងៃ ែ ឆ្នាំ\nនិងទីកន្លែងកំណើ", 10],
                ['G', "ឈ្មោះ ឪពុកម្ាយ\nមុរបរ និង ទីលំនៅ", 10],
                ['H', "ទីលំនៅបច្ចុប្បន្នរបស់\nអាណាព្យាបាលសិស្ស", 10],
                ['I', 'រយះពលសិក្សា', 10],
                ['J', 'សចក្ីផ្សងៗ', 10],
            ], 33),
        ];
        $mergeRefs = [];

        foreach ($enrollments as $index => $row) {
            $student = $row->student;
            $start = ($index * 6) + 2;
            $end = $start + 5;
            foreach (['A', 'B', 'D', 'E', 'J'] as $column) {
                $mergeRefs[] = $column . $start . ':' . $column . $end;
            }
            $mergeRefs[] = 'C' . $start . ':C' . $end;

            $father = $this->studentIdBookFamilyMember($student, 'father');
            $mother = $this->studentIdBookFamilyMember($student, 'mother');
            $birthRows = $this->studentIdBookBirthRows($student);
            $addressRows = $this->studentIdBookAddressRows($student);
            $gradeName = trim((string) ($row->grade?->grade_short_name ?: $row->grade?->grade ?? ''));
            $sectionName = trim((string) ($row->schoolClass?->class_name ?? ''));
            $className = $sectionName && $gradeName && !str_starts_with($sectionName, $gradeName)
                ? $gradeName . $sectionName
                : ($sectionName ?: $gradeName);
            $enrolledOn = $row->enrolled_on ?: $row->created_at;
            $trueAddress = $student?->current_address_kh ?: '';
            $previousSchool = trim((string) $student?->previous_school);

            $rows[] = $this->xlsxRow($start, [
                ['A', $row->id_book_list_no ?: '', 32],
                ['B', $student?->student_id ?: '', 32],
                ['C', $student?->full_name_kh ?: $student?->full_name_en ?: '', 31],
                ['D', $this->studentIdBookGender($student), 10],
                ['E', $className, 10],
                ['F', 'កើ្ងៃទី ' . $this->studentIdBookKhmerDate($student?->date_of_birth), 27],
                ['G', 'ឈ្មោះឪពុក ' . trim((string) ($father?->full_name_kh ?: $father?->full_name_en)), 27],
                ['H', $addressRows[0], 27],
                ['I', 'ចូល្ងៃទី ' . str_replace('-', ' ', $this->studentIdBookKhmerDate($enrolledOn)), 27],
                ['J', '', 10],
            ], 28);
            $rows[] = $this->xlsxRow($start + 1, [
                ['F', $birthRows[0], 28],
                ['G', 'មុរបរ ' . trim((string) ($father?->occupation_kh ?: $father?->occupation_en ?: $father?->occupation)), 28],
                ['H', $addressRows[1], 28],
                ['I', 'មកពីសាលា ' . $previousSchool, 28],
            ], 28);
            $rows[] = $this->xlsxRow($start + 2, [
                ['F', $birthRows[1], 28],
                ['G', 'ឈ្មោះម្ាយ ' . trim((string) ($mother?->full_name_kh ?: $mother?->full_name_en)), 28],
                ['H', $addressRows[2], 28],
                ['I', 'ចញ្ងៃទី', 28],
            ], 28);
            $rows[] = $this->xlsxRow($start + 3, [
                ['F', $birthRows[2], 28],
                ['G', 'មុរបរ ' . trim((string) ($mother?->occupation_kh ?: $mother?->occupation_en ?: $mother?->occupation)), 28],
                ['H', $addressRows[3], 28],
                ['I', '........................................', 28],
            ], 28);
            $rows[] = $this->xlsxRow($start + 4, [
                ['F', $birthRows[3], 28],
                ['G', 'ទីលំនៅពិប្រាកដ ' . $trueAddress, 28],
                ['H', '', 28],
                ['I', '........................................', 28],
            ], 28);
            $rows[] = $this->xlsxRow($start + 5, [
                ['F', $birthRows[4], 29],
                ['G', '', 29],
                ['H', '', 29],
                ['I', '', 29],
            ], 28);
        }

        if ($enrollments->isEmpty()) {
            $rows[] = $this->xlsxRow(2, [['A', 'No students found.', 5]], 28);
            $mergeRefs[] = 'A2:J2';
        }

        $mergeCells = $mergeRefs
            ? '<mergeCells count="' . count($mergeRefs) . '">' . collect($mergeRefs)->map(fn ($ref) => '<mergeCell ref="' . $ref . '"/>')->implode('') . '</mergeCells>'
            : '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:J' . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="25.5"/>'
            . '<cols><col min="1" max="2" width="7.85546875" customWidth="1"/><col min="3" max="3" width="20.42578125" customWidth="1"/><col min="4" max="4" width="5.140625" customWidth="1"/><col min="5" max="5" width="6.28515625" customWidth="1"/><col min="6" max="6" width="25" customWidth="1"/><col min="7" max="7" width="32.5703125" customWidth="1"/><col min="8" max="8" width="26.7109375" customWidth="1"/><col min="9" max="9" width="25.28515625" customWidth="1"/><col min="10" max="10" width="18.7109375" customWidth="1"/></cols>'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . $mergeCells
            . '<pageMargins left="0.7" right="0.12" top="0.25" bottom="0.12" header="0.1" footer="0.1"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToHeight="0"/>'
            . ($hasPhotos ? '<drawing r:id="rId1"/>' : '')
            . '</worksheet>';
    }

    private function studentIdBookExcelPhotos($enrollments): Collection
    {
        return collect($enrollments)->values()->map(function ($row, int $index) {
            $path = $this->studentPhotoLocalPath($row->student?->photo_path);

            if (!$path) {
                return null;
            }

            return [
                'path' => $path,
                'extension' => $this->xlsxImageExtension($path),
                'row' => ($index * 6) + 2,
            ];
        })->filter()->values();
    }

    private function studentPhotoLocalPath(?string $photoPath): ?string
    {
        $photoPath = trim((string) $photoPath);
        if ($photoPath === '') {
            return null;
        }

        $relativePath = ltrim($photoPath, '/\\');
        $candidates = [
            storage_path('app/public/' . $relativePath),
            public_path('storage/' . $relativePath),
            public_path($relativePath),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function studentIdBookCoverWorksheetXml(array $cover): string
    {
        $rows = [
            $this->xlsxRow(1, [['A', '', 2]], 18),
            $this->xlsxRow(2, [['A', 'ព្រះរាជាណាចក្រកម្ពុជា', 13]], 32),
            $this->xlsxRow(3, [['A', 'ជាិ សាសនា ព្រះមហាក្ស្រ', 13]], 30),
            $this->xlsxRow(4, [['A', '5', 14]], 20),
            $this->xlsxRow(5, [], 12),
            $this->xlsxRow(6, [['A', $cover['educationOffice'] ?? '', 13]], 30),
            $this->xlsxRow(7, [['A', trim(($cover['schoolLevelPrefix'] ?? '') . ' ' . ($cover['schoolName'] ?? '')), 13]], 26),
            $this->xlsxRow(8, [['A', $cover['campusNameKh'] ?? '', 13]], 26),
            $this->xlsxRow(9, [], 26),
            $this->xlsxRow(10, [], 18),
            $this->xlsxRow(11, [['A', 'សៀវភៅចុះអត្តលេខសិស្ស', 8]], 54),
            $this->xlsxRow(12, [], 24),
            $this->xlsxRow(13, [['A', $cover['gradeRange'] ?? '', 13]], 44),
            $this->xlsxRow(14, [['A', $cover['codeRange'] ?? '', 13]], 42),
            $this->xlsxRow(15, [['A', $cover['academicYear'] ?? '', 13]], 38),
        ];
        $mergeRefs = [
            'A1:J1', 'A2:J2', 'A3:J3', 'A4:J4', 'A5:J5',
            'A6:E6', 'A7:E7', 'A8:E8', 'A9:J9', 'A10:J10', 'A11:J11', 'A12:J12', 'A13:J13', 'A14:J14', 'A15:J15',
        ];
        $mergeCells = '<mergeCells count="' . count($mergeRefs) . '">' . collect($mergeRefs)->map(fn ($ref) => '<mergeCell ref="' . $ref . '"/>')->implode('') . '</mergeCells>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:J15"/>'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="24"/>'
            . '<cols><col min="1" max="10" width="13" customWidth="1"/></cols>'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . $mergeCells
            . '<pageMargins left="0.35" right="0.35" top="0.35" bottom="0.35" header="0.1" footer="0.1"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="1"/>'
            . '</worksheet>';
    }

    private function studentIdBookFamilyMember($student, string $relationship)
    {
        return $student?->familyMembers?->first(fn ($member) => ($member->relationship_type ?? $member->pivot?->relationship_type) === $relationship);
    }

    private function studentIdBookLevelGrades(?string $level): array
    {
        return match ($level) {
            'kindergarten' => ['N-', 'K1-', 'K2-', 'K3-'],
            'primary' => ['1', '2', '3', '4', '5', '6'],
            'secondary' => ['7', '8', '9', '10', '11', '12'],
            default => [],
        };
    }

    private function studentIdBookLevelEntryGrade(?string $level): ?string
    {
        return match ($level) {
            'kindergarten' => 'N-',
            'primary' => '1',
            'secondary' => '7',
            default => null,
        };
    }

    private function transcriptBookLevelGrades(?string $level): array
    {
        return match ($level) {
            'primary' => ['4', '5', '6'],
            'secondary' => ['7', '8', '9', '10', '11', '12'],
            default => [],
        };
    }

    private function studentIdBookGender($student): string
    {
        $gender = mb_strtolower(trim((string) ($student?->gender_kh ?: $student?->gender)));
        return str_contains($gender, 'f') || str_contains($gender, 'ស្រី') ? 'ស' : 'ប';
    }

    private function studentIdBookKhmerDate($date): string
    {
        if (!$date) return '';
        $date = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        $months = ['Jan'=>'មករា','Feb'=>'កុម្ភៈ','Mar'=>'មីនា','Apr'=>'មសា','May'=>'ឧសភា','Jun'=>'មិុនា','Jul'=>'កក្កដា','Aug'=>'សីហា','Sep'=>'កញ្ញា','Oct'=>'ុលា','Nov'=>'វិច្ឆិកា','Dec'=>'ធ្នូ'];
        return $this->khmerDigits($date->format('d')) . '-' . ($months[$date->format('M')] ?? $date->format('M')) . '-' . $this->khmerDigits($date->format('Y'));
    }

    private function studentIdBookLocationName($model, string $english, string $khmer): string
    {
        return trim((string) ($model?->{$khmer} ?: $model?->{$english} ?: ''));
    }

    private function studentIdBookBirthRows($student): array
    {
        $province = $this->studentIdBookLocationName($student?->birthProvince, 'province_name_en', 'province_name_kh');
        return [
            'ភូមិ​ ' . $this->studentIdBookLocationName($student?->birthVillage, 'village_name_en', 'village_name_kh'),
            'ឃុំ ' . $this->studentIdBookLocationName($student?->birthCommune, 'commune_name_en', 'commune_name_kh'),
            'ស្រុក ' . $this->studentIdBookLocationName($student?->birthDistrict, 'district_name_en', 'district_name_kh'),
            '្-ក្រុង ' . $province,
            '',
        ];
    }

    private function studentIdBookAddressRows($student): array
    {
        return [
            trim(trim((string) $student?->address_house_no_kh) . '     ' . trim((string) $student?->address_street_kh)),
            'សង្កា់ ' . trim((string) $student?->addressCommune?->commune_name_kh),
            'ណ្ឌ-ស្រុក ' . trim((string) $student?->addressDistrict?->district_name_kh),
            '្ក្រុង ' . trim((string) $student?->addressProvince?->province_name_kh),
        ];
    }

    private function scoreListWorksheetXml($enrollments, array $filters = [], bool $hasLogo = false): string
    {
        $enrollments = collect($enrollments)->values();
        $first = $enrollments->first();
        $lastColumn = 'U';
        $startRow = 10;
        $minRows = 25;
        $bodyCount = max($minRows, $enrollments->count()) + 4;
        $footerRow = $startRow + $bodyCount + 1;
        $khmerNoteTwoRow = $footerRow + 1;
        $englishNoteRow = $footerRow + 2;
        $englishNoteTwoRow = $footerRow + 3;
        $lastRow = $englishNoteTwoRow;
        $gradeClass = $first ? $this->gradeClassLabel($first) : '-';
        $quarter = $this->scorePrintTypeLabel($filters);
        $date = now('Asia/Phnom_Penh')->format('d-M-y');
        $kh = static fn (string $value): string => base64_decode($value);

        $rows = [
            $this->xlsxRow(1, [['P', $kh('4Z6W4Z+S4Z6a4Z+H4Z6a4Z624Z6H4Z624Z6O4Z624Z6F4Z6A4Z+S4Z6a4Z6A4Z6Y4Z+S4Z6W4Z674Z6H4Z62'), 6]], 22),
            $this->xlsxRow(2, [['P', $kh('4Z6H4Z624Z6P4Z63IOGen+GetuGen+Gek+GetiDhnpbhn5Lhnprhn4fhnpjhnqDhnrbhnoDhn5Lhnp/hno/hn5Lhnpo='), 6]], 22),
            $this->xlsxRow(3, [['P', 'KINGDOM OF CAMBODIA', 14]], 18),
            $this->xlsxRow(4, [['P', 'NATION RELIGION KING', 14]], 18),
            $this->xlsxRow(5, [], 4),
            $this->xlsxRow(6, [['A', $kh('4Z6P4Z624Z6a4Z624Z6E4Z6f4Z6Y4Z+S4Z6a4Z6E4Z+L4Z6W4Z634Z6T4Z+S4Z6R4Z67'), 8]], 28),
            $this->xlsxRow(7, [['A', 'Score List', 1]], 26),
            $this->xlsxRow(8, [
                ['A', $kh('4Z6I4Z+S4Z6Y4Z+E4Z+H4Z6C4Z+S4Z6a4Z68') . "\nTeacher Name:", 15], ['C', '', 2],
                ['F', $kh('4Z6Y4Z674Z6B4Z6c4Z634Z6H4Z+S4Z6H4Z62') . "\nSubject:", 15], ['H', '', 2],
                ['K', $kh('4Z6Q4Z+S4Z6T4Z624Z6A4Z+L4Z6R4Z64') . "\nGrade:", 15], ['L', $gradeClass, 2],
                ['N', $kh('4Z6f4Z6Y4Z+S4Z6a4Z624Z6U4Z+L') . "\nFor:", 15], ['O', $quarter, 2],
            ], 30),
            $this->xlsxRow(9, [
                ['A', 'No', 24], ['B', $kh('4Z6I4Z+S4Z6Y4Z+E4Z+H') . "\nName", 24], ['C', $kh('4Z6X4Z+B4Z6R') . "\nGender", 24], ['D', $kh('4Z6A4Z+S4Z6a4Z674Z6Y') . "\nGroup", 24], ['E', 'Conduct', 24], ['F', 'C.P.', 24],
                ['G', $kh('4Z6A4Z634Z6F4Z+S4Z6F4Z6A4Z624Z6a4Z6V4Z+S4Z6R4Z+H') . ' / Homework', 24], ['M', $kh('4Z6P4Z+B4Z6f4Z+S4Z6P4Z6B4Z+S4Z6b4Z64') . ' / Quizzews', 24], ['S', $kh('4Z6P4Z+B4Z6f4Z+S4Z6P4Z6U4Z+S4Z6a4Z6F4Z624Z+G4Z6B4Z+C') . "\nMonthly Test", 24],
            ], 28),
            $this->xlsxRow(10, [
                ['G', '1', 24], ['H', '2', 24], ['I', '3', 24], ['J', '4', 24], ['K', '5', 24], ['L', '6', 24],
                ['M', '1', 24], ['N', '2', 24], ['O', '3', 24], ['P', '4', 24], ['Q', '5', 24], ['R', '6', 24],
                ['S', '1', 24], ['T', '2', 24], ['U', '3', 24],
            ], 20),
        ];

        for ($i = 0; $i < $bodyCount; $i++) {
            $row = $enrollments->get($i);
            $style = $i % 2 === 1 ? 21 : 5;
            $studentName = $row ? trim(($row->student?->full_name_kh ?: '-') . "\n" . ($row->student?->full_name_en ?: '-')) : '';
            $cells = [
                ['A', $row ? (string) ($i + 1) : '', $style],
                ['B', $studentName, $row ? 4 : $style],
                ['C', $row ? (strtoupper(substr((string) $row->student?->gender, 0, 1)) === 'F' ? 'F' : 'M') : '', $style],
                ['D', $row?->session?->session_short_name ?: '', $style],
                ['E', '', $style], ['F', '', $style],
            ];

            foreach (range(7, 21) as $columnIndex) {
                $cells[] = [$this->xlsxColumnName($columnIndex), '', $style];
            }

            $rows[] = $this->xlsxRow($startRow + $i + 1, $cells, 22);
        }

        $rows[] = $this->xlsxRow($footerRow, [['A', $kh('4Z6F4Z+G4Z6O4Z624Z+G4Z+WIOGej+GetuGemuGetuGehOGen+GemOGfkuGemuGehOGfi+GeluGet+Gek+GfkuGekeGeu+Gek+GfgeGfhyDhnoLhnrrhnp/hnpjhn5LhnprhnrbhnpThn4vhnpvhn4ThnoDhnoLhn5LhnprhnrzhnqLhn5LhnpPhnoDhnoLhn5LhnprhnrzhnoDhno/hn4vhnpbhnrfhnpPhn5LhnpHhnrvhnoXhnrzhnpsg4Z6K4Z6+4Z6Y4Z+S4Z6U4Z644Z6E4Z624Z6Z4Z6f4Z+S4Z6a4Z694Z6b4Z6A4Z+S4Z6T4Z674Z6E4Z6A4Z624Z6a4Z6A4Z6P4Z+L4Z6W4Z634Z6T4Z+S4Z6R4Z674Z6F4Z684Z6b4Z6A4Z+S4Z6T4Z674Z6E4Z6P4Z624Z6a4Z624Z6E4Z6f4Z6Y4Z+S4Z6a4Z6E4Z+L4Z6W4Z634Z6T4Z+S4Z6R4Z674Z6i4Z+B4Z6h4Z634Z6F4Z6P4Z+S4Z6a4Z684Z6T4Z634Z6FIChFLUdyYWRlYm9vaynhn5Q='), 12], ['T', 'Date:', 2], ['U', $date, 2]], 16);
        $rows[] = $this->xlsxRow($khmerNoteTwoRow, [['A', $kh('4Z6P4Z624Z6a4Z624Z6E4Z6f4Z6Y4Z+S4Z6a4Z6E4Z+L4Z6W4Z634Z6T4Z+S4Z6R4Z674Z6T4Z+B4Z+H4Z6C4Z664Z6U4Z+S4Z6a4Z6+4Z6U4Z+S4Z6a4Z624Z6f4Z+L4Z6f4Z6Y4Z+S4Z6a4Z624Z6U4Z+L4Z6S4Z+S4Z6c4Z6+4Z6A4Z624Z6a4Z6A4Z6P4Z+L4Z6P4Z+S4Z6a4Z624Z6V4Z+S4Z6R4Z624Z6b4Z+L4Z6B4Z+S4Z6b4Z694Z6T4Z6a4Z6U4Z6f4Z+L4Z6b4Z+E4Z6A4Z6C4Z+S4Z6a4Z684Z6i4Z+S4Z6T4Z6A4Z6C4Z+S4Z6a4Z684Z6P4Z+C4Z6U4Z+J4Z674Z6O4Z+S4Z6O4Z+E4Z+H4Z+U'), 12]], 16);
        $rows[] = $this->xlsxRow($englishNoteRow, [['A', '* NOTE: This list is for teachers to keep record of all kinds of scores which is served as hard copies for egrade-book input.', 5]], 16);
        $rows[] = $this->xlsxRow($englishNoteTwoRow, [['A', 'The list is for teacher personal use. It is not required by the office.', 5]], 16);

        $mergeCells = '<mergeCells count="22"><mergeCell ref="P1:U1"/><mergeCell ref="P2:U2"/><mergeCell ref="P3:U3"/><mergeCell ref="P4:U4"/><mergeCell ref="A6:U6"/><mergeCell ref="A7:U7"/><mergeCell ref="C8:E8"/><mergeCell ref="H8:J8"/><mergeCell ref="O8:U8"/><mergeCell ref="A9:A10"/><mergeCell ref="B9:B10"/><mergeCell ref="C9:C10"/><mergeCell ref="D9:D10"/><mergeCell ref="E9:E10"/><mergeCell ref="F9:F10"/><mergeCell ref="G9:L9"/><mergeCell ref="M9:R9"/><mergeCell ref="S9:U9"/><mergeCell ref="A' . $footerRow . ':S' . $footerRow . '"/><mergeCell ref="A' . $khmerNoteTwoRow . ':S' . $khmerNoteTwoRow . '"/><mergeCell ref="A' . $englishNoteRow . ':S' . $englishNoteRow . '"/><mergeCell ref="A' . $englishNoteTwoRow . ':S' . $englishNoteTwoRow . '"/></mergeCells>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="10" topLeftCell="A11" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols><col min="1" max="1" width="5" customWidth="1"/><col min="2" max="2" width="30" customWidth="1"/><col min="3" max="6" width="7" customWidth="1"/><col min="7" max="21" width="6" customWidth="1"/></cols>'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . $mergeCells
            . '<pageMargins left="0.2" right="0.2" top="0.15" bottom="0.25" header="0.1" footer="0.1"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            . ($hasLogo ? '<drawing r:id="rId1"/>' : '')
            . '</worksheet>';
    }
    private function statisticsWorksheetXml(array $statistics, bool $hasLogo = false, array $filters = []): string
    {
        $columnLabel = static function ($column): string {
            $label = trim((string) $column);
            if (strcasecmp($label, 'Nursery') === 0) return 'N';
            if (preg_match('/^Grade\\s*(\\d+)$/i', $label, $matches)) return $matches[1];
            return $label;
        };
        $columnGroup = static function (string $label): string {
            if (in_array($label, ['N', 'K1', 'K2', 'K3'], true)) return 'early';
            if (is_numeric($label) && (int) $label >= 1 && (int) $label <= 6) return 'primary';
            if (is_numeric($label) && (int) $label >= 7 && (int) $label <= 12) return 'secondary';
            return 'other';
        };
        $styleForGroup = static fn (string $group): int => match ($group) {
            'early' => 17,
            'primary' => 18,
            'secondary' => 19,
            default => 21,
        };
        $columns = collect($statistics['columns'] ?? [])->map($columnLabel)->values();
        $groups = $columns->map($columnGroup)->values();
        $lastColumn = $this->xlsxColumnName($columns->count() + 3);
        $bodyRows = collect($statistics['rows'] ?? []);
        $bodyStartRow = 9;
        $footerRow = $bodyStartRow + $bodyRows->count();
        $groupRow = $footerRow + 1;
        $officeRow = $groupRow + 2;
        $dateRow = $officeRow + 1;
        $lastRow = $dateRow;
        $academicYear = 'Academic Year: All Academic Years';
        if (!empty($filters['academic_year_id'])) {
            $academicYear = 'Academic Year: ' . (AcademicYear::whereKey($filters['academic_year_id'])->value('academic_year') ?: 'All Academic Years');
        }
        $reportDate = \Carbon\Carbon::parse($filters['report_date'] ?? now('Asia/Phnom_Penh')->format('Y-m-d'))->format('F j, Y');

        $rows = [
            $this->xlsxRow(1, [], 14),
            $this->xlsxRow(2, [], 14),
            $this->xlsxRow(3, [], 16),
            $this->xlsxRow(4, [], 7),
            $this->xlsxRow(5, [['B', 'Student Statistics Summary', 14]], 24),
            $this->xlsxRow(6, [['B', $academicYear, 15]], 21),
            $this->xlsxRow(7, [], 5),
        ];

        $headerCells = [['A', 'Campus', 16], ['B', '', 16]];
        foreach ($columns as $index => $label) {
            $headerCells[] = [$this->xlsxColumnName($index + 3), $label, $styleForGroup($groups[$index] ?? 'other')];
        }
        $headerCells[] = [$lastColumn, 'Total', 20];
        $rows[] = $this->xlsxRow(8, $headerCells, 24);

        foreach ($bodyRows as $index => $row) {
            $cells = [
                ['A', (string) ($index + 1), 21],
                ['B', (string) ($row['campus'] ?? '-'), 22],
            ];
            foreach (($row['cells'] ?? []) as $cellIndex => $cell) {
                $value = (int) $cell === 0 ? '' : $this->xlsxStatisticValue((int) $cell, (int) ($row['newCells'][$cellIndex] ?? 0));
                $cells[] = [$this->xlsxColumnName($cellIndex + 3), $value, 21];
            }
            $cells[] = [$lastColumn, $this->xlsxStatisticValue((int) ($row['total'] ?? 0), (int) ($row['new_total'] ?? 0), true), 20];
            $rows[] = $this->xlsxRow($bodyStartRow + $index, $cells, 28);
        }

        $footerCells = [['A', "Grand\nTotal", 16], ['B', '', 16]];
        foreach (($statistics['columnTotals'] ?? []) as $index => $total) {
            $footerCells[] = [$this->xlsxColumnName($index + 3), $this->xlsxStatisticValue((int) $total, (int) ($statistics['columnNewTotals'][$index] ?? 0), true), $styleForGroup($groups[$index] ?? 'other')];
        }
        $footerCells[] = [$lastColumn, $this->xlsxStatisticValue((int) ($statistics['grandTotal'] ?? 0), (int) ($statistics['grandNewTotal'] ?? 0), true), 20];
        $rows[] = $this->xlsxRow($footerRow, $footerCells, 30);

        $groupTotals = ['early' => 0, 'primary' => 0, 'secondary' => 0];
        $groupNewTotals = ['early' => 0, 'primary' => 0, 'secondary' => 0];
        foreach (($statistics['columnTotals'] ?? []) as $index => $total) {
            $group = $groups[$index] ?? 'other';
            if (isset($groupTotals[$group])) {
                $groupTotals[$group] += (int) $total;
                $groupNewTotals[$group] += (int) ($statistics['columnNewTotals'][$index] ?? 0);
            }
        }
        $rows[] = $this->xlsxRow($groupRow, [
            ['A', '', 16],
            ['B', '', 16],
            ['C', $this->xlsxStatisticValue($groupTotals['early'], $groupNewTotals['early'], true), 17],
            ['D', '', 17],
            ['E', '', 17],
            ['F', '', 17],
            ['G', $this->xlsxStatisticValue($groupTotals['primary'], $groupNewTotals['primary'], true), 18],
            ['H', '', 18],
            ['I', '', 18],
            ['J', '', 18],
            ['K', '', 18],
            ['L', '', 18],
            ['M', $this->xlsxStatisticValue($groupTotals['secondary'], $groupNewTotals['secondary'], true), 19],
            ['N', '', 19],
            ['O', '', 19],
            ['P', '', 19],
            ['Q', '', 19],
            ['R', '', 19],
            [$lastColumn, '', 20],
        ], 27);
        $rows[] = $this->xlsxRow($officeRow, [['A', "Registrar's Office", 23]], 21);
        $rows[] = $this->xlsxRow($dateRow, [['A', $reportDate, 15]], 21);

        $mergeCells = '<mergeCells count="10"><mergeCell ref="B5:' . $lastColumn . '5"/><mergeCell ref="B6:' . $lastColumn . '6"/><mergeCell ref="A8:B8"/><mergeCell ref="A' . $footerRow . ':B' . $groupRow . '"/><mergeCell ref="' . $lastColumn . $footerRow . ':' . $lastColumn . $groupRow . '"/><mergeCell ref="C' . $groupRow . ':F' . $groupRow . '"/><mergeCell ref="G' . $groupRow . ':L' . $groupRow . '"/><mergeCell ref="M' . $groupRow . ':R' . $groupRow . '"/><mergeCell ref="A' . $officeRow . ':D' . $officeRow . '"/><mergeCell ref="A' . $dateRow . ':D' . $dateRow . '"/></mergeCells>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="8" topLeftCell="A9" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols><col min="1" max="1" width="4.5" customWidth="1"/><col min="2" max="2" width="10.5" customWidth="1"/><col min="3" max="18" width="8.2" customWidth="1"/><col min="19" max="19" width="10.5" customWidth="1"/></cols>'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . $mergeCells
            . '<pageMargins left="0.2" right="0.2" top="0.35" bottom="0.35" header="0.1" footer="0.1"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            . ($hasLogo ? '<drawing r:id="rId1"/>' : '')
            . '</worksheet>';
    }

    private function statisticsDetailWorksheetXml(array $statistics, bool $hasLogo = false, array $filters = []): string
    {
        $groups = collect($statistics['groups'] ?? [])->values();
        $baseColumnCount = 9;
        $lastColumn = $this->xlsxColumnName($baseColumnCount + ($groups->count() * 2));
        $academicYear = 'All Academic Years';
        if (!empty($filters['academic_year_id'])) {
            $academicYear = AcademicYear::whereKey($filters['academic_year_id'])->value('academic_year') ?: 'All Academic Years';
        }
        $reportMonth = $filters['month'] ?? now('Asia/Phnom_Penh')->format('Y-m');
        $reportDate = \Carbon\Carbon::createFromFormat('Y-m-d', $reportMonth . '-01')->endOfMonth();
        $value = static fn ($number): string => (int) $number === 0 ? '' : (string) (int) $number;
        $totalValue = static fn ($number): string => (string) (int) $number;

        $rows = [
            $this->xlsxRow(1, [], 14),
            $this->xlsxRow(2, [['B', 'Student Statistics for ' . $reportDate->format('F Y'), 14]], 24),
            $this->xlsxRow(3, [['B', 'Academic Year: ' . $academicYear, 15]], 21),
            $this->xlsxRow(4, [], 5),
        ];

        $topHeader = [['A', 'No', 24], ['B', 'Grade', 24]];
        $secondHeader = [['A', '', 24], ['B', '', 24]];
        $mergeRefs = ['B2:' . $lastColumn . '2', 'B3:' . $this->xlsxColumnName(max(2, $baseColumnCount + ($groups->count() * 2) - 3)) . '3'];
        $columnIndex = 3;
        foreach ($groups as $group) {
            $start = $this->xlsxColumnName($columnIndex);
            $end = $this->xlsxColumnName($columnIndex + 1);
            $topHeader[] = [$start, 'Group ' . $group, 24];
            $topHeader[] = [$end, '', 24];
            $secondHeader[] = [$start, 'Total', 24];
            $secondHeader[] = [$end, 'Female', 24];
            $mergeRefs[] = $start . '5:' . $end . '5';
            $columnIndex += 2;
        }

        $totalStart = $this->xlsxColumnName($columnIndex);
        $totalEnd = $this->xlsxColumnName($columnIndex + 1);
        $topHeader[] = [$totalStart, 'Total', 24];
        $topHeader[] = [$totalEnd, '', 24];
        $secondHeader[] = [$totalStart, 'Total', 24];
        $secondHeader[] = [$totalEnd, 'Female', 24];
        $mergeRefs[] = $totalStart . '5:' . $totalEnd . '5';
        $columnIndex += 2;

        foreach (['Old', 'New', 'Monthly Dropout', 'Cumulative Dropout', 'Campus'] as $label) {
            $column = $this->xlsxColumnName($columnIndex);
            $topHeader[] = [$column, $label, 24];
            $secondHeader[] = [$column, '', 24];
            $mergeRefs[] = $column . '5:' . $column . '6';
            $columnIndex++;
        }

        $mergeRefs[] = 'A5:A6';
        $mergeRefs[] = 'B5:B6';
        $rows[] = $this->xlsxRow(5, $topHeader, 24);
        $rows[] = $this->xlsxRow(6, $secondHeader, 22);

        $rowNumber = 7;
        foreach (($statistics['campuses'] ?? []) as $campus) {
            $rows[] = $this->xlsxRow($rowNumber, [['A', (string) ($campus['campus'] ?? ''), 16]], 20);
            $mergeRefs[] = 'A' . $rowNumber . ':' . $lastColumn . $rowNumber;
            $rowNumber++;

            foreach (($campus['rows'] ?? []) as $index => $row) {
                $cells = [['A', (string) ($index + 1), 5], ['B', (string) ($row['grade'] ?? ''), 5]];
                $columnIndex = 3;
                foreach ($groups as $group) {
                    $cells[] = [$this->xlsxColumnName($columnIndex++), $value($row['groups'][$group]['total'] ?? 0), 5];
                    $cells[] = [$this->xlsxColumnName($columnIndex++), $value($row['groups'][$group]['female'] ?? 0), 5];
                }
                foreach (['total', 'female', 'old', 'new', 'monthly_dropout', 'cumulative_dropout'] as $key) {
                    $cells[] = [$this->xlsxColumnName($columnIndex++), $value($row[$key] ?? 0), 5];
                }
                $cells[] = [$this->xlsxColumnName($columnIndex++), (string) ($row['campus'] ?? ''), 5];
                $rows[] = $this->xlsxRow($rowNumber++, $cells, 18);
            }

            $cells = [['A', (string) ($campus['campus'] ?? '') . ' Total', 16], ['B', '', 16]];
            $mergeRefs[] = 'A' . $rowNumber . ':B' . $rowNumber;
            $columnIndex = 3;
            foreach ($groups as $group) {
                $cells[] = [$this->xlsxColumnName($columnIndex++), $totalValue($campus['totals']['groups'][$group]['total'] ?? 0), 16];
                $cells[] = [$this->xlsxColumnName($columnIndex++), $totalValue($campus['totals']['groups'][$group]['female'] ?? 0), 16];
            }
            foreach (['total', 'female', 'old', 'new', 'monthly_dropout', 'cumulative_dropout'] as $key) {
                $cells[] = [$this->xlsxColumnName($columnIndex++), $totalValue($campus['totals'][$key] ?? 0), 16];
            }
            $cells[] = [$this->xlsxColumnName($columnIndex++), (string) ($campus['campus'] ?? ''), 16];
            $rows[] = $this->xlsxRow($rowNumber++, $cells, 20);
        }

        if (!empty($statistics['campuses'] ?? [])) {
            $cells = [['A', 'Grand Total', 25], ['B', '', 25]];
            $mergeRefs[] = 'A' . $rowNumber . ':B' . $rowNumber;
            $columnIndex = 3;
            foreach ($groups as $group) {
                $cells[] = [$this->xlsxColumnName($columnIndex++), $totalValue($statistics['totals']['groups'][$group]['total'] ?? 0), 25];
                $cells[] = [$this->xlsxColumnName($columnIndex++), $totalValue($statistics['totals']['groups'][$group]['female'] ?? 0), 25];
            }
            foreach (['total', 'female', 'old', 'new', 'monthly_dropout', 'cumulative_dropout'] as $key) {
                $cells[] = [$this->xlsxColumnName($columnIndex++), $totalValue($statistics['totals'][$key] ?? 0), 25];
            }
            $cells[] = [$this->xlsxColumnName($columnIndex++), 'All', 25];
            $rows[] = $this->xlsxRow($rowNumber++, $cells, 22);
        }

        if (empty($statistics['campuses'] ?? [])) {
            $rows[] = $this->xlsxRow($rowNumber, [['A', 'No statistics found.', 5]], 20);
            $mergeRefs[] = 'A' . $rowNumber . ':' . $lastColumn . $rowNumber;
            $rowNumber++;
        }

        $footerStartColumn = $this->xlsxColumnName(max(1, ($baseColumnCount + ($groups->count() * 2)) - 3));
        $rows[] = $this->xlsxRow($rowNumber, [[$footerStartColumn, 'Date: ' . now('Asia/Phnom_Penh')->format('F j, Y'), 15]], 20);
        $mergeRefs[] = $footerStartColumn . $rowNumber . ':' . $lastColumn . $rowNumber;
        $rowNumber++;
        $rows[] = $this->xlsxRow($rowNumber, [[$footerStartColumn, "Registrar's Office", 15]], 20);
        $mergeRefs[] = $footerStartColumn . $rowNumber . ':' . $lastColumn . $rowNumber;
        $rowNumber++;

        $mergeCells = '<mergeCells count="' . count($mergeRefs) . '">' . collect($mergeRefs)->map(fn ($ref) => '<mergeCell ref="' . $ref . '"/>')->implode('') . '</mergeCells>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:' . $lastColumn . max(1, $rowNumber - 1) . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols><col min="1" max="1" width="5" customWidth="1"/><col min="2" max="2" width="10" customWidth="1"/><col min="3" max="' . max(3, ($baseColumnCount + ($groups->count() * 2)) - 5) . '" width="8" customWidth="1"/><col min="' . max(3, ($baseColumnCount + ($groups->count() * 2)) - 4) . '" max="' . ($baseColumnCount + ($groups->count() * 2)) . '" width="11" customWidth="1"/></cols>'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . $mergeCells
            . '<pageMargins left="0.2" right="0.2" top="0.35" bottom="0.35" header="0.1" footer="0.1"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            . ($hasLogo ? '<drawing r:id="rId1"/>' : '')
            . '</worksheet>';
    }

    private function attendanceWorksheetXml($enrollments, array $filters = [], bool $hasLogo = false): string
    {
        $reportMonth = $filters['month'] ?? now('Asia/Phnom_Penh')->format('Y-m');
        try {
            $reportDate = \Carbon\Carbon::createFromFormat('Y-m-d', $reportMonth . '-01');
        } catch (\Throwable) {
            $reportDate = now('Asia/Phnom_Penh')->startOfMonth();
        }
        $days = $reportDate->daysInMonth;
        $lastColumn = $this->xlsxColumnName($days + 7);
        $first = $enrollments->first();
        $classLabel = $first ? ($this->gradeClassLabel($first) ?: 'Selected Class') : 'Selected Class';
        $sessionLabel = $first?->session?->session_name ?: ($first?->session?->session_short_name ?: '');
        $printedAt = now('Asia/Phnom_Penh')->format('n/j/Y g:i:s A');
        $footerKhmerNote = 'ប្រសិនបើសិស្សគ្មានឈ្មោះក្នុងបញ្ជី ឬគ្មានក្រដាសអនុញ្ញាឱ្យចូល្នាក់ ឬរៀនុសក្រុម ឬ្នាក់ មិនអនុញ្ញាឱ្យចូលក្នុង្នាក់ឡើយ ្រូវបញ្ជូនសិស្សទាំងនោះមកករិយាលយសិក្សា។';
        $footerEnglishNote = "If student's name is not listed, he/she doesn't have an Admission Slip or he/she is in the wrong group, he/she must not be admitted to class.";
        $dateEnd = $this->xlsxColumnName($days + 5);
        $totalStart = $this->xlsxColumnName($days + 6);
        $totalEnd = $this->xlsxColumnName($days + 7);

        $rows = [
            $this->xlsxRow(1, [], 12),
            $this->xlsxRow(2, [['AB', 'ព្រះរាជាណាចក្រកម្ពុជា', 13]], 22.8),
            $this->xlsxRow(3, [['AB', 'ជាិ សាសនា ព្រះមហាក្ស្រ', 13]], 22.8),
            $this->xlsxRow(4, [['AB', 'KINGDOM OF CAMBODIA', 15]], 15),
            $this->xlsxRow(5, [['AB', 'NATION RELIGION KING', 15]], 15),
            $this->xlsxRow(6, [['A', 'បញ្ជីសម្រង់វ្មានសិស្ស', 8]], 36.6),
            $this->xlsxRow(7, [['A', 'Class Attendance List Grade ' . $classLabel . ' for ' . $reportDate->format('F'), 14]], 23.4),
            $this->xlsxRow(8, [['B', 'ឈ្មោះគ្រូ', 12], ['H', 'មុវិជ្ជា', 12], ['R', 'បន្ទប់', 12], ['Z', 'ពលសិក្សា', 12]], 23.4),
            $this->xlsxRow(9, [['B', 'Teacher : ______________________', 15], ['H', 'Subject : ______________________', 15], ['R', 'Room : ______________________', 15], ['Z', 'Session : ' . ($sessionLabel ?: '-'), 15]], 21.6),
            $this->xlsxRow(10, [['A', '*ចំណាំ/Note : ✓ = វ្មាន/Present , T = មកយឺ/Tardy , E = អវ្មានមានច្បាប់/Excused Absence , U = អវ្មានគ្មានច្បាប់/Unexcused Absence', 12]], 19.95),
        ];

        $headerRow1 = [['A', 'Nº', 26], ['B', 'Name', 26], ['C', 'ID', 26], ['D', 'Gender', 26], ['E', 'Group', 26]];
        for ($day = 1; $day <= $days; $day++) $headerRow1[] = [$this->xlsxColumnName($day + 5), $day === 1 ? 'Date' : '', 26];
        $headerRow1[] = [$totalStart, 'Total', 26];
        $headerRow1[] = [$totalEnd, '', 26];
        $rows[] = $this->xlsxRow(11, $headerRow1, 24);

        $headerRow2 = [['A', '', 26], ['B', '', 26], ['C', '', 26], ['D', '', 26], ['E', '', 26]];
        for ($day = 1; $day <= $days; $day++) $headerRow2[] = [$this->xlsxColumnName($day + 5), (string) $day, 26];
        $headerRow2[] = [$totalStart, 'T', 26];
        $headerRow2[] = [$totalEnd, 'E+U', 26];
        $rows[] = $this->xlsxRow(12, $headerRow2, 19.95);

        $mergeRefs = ['AB2:' . $lastColumn . '2', 'AB3:' . $lastColumn . '3', 'AB4:' . $lastColumn . '4', 'AB5:' . $lastColumn . '5', 'A6:' . $lastColumn . '6', 'A7:' . $lastColumn . '7', 'B8:G8', 'H8:Q8', 'R8:Y8', 'Z8:' . $lastColumn . '8', 'B9:G9', 'H9:Q9', 'R9:Y9', 'Z9:' . $lastColumn . '9', 'A10:' . $lastColumn . '10', 'F11:' . $dateEnd . '11', $totalStart . '11:' . $totalEnd . '11', 'A11:A12', 'B11:B12', 'C11:C12', 'D11:D12', 'E11:E12'];

        foreach ($enrollments as $index => $row) {
            $khmerRow = 13 + ($index * 2);
            $englishRow = $khmerRow + 1;
            $bodyStyle = $index % 2 === 0 ? 5 : 22;
            $nameKhStyle = $index % 2 === 0 ? 4 : 22;
            $nameEnStyle = $index % 2 === 0 ? 5 : 22;
            $khmerCells = [['A', (string) ($index + 1), $bodyStyle], ['B', $row->student?->full_name_kh ?: '', $nameKhStyle], ['C', $row->student?->student_id ?: '-', $bodyStyle], ['D', strtoupper(substr((string) $row->student?->gender, 0, 1)) === 'F' ? 'F' : 'M', $bodyStyle], ['E', $row->session?->session_short_name ?: '-', $bodyStyle]];
            $englishCells = [['A', '', $bodyStyle], ['B', $row->student?->full_name_en ?: '-', $nameEnStyle], ['C', '', $bodyStyle], ['D', '', $bodyStyle], ['E', '', $bodyStyle]];
            for ($day = 1; $day <= $days; $day++) {
                $column = $this->xlsxColumnName($day + 5);
                $khmerCells[] = [$column, '', $bodyStyle];
                $englishCells[] = [$column, '', $bodyStyle];
                $mergeRefs[] = $column . $khmerRow . ':' . $column . $englishRow;
            }
            foreach ([$totalStart, $totalEnd] as $column) {
                $khmerCells[] = [$column, '', $bodyStyle];
                $englishCells[] = [$column, '', $bodyStyle];
                $mergeRefs[] = $column . $khmerRow . ':' . $column . $englishRow;
            }
            $rows[] = $this->xlsxRow($khmerRow, $khmerCells, 20.4);
            $rows[] = $this->xlsxRow($englishRow, $englishCells, 20.4);
            foreach (['A', 'C', 'D', 'E'] as $column) $mergeRefs[] = $column . $khmerRow . ':' . $column . $englishRow;
        }

        if ($enrollments->isEmpty()) {
            $rows[] = $this->xlsxRow(13, [['A', 'No students found.', 5]], 20);
            $mergeRefs[] = 'A13:' . $lastColumn . '13';
        }

        $noteRow = max(14, 13 + ($enrollments->count() * 2));
        $rows[] = $this->xlsxRow($noteRow, [['A', '* ' . $footerKhmerNote, 12]], 18);
        $rows[] = $this->xlsxRow($noteRow + 1, [['A', '* ' . $footerEnglishNote, 15]], 18);
        $pageColumn = $this->xlsxColumnName((int) floor(($days + 7) / 2));
        $rows[] = $this->xlsxRow($noteRow + 2, [[$pageColumn, 'Page 1 of 1', 2], [$lastColumn, $printedAt, 2]], 16.05);
        $lastRow = $noteRow + 2;
        $mergeRefs[] = 'A' . $noteRow . ':' . $lastColumn . $noteRow;
        $mergeRefs[] = 'A' . ($noteRow + 1) . ':' . $lastColumn . ($noteRow + 1);
        $mergeCells = '<mergeCells count="' . count($mergeRefs) . '">' . collect($mergeRefs)->map(fn ($ref) => '<mergeCell ref="' . $ref . '"/>')->implode('') . '</mergeCells>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="12" topLeftCell="A13" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols><col min="1" max="1" width="5" customWidth="1"/><col min="2" max="2" width="32" customWidth="1"/><col min="3" max="3" width="11" customWidth="1"/><col min="4" max="5" width="6" customWidth="1"/><col min="6" max="' . ($days + 5) . '" width="3.19921875" customWidth="1"/><col min="' . ($days + 6) . '" max="' . ($days + 7) . '" width="7" customWidth="1"/></cols>'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . $mergeCells
            . '<pageMargins left="0.2" right="0.2" top="0.35" bottom="0.35" header="0.1" footer="0.1"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            . ($hasLogo ? '<drawing r:id="rId1"/>' : '')
            . '</worksheet>';
    }
    private function xlsxRow(int $row, array $cells, int|float|null $height = null): string
    {
        $heightAttribute = $height ? ' ht="' . $height . '" customHeight="1"' : '';

        return '<row r="' . $row . '"' . $heightAttribute . '>' . collect($cells)->map(function ($cell) use ($row) {
            [$column, $value, $style] = $cell;

            return '<c r="' . $column . $row . '" s="' . $style . '" t="inlineStr"><is>' . $this->xlsxInlineStringXml($value) . '</is></c>';
        })->implode('') . '</row>';
    }

    private function shortGender(?string $gender): string
    {
        $gender = mb_strtolower(trim((string) $gender));

        return str_starts_with($gender, 'f') || str_contains($gender, 'ស្រី') ? 'F' : (str_starts_with($gender, 'm') || str_contains($gender, 'ប្រុស') ? 'M' : '-');
    }

    private function displayDate($date, string $format = 'd-M-Y'): string
    {
        if (!$date) return '-';

        return ($date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date))->format($format);
    }

    private function withdrawalStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pending',
            'principal_approved' => 'Principal Approved',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'all' => 'All Statuses',
            default => $status ? Str::headline($status) : '-',
        };
    }

    private function withdrawalReasonText($row): string
    {
        $khmer = collect([
            $row->reason_kh,
            $row->other_reason_kh,
        ])->filter(fn ($value) => filled(trim((string) $value)))->map(fn ($value) => trim((string) $value))->unique()->join(' / ');
        $english = collect([
            $row->reason,
            $row->other_reason_en,
            $row->new_school ? 'New school: ' . $row->new_school : null,
        ])->filter(fn ($value) => filled(trim((string) $value)))->map(fn ($value) => trim((string) $value))->unique()->join(' / ');
        $lines = collect([$khmer, $english])->filter(fn ($value) => filled(trim((string) $value)))->values();

        return $lines->isEmpty() ? '-' : $lines->join("\n");
    }

    private function xlsxStatisticValue(int $total, int $newTotal, bool $emphasized = false): array
    {
        return [
            'rich' => [
                [
                    'text' => (string) $total,
                    'size' => $emphasized ? 11 : 10,
                    'bold' => $emphasized,
                ],
                [
                    'text' => "\nNew: " . $newTotal,
                    'size' => $emphasized ? 7 : 6,
                    'bold' => false,
                ],
            ],
        ];
    }

    private function xlsxInlineStringXml(mixed $value): string
    {
        if (is_array($value) && isset($value['rich']) && is_array($value['rich'])) {
            return collect($value['rich'])->map(function ($run) {
                $text = (string) ($run['text'] ?? '');
                $size = (int) ($run['size'] ?? 10);
                $bold = !empty($run['bold']) ? '<b/>' : '';
                $preserve = preg_match('/^\s|\s$/u', $text) || str_contains($text, "\n") ? ' xml:space="preserve"' : '';

                return '<r><rPr><sz val="' . $size . '"/><color rgb="FF002147"/><rFont val="Calibri"/><family val="2"/>' . $bold . '</rPr><t' . $preserve . '>' . $this->xlsxEscape($text) . '</t></r>';
            })->implode('');
        }

        return '<t>' . $this->xlsxEscape((string) $value) . '</t>';
    }

    private function xlsxEscape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function xlsxColumnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function xlsxContentTypes(int $sheetCount = 1, array $drawingSheetNumbers = [], array $imageExtensions = []): string
    {
        $worksheetOverrides = collect(range(1, max(1, $sheetCount)))
            ->map(fn ($index) => '<Override PartName="/xl/worksheets/sheet' . $index . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>')
            ->implode('');
        $drawingOverrides = $drawingSheetNumbers
            ? collect($drawingSheetNumbers)
                ->map(fn ($index) => '<Override PartName="/xl/drawings/drawing' . $index . '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>')
                ->implode('')
            : '';
        $imageDefault = collect($imageExtensions)
            ->flatMap(function ($extension) {
                return match (strtolower((string) $extension)) {
                    'jpg', 'jpeg' => [
                        '<Default Extension="jpeg" ContentType="image/jpeg"/>',
                        '<Default Extension="jpg" ContentType="image/jpeg"/>',
                    ],
                    'gif' => ['<Default Extension="gif" ContentType="image/gif"/>'],
                    default => ['<Default Extension="png" ContentType="image/png"/>'],
                };
            })
            ->unique()
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . $imageDefault
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $worksheetOverrides
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $drawingOverrides
            . '</Types>';
    }

    private function xlsxRootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function xlsxWorkbook(array $sheets): string
    {
        $sheetNodes = collect($sheets)->map(function ($sheet, $index) {
            return '<sheet name="' . $this->xlsxEscape($this->sanitizeSheetName($sheet['name'] ?? ('Sheet ' . ($index + 1)))) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
        })->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $sheetNodes . '</sheets></workbook>';
    }

    private function xlsxWorkbookRels(int $sheetCount = 1): string
    {
        $relationships = collect(range(1, max(1, $sheetCount)))
            ->map(fn ($index) => '<Relationship Id="rId' . $index . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $index . '.xml"/>')
            ->implode('');
        $relationships .= '<Relationship Id="rId' . (max(1, $sheetCount) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relationships . '</Relationships>';
    }

    private function sanitizeSheetName(string $name): string
    {
        $name = trim(preg_replace('/[\\\\\/\?\*\[\]:]/', '-', $name)) ?: 'Sheet';

        return mb_substr($name, 0, 31);
    }

    private function uniqueSheetName(string $name, array &$usedNames): string
    {
        $base = $this->sanitizeSheetName($name);
        $sheetName = $base;
        $counter = 2;

        while (isset($usedNames[mb_strtolower($sheetName)])) {
            $suffix = ' ' . $counter;
            $sheetName = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
            $counter++;
        }

        $usedNames[mb_strtolower($sheetName)] = true;

        return $sheetName;
    }

    private function moeysKhmerDateLines(?string $reportDate, ?SchoolInfo $campus): array
    {
        $date = \Carbon\Carbon::parse($reportDate ?: now('Asia/Phnom_Penh')->format('Y-m-d'));
        $digits = fn ($value) => $this->khmerDigits((string) $value);
        $weekdays = ['អាទិ្យ', 'ចន្ទ', 'អង្គារ', 'ពុធ', 'ព្រហស្បិ', 'សុក្រ', 'សៅរ'];
        $months = ['មករា', 'កុម្ភៈ', 'មីនា', 'មសា', 'ឧសភា', 'មិុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'ុលា', 'វិច្ឆិកា', 'ធ្នូ'];
        $location = $this->moeysLocation($campus);

        return [
            $this->moeysKhmerLunarDate($date) ?: ('្ងៃ' . $weekdays[$date->dayOfWeek] . ' ព.ស.' . $digits($date->year + 544)),
            $location . ' ្ងៃទី' . $digits($date->day) . ' ែ' . $months[$date->month - 1] . ' ឆ្នាំ' . $digits($date->year),
        ];
    }

    private function moeysKhmerLunarDate(\Carbon\Carbon $date): ?string
    {
        $packagePath = base_path('node_modules/@thyrith/momentkh/momentkh.js');
        if (!is_file($packagePath) || !class_exists(\Symfony\Component\Process\Process::class)) {
            return null;
        }

        $node = $this->nodeBinary();
        if ($node === null) {
            return null;
        }

        $script = <<<'JS'
const momentkh = require(process.argv[1]);
const year = Number(process.argv[2]);
const month = Number(process.argv[3]);
const day = Number(process.argv[4]);
const khmerDigits = (value) => String(value).replace(/[0-9]/g, (digit) => '០១២៣៤៥៦៧៨៩'[digit]);
const result = momentkh.fromGregorian(year, month, day);
const lunar = result.khmer;
process.stdout.write(`្ងៃ${lunar.dayOfWeekName} ${khmerDigits(lunar.day)}${lunar.moonPhaseName} ែ${lunar.monthName} ឆ្នាំ${lunar.animalYearName} ${lunar.sakName} ព.ស.${khmerDigits(lunar.beYear)}`);
JS;

        try {
            $process = new \Symfony\Component\Process\Process([
                $node,
                '-e',
                $script,
                $packagePath,
                (string) $date->year,
                (string) $date->month,
                (string) $date->day,
            ], base_path());
            $process->setTimeout(3);
            $process->run();

            if ($process->isSuccessful()) {
                $output = trim($process->getOutput());
                return $output !== '' ? $output : null;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function nodeBinary(): ?string
    {
        $candidates = [
            'C:\\laragon\\bin\\nodejs\\node-v22\\node.exe',
            'C:\\laragon\\bin\\nodejs\\node-v22\\node.cmd',
            'node',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === 'node' || is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
    private function khmerDigits(string $value): string
    {
        return strtr($value, ['0' => '០', '1' => '១', '2' => '២', '3' => '៣', '4' => '៤', '5' => '៥', '6' => '៦', '7' => '៧', '8' => '៨', '9' => '៩']);
    }

    private function studentIdBookCoverData(array $payload, ?AcademicYear $academicYear, ?SchoolInfo $campus): array
    {
        $filters = $payload['filters'] ?? [];
        $enrollments = collect($payload['enrollments'] ?? []);
        $codes = $enrollments
            ->pluck('id_book_list_no')
            ->filter(fn ($code) => filled($code))
            ->map(fn ($code) => (string) $code)
            ->sort()
            ->values();

        $level = $filters['id_book_level'] ?? '';
        $gradeRange = match ($level) {
            'kindergarten' => '្នាក់ម្យ្យ',
            'secondary' => 'ថ្នាក់ទី' . $this->khmerDigits('7') . ' ដល់ ថ្នាក់ទី' . $this->khmerDigits('12'),
            default => 'ថ្នាក់ទី' . $this->khmerDigits('1') . ' ដល់ ថ្នាក់ទី' . $this->khmerDigits('6'),
        };
        $schoolLevelPrefix = match ($level) {
            'kindergarten' => 'សាលាម្យ្យ',
            'secondary' => 'វិទ្យាលយ',
            default => 'សាលាបឋមសិក្សា',
        };

        $startCode = $codes->first();
        $endCode = $codes->last();
        $codeRange = $startCode && $endCode
            ? 'អ្លពី ' . $this->khmerDigits($startCode) . ' ដល់' . $this->khmerDigits($endCode)
            : 'អ្លពី ........................ ដល់........................';

        $academicYearText = $academicYear?->academic_year
            ? 'ឆ្នាំសិក្សា ' . $this->khmerDigits($academicYear->academic_year)
            : 'ឆ្នាំសិក្សា ........................';

        $schoolName = trim((string) ($campus?->school_name_kh ?: 'វស្ទើនអន្រជាិ'));
        $schoolName = trim(preg_replace('/^សាលា/u', '', $schoolName)) ?: 'វស្ទើនអន្រជាិ';
        $campusNameKh = trim((string) ($campus?->campus_name_kh ?: ''));

        return [
            'educationOffice' => 'មន្ទីរអប់រំ យុវជន និង កីឡា ' . $this->moeysLocation($campus),
            'schoolLevelPrefix' => $schoolLevelPrefix,
            'schoolName' => $schoolName,
            'campusNameKh' => $campusNameKh,
            'gradeRange' => $gradeRange,
            'codeRange' => $codeRange,
            'academicYear' => $academicYearText,
        ];
    }

    private function moeysLocation(?SchoolInfo $campus): string
    {
        $value = mb_strtolower(($campus?->campus_name_kh ?? '') . ' ' . ($campus?->campus_name_en ?? '') . ' ' . ($campus?->address ?? ''));
        $locations = [
            ['កំពង់ចាម|kampong cham', '្កំពង់ចាម'],
            ['ព្រះសីហនុ|sihanoukville|preah sihanouk', '្ព្រះសីហនុ'],
            ['កំពង់ស្ពឺ|kampong speu', '្កំពង់ស្ពឺ'],
            ['កំពង់ឆ្នាំង|kampong chhnang', '្កំពង់ឆ្នាំង'],
            ['កំពង់ធំ|kampong thom', '្កំពង់ធំ'],
            ['កណ្ដាល|កណ្ាល|kandal', '្កណ្ដាល'],
            ['កោះកុង|koh kong', '្កោះកុង'],
            ['ក្រចះ|kratie', '្ក្រចះ'],
            ['ាកែវ|takeo', '្ាកែវ'],
            ['បា់ដំបង|battambang', '្បា់ដំបង'],
            ['បន្ទាយមានជយ|banteay meanchey', '្បន្ទាយមានជយ'],
            ['ពោធិសា់|pursat', '្ពោធិសា់'],
            ['ព្រៃវែង|prey veng', '្ព្រៃវែង'],
            ['សៀមរាប|siem reap', '្សៀមរាប'],
            ['ស្វាយរៀង|svay rieng', '្ស្វាយរៀង'],
            ['ស្ទឹង្រែង|stung treng', '្ស្ទឹង្រែង'],
            ['កំព|kampot', '្កំព'],
            ['ប៉ៃលិន|pailin', '្ប៉ៃលិន'],
            ['ឧ្រមានជយ|oddar meanchey', '្ឧ្រមានជយ'],
            ['មណ្ឌលគិរី|mondulkiri', '្មណ្ឌលគិរី'],
            ['រនគិរី|ratanakiri', '្រនគិរី'],
            ['្បូងឃ្មុំ|tboung khmum', '្្បូងឃ្មុំ'],
            ['បឹងឈូក|bch', 'រាជធានីភ្នំពញ'],
            ['ភ្នំពញ|phnom penh|រាជធានី', 'រាជធានីភ្នំពញ'],
        ];
        foreach ($locations as [$pattern, $location]) {
            if (preg_match('/' . $pattern . '/iu', $value)) {
                return $location;
            }
        }

        return 'រាជធានីភ្នំពញ';
    }
    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="11"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="16"/><name val="Arial"/><color rgb="FF1F3A5F"/></font><font><b/><sz val="11"/><name val="Arial"/></font><font><sz val="12"/><name val="Khmer OS Siemreap"/></font><font><b/><sz val="11"/><name val="Khmer OS Muol Light"/></font><font><sz val="16"/><color rgb="FF1F3A5F"/><name val="Khmer OS Muol Light"/></font><font><b/><sz val="12"/><name val="Khmer OS Siemreap"/></font><font><sz val="12"/><name val="Arial"/></font><font><sz val="14"/><name val="Khmer OS Siemreap"/></font><font><sz val="12"/><name val="Khmer OS Muol Light"/></font><font><b/><sz val="12"/><name val="Arial"/><color rgb="FFFFFFFF"/></font></fonts>'
            . '<fills count="13"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF1F3F5"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9EDF2"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9D7E3"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE2F0D9"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFCE4D6"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFB4C7E7"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF2F2F2"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF203864"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF3B73C9"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF3B73C9"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFFF99"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="6"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FF9AA8BA"/></left><right style="thin"><color rgb="FF9AA8BA"/></right><top style="thin"><color rgb="FF9AA8BA"/></top><bottom style="thin"><color rgb="FF9AA8BA"/></bottom><diagonal/></border><border><left style="thin"><color rgb="FF222222"/></left><right style="thin"><color rgb="FF222222"/></right><top style="thin"><color rgb="FF222222"/></top><bottom style="thin"><color rgb="FF222222"/></bottom><diagonal/></border><border><left style="thin"><color rgb="FF9AA8BA"/></left><right style="thin"><color rgb="FF9AA8BA"/></right><top style="thin"><color rgb="FF9AA8BA"/></top><bottom/><diagonal/></border><border><left style="thin"><color rgb="FF9AA8BA"/></left><right style="thin"><color rgb="FF9AA8BA"/></right><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FF9AA8BA"/></left><right style="thin"><color rgb="FF9AA8BA"/></right><top/><bottom style="thin"><color rgb="FF9AA8BA"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="33"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="6" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="7" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="8" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="9" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="7" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="3" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="4" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="5" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="6" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="7" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="8" borderId="2" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="8" borderId="2" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="10" fillId="9" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="10" fillId="10" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="10" fillId="11" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="12" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="3" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="4" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="5" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="5" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="3" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" textRotation="90" wrapText="1"/></xf></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
    private function xlsxWorksheetRels(int $sheetNumber): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing' . $sheetNumber . '.xml"/></Relationships>';
    }

    private function xlsxLogoDrawing(int $sheetNumber, int $tacteingColumn = 4, bool $showTacteing = true, int $logoCx = 931560, int $logoCy = 950000, int $logoRow = 0, int $logoRowOff = 217160, int $logoColOff = 97140): string
    {
        $toColumn = $tacteingColumn + 3;
        $logoAnchor = '<xdr:oneCellAnchor><xdr:from><xdr:col>0</xdr:col><xdr:colOff>' . $logoColOff . '</xdr:colOff><xdr:row>' . $logoRow . '</xdr:row><xdr:rowOff>' . $logoRowOff . '</xdr:rowOff></xdr:from><xdr:ext cx="' . $logoCx . '" cy="' . $logoCy . '"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="' . $sheetNumber . '" name="Report Logo"/><xdr:cNvPicPr/></xdr:nvPicPr><xdr:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
        $tacteingAnchor = $showTacteing ? '<xdr:twoCellAnchor><xdr:from><xdr:col>' . $tacteingColumn . '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>4</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from><xdr:to><xdr:col>' . $toColumn . '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>5</xdr:row><xdr:rowOff>90000</xdr:rowOff></xdr:to><xdr:sp macro="" textlink=""><xdr:nvSpPr><xdr:cNvPr id="' . ($sheetNumber + 1000) . '" name="Tacteing 5"/><xdr:cNvSpPr txBox="1"/></xdr:nvSpPr><xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/><a:ln><a:noFill/></a:ln></xdr:spPr><xdr:txBody><a:bodyPr vertOverflow="clip" horzOverflow="clip" wrap="square" rtlCol="0" anchor="ctr"/><a:lstStyle/><a:p><a:pPr algn="ctr"/><a:r><a:rPr lang="en-US" sz="3200"><a:latin typeface="Tacteing" pitchFamily="2" charset="0"/></a:rPr><a:t>5</a:t></a:r></a:p></xdr:txBody></xdr:sp><xdr:clientData/></xdr:twoCellAnchor>' : '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">' . $logoAnchor . $tacteingAnchor . '</xdr:wsDr>';
    }
    private function xlsxLogoDrawingRels(string $logoExtension): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/report-logo.' . $logoExtension . '"/></Relationships>';
    }

    private function xlsxStudentIdBookPhotoDrawing(int $sheetNumber, array $photos): string
    {
        $anchors = collect($photos)->map(function ($photo, int $index) use ($sheetNumber) {
            $row = max(1, (int) ($photo['row'] ?? 2)) - 1;
            $relationshipId = 'rId' . ($index + 1);
            $pictureId = ($sheetNumber * 1000) + $index + 1;

            return '<xdr:oneCellAnchor>'
                . '<xdr:from><xdr:col>2</xdr:col><xdr:colOff>365760</xdr:colOff><xdr:row>' . $row . '</xdr:row><xdr:rowOff>91440</xdr:rowOff></xdr:from>'
                . '<xdr:ext cx="960120" cy="1280160"/>'
                . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="' . $pictureId . '" name="Student Photo ' . ($index + 1) . '"/><xdr:cNvPicPr/></xdr:nvPicPr>'
                . '<xdr:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="' . $relationshipId . '"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
                . '<xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
        })->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">' . $anchors . '</xdr:wsDr>';
    }

    private function xlsxStudentIdBookPhotoDrawingRels(int $sheetNumber, array $photos): string
    {
        $relationships = collect($photos)->map(function ($photo, int $index) use ($sheetNumber) {
            return '<Relationship Id="rId' . ($index + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/' . $this->studentIdBookPhotoMediaName($sheetNumber, $index, $photo['extension'] ?? 'png') . '"/>';
        })->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relationships . '</Relationships>';
    }

    private function studentIdBookPhotoMediaName(int $sheetNumber, int $photoIndex, string $extension): string
    {
        return 'student-id-book-photo-' . $sheetNumber . '-' . ($photoIndex + 1) . '.' . strtolower($extension ?: 'png');
    }

    private function xlsxImageExtension(string $path): string
    {
        return match (mime_content_type($path)) {
            'image/jpeg' => 'jpeg',
            'image/gif' => 'gif',
            default => 'png',
        };
    }

    private function reportExcelLogoPath(string $type = ''): ?string
    {
        $branding = BrandingSetting::current();
        $school = SchoolInfo::latest('id')->first();
        $candidates = in_array($type, ['student-statistics', 'student-statistics-detail', 'attendance-list', 'withdrawn-students'], true)
            ? array_filter([
                $branding?->report_logo_1_path ? storage_path('app/public/' . ltrim($branding->report_logo_1_path, '/')) : null,
                $branding?->report_logo_2_path ? storage_path('app/public/' . ltrim($branding->report_logo_2_path, '/')) : null,
                $school?->logo_path ? storage_path('app/public/' . ltrim($school->logo_path, '/')) : null,
                storage_path('app/public/school_logo/wis_logo.png'),
            ])
            : array_filter([
                $branding?->report_logo_2_path ? storage_path('app/public/' . ltrim($branding->report_logo_2_path, '/')) : null,
                $branding?->report_logo_1_path ? storage_path('app/public/' . ltrim($branding->report_logo_1_path, '/')) : null,
                $school?->logo_path ? storage_path('app/public/' . ltrim($school->logo_path, '/')) : null,
                storage_path('app/public/school_logo/wis_logo.png'),
            ]);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function writeZipArchive(string $path, array $entries): void
    {
        $file = fopen($path, 'wb');
        $centralDirectory = '';
        $offset = 0;
        $dosTime = $this->zipDosTime();
        $dosDate = $this->zipDosDate();

        foreach ($entries as $name => $contents) {
            $name = str_replace('\\', '/', $name);
            $contents = (string) $contents;
            $size = strlen($contents);
            $crc = crc32($contents);
            $nameLength = strlen($name);
            $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0);

            fwrite($file, $localHeader . $name . $contents);
            $centralDirectory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset) . $name;
            $offset += strlen($localHeader) + $nameLength + $size;
        }

        $centralDirectorySize = strlen($centralDirectory);
        fwrite($file, $centralDirectory);
        fwrite($file, pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), $centralDirectorySize, $offset, 0));
        fclose($file);
    }

    private function zipDosTime(): int
    {
        $time = now();

        return ($time->hour << 11) | ($time->minute << 5) | intdiv($time->second, 2);
    }

    private function zipDosDate(): int
    {
        $time = now();

        return (($time->year - 1980) << 9) | ($time->month << 5) | $time->day;
    }
    private function reportPayload(Request $request, string $type, bool $preview = false): array
    {
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer'],
            'period_type' => ['nullable', 'in:all,regular,summer'],
            'campus_id' => ['nullable', 'integer'],
            'id_book_level' => ['nullable', 'in:kindergarten,primary,secondary'],
            'transcript_level' => ['nullable', 'in:primary,secondary'],
            'transcript_student_id' => ['nullable', 'integer', 'min:1'],
            'certificate_student_id' => ['nullable', 'integer', 'min:1'],
            'certificate_show_qr' => ['nullable', 'boolean'],
            'photo_student_id' => ['nullable', 'integer', 'min:1'],
            'photo_size' => ['nullable', 'in:4x6,3x4'],
            'withdrawal_status' => ['nullable', 'in:all,pending,principal_approved,approved,rejected,cancelled'],
            'grade_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'grade_class' => ['nullable', 'string'],
            'session_id' => ['nullable', 'integer'],
            'print_scope' => ['nullable', 'in:selected_class,selected_classes,all_classes'],
            'print_format' => ['nullable', 'in:internal,moeys'],
            'print_grade_classes' => ['nullable', 'array'],
            'print_grade_classes.*' => ['string'],
            'selected_columns_submitted' => ['nullable', 'string'],
            'selected_columns' => ['nullable', 'array'],
            'selected_columns.*' => ['string'],
            'preview_page_size' => ['nullable', $type === 'g12-certificate-wis' ? 'in:'.implode(',', G12CertificateReport::PREVIEW_SIZES) : ($type === 'g9-certificate-wis' ? 'in:'.implode(',', G9CertificateReport::PREVIEW_SIZES) : ($type === 'k3-certificate-wis' ? 'in:'.implode(',', K3CertificateReport::PREVIEW_SIZES) : 'in:all,25,50,75,100'))],
            'preview_page' => ['nullable', 'integer', 'min:1'],
            'report_date' => ['nullable', 'date'],
            'month' => ['nullable', 'date_format:Y-m'],
            'score_columns' => ['nullable', 'integer', 'min:1', 'max:12'],
            'print_type' => ['nullable', 'in:quarter_1,quarter_2,quarter_3,quarter_4'],
        ]);
        $filters['month'] = $filters['month'] ?? now()->format('Y-m');
        $filters['period_type'] = $filters['period_type'] ?? 'all';
        $filters['score_columns'] = (int) ($filters['score_columns'] ?? 5);
        $filters['print_type'] = $filters['print_type'] ?? 'quarter_1';
        $filters['report_date'] = $filters['report_date'] ?? now()->format('Y-m-d');
        if ($type === 'student-photo') {
            $request->validate(['grade_class' => ['nullable', 'regex:/^\d+:\d+$/']]);
            return app(StudentPhotoReport::class)->payload($request, $filters, $preview);
        }
        if ($type === 'k3-certificate-wis') {
            return app(K3CertificateReport::class)->payload($request, $filters, $preview);
        }
        if ($type === 'g9-certificate-wis') {
            return app(G9CertificateReport::class)->payload($request, $filters, $preview);
        }
        if ($type === 'g12-certificate-wis') {
            return app(G12CertificateReport::class)->payload($request, $filters, $preview);
        }
        if ($type === 'moeys-id-number-book') {
            $availableColumns = array_keys($this->getStudentListColumnDefinitions());
            $defaultColumns = ['student_id', 'full_name_en', 'gender', 'date_of_birth', 'academic_year', 'campus', 'grade_class', 'group', 'student_type', 'mother_name_en', 'mother_phone', 'father_name_en', 'father_phone'];
            $submittedColumns = $request->has('selected_columns_submitted') || $request->has('selected_columns');
            $filters['selected_columns'] = collect($submittedColumns ? ($filters['selected_columns'] ?? []) : $defaultColumns)->intersect($availableColumns)->values()->all();
            $filters['preview_page_size'] = $filters['preview_page_size'] ?? (string) self::GET_STUDENT_LIST_PREVIEW_DEFAULT_SIZE;
            $filters['preview_page'] = max(1, (int) ($filters['preview_page'] ?? 1));
            $sort = $request->validate([
                'sort_by' => ['nullable', \Illuminate\Validation\Rule::in(array_merge(['row_no'], $availableColumns))],
                'sort_dir' => ['nullable', 'in:asc,desc'],
            ]);
            $filters['sort_by'] = $sort['sort_by'] ?? '';
            $filters['sort_dir'] = $sort['sort_dir'] ?? 'asc';
            if ($filters['sort_by'] !== 'row_no' && !in_array($filters['sort_by'], $filters['selected_columns'], true)) {
                $filters['sort_by'] = '';
            }
        }
        if ($type === 'moeys-sikkhakarik-book') {
            $filters['transcript_level'] = $filters['transcript_level'] ?? '';
        }
        if ($type === 'withdrawn-students') {
            $filters['withdrawal_status'] = $filters['withdrawal_status'] ?? 'approved';
        }
        if ($this->isReportStub($type)) {
            return [
                'isReportStub' => true,
                'filters' => $filters,
                'enrollments' => collect(),
                'previewLimit' => null,
                'hasMorePreviewRows' => false,
                'studentListPagination' => null,
                'studentSummary' => $this->emptyStudentListSummary(),
                'statistics' => null,
                'hasDataFilter' => false,
            ];
        }
        if (!empty($filters['academic_year_id']) && ($filters['period_type'] ?? 'all') !== 'all') {
            $academicYearPeriod = AcademicYear::whereKey($filters['academic_year_id'])->value('period_type');
            if ($academicYearPeriod !== $filters['period_type']) {
                unset($filters['academic_year_id']);
            }
        }
        if (!empty($filters['grade_class']) && str_contains($filters['grade_class'], ':')) {
            [$filters['grade_id'], $filters['class_id']] = array_map('intval', explode(':', $filters['grade_class'], 2));
        }
        if (($this->isStudentListReport($type) || $this->isStudentContactListReport($type) || $type === 'attendance-list' || $type === 'score-list') && !empty($filters['print_grade_classes'])) {
            unset($filters['grade_id'], $filters['class_id'], $filters['grade_class']);
        } elseif (($this->isStudentListReport($type) || $this->isStudentContactListReport($type) || $type === 'attendance-list' || $type === 'score-list') && ($filters['print_scope'] ?? null) === 'all_classes') {
            unset($filters['grade_id'], $filters['class_id'], $filters['grade_class']);
        }
        if ($type === 'moeys-id-number-book') {
            $hasDataFilter = filled($filters['academic_year_id'] ?? null);
        } elseif ($type === 'moeys-sikkhakarik-book') {
            $hasDataFilter = filled($filters['academic_year_id'] ?? null)
                && filled($filters['campus_id'] ?? null)
                && filled($filters['transcript_level'] ?? null)
                && filled($filters['grade_class'] ?? null)
                && filled($filters['report_date'] ?? null);
        } elseif ($type === 'student-id-books-moeys') {
            unset($filters['grade_id'], $filters['class_id'], $filters['grade_class'], $filters['print_scope'], $filters['print_grade_classes']);
            $hasDataFilter = filled($filters['academic_year_id'] ?? null)
                && filled($filters['id_book_level'] ?? null)
                && filled($filters['campus_id'] ?? null);
        } elseif ($type === 'withdrawn-students') {
            $hasDataFilter = collect(['academic_year_id', 'campus_id', 'grade_id', 'class_id', 'session_id'])
                ->contains(fn ($key) => filled($filters[$key] ?? null));
            $hasDataFilter = $hasDataFilter || (($filters['withdrawal_status'] ?? 'approved') !== 'all');
        } elseif (in_array($type, ['attendance-list', 'score-list'], true)) {
            $hasDataFilter = filled($filters['academic_year_id'] ?? null)
                && filled($filters['campus_id'] ?? null)
                && (
                    filled($filters['grade_id'] ?? null)
                    || !empty($filters['print_grade_classes'])
                    || ($filters['print_scope'] ?? null) === 'all_classes'
                );
        } else {
            $hasDataFilter = collect(['academic_year_id', 'campus_id', 'grade_id', 'class_id', 'session_id'])
                ->contains(fn ($key) => filled($filters[$key] ?? null));
            $hasDataFilter = $hasDataFilter || !empty($filters['print_grade_classes']);
        }
        $withdrawalQuery = $type === 'withdrawn-students' && $hasDataFilter ? $this->withdrawals($request, $filters) : null;
        $withdrawals = $withdrawalQuery ? $withdrawalQuery->get() : collect();
        $enrollmentQuery = $hasDataFilter && $type !== 'withdrawn-students' ? $this->enrollments($request, $filters, $type) : null;
        $transcriptStudentOptions = collect();
        if ($preview && $type === 'moeys-sikkhakarik-book' && $hasDataFilter) {
            $transcriptStudentOptions = $this->transcriptStudentOptions($request, $filters);
        }
        $usesLimitedPreview = $preview && ($this->isStudentListReport($type) || $this->isStudentContactListReport($type));
        $currentPreviewLimit = self::PREVIEW_LIMIT;
        $studentListPagination = null;
        if ($preview && $type === 'moeys-id-number-book' && $enrollmentQuery) {
            $pageSize = in_array((string) ($filters['preview_page_size'] ?? ''), self::GET_STUDENT_LIST_PREVIEW_SIZES, true)
                ? (string) $filters['preview_page_size']
                : (string) self::GET_STUDENT_LIST_PREVIEW_DEFAULT_SIZE;
            $page = max(1, (int) ($filters['preview_page'] ?? 1));
            $totalRows = (clone $enrollmentQuery)->count();
            $sortedIds = filled($filters['sort_by'] ?? null)
                // Hydrate in batches so sorting a year does not load every student's relations at once.
                ? $this->getStudentListSortedIds((clone $enrollmentQuery)->orderBy('tb_student_enrollment.id')->lazy(200), $filters['sort_by'], $filters['sort_dir'])
                : null;
            if ($pageSize === 'all') {
                $lastPage = 1;
                $page = 1;
                $enrollments = $enrollmentQuery->get();
            } else {
                $perPage = (int) $pageSize;
                $lastPage = max(1, (int) ceil($totalRows / $perPage));
                $page = min($page, $lastPage);
                $enrollments = $sortedIds === null
                    ? $enrollmentQuery->skip(($page - 1) * $perPage)->take($perPage)->get()
                    : $enrollmentQuery->whereIn('tb_student_enrollment.id', $sortedIds->slice(($page - 1) * $perPage, $perPage)->all())->get();
            }
            if ($sortedIds !== null) {
                $positions = $sortedIds->flip();
                $enrollments = $enrollments->sortBy(fn ($row) => $positions[$row->id])->values();
            }
            $filters['preview_page_size'] = $pageSize;
            $filters['preview_page'] = $page;
            $studentListPagination = [
                'page' => $page,
                'pageSize' => $pageSize,
                'perPage' => $pageSize === 'all' ? $totalRows : (int) $pageSize,
                'total' => $totalRows,
                'lastPage' => $lastPage,
                'from' => $totalRows === 0 ? 0 : ($pageSize === 'all' ? 1 : (($page - 1) * (int) $pageSize) + 1),
                'to' => $totalRows === 0 ? 0 : ($pageSize === 'all' ? $totalRows : min($totalRows, $page * (int) $pageSize)),
                'sizes' => self::GET_STUDENT_LIST_PREVIEW_SIZES,
            ];
            $hasMorePreviewRows = false;
        } else {
            $enrollments = $enrollmentQuery
                ? ($usesLimitedPreview
                    ? $enrollmentQuery->limit($currentPreviewLimit + 1)->get()
                    : $enrollmentQuery->get())
                : collect();
            $hasMorePreviewRows = $usesLimitedPreview && $enrollments->count() > $currentPreviewLimit;
            if ($hasMorePreviewRows) {
                $enrollments = $enrollments->take($currentPreviewLimit);
            }
            if ($type === 'moeys-id-number-book' && filled($filters['sort_by'] ?? null)) {
                $positions = $this->getStudentListSortedIds($enrollments, $filters['sort_by'], $filters['sort_dir'])->flip();
                $enrollments = $enrollments->sortBy(fn ($row) => $positions[$row->id])->values();
            }
        }
        $studentSummary = ($this->isStudentListReport($type) || $this->isStudentContactListReport($type)) && $hasDataFilter
            ? $this->studentListSummary($request, $filters)
            : $this->emptyStudentListSummary();

        return [
            'filters' => $filters,
            'enrollments' => $enrollments,
            'transcriptStudentOptions' => $transcriptStudentOptions,
            'withdrawals' => $withdrawals,
            'previewLimit' => $usesLimitedPreview ? $currentPreviewLimit : null,
            'hasMorePreviewRows' => $hasMorePreviewRows,
            'studentListPagination' => $studentListPagination,
            'studentSummary' => $studentSummary,
            'statistics' => $this->isStudentStatisticsReport($type) ? ($hasDataFilter ? ($type === 'student-statistics-detail' ? $this->statisticsDetail($request, $filters) : $this->statistics($request, $filters)) : ($type === 'student-statistics-detail' ? $this->emptyStatisticsDetail() : $this->emptyStatistics())) : null,
            'hasDataFilter' => $hasDataFilter,
            'selectedColumns' => $type === 'moeys-id-number-book' ? collect($filters['selected_columns'] ?? [])->map(fn ($key) => $this->getStudentListColumnDefinitions()[$key] ?? null)->filter()->values()->all() : [],
            'studentListColumns' => $type === 'moeys-id-number-book' ? collect($this->getStudentListColumnDefinitions())->groupBy('group')->all() : [],
        ];
    }

    private function transcriptStudentOptions(Request $request, array $filters): Collection
    {
        // Keep the entire permitted class available after selecting one student.
        unset($filters['transcript_student_id']);
        return $this->enrollments($request, $filters, 'moeys-sikkhakarik-book')
            ->withoutEagerLoads()->reorder()->toBase()
            ->select('tb_student.id', 'tb_student.student_id', 'tb_student.full_name_kh', 'tb_student.full_name_en')
            ->distinct()->orderBy('tb_student.full_name_kh')->orderBy('tb_student.full_name_en')->orderBy('tb_student.id')
            ->get();
    }

    private function academicYears(array $filters, string $type = ''): Collection
    {
        return AcademicYear::query()
            ->when(in_array($type, ['moeys-sikkhakarik-book', 'k3-certificate-wis', 'g9-certificate-wis', 'g12-certificate-wis'], true), fn ($q) => $q->where('period_type', 'regular'))
            ->when(($filters['period_type'] ?? 'all') !== 'all', fn ($q) => $q->where('period_type', $filters['period_type']))
            ->orderByDesc('academic_year')
            ->get(['id', 'academic_year', 'period_type', 'parent_academic_year_id']);
    }

    private function emptyStatistics(): array
    {
        $columns = Grade::where('status', 1)->orderByRaw('CAST(grade_order AS UNSIGNED)')->pluck('grade')->all();
        return ['columns' => $columns, 'rows' => [], 'columnTotals' => array_fill(0, count($columns), 0), 'columnNewTotals' => array_fill(0, count($columns), 0), 'grandTotal' => 0, 'grandNewTotal' => 0];
    }

    private function emptyStatisticsDetail(): array
    {
        return ['groups' => [], 'campuses' => [], 'totals' => ['total' => 0, 'female' => 0, 'old' => 0, 'new' => 0, 'monthly_dropout' => 0, 'cumulative_dropout' => 0]];
    }

    private function campuses(Request $request, array $filters = [], string $type = ''): Collection
    {
        $campuses = $request->user()->isSuperAdmin()
            ? SchoolInfo::where('status', 1)->orderBy('campus_name_en')->get(['id', 'campus_name_en'])
            : $request->user()->accessibleCampuses()->where('tb_school_info.status', 1)->orderBy('campus_name_en')->get(['tb_school_info.id', 'campus_name_en']);

        if (($this->isStudentListReport($type) || $this->isStudentContactListReport($type)) && empty($filters['academic_year_id'])) {
            return collect();
        }

        if ($type === 'withdrawn-students' && !empty($filters['academic_year_id'])) {
            $ids = $this->withdrawalOptionBaseQuery($request, [
                'period_type' => $filters['period_type'] ?? 'all',
                'academic_year_id' => $filters['academic_year_id'],
                'withdrawal_status' => $filters['withdrawal_status'] ?? 'approved',
            ])->pluck('tb_student_enrollment_history.campus_id')->unique();
            $campuses = $campuses->whereIn('id', $ids)->values();
        } elseif (!empty($filters['academic_year_id'])) {
            $ids = $this->reportOptionEnrollments($request, [
                'period_type' => $filters['period_type'] ?? 'all',
                'academic_year_id' => $filters['academic_year_id'],
            ])->pluck('tb_student_enrollment.campus_id')->unique();
            $campuses = $campuses->whereIn('id', $ids)->values();
        }
        return $campuses;
    }

    private function withdrawals(Request $request, array $filters)
    {
        return StudentEnrollmentHistory::with([
                'student:id,student_no,student_id,photo_path,full_name_en,full_name_kh,gender,home_phone',
                'academicYear:id,academic_year,period_type',
                'campus:id,campus_name_en,campus_name_kh',
                'grade:id,grade,grade_short_name,grade_order',
                'schoolClass:id,class_name,class_order',
                'session:id,session_name,session_short_name',
                'changedBy:id,name',
            ])
            ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment_history.student_id')
            ->where('tb_student_enrollment_history.action_type', 'withdrawal')
            ->when(($filters['period_type'] ?? 'all') !== 'all', fn ($q) => $q->whereHas('academicYear', fn ($year) => $year->where('period_type', $filters['period_type'])))
            ->when(!$request->user()->isSuperAdmin(), fn ($q) => $q->whereIn('tb_student_enrollment_history.campus_id', $request->user()->accessibleCampuses()->pluck('tb_school_info.id')))
            ->when($filters['academic_year_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment_history.academic_year_id', $id))
            ->when($filters['campus_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment_history.campus_id', $id))
            ->when($filters['grade_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment_history.grade_id', $id))
            ->when($filters['class_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment_history.class_id', $id))
            ->when($filters['session_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment_history.session_id', $id))
            ->when(($filters['withdrawal_status'] ?? 'approved') !== 'all', fn ($q) => $q->where('tb_student_enrollment_history.withdrawal_status', $filters['withdrawal_status'] ?? 'approved'))
            ->select('tb_student_enrollment_history.*')
            ->orderByRaw('CAST(COALESCE((SELECT grade_order FROM tb_grade WHERE tb_grade.id = tb_student_enrollment_history.grade_id), 999) AS UNSIGNED) ASC')
            ->orderByRaw('CAST(COALESCE((SELECT class_order FROM tb_class WHERE tb_class.id = tb_student_enrollment_history.class_id), 999) AS UNSIGNED) ASC')
            ->orderByRaw("COALESCE(NULLIF(TRIM(tb_student.full_name_kh), ''), tb_student.full_name_en, '') ASC")
            ->orderBy('tb_student_enrollment_history.effective_on');
    }

    private function enrollments(Request $request, array $filters, string $type = 'student-list')
    {
        $studentRelation = in_array($type, ['student-id-books-moeys', 'moeys-id-number-book', 'moeys-sikkhakarik-book'], true)
            ? 'student:id,student_no,student_id,photo_path,family_number,full_name_en,full_name_kh,gender,gender_kh,date_of_birth,nationality_country_id,birth_country_id,birth_province_id,birth_district_id,birth_commune_id,birth_village_id,address_country_id,address_province_id,address_district_id,address_commune_id,address_village_id,address_house_no_en,address_house_no_kh,address_street_en,address_street_kh,current_address_en,current_address_kh,home_phone,email,previous_school,experienced_english,test_result,tested_by,remarks'
            : 'student:id,student_id,full_name_en,full_name_kh,gender,home_phone,email,current_address_en';
        $relations = [
            $studentRelation,
            'academicYear:id,academic_year,period_type',
            'campus:id,campus_name_en,campus_name_kh,school_name_en,school_name_kh,logo_path,address_province_id,address_district_id,address_commune_id,address_village_id,address_kh',
            'grade:id,grade,grade_short_name,grade_order',
            'schoolClass:id,class_name,class_order',
            'academicTrack:id,name_en',
            'session:id,session_name,session_short_name',
        ];

        if ($type === 'student-contact-list') {
            $relations[] = 'student.contacts';
            $relations[] = 'student.familyMembers:id,phone,relationship_type';
        }
        if ($type === 'moeys-id-number-book' || $type === 'moeys-sikkhakarik-book') {
            $relations[] = 'student.familyMembers:id,full_name_en,full_name_kh,relationship_type,phone,email,occupation,occupation_en,occupation_kh,workplace,nationality_en,nationality_kh'.($type === 'moeys-sikkhakarik-book' ? ',occupation_id' : '');
            if ($type === 'moeys-sikkhakarik-book') {
                $relations[] = 'student.familyMembers.occupationRecord:id,occupation_name_en,occupation_name_kh';
            }
            $relations[] = 'student.nationalityCountry:id,country_name_en,country_name_kh,nationality_name_en,nationality_name_kh';
            $relations[] = 'student.birthCountry:id,country_name_en,country_name_kh';
            $relations[] = 'student.birthProvince:id,province_name_en,province_name_kh';
            $relations[] = 'student.birthDistrict:id,district_name_en,district_name_kh';
            $relations[] = 'student.birthCommune:id,commune_name_en,commune_name_kh';
            $relations[] = 'student.birthVillage:id,village_name_en,village_name_kh';
            $relations[] = 'student.addressCountry:id,country_name_en,country_name_kh';
            $relations[] = 'student.addressProvince:id,province_name_en,province_name_kh';
            $relations[] = 'student.addressDistrict:id,district_name_en,district_name_kh';
            $relations[] = 'student.addressCommune:id,commune_name_en,commune_name_kh';
            $relations[] = 'student.addressVillage:id,village_name_en,village_name_kh';
            $relations[] = 'campus.addressProvince:id,province_name_kh';
            $relations[] = 'campus.addressDistrict:id,district_name_kh';
            $relations[] = 'campus.addressCommune:id,commune_name_kh';
            $relations[] = 'campus.addressVillage:id,village_name_kh';
        }
        if ($type === 'student-id-books-moeys') {
            $relations[] = 'student.familyMembers:id,full_name_en,full_name_kh,relationship_type,occupation,occupation_en,occupation_kh';
            $relations[] = 'student.birthCountry:id,country_name_en,country_name_kh';
            $relations[] = 'student.birthProvince:id,province_name_en,province_name_kh';
            $relations[] = 'student.birthDistrict:id,district_name_en,district_name_kh';
            $relations[] = 'student.birthCommune:id,commune_name_en,commune_name_kh';
            $relations[] = 'student.birthVillage:id,village_name_en,village_name_kh';
            $relations[] = 'student.addressProvince:id,province_name_en,province_name_kh';
            $relations[] = 'student.addressDistrict:id,district_name_en,district_name_kh';
            $relations[] = 'student.addressCommune:id,commune_name_en,commune_name_kh';
            $relations[] = 'student.addressVillage:id,village_name_en,village_name_kh';
        }

        return StudentEnrollment::with($relations)
            ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
            ->where('tb_student_enrollment.status', 1)
            ->whereIn('tb_student_enrollment.enrollment_status', ['active', 'completed'])
            ->where('tb_student.status', 1)
            ->when(($filters['period_type'] ?? 'all') !== 'all', fn ($q) => $q->whereHas('academicYear', fn ($year) => $year->where('period_type', $filters['period_type'])))
            ->when(!$request->user()->isSuperAdmin(), fn ($q) => $q->whereIn('tb_student_enrollment.campus_id', $request->user()->accessibleCampuses()->pluck('tb_school_info.id')))
            ->when($filters['academic_year_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.academic_year_id', $id))
            ->when($filters['campus_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.campus_id', $id))
            ->when($type === 'student-id-books-moeys' && filled($filters['id_book_level'] ?? null), function ($q) use ($filters) {
                $grades = $this->studentIdBookLevelGrades($filters['id_book_level']);
                $entryGrade = $this->studentIdBookLevelEntryGrade($filters['id_book_level']);
                $gradeExpression = "UPPER(COALESCE(NULLIF(TRIM(grade_short_name), ''), REPLACE(grade, 'Grade ', '')))";

                $q->whereHas('grade', function ($gradeQuery) use ($filters) {
                    $gradeQuery->whereIn(
                        DB::raw("UPPER(COALESCE(NULLIF(TRIM(grade_short_name), ''), REPLACE(grade, 'Grade ', '')))"),
                        $this->studentIdBookLevelGrades($filters['id_book_level'])
                    );
                })->where(function ($studentTypeQuery) use ($grades, $entryGrade, $gradeExpression) {
                    if ($entryGrade) {
                        $studentTypeQuery->whereHas('grade', fn ($gradeQuery) => $gradeQuery->whereRaw($gradeExpression . ' = ?', [$entryGrade]));
                    }

                    $studentTypeQuery->orWhere(function ($newOnlyQuery) use ($grades, $entryGrade, $gradeExpression) {
                        $newOnlyGrades = array_values(array_diff($grades, array_filter([$entryGrade])));
                        if ($newOnlyGrades) {
                            $newOnlyQuery->whereHas('grade', fn ($gradeQuery) => $gradeQuery->whereIn(DB::raw($gradeExpression), $newOnlyGrades))
                                ->whereRaw("LOWER(COALESCE(tb_student_enrollment.student_type, '')) = 'new'");
                        } else {
                            $newOnlyQuery->whereRaw('1 = 0');
                        }
                    });
                });
            })
            ->when($type === 'moeys-sikkhakarik-book' && filled($filters['transcript_level'] ?? null), function ($q) use ($filters) {
                $grades = $this->transcriptBookLevelGrades($filters['transcript_level']);
                if (!$grades) {
                    $q->whereRaw('1 = 0');
                    return;
                }
                $q->whereHas('grade', function ($gradeQuery) use ($grades) {
                    $gradeQuery->whereIn(
                        DB::raw("UPPER(TRIM(REPLACE(REPLACE(COALESCE(NULLIF(TRIM(grade_short_name), ''), grade), 'Grade', ''), '-', '')))"),
                        $grades
                    );
                });
            })
            ->when($filters['grade_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.grade_id', $id))
            ->when($filters['class_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.class_id', $id))
            ->when($filters['session_id'] ?? null, fn ($q, $id) => $q->where('session_id', $id))
            ->when($type === 'moeys-sikkhakarik-book' && filled($filters['transcript_student_id'] ?? null), fn ($q) => $q->where('tb_student_enrollment.student_id', $filters['transcript_student_id']))
            ->when($filters['print_grade_classes'] ?? null, function ($q, $values) {
                $pairs = collect($values)->map(fn ($value) => array_map('intval', explode(':', $value, 2)))->filter(fn ($pair) => count($pair) === 2)->values();
                $q->where(function ($nested) use ($pairs) {
                    foreach ($pairs as [$gradeId, $classId]) $nested->orWhere(fn ($pairQuery) => $pairQuery->where('tb_student_enrollment.grade_id', $gradeId)->where('tb_student_enrollment.class_id', $classId));
                });
            })
            ->select('tb_student_enrollment.*')
            ->when(
                in_array($type, ['student-id-books-moeys', 'student-profile-label', 'moeys-id-number-book', 'moeys-sikkhakarik-book'], true),
                fn ($q) => $q
                    ->orderByRaw('CAST(COALESCE((SELECT grade_order FROM tb_grade WHERE tb_grade.id = tb_student_enrollment.grade_id), 999) AS UNSIGNED) ASC')
                    ->orderByRaw('CAST(COALESCE((SELECT class_order FROM tb_class WHERE tb_class.id = tb_student_enrollment.class_id), 999) AS UNSIGNED) ASC')
                    ->orderByRaw("COALESCE((SELECT class_name FROM tb_class WHERE tb_class.id = tb_student_enrollment.class_id), '') ASC")
                    ->orderByRaw(in_array($type, ['student-id-books-moeys', 'moeys-id-number-book', 'moeys-sikkhakarik-book'], true) ? "COALESCE(NULLIF(TRIM(tb_student.full_name_kh), ''), tb_student.full_name_en, '') ASC" : "LOWER(COALESCE(tb_student.full_name_en, '')) ASC"),
                fn ($q) => $q->when(
                    (($this->isStudentListReport($type) || $this->isStudentContactListReport($type)) && ($filters['print_format'] ?? 'internal') === 'moeys'),
                    fn ($moeysQuery) => $moeysQuery->orderByRaw("COALESCE(NULLIF(TRIM(tb_student.full_name_kh), ''), tb_student.full_name_en, '') ASC"),
                    fn ($defaultQuery) => $defaultQuery->orderByRaw("LOWER(COALESCE(tb_student.full_name_en, '')) ASC"),
                ),
            )
            ->orderBy('tb_student_enrollment.student_id');
    }

    private function filteredStudentListBaseQuery(Request $request, array $filters)
    {
        return StudentEnrollment::query()
            ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
            ->where('tb_student_enrollment.status', 1)
            ->whereIn('tb_student_enrollment.enrollment_status', ['active', 'completed'])
            ->where('tb_student.status', 1)
            ->when(($filters['period_type'] ?? 'all') !== 'all', fn ($q) => $q->whereHas('academicYear', fn ($year) => $year->where('period_type', $filters['period_type'])))
            ->when(!$request->user()->isSuperAdmin(), fn ($q) => $q->whereIn('tb_student_enrollment.campus_id', $request->user()->accessibleCampuses()->pluck('tb_school_info.id')))
            ->when($filters['academic_year_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.academic_year_id', $id))
            ->when($filters['campus_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.campus_id', $id))
            ->when($filters['grade_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.grade_id', $id))
            ->when($filters['class_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.class_id', $id))
            ->when($filters['session_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.session_id', $id))
            ->when($filters['print_grade_classes'] ?? null, function ($q, $values) {
                $pairs = collect($values)->map(fn ($value) => array_map('intval', explode(':', $value, 2)))->filter(fn ($pair) => count($pair) === 2)->values();
                $q->where(function ($nested) use ($pairs) {
                    foreach ($pairs as [$gradeId, $classId]) $nested->orWhere(fn ($pairQuery) => $pairQuery->where('tb_student_enrollment.grade_id', $gradeId)->where('tb_student_enrollment.class_id', $classId));
                });
            });
    }

    private function studentListSummary(Request $request, array $filters): array
    {
        $row = $this->filteredStudentListBaseQuery($request, $filters)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN LOWER(COALESCE(tb_student.gender, '')) LIKE 'f%' THEN 1 ELSE 0 END) as total_female,
                SUM(CASE WHEN LOWER(COALESCE(tb_student.gender, '')) LIKE 'm%' THEN 1 ELSE 0 END) as total_male,
                SUM(CASE WHEN LOWER(COALESCE(tb_student_enrollment.student_type, '')) = 'new' THEN 1 ELSE 0 END) as new_total,
                SUM(CASE WHEN LOWER(COALESCE(tb_student_enrollment.student_type, '')) = 'new' AND LOWER(COALESCE(tb_student.gender, '')) LIKE 'f%' THEN 1 ELSE 0 END) as new_female,
                SUM(CASE WHEN LOWER(COALESCE(tb_student_enrollment.student_type, '')) = 'new' AND LOWER(COALESCE(tb_student.gender, '')) LIKE 'm%' THEN 1 ELSE 0 END) as new_male
            ")
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'total_female' => (int) ($row->total_female ?? 0),
            'total_male' => (int) ($row->total_male ?? 0),
            'new_total' => (int) ($row->new_total ?? 0),
            'new_female' => (int) ($row->new_female ?? 0),
            'new_male' => (int) ($row->new_male ?? 0),
        ];
    }

    private function emptyStudentListSummary(): array
    {
        return [
            'total' => 0,
            'total_female' => 0,
            'total_male' => 0,
            'new_total' => 0,
            'new_female' => 0,
            'new_male' => 0,
        ];
    }

    public function gradeClassLabel(StudentEnrollment $enrollment): string
    {
        $grade = trim((string) ($enrollment->grade?->grade_short_name ?: $enrollment->grade?->grade));
        $class = trim((string) ($enrollment->schoolClass?->class_name ?? ''));

        return $grade || $class ? $grade . $class : '-';
    }

    private function reportOptionEnrollments(Request $request, array $filters)
    {
        return StudentEnrollment::query()
            ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
            ->where('tb_student_enrollment.status', 1)
            ->whereIn('tb_student_enrollment.enrollment_status', ['active', 'completed'])
            ->where('tb_student.status', 1)
            ->when(($filters['period_type'] ?? 'all') !== 'all', fn ($q) => $q->whereHas('academicYear', fn ($year) => $year->where('period_type', $filters['period_type'])))
            ->when(!$request->user()->isSuperAdmin(), fn ($q) => $q->whereIn('tb_student_enrollment.campus_id', $request->user()->accessibleCampuses()->pluck('tb_school_info.id')))
            ->when($filters['academic_year_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.academic_year_id', $id))
            ->when($filters['campus_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.campus_id', $id))
            ->when($filters['grade_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.grade_id', $id))
            ->when($filters['class_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.class_id', $id));
    }

    private function gradeClassOptions(Request $request, array $filters, string $type = 'student-list'): Collection
    {
        if (empty($filters['academic_year_id']) && empty($filters['campus_id'])) {
            return collect();
        }

        $filters = array_diff_key($filters, array_flip(['grade_id', 'class_id', 'grade_class']));
        return $this->reportOptionEnrollments($request, $filters)
            ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
            ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
            ->when($type === 'moeys-sikkhakarik-book' && filled($filters['transcript_level'] ?? null), function ($query) use ($filters) {
                $grades = $this->transcriptBookLevelGrades($filters['transcript_level']);
                if (!$grades) {
                    $query->whereRaw('1 = 0');
                    return;
                }
                $query->whereIn(
                    DB::raw("UPPER(TRIM(REPLACE(REPLACE(COALESCE(NULLIF(TRIM(tb_grade.grade_short_name), ''), tb_grade.grade), 'Grade', ''), '-', '')))"),
                    $grades
                );
            })
            ->select('tb_student_enrollment.grade_id', 'tb_student_enrollment.class_id', 'tb_grade.grade', 'tb_grade.grade_short_name', 'tb_class.class_name', 'tb_grade.grade_order', 'tb_class.class_order')
            ->distinct()->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')->orderByRaw('CAST(tb_class.class_order AS UNSIGNED)')->get()
            ->map(fn ($row) => [
                'value' => $row->grade_id . ':' . $row->class_id,
                'label' => $this->gradeClassOptionLabel($row->grade_short_name ?: $row->grade, $row->class_name),
            ]);
    }

    private function gradeClassOptionLabel(?string $grade, ?string $class): string
    {
        $grade = trim((string) $grade);
        $class = trim((string) $class);
        if ($grade === '') {
            return $class ?: '-';
        }
        if ($class === '') {
            return $grade;
        }

        if (str_ends_with($grade, '-')) {
            return $grade . $class;
        }

        return preg_match('/^[A-Za-z]/', $grade) ? $grade . '-' . $class : $grade . $class;
    }

    private function withdrawalGradeClassLabel(StudentEnrollmentHistory $withdrawal): string
    {
        return $this->gradeClassOptionLabel($withdrawal->grade?->grade_short_name ?: $withdrawal->grade?->grade, $withdrawal->schoolClass?->class_name);
    }

    private function withdrawalCampusGradeGroupLabel(StudentEnrollmentHistory $withdrawal): string
    {
        return collect([
            $withdrawal->campus?->campus_name_en,
            $this->withdrawalGradeClassLabel($withdrawal),
            $withdrawal->session?->session_short_name ?: $withdrawal->session?->session_name,
        ])->filter(fn ($value) => filled(trim((string) $value)) && trim((string) $value) !== '-')->join('-') ?: '-';
    }

    private function withdrawalGradeGroupLabel(StudentEnrollmentHistory $withdrawal): string
    {
        return collect([
            $this->withdrawalGradeClassLabel($withdrawal),
            $withdrawal->session?->session_short_name ?: $withdrawal->session?->session_name,
        ])->filter(fn ($value) => filled(trim((string) $value)) && trim((string) $value) !== '-')->join('-') ?: '-';
    }

    private function withdrawalOptionBaseQuery(Request $request, array $filters)
    {
        return StudentEnrollmentHistory::query()
            ->where('tb_student_enrollment_history.action_type', 'withdrawal')
            ->when(($filters['period_type'] ?? 'all') !== 'all', fn ($q) => $q->whereHas('academicYear', fn ($year) => $year->where('period_type', $filters['period_type'])))
            ->when(!$request->user()->isSuperAdmin(), fn ($q) => $q->whereIn('tb_student_enrollment_history.campus_id', $request->user()->accessibleCampuses()->pluck('tb_school_info.id')))
            ->when($filters['academic_year_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment_history.academic_year_id', $id))
            ->when($filters['campus_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment_history.campus_id', $id))
            ->when(($filters['withdrawal_status'] ?? 'approved') !== 'all', fn ($q) => $q->where('tb_student_enrollment_history.withdrawal_status', $filters['withdrawal_status'] ?? 'approved'));
    }

    private function withdrawalGradeClassOptions(Request $request, array $filters): Collection
    {
        if (empty($filters['academic_year_id']) && empty($filters['campus_id'])) {
            return collect();
        }

        return $this->withdrawalOptionBaseQuery($request, array_diff_key($filters, array_flip(['grade_id', 'class_id', 'grade_class'])))
            ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment_history.grade_id')
            ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment_history.class_id')
            ->select('tb_student_enrollment_history.grade_id', 'tb_student_enrollment_history.class_id', 'tb_grade.grade', 'tb_grade.grade_short_name', 'tb_class.class_name', 'tb_grade.grade_order', 'tb_class.class_order')
            ->distinct()
            ->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')
            ->orderByRaw('CAST(tb_class.class_order AS UNSIGNED)')
            ->get()
            ->map(fn ($row) => [
                'value' => $row->grade_id . ':' . $row->class_id,
                'label' => $this->gradeClassOptionLabel($row->grade_short_name ?: $row->grade, $row->class_name),
            ]);
    }

    private function withdrawalGroupOptions(Request $request, array $filters): Collection
    {
        if (empty($filters['academic_year_id']) && empty($filters['campus_id'])) {
            return collect();
        }

        return $this->withdrawalOptionBaseQuery($request, $filters)
            ->whereNotNull('tb_student_enrollment_history.session_id')
            ->join('tb_session', 'tb_session.id', '=', 'tb_student_enrollment_history.session_id')
            ->select('tb_student_enrollment_history.session_id', 'tb_session.session_short_name', 'tb_session.session_order')
            ->distinct()
            ->orderBy('tb_session.session_order')
            ->get();
    }

    private function groupOptions(Request $request, array $filters): Collection
    {
        if (empty($filters['academic_year_id']) && empty($filters['campus_id'])) {
            return collect();
        }

        return $this->reportOptionEnrollments($request, $filters)
            ->whereNotNull('tb_student_enrollment.session_id')->join('tb_session', 'tb_session.id', '=', 'tb_student_enrollment.session_id')
            ->select('tb_student_enrollment.session_id', 'tb_session.session_short_name', 'tb_session.session_order')->distinct()->orderBy('tb_session.session_order')->get();
    }

    private function statistics(Request $request, array $filters): array
    {
        $query = $this->enrollments($request, $filters, 'student-statistics')
            ->join('tb_school_info', 'tb_school_info.id', '=', 'tb_student_enrollment.campus_id')
            ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
            ->reorder()
            ->select('tb_student_enrollment.campus_id', 'tb_school_info.campus_name_en', 'tb_student_enrollment.grade_id', 'tb_grade.grade', DB::raw('COUNT(DISTINCT tb_student_enrollment.student_id) as total'), DB::raw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student_enrollment.student_type, '')) = 'new' THEN tb_student_enrollment.student_id END) as new_total"))
            ->groupBy('tb_student_enrollment.campus_id', 'tb_school_info.campus_name_en', 'tb_student_enrollment.grade_id', 'tb_grade.grade')
            ->orderBy('tb_school_info.campus_name_en')
            ->orderByRaw('CAST(tb_grade.grade AS UNSIGNED)')
            ->get();
        $grades = Grade::where('status', 1)->orderByRaw('CAST(grade_order AS UNSIGNED)')->get(['id', 'grade']);
        $campusRows = $query->groupBy('campus_id')->map(function ($items) use ($grades) {
            $byGrade = $items->keyBy('grade_id');
            $cells = $grades->map(fn ($grade) => (int) ($byGrade->get($grade->id)->total ?? 0))->all();
            $newCells = $grades->map(fn ($grade) => (int) ($byGrade->get($grade->id)->new_total ?? 0))->all();
            return ['campus' => $items->first()->campus_name_en, 'cells' => $cells, 'newCells' => $newCells, 'total' => array_sum($cells), 'new_total' => array_sum($newCells)];
        })->values()->all();
        $columns = $grades->pluck('grade')->all();
        $columnTotals = array_map(
            fn ($i) => array_sum(array_map(fn ($row) => (int) ($row['cells'][$i] ?? 0), $campusRows)),
            array_keys($columns)
        );
        $columnNewTotals = array_map(
            fn ($i) => array_sum(array_map(fn ($row) => (int) ($row['newCells'][$i] ?? 0), $campusRows)),
            array_keys($columns)
        );
        return ['columns' => $columns, 'rows' => $campusRows, 'columnTotals' => $columnTotals, 'columnNewTotals' => $columnNewTotals, 'grandTotal' => array_sum($columnTotals), 'grandNewTotal' => array_sum($columnNewTotals)];
    }

    private function statisticsDetail(Request $request, array $filters): array
    {
        $reportMonth = $filters['month'] ?? now('Asia/Phnom_Penh')->format('Y-m');
        $reportDate = \Carbon\Carbon::createFromFormat('Y-m-d', $reportMonth . '-01')->endOfMonth();
        $reportDateString = $reportDate->toDateString();
        $monthStart = $reportDate->copy()->startOfMonth()->toDateString();
        $monthEnd = $reportDate->copy()->endOfMonth()->toDateString();

        $rows = $this->enrollments($request, $filters, 'student-statistics-detail')
            ->join('tb_school_info', 'tb_school_info.id', '=', 'tb_student_enrollment.campus_id')
            ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
            ->leftJoin('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
            ->leftJoin('tb_session', 'tb_session.id', '=', 'tb_student_enrollment.session_id')
            ->whereDate('tb_student_enrollment.enrolled_on', '<=', $reportDateString)
            ->reorder()
            ->select(
                'tb_student_enrollment.campus_id',
                'tb_school_info.campus_name_en',
                'tb_student_enrollment.grade_id',
                'tb_student_enrollment.class_id',
                'tb_student_enrollment.session_id',
                'tb_grade.grade',
                'tb_grade.grade_short_name',
                'tb_grade.grade_order',
                'tb_class.class_name',
                'tb_class.class_order',
                'tb_session.session_short_name',
                'tb_session.session_order',
                DB::raw('COUNT(DISTINCT tb_student_enrollment.student_id) as total'),
                DB::raw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student.gender, '')) LIKE 'f%' THEN tb_student_enrollment.student_id END) as female"),
                DB::raw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student_enrollment.student_type, '')) = 'new' THEN tb_student_enrollment.student_id END) as new_total")
            )
            ->groupBy(
                'tb_student_enrollment.campus_id',
                'tb_school_info.campus_name_en',
                'tb_student_enrollment.grade_id',
                'tb_student_enrollment.class_id',
                'tb_student_enrollment.session_id',
                'tb_grade.grade',
                'tb_grade.grade_short_name',
                'tb_grade.grade_order',
                'tb_class.class_name',
                'tb_class.class_order',
                'tb_session.session_short_name',
                'tb_session.session_order'
            )
            ->orderBy('tb_school_info.campus_name_en')
            ->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')
            ->orderByRaw('CAST(tb_class.class_order AS UNSIGNED)')
            ->orderByRaw('CAST(tb_session.session_order AS UNSIGNED)')
            ->get();

        $dropoutRows = DB::table('tb_student_enrollment_history')
            ->leftJoin('tb_academic_year', 'tb_academic_year.id', '=', 'tb_student_enrollment_history.academic_year_id')
            ->where('tb_student_enrollment_history.action_type', 'withdrawal')
            ->where('tb_student_enrollment_history.withdrawal_status', 'approved')
            ->where('tb_student_enrollment_history.enrollment_status', 'withdrawn')
            ->whereNotNull('tb_student_enrollment_history.effective_on')
            ->whereDate('tb_student_enrollment_history.effective_on', '<=', $reportDateString)
            ->when(($filters['period_type'] ?? 'all') !== 'all', fn ($q) => $q->where('tb_academic_year.period_type', $filters['period_type']))
            ->when(!$request->user()->isSuperAdmin(), fn ($q) => $q->whereIn('tb_student_enrollment_history.campus_id', $request->user()->accessibleCampuses()->pluck('tb_school_info.id')))
            ->when($filters['academic_year_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment_history.academic_year_id', $id))
            ->when($filters['campus_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment_history.campus_id', $id))
            ->when($filters['grade_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment_history.grade_id', $id))
            ->when($filters['class_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment_history.class_id', $id))
            ->when($filters['session_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment_history.session_id', $id))
            ->select(
                'tb_student_enrollment_history.grade_id',
                'tb_student_enrollment_history.class_id',
                DB::raw("COUNT(DISTINCT CASE WHEN tb_student_enrollment_history.effective_on BETWEEN '" . $monthStart . "' AND '" . $monthEnd . "' THEN tb_student_enrollment_history.student_id END) as monthly_dropout"),
                DB::raw('COUNT(DISTINCT tb_student_enrollment_history.student_id) as cumulative_dropout')
            )
            ->groupBy('tb_student_enrollment_history.grade_id', 'tb_student_enrollment_history.class_id')
            ->get()
            ->keyBy(fn ($row) => ($row->grade_id ?: 0) . ':' . ($row->class_id ?: 0));

        $groups = Session::where('status', 1)
            ->whereNotNull('session_short_name')
            ->orderByRaw('CAST(session_order AS UNSIGNED)')
            ->orderBy('session_short_name')
            ->pluck('session_short_name')
            ->map(fn ($group) => trim((string) $group))
            ->filter()
            ->unique(fn ($group) => mb_strtoupper($group))
            ->values()
            ->all();
        $totals = ['groups' => collect($groups)->mapWithKeys(fn ($group) => [$group => ['total' => 0, 'female' => 0]])->all(), 'total' => 0, 'female' => 0, 'old' => 0, 'new' => 0, 'monthly_dropout' => 0, 'cumulative_dropout' => 0];

        $campuses = $rows->groupBy('campus_id')->map(function ($campusRows) use ($groups, $dropoutRows, &$totals) {
            $campusTotal = ['groups' => collect($groups)->mapWithKeys(fn ($group) => [$group => ['total' => 0, 'female' => 0]])->all(), 'total' => 0, 'female' => 0, 'old' => 0, 'new' => 0, 'monthly_dropout' => 0, 'cumulative_dropout' => 0];
            $classRows = $campusRows
                ->groupBy(fn ($row) => ($row->grade_id ?: 0) . ':' . ($row->class_id ?: 0))
                ->map(function ($items) use ($groups, $dropoutRows, &$campusTotal, &$totals) {
                    $first = $items->first();
                    $groupCells = collect($groups)->mapWithKeys(function ($group) use ($items) {
                        $matched = $items->first(fn ($item) => mb_strtoupper(trim((string) $item->session_short_name)) === mb_strtoupper($group));
                        return [$group => [
                            'total' => (int) ($matched->total ?? 0),
                            'female' => (int) ($matched->female ?? 0),
                        ]];
                    })->all();
                    $total = (int) $items->sum('total');
                    $female = (int) $items->sum('female');
                    $new = (int) $items->sum('new_total');
                    $old = max(0, $total - $new);
                    $dropout = $dropoutRows->get(($first->grade_id ?: 0) . ':' . ($first->class_id ?: 0));
                    $class = trim((string) (($first->grade_short_name ?: $first->grade) . ($first->class_name ?? ''))) ?: '-';
                    $row = [
                        'grade' => $class,
                        'groups' => $groupCells,
                        'total' => $total,
                        'female' => $female,
                        'old' => $old,
                        'new' => $new,
                        'monthly_dropout' => (int) ($dropout->monthly_dropout ?? 0),
                        'cumulative_dropout' => (int) ($dropout->cumulative_dropout ?? 0),
                        'campus' => $first->campus_name_en ?: '-',
                        'sort' => sprintf('%06d-%06d-%s', (int) ($first->grade_order ?? 999999), (int) ($first->class_order ?? 999999), $class),
                    ];
                    foreach ($groups as $group) {
                        $campusTotal['groups'][$group]['total'] += (int) ($groupCells[$group]['total'] ?? 0);
                        $campusTotal['groups'][$group]['female'] += (int) ($groupCells[$group]['female'] ?? 0);
                        $totals['groups'][$group]['total'] += (int) ($groupCells[$group]['total'] ?? 0);
                        $totals['groups'][$group]['female'] += (int) ($groupCells[$group]['female'] ?? 0);
                    }
                    foreach (['total', 'female', 'old', 'new', 'monthly_dropout', 'cumulative_dropout'] as $key) {
                        $campusTotal[$key] += $row[$key];
                        $totals[$key] += $row[$key];
                    }
                    return $row;
                })
                ->sortBy('sort')
                ->values()
                ->all();

            return [
                'campus' => $campusRows->first()->campus_name_en ?: '-',
                'rows' => $classRows,
                'totals' => $campusTotal,
            ];
        })->values()->all();

        return ['groups' => $groups, 'campuses' => $campuses, 'totals' => $totals];
    }
}


