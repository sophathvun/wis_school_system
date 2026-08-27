<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\EnrollmentWorkflowAction;
use App\Models\StudentEnrollmentHistory;
use App\Models\StudentGraduation;
use App\Models\StudentEnrollment;
use App\Models\SchoolInfo;
use App\Models\BrandingSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\SettingsQuery;
use Illuminate\Support\Facades\DB;

class AcademicYearController
{
    public function index()
    {
        return view('academicYears');
    }

    // fetch all academic years
    public function fetchData(Request $request)
    {
        $perPage = SettingsQuery::perPage($request);
        $search = SettingsQuery::search($request);
        $sortBy = $request->input('sortBy', 'academic_year');
        $sortDir = strtolower($request->input('sortDir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortColumns = [
            'id' => 'id',
            'academic_year' => 'academic_year',
            'period_type' => 'period_type',
            'academic_year_code' => 'academic_year_code',
            'start_date' => 'start_date',
            'end_date' => 'end_date',
            'description' => 'description',
            'lifecycle_status' => 'lifecycle_status',
        ];
        $sortColumn = $sortColumns[$sortBy] ?? 'academic_year';

        $academicYearsQuery = $request->boolean('include_deleted')
            ? AcademicYear::withTrashed()
            : AcademicYear::query();

        $academicYears = $academicYearsQuery
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('academic_year', 'like', "%{$search}%")
                        ->orWhere('academic_year_code', 'like', "%{$search}%")
                        ->orWhere('lifecycle_status', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('academic_year_filter'), fn ($query) => $query->where('academic_year', $request->input('academic_year_filter')))
            ->when($request->filled('period_type_filter'), fn ($query) => $query->where('period_type', $request->input('period_type_filter')))
            ->orderBy($sortColumn, $sortDir)
            ->paginate($perPage);

        $academicYears->getCollection()->transform(function (AcademicYear $academicYear) {
            $academicYear->linked_data_count = collect([
                StudentEnrollment::where('academic_year_id', $academicYear->id)->count(),
                $academicYear->classes()->withTrashed()->count(),
                $academicYear->programs()->withTrashed()->count(),
                StudentEnrollmentHistory::where('academic_year_id', $academicYear->id)->count(),
                StudentGraduation::where('academic_year_id', $academicYear->id)->count(),
                EnrollmentWorkflowAction::where(function ($query) use ($academicYear) {
                    $query->where('from_academic_year_id', $academicYear->id)
                        ->orWhere('to_academic_year_id', $academicYear->id);
                })->count(),
                AcademicYear::where('parent_academic_year_id', $academicYear->id)->withTrashed()->count(),
            ])->sum();

            return $academicYear;
        });

        $filterQuery = $request->boolean('include_deleted') ? AcademicYear::withTrashed() : AcademicYear::query();
        $filterOptions = [
            'academicYears' => (clone $filterQuery)->whereNotNull('academic_year')->distinct()->orderByDesc('academic_year')->pluck('academic_year')->values(),
            'periodTypes' => (clone $filterQuery)->whereNotNull('period_type')->distinct()->pluck('period_type')->values(),
        ];

        return response()->json(array_merge($academicYears->toArray(), ['filterOptions' => $filterOptions]));
    }

    public function exportPdf()
    {
        $academicYears = AcademicYear::orderByDesc('academic_year')->get();
        $logoSrc = $this->academicYearReportLogoSrc();

        $pdf = Pdf::loadView('academicYears-pdf', compact('academicYears', 'logoSrc') + ['printMode' => false]);

        return $pdf->stream('academic-years.pdf');
    }

    public function print()
    {
        return view('academicYears-pdf', [
            'academicYears' => $this->academicYearReportRows(),
            'logoSrc' => $this->academicYearReportLogoSrc(),
            'printMode' => true,
        ]);
    }

    public function exportExcel()
    {
        $filename = 'academic-year-list-' . now()->format('Ymd-His') . '.xlsx';
        $path = $this->academicYearExcelPath($this->academicYearReportRows());

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    private function academicYearReportRows()
    {
        return AcademicYear::orderByDesc('academic_year')
            ->orderBy('period_type')
            ->get();
    }

    private function academicYearReportLogoSrc(): ?string
    {
        $logoPath = $this->academicYearReportLogoPath();

        return $logoPath
            ? 'data:' . (mime_content_type($logoPath) ?: 'image/png') . ';base64,' . base64_encode(file_get_contents($logoPath))
            : null;
    }

    private function academicYearReportLogoPath(): ?string
    {
        $school = SchoolInfo::latest('id')->first();
        $branding = BrandingSetting::current();
        $logoPath = $branding->report_logo_1_path
            ? storage_path('app/public/' . ltrim($branding->report_logo_1_path, '/'))
            : ($school?->logo_path
                ? storage_path('app/public/' . ltrim($school->logo_path, '/'))
                : storage_path('app/public/school_logo/wis_logo.png'));

        if (!is_file($logoPath)) {
            $logoPath = $school?->logo_path
                ? storage_path('app/public/' . ltrim($school->logo_path, '/'))
                : storage_path('app/public/school_logo/wis_logo.png');
        }

        return is_file($logoPath) ? $logoPath : null;
    }

    private function academicYearExcelPath($academicYears): string
    {
        $path = tempnam(sys_get_temp_dir(), 'academic-year-list-');
        $xlsxPath = $path . '.xlsx';
        rename($path, $xlsxPath);

        $logoPath = $this->academicYearReportLogoPath();
        $logoExtension = $logoPath ? $this->xlsxImageExtension($logoPath) : null;
        $entries = [
            '[Content_Types].xml' => $this->xlsxContentTypes($logoExtension),
            '_rels/.rels' => $this->xlsxRootRels(),
            'xl/workbook.xml' => $this->xlsxWorkbook(),
            'xl/_rels/workbook.xml.rels' => $this->xlsxWorkbookRels(),
            'xl/styles.xml' => $this->xlsxStyles(),
            'xl/worksheets/sheet1.xml' => $this->academicYearWorksheetXml($academicYears, (bool) $logoPath),
        ];

        if ($logoPath && $logoExtension) {
            $entries['xl/worksheets/_rels/sheet1.xml.rels'] = $this->xlsxWorksheetRels();
            $entries['xl/drawings/drawing1.xml'] = $this->xlsxLogoDrawing();
            $entries['xl/drawings/_rels/drawing1.xml.rels'] = $this->xlsxLogoDrawingRels($logoExtension);
            $entries['xl/media/report-logo.' . $logoExtension] = file_get_contents($logoPath);
        }

        $this->writeZipArchive($xlsxPath, $entries);

        return $xlsxPath;
    }

    private function academicYearWorksheetXml($academicYears, bool $hasLogo): string
    {
        $lastRow = $academicYears->count() + 9;
        $rows = [
            $this->xlsxRow(1, [], 28),
            $this->xlsxRow(2, [], 28),
            $this->xlsxRow(3, [], 28),
            $this->xlsxRow(4, [], 10),
            $this->xlsxRow(5, [['A', 'តារាងឆ្នាំសិក្សា', 1]], 30),
            $this->xlsxRow(6, [['A', 'Academic Year List', 2]], 26),
            $this->xlsxRow(7, [['A', 'Generated: ' . now()->format('d-M-Y h:i A'), 6]], 20),
            $this->xlsxRow(8, [], 8),
            $this->xlsxRow(9, [
                ['A', 'No.', 3],
                ['B', 'Academic Year', 3],
                ['C', 'Type', 3],
                ['D', 'AY Code', 3],
                ['E', 'Start Date', 3],
                ['F', 'End Date', 3],
                ['G', 'Status', 3],
            ], 24),
        ];

        foreach ($academicYears as $index => $year) {
            $row = $index + 10;
            $status = strtolower($year->lifecycle_status ?? ($year->status ? 'started' : 'finished'));
            $rows[] = $this->xlsxRow($row, [
                ['A', (string) ($index + 1), 5],
                ['B', $year->academic_year ?: '-', 5],
                ['C', $year->isSummer() ? 'Summer School' : 'Regular', 5],
                ['D', $year->ay_code ?: '-', 5],
                ['E', $year->start_date?->format('Y-m-d') ?: '-', 5],
                ['F', $year->end_date?->format('Y-m-d') ?: '-', 5],
                ['G', ucfirst($status), 5],
            ], 22);
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:G' . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="9" topLeftCell="A10" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="20" customWidth="1"/><col min="3" max="3" width="18" customWidth="1"/><col min="4" max="4" width="16" customWidth="1"/><col min="5" max="6" width="16" customWidth="1"/><col min="7" max="7" width="14" customWidth="1"/></cols>'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . '<mergeCells count="5"><mergeCell ref="A1:G3"/><mergeCell ref="A5:G5"/><mergeCell ref="A6:G6"/><mergeCell ref="A7:G7"/><mergeCell ref="A8:G8"/></mergeCells>'
            . ($hasLogo ? '<drawing r:id="rId1"/>' : '')
            . '</worksheet>';
    }

    private function xlsxRow(int $row, array $cells, ?int $height = null): string
    {
        $heightAttribute = $height ? ' ht="' . $height . '" customHeight="1"' : '';

        return '<row r="' . $row . '"' . $heightAttribute . '>' . collect($cells)->map(function ($cell) use ($row) {
            [$column, $value, $style] = $cell;

            return '<c r="' . $column . $row . '" s="' . $style . '" t="inlineStr"><is><t>' . $this->xlsxEscape($value) . '</t></is></c>';
        })->implode('') . '</row>';
    }

    private function xlsxEscape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function xlsxContentTypes(?string $logoExtension = null): string
    {
        $imageDefault = match ($logoExtension) {
            'jpg', 'jpeg' => '<Default Extension="jpeg" ContentType="image/jpeg"/><Default Extension="jpg" ContentType="image/jpeg"/>',
            'gif' => '<Default Extension="gif" ContentType="image/gif"/>',
            default => '<Default Extension="png" ContentType="image/png"/>',
        };

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . ($logoExtension ? $imageDefault : '')
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . ($logoExtension ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '')
            . '</Types>';
    }

    private function xlsxRootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function xlsxWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Academic Year List" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function xlsxWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4"><font><sz val="11"/><name val="Arial"/></font><font><sz val="20"/><name val="Khmer OS Muol Light"/><color rgb="FF4F6380"/></font><font><b/><sz val="12"/><name val="Arial"/></font><font><sz val="11"/><name val="Khmer OS Siemreap"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE6F1FB"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FF999999"/></left><right style="thin"><color rgb="FF999999"/></right><top style="thin"><color rgb="FF999999"/></top><bottom style="thin"><color rgb="FF999999"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="7"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function xlsxWorksheetRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/></Relationships>';
    }

    private function xlsxLogoDrawing(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><xdr:oneCellAnchor><xdr:from><xdr:col>0</xdr:col><xdr:colOff>320000</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>120000</xdr:rowOff></xdr:from><xdr:ext cx="2500000" cy="850000"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="1" name="School Logo 1"/><xdr:cNvPicPr/></xdr:nvPicPr><xdr:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>';
    }

    private function xlsxLogoDrawingRels(string $logoExtension): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/report-logo.' . $logoExtension . '"/></Relationships>';
    }

    private function xlsxImageExtension(string $path): string
    {
        return match (mime_content_type($path)) {
            'image/jpeg' => 'jpeg',
            'image/gif' => 'gif',
            default => 'png',
        };
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

    public function save(Request $request)
    {
        $academicYearId = $request->input('academic_year_id');
        $academicYearValue = $request->input('academic_year');
        $ayCodeValue = $request->input('ay_code');
        $academicYearMatch = filled($academicYearValue) ? AcademicYear::withTrashed()
            ->where('academic_year', $academicYearValue)
            ->when($academicYearId, fn ($query) => $query->where('id', '!=', $academicYearId))
            ->first() : null;
        $restorableAcademicYearId = !$academicYearId && $academicYearMatch?->trashed()
            ? $academicYearMatch->id
            : null;

        $academicYearExists = $academicYearMatch && !$academicYearMatch->trashed();

        $ayCodeExists = filled($ayCodeValue) && AcademicYear::query()
            ->where('academic_year_code', $ayCodeValue)
            ->when($academicYearId, fn ($query) => $query->where('id', '!=', $academicYearId))
            ->exists();

        if ($academicYearExists || $ayCodeExists) {
            if ($academicYearExists && $ayCodeExists) {
                $message = "Unable to save Academic Year. Academic Year '{$academicYearValue}' and AY Code '{$ayCodeValue}' are already existed.";
            } elseif ($academicYearExists) {
                $message = "Unable to save Academic Year. Academic Year '{$academicYearValue}' already existed.";
            } else {
                $message = "Unable to save Academic Year. AY Code '{$ayCodeValue}' already existed.";
            }

            return response()->json(['status' => 'error', 'message' => $message], 422);
        }

        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'max:20', Rule::unique('tb_academic_year', 'academic_year')->ignore($academicYearId)->withoutTrashed()],
            'ay_code' => ['nullable', 'string', 'max:20', Rule::unique('tb_academic_year', 'academic_year_code')->ignore($academicYearId)->withoutTrashed()],
            'period_type' => ['required', Rule::in(['regular', 'summer'])],
            'parent_academic_year_id' => ['nullable', 'exists:tb_academic_year,id', 'different:academic_year_id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:100'],
            'lifecycle_status' => ['required', Rule::in(['draft', 'pending', 'started', 'finished', 'archived'])],
        ]);

