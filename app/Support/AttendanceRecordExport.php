<?php

namespace App\Support;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use ZipArchive;

class AttendanceRecordExport
{
    public function pdf(array $data, bool $statistics = false): string
    {
        $temp = storage_path('app/private/attendance-pdf');
        if (!is_dir($temp)) mkdir($temp, 0755, true);
        $pdf = new Mpdf([
            'mode' => 'utf-8', 'format' => $statistics ? 'A4-L' : 'A4', 'tempDir' => $temp,
            'margin_left' => 12, 'margin_right' => 12, 'margin_top' => 12, 'margin_bottom' => 12,
            'fontDir' => [...(new ConfigVariables)->getDefaults()['fontDir'], public_path('fonts/khmer')],
            'fontdata' => (new FontVariables)->getDefaults()['fontdata'] + ['attendancesiemreap' => ['R' => 'KhmerOSsiemreap.ttf', 'useOTL' => 0xFF]],
            'default_font' => 'dejavusans', 'default_font_size' => 10,
        ]);
        $pdf->SetTitle($data['title'] ?? 'Student Absent Record');
        $pdf->SetHTMLFooter('<div style="text-align:right;font-size:8pt">Page {PAGENO} of {nbpg}</div>');
        $pdf->WriteHTML(file_get_contents(resource_path('css/pages/attendance-record-print.css')), \Mpdf\HTMLParserMode::HEADER_CSS);
        if ($statistics) $pdf->WriteHTML(file_get_contents(resource_path('css/pages/attendance-statistics-print.css')), \Mpdf\HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML(view($statistics ? 'attendance.statistics-print' : 'attendance.record-print', $data + ['pdfExport' => true])->render());
        return $pdf->Output('', Destination::STRING_RETURN);
    }

    public function xlsx(array $data): string
    {
        $student = $data['student'];
        $cell = fn ($value, $style = 0) => ['value' => $value, 'style' => $style];
        $rows = [
            ['cells' => [], 'height' => $data['logo'] ? 65 : 8],
            ['cells' => [$cell('Student Absent Record', 1)], 'height' => 30],
            ['cells' => [$cell($student['academic_year'].' | '.$data['dateRange'])], 'height' => 25],
            ['cells' => [$cell('Academic Year: '.$student['academic_year'], 4), null, $cell('Gender: '.($student['gender'] ?: '-'), 4)], 'height' => 24],
            ['cells' => [$cell('Student ID: '.$student['student_code'], 4), null, $cell('Grade: '.$student['class'], 4)], 'height' => 24],
            ['cells' => [$cell('Student Name: '.$student['name_kh'], 4), null, $cell('Group: '.($student['group'] ?: '-'), 4)], 'height' => 28],
            ['cells' => [$cell($student['name_en'], 4), null, $cell('Campus: '.$student['campus'], 4)], 'height' => 24],
            ['cells' => array_map(fn ($status) => $cell('Total '.($status === 'absent' ? 'Absent' : (['EX'=>'E','UX'=>'U'][$status] ?? $status)).': '.($status === 'absent' ? $data['totals']['EX'] + $data['totals']['UX'] : $data['totals'][$status]), 2), ['absent', 'EX', 'UX', 'L']), 'height' => 28],
            ['cells' => array_map(fn ($label) => $cell($label, 2), ['No.', 'Date', 'Attendance Status', 'Reason']), 'height' => 28],
        ];
        foreach ($data['data'] as $index => $record) $rows[] = ['cells' => [
            $cell($index + 1, 3), $cell(\Carbon\CarbonImmutable::parse($record['date'])->format('d-M-Y'), 3),
            $cell(['EX'=>'E','UX'=>'U'][$record['status']] ?? $record['status'], 3), $cell($record['reason'] ?: '-', 4),
        ], 'height' => max(28, 16 * (int) ceil(mb_strlen($record['reason'] ?? '') / 45))];
        if (!$data['data']) $rows[] = ['cells' => [$cell('No absent or late records found.')], 'height' => 25];
        $merges = ['A2:D2', 'A3:D3'];
        foreach (range(4, 7) as $row) { $merges[] = 'A'.$row.':B'.$row; $merges[] = 'C'.$row.':D'.$row; }
        $images = $data['logo'] ? [['src' => $data['logo'], 'row' => 0, 'column' => 0, 'width' => 240, 'height' => 80]] : [];
        return $this->workbook($rows, $merges, [10, 24, 25, 62], $images);
    }

    public function statisticsXlsx(array $data): string
    {
        $cell = fn ($value, $style = 0) => ['value' => $value, 'style' => $style];
        $rows = [
            ['cells' => [], 'height' => $data['logo'] ? 65 : 8],
            ['cells' => [$cell($data['title'], 11)], 'height' => 30],
            ['cells' => [$cell($data['dateRange'], 11)], 'height' => 27],
            ['cells' => [$cell('Academic Year: '.$data['academicYear'], 11)], 'height' => 27],
            ['cells' => [$cell('Campus: '.$data['campus'], 11)], 'height' => 27],
            ['cells' => [$cell('Grade: '.$data['selectedGrade'], 11)], 'height' => 27],
            ['cells' => [], 'height' => 8],
            ['cells' => array_map(fn ($label) => $cell($label, 2), ['No.', 'Student Name Khmer', 'Student Name English', 'Student ID', 'Gender', 'Grade', 'Group', 'Total Tardy', 'Total Excused', 'Total Unexcused', 'Total Absent']), 'height' => 32],
        ];
        $merges = array_map(fn ($row) => 'A'.$row.':K'.$row, range(2, 6));
        foreach ($data['data'] as $index => $student) {
            if ($data['groupByGrade'] && ($index === 0 || $data['data'][$index - 1]['class'] !== $student['class'])) {
                $rows[] = ['cells' => [$cell('Grade: '.($student['class'] ?: '-'), 2)], 'height' => 26];
                $merges[] = 'A'.count($rows).':K'.count($rows);
            }
            $rows[] = ['cells' => [
            $cell($index + 1, 3), $cell($student['name_kh'], 4), $cell($student['name_en'], 4),
            $cell($student['student_code'], 3), $cell($student['gender'] ?: '-', 4), $cell($student['class'] ?: '-', 3), $cell($student['group'] ?: '-', 3),
            $cell($student['L'], 3), $cell($student['EX'], 3), $cell($student['UX'], 3), $cell($student['total_absent'], 3),
        ], 'height' => 30];
        }
        if (!$data['data']) $rows[] = ['cells' => [$cell('No students found for the selected filters.')], 'height' => 24];
        $images = $data['logo'] ? [['src' => $data['logo'], 'row' => 0, 'column' => 0, 'width' => 240, 'height' => 80]] : [];
        return $this->workbook($rows, $merges, [6, 26, 30, 18, 10, 10, 10, 13, 14, 15, 14], $images, 8, 'landscape');
    }

    private function workbook(array $rows, array $merges, array $widths, array $images, int $headerRow = 9, string $orientation = 'portrait'): string
    {
        $sheet = '';
        foreach ($rows as $index => $row) {
            $number = $index + 1;
            $sheet .= '<row r="'.$number.'" ht="'.$row['height'].'" customHeight="1">';
            foreach ($row['cells'] as $column => $cell) {
                if ($cell === null) continue;
                $ref = $this->column($column + 1).$number;
                $sheet .= '<c r="'.$ref.'" s="'.$cell['style'].'"'.(is_int($cell['value']) ? '><v>'.$cell['value'].'</v>' : ' t="inlineStr"><is><t xml:space="preserve">'.$this->xml((string)$cell['value']).'</t></is>').'</c>';
            }
            $sheet .= '</row>';
        }
        $cols = ''; foreach ($widths as $index => $width) $cols .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        $mergeXml = ''; foreach ($merges as $merge) $mergeXml .= '<mergeCell ref="'.$merge.'"/>';
        $last = $this->column(count($widths));
        $worksheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:'.$last.count($rows).'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$headerRow.'" topLeftCell="A'.($headerRow + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>'.$cols.'</cols><sheetData>'.$sheet.'</sheetData><mergeCells count="'.count($merges).'">'.$mergeXml.'</mergeCells><pageMargins left="0.39" right="0.39" top="0.39" bottom="0.39" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="'.$orientation.'" fitToWidth="1" fitToHeight="'.(0).'"/>'.($images ? '<drawing r:id="rId1"/>' : '').'</worksheet>';
        $relsNs = 'http://schemas.openxmlformats.org/package/2006/relationships';
        $officeNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $entries = [
            '_rels/.rels' => '<?xml version="1.0"?><Relationships xmlns="'.$relsNs.'"><Relationship Id="rId1" Type="'.$officeNs.'/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="'.$officeNs.'"><sheets><sheet name="'.'Attendance Record'.'" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm.Print_Area" localSheetId="0">\''.'Attendance Record'.'\'!$A$1:$'.$last.'$'.count($rows).'</definedName><definedName name="_xlnm.Print_Titles" localSheetId="0">\''.'Attendance Record'.'\'!$'.$headerRow.':$'.$headerRow.'</definedName></definedNames></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="'.$relsNs.'"><Relationship Id="rId1" Type="'.$officeNs.'/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="'.$officeNs.'/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => $this->styles(), 'xl/worksheets/sheet1.xml' => $worksheet,
        ];
        $imageTypes = [];
        if ($images) {
            $drawing = ''; $drawingRels = '';
            foreach ($images as $index => $image) {
                preg_match('~^data:(image/[\w.+-]+);base64,(.+)$~s', $image['src'], $match);
                $bytes = isset($match[2]) ? base64_decode($match[2], true) : false;
                if ($bytes === false) throw new \RuntimeException('Unable to read report image.');
                $extension = 'png';
                if (($match[1] ?? '') === 'image/svg+xml') {
                    $svg = new \DOMDocument();
                    if (!@$svg->loadXML($bytes, LIBXML_NONET) || $svg->documentElement?->localName !== 'svg') throw new \RuntimeException('Invalid School Logo 1.');
                    $box = preg_split('/[\s,]+/', trim($svg->documentElement->getAttribute('viewBox')));
                    $size = count($box) === 4 ? [(float) $box[2], (float) $box[3]] : [(float) $svg->documentElement->getAttribute('width'), (float) $svg->documentElement->getAttribute('height')];
                    if ($size[0] <= 0 || $size[1] <= 0) $size = [240, 80];
                    $png = $bytes; $extension = 'svg';
                } else {
                $size = getimagesizefromstring($bytes);
                if (!$size) throw new \RuntimeException('Invalid report image.');
                // Normalize uploaded formats to PNG so Excel can display WebP/GIF/JPEG photos too.
                $resource = imagecreatefromstring($bytes);
                if (!$resource) throw new \RuntimeException('Unable to decode report image.');
                ob_start(); imagepng($resource); $png = ob_get_clean(); imagedestroy($resource);
                }
                $id = $index + 1; $imageTypes[$extension] = $extension === 'svg' ? 'image/svg+xml' : 'image/png';
                $entries['xl/media/image'.$id.'.'.$extension] = $png;
                $scale = min($image['width'] / $size[0], $image['height'] / $size[1]);
                $cx = (int)round($size[0] * $scale * 9525); $cy = (int)round($size[1] * $scale * 9525);
                $drawing .= '<xdr:oneCellAnchor><xdr:from><xdr:col>'.$image['column'].'</xdr:col><xdr:colOff>38100</xdr:colOff><xdr:row>'.$image['row'].'</xdr:row><xdr:rowOff>38100</xdr:rowOff></xdr:from><xdr:ext cx="'.$cx.'" cy="'.$cy.'"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="'.$id.'" name="Report image '.$id.'"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr><xdr:blipFill><a:blip r:embed="rId'.$id.'"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
                $drawingRels .= '<Relationship Id="rId'.$id.'" Type="'.$officeNs.'/image" Target="../media/image'.$id.'.'.$extension.'"/>';
            }
            $entries['xl/worksheets/_rels/sheet1.xml.rels'] = '<?xml version="1.0"?><Relationships xmlns="'.$relsNs.'"><Relationship Id="rId1" Type="'.$officeNs.'/drawing" Target="../drawings/drawing1.xml"/></Relationships>';
            $entries['xl/drawings/drawing1.xml'] = '<?xml version="1.0"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="'.$officeNs.'">'.$drawing.'</xdr:wsDr>';
            $entries['xl/drawings/_rels/drawing1.xml.rels'] = '<?xml version="1.0"?><Relationships xmlns="'.$relsNs.'">'.$drawingRels.'</Relationships>';
        }
        $types = '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>';
        foreach ($imageTypes as $extension => $mime) $types .= '<Default Extension="'.$extension.'" ContentType="'.$mime.'"/>';
        foreach (['workbook' => 'sheet.main', 'styles' => 'styles', 'worksheets/sheet1' => 'worksheet'] as $part => $type) $types .= '<Override PartName="/xl/'.$part.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.'.$type.'+xml"/>';
        if ($images) $types .= '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
        $entries['[Content_Types].xml'] = $types.'</Types>';
        $path = tempnam(sys_get_temp_dir(), 'attendance-xlsx-');
        if ($path === false) throw new \RuntimeException('Unable to create report workbook.');
        try {
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('Unable to create report workbook.');
            foreach ($entries as $name => $contents) $zip->addFromString($name, $contents);
            $zip->close();
            return file_get_contents($path);
        } finally { if (is_file($path)) unlink($path); }
    }

    private function styles(): string
    {
        $fonts = '<font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="18"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><sz val="11"/><name val="Khmer OS Siemreap"/></font><font><b/><sz val="14"/><name val="Calibri"/></font><font><sz val="12"/><name val="Calibri"/></font><font><sz val="14"/><name val="Calibri"/></font>';
        $fills = '<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>';
        foreach (['EDF1F5', 'D0E9ED', 'DEDCE7', 'E2EFD9', 'FBE5D5'] as $color) $fills .= '<fill><patternFill patternType="solid"><fgColor rgb="FF'.$color.'"/><bgColor indexed="64"/></patternFill></fill>';
        $xfs = '';
        foreach ([[0,0,0,'center'],[1,0,0,'center'],[2,2,1,'center'],[0,0,1,'center'],[3,0,1,'left'],[4,0,0,'center'],[5,0,0,'center'],[2,3,1,'center'],[2,4,1,'center'],[2,5,1,'center'],[2,6,1,'center'],[6,0,0,'center']] as [$font,$fill,$border,$align]) $xfs .= '<xf numFmtId="0" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="'.$align.'" vertical="center" wrapText="1"/></xf>';
        return '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="7">'.$fonts.'</fonts><fills count="7">'.$fills.'</fills><borders count="2"><border/><border><left style="thin"><color rgb="FF8B929A"/></left><right style="thin"><color rgb="FF8B929A"/></right><top style="thin"><color rgb="FF8B929A"/></top><bottom style="thin"><color rgb="FF8B929A"/></bottom></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="12">'.$xfs.'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function xml(string $text): string { return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text), ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
    private function column(int $index): string { $name = ''; while ($index > 0) { $index--; $name = chr(65 + $index % 26).$name; $index = intdiv($index, 26); } return $name; }
}
