<?php

namespace App\Support;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use ZipArchive;

class StudentSkippingGradeExport
{
    public function pdf(string $kind, array $data): string
    {
        $temp = storage_path('framework/cache/skipping-grade-pdf');
        if (!is_dir($temp)) mkdir($temp, 0775, true);
        $pdf = new Mpdf([
            'mode' => 'utf-8', 'format' => $kind === 'statistics' ? 'A4-L' : 'A4', 'tempDir' => $temp,
            'margin_left' => 10, 'margin_right' => 10, 'margin_top' => 10, 'margin_bottom' => 10,
            'fontDir' => [...(new ConfigVariables())->getDefaults()['fontDir'], public_path('fonts/khmer')],
            'fontdata' => (new FontVariables())->getDefaults()['fontdata'] + ['skippingsiemreap' => ['R' => 'KhmerOSsiemreap.ttf', 'useOTL' => 0xFF]],
            'default_font' => 'dejavusans', 'default_font_size' => 8,
        ]);
        $pdf->SetTitle($kind === 'statistics' ? 'Statistics of Students Skipping Grades' : 'List of Grade Skipping Requests');
        $pdf->SetAuthor("Registrar's Office");
        $pdf->WriteHTML(view('student-skipping-grade.'.($kind === 'statistics' ? 'print-statistics' : 'print-report'), array_replace($data, ['pdfExport' => true]))->render());
        return $pdf->Output('', Destination::STRING_RETURN);
    }

    public function xlsx(string $kind, array $data): string
    {
        [$rows, $merges, $widths, $images] = $kind === 'statistics' ? $this->statisticsSheet($data) : $this->listSheet($data);
        return $this->workbook($kind, $rows, $merges, $widths, $images);
    }

    private function listSheet(array $data): array
    {
        $rows = [
            ['cells' => [], 'height' => $data['reportLogo'] ? 80 : 8],
            ['cells' => [$this->cell('List of Grade Skipping Requests', 1)], 'height' => 28],
            ['cells' => [$this->cell($data['reportYearPairs']->map(fn($years) => 'From Academic Year '.$years['from'].' to Academic Year '.$years['to'])->implode("\n"), 0)], 'height' => max(22, $data['reportYearPairs']->count() * 18)],
            ['cells' => [$this->cell($this->filterSummary($data), 0)], 'height' => 34],
            ['cells' => [$this->cell('Total Student: '.$data['records']->count().(blank($data['filters']['status'] ?? null) && $data['reportStatusSummary'] ? ' ( '.$data['reportStatusSummary'].' )' : ''), 0)], 'height' => 24],
            ['cells' => array_map(fn($label) => $this->cell($label, 2), ['No.', 'Student Photo', 'Student Name', 'Current Grade', 'Skipped To', 'Requested By', 'Status']), 'height' => 25],
        ];
        $merges = ['A2:G2', 'A3:G3', 'A4:G4', 'A5:G5'];
        $images = [];
        if ($data['reportLogo']) $images[] = ['src' => $data['reportLogo'], 'row' => 0, 'column' => 0, 'width' => 245, 'height' => 100];
        foreach ($data['records'] as $index => $record) {
            $s = $record->student_snapshot ?? [];
            $row = $data['reportRows'][$record->id];
            $status = $this->statusLabel($record->status);
            if ($record->status === 'approved') $status .= "\n".($record->approval_date?->format('d-M-Y') ?? '—');
            $status .= "\nNo.: ".($record->reference_number ?: '—');
            $rows[] = ['cells' => [
                $this->cell($index + 1, 3), $this->cell('', 3),
                $this->cell('Stu. ID: '.($s['student_id'] ?? '—')."\n".($s['name_kh'] ?? '—')."\n".($s['name_en'] ?? '—')."\nDOB: ".$row['dob'], 4),
                $this->cell(($s['academic_year'] ?? '—')."\n".($s['campus_en'] ?? '—').'-'.$row['source_grade'], 3),
                $this->cell(($s['target_academic_year'] ?? $s['academic_year'] ?? '—')."\n".($s['campus_en'] ?? '—').'-'.$row['target_grade'], 3),
                $this->cell(($record->parent_name ?: '—')."\n".($record->parent_phone ?: '—')."\n".($record->application_date?->format('d-M-Y') ?? '—'), 3),
                $this->cell($status, 3),
            ], 'height' => 72];
            if ($data['photoUrls'][$record->id] ?? null) $images[] = ['src' => $data['photoUrls'][$record->id], 'row' => count($rows) - 1, 'column' => 1, 'width' => 48, 'height' => 64];
        }
        $rows[] = ['cells' => [null, null, null, null, null, $this->cell("Registrar's Office", 5)], 'height' => 25];
        $merges[] = 'F'.count($rows).':G'.count($rows);
        $rows[] = ['cells' => [null, null, null, null, null, $this->cell($data['reportDate']->format('F j, Y'), 6)], 'height' => 22];
        $merges[] = 'F'.count($rows).':G'.count($rows);
        return [$rows, $merges, [5, 10, 34, 23, 23, 27, 25], $images];
    }