        if ($validated['period_type'] === 'summer') {
            if (!empty($validated['parent_academic_year_id'])) {
                $parent = AcademicYear::find($validated['parent_academic_year_id']);
                if (!$parent || $parent->period_type === 'summer' || ($academicYearId && (int) $parent->id === (int) $academicYearId)) {
                    throw ValidationException::withMessages(['parent_academic_year_id' => 'The optional parent school year must be a regular academic year.']);
                }
            }

        } else {
            $validated['parent_academic_year_id'] = null;
            $validated['start_date'] = null;
            $validated['end_date'] = null;
        }

        $validated['ay_code'] = filled($validated['ay_code'] ?? null)
            ? trim($validated['ay_code'])
            : null;

        $validated['status'] = in_array($validated['lifecycle_status'], ['draft', 'pending', 'started'], true) ? 1 : 0;

        $academicYear = DB::transaction(function () use ($academicYearId, $restorableAcademicYearId, $validated) {
            if ($validated['lifecycle_status'] === 'started') {
                AcademicYear::where('period_type', $validated['period_type'])
                    ->where('lifecycle_status', 'started')
                    ->when($academicYearId, fn ($query) => $query->where('id', '!=', $academicYearId))
                    ->get()
                    ->each(function (AcademicYear $year) use ($validated) {
                        $status = $this->displacedLifecycleStatus($year->academic_year, $validated['academic_year']);
                        $year->update([
                            'lifecycle_status' => $status,
                            'status' => $status === 'pending' ? 1 : 0,
                        ]);
                        $this->syncEnrollmentStatuses($year);
                    });
            }

            $academicYear = $academicYearId
                ? AcademicYear::findOrFail($academicYearId)
                : ($restorableAcademicYearId
                    ? AcademicYear::withTrashed()->findOrFail($restorableAcademicYearId)
                    : new AcademicYear());

            if ($academicYear->trashed()) {
                $academicYear->restore();
            }

            $academicYear->fill($validated);
            $academicYear->save();
            $this->syncEnrollmentStatuses($academicYear);

            return $academicYear;
        });

