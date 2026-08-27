<?php

namespace App\Http\Controllers;

use App\Models\BrandingSetting;
use App\Models\SchoolInfo;
use App\Models\WithdrawalReason;
use App\Support\SettingsQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WithdrawalReasonController
{
    public function index(Request $request)
    {
        $sortBy = in_array($request->string('sortBy')->toString(), ['sort_order', 'name_en', 'name_kh', 'status'], true)
            ? $request->string('sortBy')->toString()
            : 'sort_order';
        $sortDir = strtolower($request->string('sortDir')->toString()) === 'desc' ? 'desc' : 'asc';

        return view('withdrawal-reasons', [
            'reasons' => WithdrawalReason::when(SettingsQuery::search($request), fn ($q, $term) => $q->where('name_en', 'like', "%{$term}%")->orWhere('name_kh', 'like', "%{$term}%"))
                ->orderBy($sortBy, $sortDir)
                ->orderBy('name_en')
                ->paginate(SettingsQuery::perPage($request))
                ->withQueryString(),
        ]);
    }

    public function print()
    {
        return view('withdrawal-reasons-pdf', [
            'reasons' => $this->withdrawalReasonReportRows(),
            'logoSrc' => $this->withdrawalReasonReportLogoSrc(),
            'printMode' => true,
        ]);
    }

    public function exportExcel()
    {
        $filename = 'withdrawal-reason-list-' . now()->format('Ymd-His') . '.xlsx';
        $path = $this->withdrawalReasonExcelPath($this->withdrawalReasonReportRows());

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function save(Request $request)
    {
        $id = $request->integer('reason_id') ?: null;
        $data = $request->validate([
            'reason_key' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('tb_withdrawal_reason', 'reason_key')->ignore($id)],
            'name_en' => ['required', 'string', 'max:255'], 'name_kh' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'], 'status' => ['required', 'boolean'],
        ]);
        $reason = $id ? WithdrawalReason::findOrFail($id) : new WithdrawalReason();
        $reason->fill($data)->save();
        return back()->with('success', $id ? 'Withdrawal reason updated successfully.' : 'Withdrawal reason created successfully.');
    }

    public function delete(WithdrawalReason $withdrawalReason)
    {
        $withdrawalReason->update(['status' => false]);
        return back()->with('success', 'Withdrawal reason deactivated successfully. Existing forms are unchanged.');
    }

    private function withdrawalReasonReportRows()
    {
        return WithdrawalReason::orderBy('sort_order')->orderBy('name_en')->get();
    }

    private function withdrawalReasonReportLogoSrc(): ?string
    {
        $logoPath = $this->withdrawalReasonReportLogoPath();

        if (!$logoPath) {
            return null;
        }

        return 'data:' . (mime_content_type($logoPath) ?: 'image/png') . ';base64,' . base64_encode(file_get_contents($logoPath));
    }

    private function withdrawalReasonReportLogoPath(): ?string
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

    private function withdrawalReasonExcelPath($reasons): string
    {
        $path = tempnam(sys_get_temp_dir(), 'withdrawal-reason-list-');
        $xlsxPath = $path . '.xlsx';
        rename($path, $xlsxPath);

        $logoPath = $this->withdrawalReasonReportLogoPath();
        $logoExtension = $logoPath ? $this->xlsxImageExtension($logoPath) : null;
        $entries = [
            '[Content_Types].xml' => $this->xlsxContentTypes($logoExtension),
            '_rels/.rels' => $this->xlsxRootRels(),
            'xl/workbook.xml' => $this->xlsxWorkbook(),
            'xl/_rels/workbook.xml.rels' => $this->xlsxWorkbookRels(),
            'xl/styles.xml' => $this->xlsxStyles(),
            'xl/worksheets/sheet1.xml' => $this->withdrawalReasonWorksheetXml($reasons, (bool) $logoPath),
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

    private function withdrawalReasonWorksheetXml($reasons, bool $hasLogo): string
    {
        $lastRow = $reasons->count() + 9;
        $rows = [
            $this->xlsxRow(1, [], 28),
            $this->xlsxRow(2, [], 28),
            $this->xlsxRow(3, [], 28),
            $this->xlsxRow(4, [], 10),
            $this->xlsxRow(5, [['A', 'តារាងមូលហេតុនៃការបោះបង់ការសិក្សា', 1]], 30),
            $this->xlsxRow(6, [['A', 'Withdrawal Reason List', 2]], 26),
            $this->xlsxRow(7, [['A', 'Generated: ' . now()->format('d-M-Y h:i A'), 6]], 20),
            $this->xlsxRow(8, [], 8),
            $this->xlsxRow(9, [
                ['A', 'No.', 3],
                ['B', 'Order', 3],
                ['C', 'Reason (English)', 3],
                ['D', 'Reason (Khmer)', 3],
                ['E', 'System Key', 3],
                ['F', 'Status', 3],
            ], 24),
        ];

        foreach ($reasons as $index => $reason) {
            $row = $index + 10;
            $rows[] = $this->xlsxRow($row, [
                ['A', (string) ($index + 1), 5],
                ['B', (string) $reason->sort_order, 5],
                ['C', $reason->name_en ?: '-', 5],
                ['D', $reason->name_kh ?: '-', 4],
                ['E', $reason->reason_key ?: '-', 5],
                ['F', $reason->status ? 'Active' : 'Inactive', 5],
            ], 22);
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:F' . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="9" topLeftCell="A10" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="12" customWidth="1"/><col min="3" max="3" width="38" customWidth="1"/><col min="4" max="4" width="42" customWidth="1"/><col min="5" max="5" width="28" customWidth="1"/><col min="6" max="6" width="14" customWidth="1"/></cols>'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . '<mergeCells count="5"><mergeCell ref="A1:F3"/><mergeCell ref="A5:F5"/><mergeCell ref="A6:F6"/><mergeCell ref="A7:F7"/><mergeCell ref="A8:F8"/></mergeCells>'
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
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function xlsxWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Withdrawal Reason List" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function xlsxWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4"><font><sz val="11"/><name val="Arial"/></font><font><sz val="18"/><name val="Khmer OS Muol Light"/><color rgb="FF4F6380"/></font><font><b/><sz val="12"/><name val="Arial"/></font><font><sz val="11"/><name val="Khmer OS Siemreap"/></font></fonts>'
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
}