    private function statisticsSheet(array $data): array
    {
        $stats = $data['statistics'];
        $count = count($stats['columns']) * 2 + 4;
        $last = $this->column($count);
        $rows = [
            ['cells' => [], 'height' => $data['reportLogo'] ? 80 : 8],
            ['cells' => [$this->cell('Statistics of Students Skipping Grades', 1)], 'height' => 30],
            ['cells' => [$this->cell($data['statisticsYearSubtitle'], 6)], 'height' => 24],
            ['cells' => [$this->cell($this->filterSummary($data), 0)], 'height' => 32],
            ['cells' => [], 'height' => 8],
        ];
        $merges = ['A2:'.$last.'2', 'A3:'.$last.'3', 'A4:'.$last.'4', 'A6:B6'];
        $top = [$this->cell('Campus', 7), $this->cell('', 7)];
        $bottom = [$this->cell('No.', 7), $this->cell('Campus', 7)];
        foreach ($stats['groups'] as $group => $columns) foreach ($columns as $label) {
            $style = $this->groupStyle($group);
            $column = count($top) + 1;
            $header = $stats['headers'][$label];
            $heading = $header['source'].($header['target'] ? '→'.$header['target'] : '');
            $top[] = $this->cell($heading, $style); $top[] = $this->cell('', $style);
            $bottom[] = $this->cell('Stu.', $style); $bottom[] = $this->cell('F', $style);
            $merges[] = $this->column($column).'6:'.$this->column($column + 1).'6';
        }
        $merges[] = $this->column($count - 1).'6:'.$last.'6';
        $rows[] = ['cells' => [...$top, $this->cell('Total', 7), $this->cell('', 7)], 'height' => 25];
        $rows[] = ['cells' => [...$bottom, $this->cell('Stu.', 7), $this->cell('F', 7)], 'height' => 22];
        foreach ($stats['rows'] as $index => $row) {
            $cells = [$this->cell($index + 1, 3), $this->cell($row['campus'], 3)];
            foreach ($stats['columns'] as $column) { $cells[] = $this->cell($row['cells'][$column]['total'] ?: '', 3); $cells[] = $this->cell($row['cells'][$column]['female'] ?: '', 3); }
            $rows[] = ['cells' => [...$cells, $this->cell($row['total'], 3), $this->cell($row['female'], 3)], 'height' => 22];
        }
        $totalRow = count($rows) + 1;
        $cells = [$this->cell('Grand Total', 7), $this->cell('', 7)];
        foreach ($stats['groups'] as $group => $columns) foreach ($columns as $column) { $cells[] = $this->cell($stats['totals'][$column]['total'], $this->groupStyle($group)); $cells[] = $this->cell($stats['totals'][$column]['female'], $this->groupStyle($group)); }
        $rows[] = ['cells' => [...$cells, $this->cell($stats['total'], 7), $this->cell($stats['female'], 7)], 'height' => 23];
        $merges[] = 'A'.$totalRow.':B'.($totalRow + 1);
        $merges[] = $this->column($count - 1).$totalRow.':'.$this->column($count - 1).($totalRow + 1);
        $merges[] = $last.$totalRow.':'.$last.($totalRow + 1);
        $subtotals = array_fill(0, $count, null);
        $start = 3;
        foreach ($stats['groupTotals'] as $group => $total) {
            $half = $total['span'] / 2;
            foreach (['total' => 'Total: ', 'female' => 'Female: '] as $key => $label) {
                $subtotals[$start - 1] = $this->cell($label.$total[$key], $this->groupStyle($group));
                if ($half > 1) $merges[] = $this->column($start).($totalRow + 1).':'.$this->column($start + $half - 1).($totalRow + 1);
                $start += $half;
            }
        }
        $rows[] = ['cells' => $subtotals, 'height' => 24];
        $rows[] = ['cells' => [], 'height' => 8];
        $officeRow = count($rows) + 1;
        $office = array_fill(0, $count, null); $office[0] = $this->cell("Registrar's Office", 5); $office[8] = $this->cell('Notes', 2);
        $rows[] = ['cells' => $office, 'height' => 25];
        $merges[] = 'A'.$officeRow.':H'.$officeRow; $merges[] = 'I'.$officeRow.':'.$last.$officeRow;
        $date = array_fill(0, $count, null); $date[0] = $this->cell($data['reportDate']->format('F j, Y'), 6); $date[8] = $this->cell($stats['notes']->map(fn($note) => $note['source'].' skipped to '.$note['target'])->implode(' | '), 0);
        $rows[] = ['cells' => $date, 'height' => max(32, ceil($stats['notes']->count() / 5) * 16)];
        $merges[] = 'A'.count($rows).':H'.count($rows); $merges[] = 'I'.count($rows).':'.$last.count($rows);
        $rows[] = ['cells' => [$this->cell('Stu. = total students; F = female students. Each student is counted once per academic year, using the latest matching request.', 0)], 'height' => 24];
        $merges[] = 'A'.count($rows).':'.$last.count($rows);
        $images = $data['reportLogo'] ? [['src' => $data['reportLogo'], 'row' => 0, 'column' => 0, 'width' => 245, 'height' => 100]] : [];
        return [$rows, $merges, [5, 10, ...array_fill(0, $count - 4, 4.5), 7, 7], $images];
    }

