<?php

namespace App\Http\Controllers;

use App\Models\BrandingSetting;
use App\Models\SchoolInfo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\SettingsQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SchoolInfoController
{
    public function index()
    {
        return view('school_profile');
    }

    public function fetchData(Request $request)
    {
        $perPage = SettingsQuery::perPage($request);
        $search = SettingsQuery::search($request);
        $allowedSorts = ['id', 'logo_path', 'school_name_en', 'campus_name_en', 'phone', 'address_en', 'address_kh', 'status'];
        $sortBy = SettingsQuery::sort($request, $allowedSorts, 'id');
        $sortDir = SettingsQuery::direction($request);

        $schools = SchoolInfo::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('school_name_en', 'like', "%{$search}%")
                        ->orWhere('school_name_kh', 'like', "%{$search}%")
                        ->orWhere('campus_name_en', 'like', "%{$search}%")
                        ->orWhere('campus_name_kh', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('address_en', 'like', "%{$search}%")
                        ->orWhere('address_kh', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);

        $schools->getCollection()->transform(function (SchoolInfo $school) {
            $linkSummary = $this->campusLinkSummary($school->id);
            $school->is_linked = $linkSummary['total'] > 0;
            $school->linked_message = $this->campusLinkedMessage($linkSummary);

            return $school;
        });

        return response()->json($schools);
    }

    public function exportPdf()
    {
        $schools = $this->schoolProfileReportRows();
        $logoPath = $this->schoolProfileReportLogoPath();
        $logoDataUri = $this->schoolProfileLogoDataUri($logoPath);

        return Pdf::loadView('school-profile-pdf', compact('schools', 'logoPath', 'logoDataUri'))->stream('school-profiles.pdf');
    }

    public function print()
    {
        return view('school-profile-pdf', [
            'schools' => $this->schoolProfileReportRows(),
            'logoPath' => $this->schoolProfileReportLogoPath(),
            'logoDataUri' => $this->schoolProfileLogoDataUri($this->schoolProfileReportLogoPath()),
            'printMode' => true,
        ]);
    }

    public function exportExcel()
    {
        $filename = 'school-profile-list-' . now()->format('Ymd-His') . '.xlsx';
        $path = $this->schoolProfileExcelPath($this->schoolProfileReportRows());

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    private function schoolProfileReportRows()
    {
        return SchoolInfo::orderBy('school_name_en')->orderBy('campus_name_en')->get();
    }

    private function schoolProfileReportLogoPath(): ?string
    {
        $school = SchoolInfo::latest('id')->first();
        $branding = BrandingSetting::current();

        $logoPath = $branding?->report_logo_1_path
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

    private function schoolProfileLogoDataUri(?string $logoPath): ?string
    {
        if (!$logoPath || !is_file($logoPath)) {
            return null;
        }

        $mime = mime_content_type($logoPath) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
    }

    private function schoolProfileExcelPath($schools): string
    {
        $path = tempnam(sys_get_temp_dir(), 'school-profile-list-');
        $xlsxPath = $path . '.xlsx';
        rename($path, $xlsxPath);

        $logoPath = $this->schoolProfileReportLogoPath();
        $rowLogoPaths = $schools
            ->map(fn (SchoolInfo $school) => $this->schoolProfileRowLogoPath($school))
            ->values()
            ->all();
        $hasImages = (bool) $logoPath || collect($rowLogoPaths)->filter()->isNotEmpty();
        $logoExtension = $logoPath ? $this->xlsxImageExtension($logoPath) : null;
        $entries = [
            '[Content_Types].xml' => $this->xlsxContentTypes($hasImages ? 'png' : null),
            '_rels/.rels' => $this->xlsxRootRels(),
            'xl/workbook.xml' => $this->xlsxWorkbook(),
            'xl/_rels/workbook.xml.rels' => $this->xlsxWorkbookRels(),
            'xl/styles.xml' => $this->xlsxStyles(),
            'xl/worksheets/sheet1.xml' => $this->schoolProfileWorksheetXml($schools, $hasImages),
        ];

        if ($hasImages) {
            $entries['xl/worksheets/_rels/sheet1.xml.rels'] = $this->xlsxWorksheetRels();
            $entries['xl/drawings/drawing1.xml'] = $this->xlsxLogoDrawing($logoPath, $rowLogoPaths);
            $entries['xl/drawings/_rels/drawing1.xml.rels'] = $this->xlsxLogoDrawingRels($logoPath, $rowLogoPaths);

            if ($logoPath && $logoExtension) {
                $entries['xl/media/report-logo.' . $logoExtension] = file_get_contents($logoPath);
            }

            foreach ($rowLogoPaths as $index => $rowLogoPath) {
                if (!$rowLogoPath) {
                    continue;
                }

                $extension = $this->xlsxImageExtension($rowLogoPath);
                $entries["xl/media/school-logo-{$index}.{$extension}"] = file_get_contents($rowLogoPath);
            }
        }

        $this->writeZipArchive($xlsxPath, $entries);

        return $xlsxPath;
    }

    private function schoolProfileRowLogoPath(SchoolInfo $school): ?string
    {
        if (!$school->logo_path) {
            return null;
        }

        $logoPath = storage_path('app/public/' . ltrim($school->logo_path, '/'));

        return is_file($logoPath) ? $logoPath : null;
    }

    private function schoolProfileWorksheetXml($schools, bool $hasLogo): string
    {
        $lastRow = $schools->count() + 9;
        $rows = [
            $this->xlsxRow(1, [], 28),
            $this->xlsxRow(2, [], 28),
            $this->xlsxRow(3, [], 28),
            $this->xlsxRow(4, [], 10),
            $this->xlsxRow(5, [['A', 'តារាងព័ត៌មានសាលា', 1]], 30),
            $this->xlsxRow(6, [['A', 'School Profile List', 2]], 26),
            $this->xlsxRow(7, [['A', 'Generated: ' . now()->format('d-M-Y h:i A'), 6]], 20),
            $this->xlsxRow(8, [], 8),
            $this->xlsxRow(9, [
                ['A', 'No.', 3],
                ['B', 'Logo', 3],
                ['C', 'School Name (Khmer)', 3],
                ['D', 'School Name (English)', 3],
                ['E', 'Campus (Khmer)', 3],
                ['F', 'Campus (English)', 3],
                ['G', 'Phone Number', 3],
                ['H', 'Address', 3],
            ], 24),
        ];

        foreach ($schools as $index => $school) {
            $row = $index + 10;
            $rows[] = $this->xlsxRow($row, [
                ['A', (string) ($index + 1), 5],
                ['B', $this->schoolProfileRowLogoPath($school) ? '' : '-', 5],
                ['C', $school->school_name_kh ?: '-', 4],
                ['D', $school->school_name_en ?: '-', 5],
                ['E', $school->campus_name_kh ?: '-', 4],
                ['F', $school->campus_name_en ?: '-', 5],
                ['G', $school->phone ?: '-', 5],
                ['H', $school->address ?: '-', 5],
            ], 42);
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:H' . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="9" topLeftCell="A10" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="12" customWidth="1"/><col min="3" max="4" width="26" customWidth="1"/><col min="5" max="6" width="22" customWidth="1"/><col min="7" max="7" width="16" customWidth="1"/><col min="8" max="8" width="32" customWidth="1"/></cols>'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . '<mergeCells count="5"><mergeCell ref="A1:H3"/><mergeCell ref="A5:H5"/><mergeCell ref="A6:H6"/><mergeCell ref="A7:H7"/><mergeCell ref="A8:H8"/></mergeCells>'
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
        $imageDefaults = $logoExtension
            ? '<Default Extension="png" ContentType="image/png"/><Default Extension="jpeg" ContentType="image/jpeg"/><Default Extension="jpg" ContentType="image/jpeg"/><Default Extension="gif" ContentType="image/gif"/>'
            : '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . $imageDefaults
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
            . '<sheets><sheet name="School Profile List" sheetId="1" r:id="rId1"/></sheets>'
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

    private function xlsxLogoDrawing(?string $reportLogoPath, array $rowLogoPaths): string
    {
        $anchors = '';
        $relationshipId = 1;
        $pictureId = 1;

        if ($reportLogoPath) {
            $anchors .= $this->xlsxImageAnchor($pictureId++, 'School Logo 1', "rId{$relationshipId}", 0, 0, 2500000, 850000, 320000, 120000);
            $relationshipId++;
        }

        foreach ($rowLogoPaths as $index => $rowLogoPath) {
            if (!$rowLogoPath) {
                continue;
            }

            $excelRow = $index + 9;
            $anchors .= $this->xlsxImageAnchor($pictureId++, "School Profile Logo " . ($index + 1), "rId{$relationshipId}", 1, $excelRow, 520000, 520000, 220000, 70000);
            $relationshipId++;
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            . $anchors
            . '</xdr:wsDr>';
    }

    private function xlsxImageAnchor(int $pictureId, string $name, string $relationshipId, int $column, int $row, int $width, int $height, int $columnOffset = 0, int $rowOffset = 0): string
    {
        return '<xdr:oneCellAnchor>'
            . '<xdr:from><xdr:col>' . $column . '</xdr:col><xdr:colOff>' . $columnOffset . '</xdr:colOff><xdr:row>' . $row . '</xdr:row><xdr:rowOff>' . $rowOffset . '</xdr:rowOff></xdr:from>'
            . '<xdr:ext cx="' . $width . '" cy="' . $height . '"/>'
            . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="' . $pictureId . '" name="' . $this->xlsxEscape($name) . '"/><xdr:cNvPicPr/></xdr:nvPicPr>'
            . '<xdr:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="' . $relationshipId . '"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            . '<xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
    }

    private function xlsxLogoDrawingRels(?string $reportLogoPath, array $rowLogoPaths): string
    {
        $relationships = '';
        $relationshipId = 1;

        if ($reportLogoPath) {
            $relationships .= '<Relationship Id="rId' . $relationshipId++ . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/report-logo.' . $this->xlsxImageExtension($reportLogoPath) . '"/>';
        }

        foreach ($rowLogoPaths as $index => $rowLogoPath) {
            if (!$rowLogoPath) {
                continue;
            }

            $relationships .= '<Relationship Id="rId' . $relationshipId++ . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/school-logo-' . $index . '.' . $this->xlsxImageExtension($rowLogoPath) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relationships . '</Relationships>';
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
        $schoolId = $request->input('school_id');
        $campusNameEnValue = $request->input('campus_name_en');
        $campusNameKhValue = $request->input('campus_name_kh');
        $phoneValue = $request->input('phone');

        $duplicateFields = [];

        if (filled($campusNameEnValue) && SchoolInfo::query()
            ->where('campus_name_en', $campusNameEnValue)
            ->when($schoolId, fn ($query) => $query->where('id', '!=', $schoolId))
            ->exists()) {
            $duplicateFields[] = "Campus Name in English '{$campusNameEnValue}'";
        }

        if (filled($campusNameKhValue) && SchoolInfo::query()
            ->where('campus_name_kh', $campusNameKhValue)
            ->when($schoolId, fn ($query) => $query->where('id', '!=', $schoolId))
            ->exists()) {
            $duplicateFields[] = "Campus Name in Khmer '{$campusNameKhValue}'";
        }

        if (filled($phoneValue) && SchoolInfo::query()
            ->where('phone', $phoneValue)
            ->when($schoolId, fn ($query) => $query->where('id', '!=', $schoolId))
            ->exists()) {
            $duplicateFields[] = "Phone Number '{$phoneValue}'";
        }

        if ($duplicateFields) {
            $message = count($duplicateFields) === 1
                ? "Unable to save School Profile. {$duplicateFields[0]} already existed."
                : 'Unable to save School Profile. ' . implode(' and ', $duplicateFields) . ' are already existed.';

            return response()->json(['status' => 'error', 'message' => $message], 422);
        }

        $validated = $request->validate([
            'school_name_en' => ['required', 'string', 'max:100'],
            'school_name_kh' => ['required', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'campus_name_en' => ['required', 'string', 'max:20', Rule::unique('tb_school_info', 'campus_name_en')->ignore($schoolId)],
            'campus_name_kh' => ['required', 'string', 'max:20', Rule::unique('tb_school_info', 'campus_name_kh')->ignore($schoolId)],
            'address' => ['nullable', 'string', 'max:250'],
            'address_country_id' => ['nullable', 'integer', 'exists:tb_country,id'],
            'address_province_id' => ['nullable', 'integer', 'exists:tb_province,id'],
            'address_district_id' => ['nullable', 'integer', 'exists:tb_district,id'],
            'address_commune_id' => ['nullable', 'integer', 'exists:tb_commune,id'],
            'address_village_id' => ['nullable', 'integer', 'exists:tb_village,id'],
            'address_en' => ['nullable', 'string', 'max:500'],
            'address_kh' => ['nullable', 'string', 'max:500'],
            'address_house_no_en' => ['nullable', 'string', 'max:100'],
            'address_house_no_kh' => ['nullable', 'string', 'max:100'],
            'address_street_en' => ['nullable', 'string', 'max:100'],
            'address_street_kh' => ['nullable', 'string', 'max:100'],
            'google_map_url' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:50', Rule::unique('tb_school_info', 'phone')->ignore($schoolId)],
            'description' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'boolean'],
        ], [
            'school_name_en.required' => 'School name in English is required.',
            'school_name_kh.required' => 'School name in Khmer is required.',
            'campus_name_en.required' => 'Campus name in English is required.',
            'campus_name_kh.required' => 'Campus name in Khmer is required.',
        ]);

        $school = $schoolId ? SchoolInfo::findOrFail($schoolId) : new SchoolInfo();
        $school->fill($validated);

        if ($request->hasFile('logo')) {
            if ($school->logo_path) {
                Storage::disk('public')->delete($school->logo_path);
            }
            $school->logo_path = $request->file('logo')->store('school_logos', 'public');
        }

        $school->save();

        return response()->json([
            'status' => 'success',
            'message' => $schoolId ? 'School profile updated successfully.' : 'School profile created successfully.',
            'data' => $school,
        ], $schoolId ? 200 : 201);
    }

    public function delete($id)
    {
        $school = SchoolInfo::find($id);
        if (!$school) return response()->json(['status' => 'error', 'message' => 'School profile not found.'], 404);

        $linkSummary = $this->campusLinkSummary($school->id);
        if ($linkSummary['total'] > 0) {
            return response()->json([
                'status' => 'error',
                'message' => $this->campusLinkedMessage($linkSummary),
            ], 409);
        }

        if ($school->logo_path) {
            Storage::disk('public')->delete($school->logo_path);
        }
        $school->delete();
        return response()->json(['status' => 'success', 'message' => 'School profile deleted successfully.']);
    }

    private function campusLinkSummary(int $campusId): array
    {
        $counts = [
            'student enrollments' => DB::table('tb_student_enrollment')->where('campus_id', $campusId)->count(),
            'enrollment history records' => DB::table('tb_student_enrollment_history')->where('campus_id', $campusId)->count(),
            'graduation records' => DB::table('tb_student_graduation')->where('campus_id', $campusId)->count(),
            'workflow source campus records' => DB::table('tb_student_enrollment_workflow')->where('from_campus_id', $campusId)->count(),
            'workflow target campus records' => DB::table('tb_student_enrollment_workflow')->where('to_campus_id', $campusId)->count(),
            'user campus assignments' => DB::table('access_user_campuses')->where('campus_id', $campusId)->count(),
            'active user campus settings' => DB::table('users')->where('active_campus_id', $campusId)->count(),
            'role campus assignments' => DB::table('access_user_roles')->where('campus_id', $campusId)->count(),
        ];

        return [
            'counts' => array_filter($counts),
            'total' => array_sum($counts),
        ];
    }

    private function campusLinkedMessage(array $linkSummary): string
    {
        if (($linkSummary['total'] ?? 0) <= 0) {
            return '';
        }

        $parts = collect($linkSummary['counts'])
            ->map(fn ($count, $label) => "{$count} {$label}")
            ->values()
            ->join(', ');

        return "This campus cannot be deleted because it is already linked to other data: {$parts}.";
    }
}