        return response()->json([
            'status' => 'success',
            'message' => $academicYearId
                ? 'Academic year updated successfully.'
                : ($restorableAcademicYearId
                    ? 'The deleted academic year was restored successfully.'
                    : 'Academic year created successfully.'),
            'data' => $academicYear,
        ], $academicYearId ? 200 : 201);
    }

    public function delete($id)
    {
        $academicYear = AcademicYear::find($id);

        if (!$academicYear) {
            return response()->json([
                'status' => 'error',
                'message' => 'Academic year not found.',
            ], 404);
        }

        $linkedRecords = collect([
            'student enrollment(s)' => StudentEnrollment::where('academic_year_id', $academicYear->id)->count(),
            'class(es)' => $academicYear->classes()->withTrashed()->count(),
            'program(s)' => $academicYear->programs()->withTrashed()->count(),
            'enrollment history record(s)' => StudentEnrollmentHistory::where('academic_year_id', $academicYear->id)->count(),
            'graduation record(s)' => StudentGraduation::where('academic_year_id', $academicYear->id)->count(),
            'promotion or transfer record(s)' => EnrollmentWorkflowAction::where(function ($query) use ($academicYear) {
                $query->where('from_academic_year_id', $academicYear->id)
                    ->orWhere('to_academic_year_id', $academicYear->id);
            })->count(),
            'linked Summer School period(s)' => AcademicYear::where('parent_academic_year_id', $academicYear->id)->withTrashed()->count(),
        ])->filter();

        if ($linkedRecords->isNotEmpty()) {
            $details = $linkedRecords
                ->map(fn ($count, $label) => "{$count} {$label}")
                ->implode(', ');

            return response()->json([
                'status' => 'error',
                'message' => "This academic year cannot be deleted because it contains {$details}. Use Finished or Archived status to preserve the records.",
            ], 422);
        }

        $academicYear->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Academic year deleted successfully.',
        ]);
    }

    public function restore($id)
    {
        $academicYear = AcademicYear::withTrashed()->find($id);

        if (!$academicYear) {
            return response()->json([
                'status' => 'error',
                'message' => 'Academic year not found.',
            ], 404);
        }

        if (!$academicYear->trashed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Academic year is already active.',
            ], 422);
        }

        $academicYear->restore();

        return response()->json([
            'status' => 'success',
            'message' => "Academic year {$academicYear->academic_year} restored successfully.",
            'data' => $academicYear->fresh(),
        ]);
    }

    public function setCurrent(AcademicYear $academicYear)
    {
        DB::transaction(function () use ($academicYear) {
            AcademicYear::where('period_type', $academicYear->period_type)
                ->where('lifecycle_status', 'started')
                ->where('id', '!=', $academicYear->id)
                ->get()
                ->each(function (AcademicYear $year) use ($academicYear) {
                    $status = $this->displacedLifecycleStatus($year->academic_year, $academicYear->academic_year);
                    $year->update([
                        'lifecycle_status' => $status,
                        'status' => $status === 'pending' ? 1 : 0,
                    ]);
                    $this->syncEnrollmentStatuses($year);
                });

            $academicYear->update([
                'lifecycle_status' => 'started',
                'status' => 1,
            ]);
            $this->syncEnrollmentStatuses($academicYear);
        });

        return response()->json([
            'status' => 'success',
            'message' => $academicYear->period_type === 'summer'
                ? 'Summer School set as started. Previous started Summer School was finished automatically.'
                : 'Academic year set as started. Previous started regular year was finished automatically.',
            'data' => $academicYear->fresh(),
        ]);
    }

    public function createNext(AcademicYear $academicYear)
    {
        abort_unless($academicYear->period_type === 'regular', 422, 'Only regular academic years can have a next academic year.');

        $nextAcademicYear = $this->nextAcademicYearValue($academicYear->academic_year);

        if (!$nextAcademicYear) {
            throw ValidationException::withMessages([
                'academic_year' => 'Use an academic year in the format YYYY-YYYY, such as 2025-2026.',
            ]);
        }

        $existing = AcademicYear::withTrashed()->where('period_type', 'regular')
            ->where('academic_year', $nextAcademicYear)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
                $existing->update([
                    'period_type' => 'regular',
                    'status' => 1,
                    'lifecycle_status' => 'pending',
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => "The deleted academic year {$nextAcademicYear} was restored as Pending.",
                    'data' => $existing->fresh(),
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => "Academic year {$nextAcademicYear} already exists.",
                'data' => $existing,
            ], 422);
        }

        $created = AcademicYear::create([
            'academic_year' => $nextAcademicYear,
            'period_type' => 'regular',
            'parent_academic_year_id' => null,
            'start_date' => null,
            'end_date' => null,
            'description' => "Next academic year after {$academicYear->academic_year}",
            'status' => 1,
            'lifecycle_status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Academic year {$nextAcademicYear} was created as Pending.",
            'data' => $created,
        ], 201);
    }

    private function displacedLifecycleStatus(string $displacedYear, string $newStartedYear): string
    {
        return $this->academicYearSortValue($displacedYear) > $this->academicYearSortValue($newStartedYear)
            ? 'pending'
            : 'finished';
    }

    private function academicYearSortValue(string $academicYear): int
    {
        preg_match_all('/\d{4}/', $academicYear, $matches);
        $years = array_map('intval', $matches[0] ?? []);

        return $years ? max($years) : 0;
    }

    private function nextAcademicYearValue(string $academicYear): ?string
    {
        if (!preg_match('/^(\d{4})([^\d]+)(\d{4})$/', trim($academicYear), $matches)) {
            return null;
        }

        $startYear = (int) $matches[1];
        $endYear = (int) $matches[3];

        if ($endYear !== $startYear + 1) {
            return null;
        }

        return ($startYear + 1) . $matches[2] . ($endYear + 1);
    }

    private function syncEnrollmentStatuses(AcademicYear $academicYear): void
    {
        $enrollmentStatus = match ($academicYear->lifecycle_status) {
            'started' => 'active',
            'pending' => 'pending',
            'finished' => 'completed',
            default => null,
        };

        if ($enrollmentStatus) {
            $query = StudentEnrollment::where('academic_year_id', $academicYear->id);

            $query->whereIn('enrollment_status', ['active', 'pending', 'completed'])
                ->update(['enrollment_status' => $enrollmentStatus]);
        }
    }

}