    private function filterSummary(array $data): string
    {
        $parts = ['Campus: '.($data['reportCampus']?->campus_name_en ?? 'All Campuses'), 'Status: '.$this->statusLabel($data['filters']['status'] ?? '')];
        if (filled($data['filters']['grade_class'] ?? null)) $parts[] = 'Current Grade: '.($data['gradeOptions']->firstWhere('value', $data['filters']['grade_class'])['label'] ?? '—');
        if (filled($data['filters']['approval_number'] ?? null)) $parts[] = 'Approved Number: '.$data['filters']['approval_number'];
        if (filled($data['filters']['search'] ?? null)) $parts[] = 'Search: '.$data['filters']['search'];
        return implode(' | ', $parts);
    }

    private function statusLabel(string $status): string { return ['draft' => 'Draft', 'pending' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected'][$status] ?? 'All Statuses'; }
    private function groupStyle(string $group): int { return ['early' => 8, 'primary' => 9, 'secondary' => 10][$group] ?? 2; }
    private function cell(mixed $value, int $style): array { return compact('value', 'style'); }

    private function workbook(string $kind, array $rows, array $merges, array $widths, array $images): string
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
        $worksheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:'.$last.count($rows).'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="'.($kind === 'statistics' ? 7 : 6).'" topLeftCell="A'.($kind === 'statistics' ? 8 : 7).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>'.$cols.'</cols><sheetData>'.$sheet.'</sheetData><mergeCells count="'.count($merges).'">'.$mergeXml.'</mergeCells><pageMargins left="0.39" right="0.39" top="0.39" bottom="0.39" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="'.($kind === 'statistics' ? 'landscape' : 'portrait').'" fitToWidth="1" fitToHeight="'.($kind === 'statistics' ? 1 : 0).'"/>'.($images ? '<drawing r:id="rId1"/>' : '').'</worksheet>';
        $relsNs = 'http://schemas.openxmlformats.org/package/2006/relationships';
        $officeNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $entries = [
            '_rels/.rels' => '<?xml version="1.0"?><Relationships xmlns="'.$relsNs.'"><Relationship Id="rId1" Type="'.$officeNs.'/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="'.$officeNs.'"><sheets><sheet name="'.($kind === 'statistics' ? 'Grade Skipping Statistics' : 'Grade Skipping List').'" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm.Print_Area" localSheetId="0">\''.($kind === 'statistics' ? 'Grade Skipping Statistics' : 'Grade Skipping List').'\'!$A$1:$'.$last.'$'.count($rows).'</definedName><definedName name="_xlnm.Print_Titles" localSheetId="0">\''.($kind === 'statistics' ? 'Grade Skipping Statistics' : 'Grade Skipping List').'\'!$'.($kind === 'statistics' ? '6:$7' : '6:$6').'</definedName></definedNames></workbook>',
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
                $size = getimagesizefromstring($bytes);
                if (!$size) throw new \RuntimeException('Invalid report image.');
                // Normalize uploaded formats to PNG so Excel can display WebP/GIF/JPEG photos too.
                $resource = imagecreatefromstring($bytes);
                if (!$resource) throw new \RuntimeException('Unable to decode report image.');
                ob_start(); imagepng($resource); $png = ob_get_clean(); imagedestroy($resource);
                $id = $index + 1; $imageTypes['png'] = 'image/png';
                $entries['xl/media/image'.$id.'.png'] = $png;
                $scale = min($image['width'] / $size[0], $image['height'] / $size[1]);
                $cx = (int)round($size[0] * $scale * 9525); $cy = (int)round($size[1] * $scale * 9525);
                $drawing .= '<xdr:oneCellAnchor><xdr:from><xdr:col>'.$image['column'].'</xdr:col><xdr:colOff>38100</xdr:colOff><xdr:row>'.$image['row'].'</xdr:row><xdr:rowOff>38100</xdr:rowOff></xdr:from><xdr:ext cx="'.$cx.'" cy="'.$cy.'"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="'.$id.'" name="Report image '.$id.'"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr><xdr:blipFill><a:blip r:embed="rId'.$id.'"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
                $drawingRels .= '<Relationship Id="rId'.$id.'" Type="'.$officeNs.'/image" Target="../media/image'.$id.'.png"/>';
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
        $path = tempnam(sys_get_temp_dir(), 'skipping-xlsx-');
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
        $fonts = '<font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="18"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><sz val="11"/><name val="Khmer OS Siemreap"/></font><font><b/><sz val="14"/><name val="Calibri"/></font><font><sz val="12"/><name val="Calibri"/></font>';
        $fills = '<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>';
        foreach (['EDF1F5', 'D0E9ED', 'DEDCE7', 'E2EFD9', 'FBE5D5'] as $color) $fills .= '<fill><patternFill patternType="solid"><fgColor rgb="FF'.$color.'"/><bgColor indexed="64"/></patternFill></fill>';
        $xfs = '';
        foreach ([[0,0,0,'center'],[1,0,0,'center'],[2,2,1,'center'],[0,0,1,'center'],[3,0,1,'left'],[4,0,0,'center'],[5,0,0,'center'],[2,3,1,'center'],[2,4,1,'center'],[2,5,1,'center'],[2,6,1,'center']] as [$font,$fill,$border,$align]) $xfs .= '<xf numFmtId="0" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="'.$align.'" vertical="center" wrapText="1"/></xf>';
        return '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="6">'.$fonts.'</fonts><fills count="7">'.$fills.'</fills><borders count="2"><border/><border><left style="thin"><color rgb="FF8B929A"/></left><right style="thin"><color rgb="FF8B929A"/></right><top style="thin"><color rgb="FF8B929A"/></top><bottom style="thin"><color rgb="FF8B929A"/></bottom></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="11">'.$xfs.'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function xml(string $text): string { return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text), ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
    private function column(int $index): string { $name = ''; while ($index > 0) { $index--; $name = chr(65 + $index % 26).$name; $index = intdiv($index, 26); } return $name; }
}
