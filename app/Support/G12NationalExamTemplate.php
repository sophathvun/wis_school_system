<?php

namespace App\Support;

use ZipArchive;

class G12NationalExamTemplate
{
    public function headers(): array
    {
        return array_values(array_unique(array_merge(array_keys(G12NationalExamFields::labels()), [
            'received_soft_certificate', 'received_soft_cert_date', 'received_soft_cert_by',
            'received_hard_certificate', 'received_hard_cert_date', 'received_hard_cert_by',
        ], array_values(G12NationalExamFields::DOCUMENT_FIELDS))));
    }

    public function generate(string $campus, array $centers): string
    {
        $headers = $this->headers();
        $centers = $centers ?: [['source_id' => '001', 'name' => 'SAMPLE EXAM CENTER']];
        $sample = [
            'student_id' => 'SAMPLE-001', 'academic_year' => '2025-2026',
            'student_name_en' => 'SAMPLE STUDENT - REPLACE THIS ROW', 'gender' => 'Female',
            'dob' => '2008-01-15', 'nationality' => 'Khmer', 'grade' => '12A',
            'school_name' => 'Western International School', 'campus' => $campus ?: 'BCH',
            'phone_number' => '012345678', 'class_sellected' => 'Science', 'language_exam' => 'English',
            'g12_exam_date' => '2026-08-17', 'g12_exam_center' => $centers[0]['name'],
            'g12_exam_room' => '01', 'g12_exam_seat' => '005', 'g12_letter_grade' => 'A',
            'lettergrade_khmer' => 'B', 'lettergrade_math' => 'A', 'lettergrade_biology' => 'B',
            'lettergrade_history' => 'B', 'lettergrade_chemistry' => 'A', 'lettergrade_physics' => 'A',
            'lettergrade_english' => 'A', 'photo_filename' => '120804049559121000001.jpg',
            'received_soft_certificate' => 'Not Yet', 'received_hard_certificate' => 'Not Yet',
            'remarks' => 'Sample only. Replace with the actual student information before importing.',
        ];
        $instructions = [
            ['Topic / Field', 'Instructions'],
            ['Before importing', 'Replace the SAMPLE student row with real data. Keep the first two sheets and their header rows. Do not rename column headers.'],
            ['Sheet 1: g12_national_exam', 'One row per student and academic year. Add more student rows below the headers. Blank optional fields are allowed.'],
            ['Required fields', 'student_id, academic_year, student_name_en, campus'],
            ['Student ID', 'Keep IDs as text, including leading zeros. Student ID + Academic Year identifies an existing record when reimporting.'],
            ['Academic year', 'Use consecutive years in YYYY-YYYY format, for example 2025-2026. Replace the sample year and dates.'],
            ['Dates', 'Use YYYY-MM-DD, for example 2008-01-15. Normal Excel dates are also accepted for birth and certificate collection dates.'],
            ['Campus', 'Use the campus name/code from the system. Unknown names must be mapped to an authorized campus in the import preview.'],
            ['Sheet 2: Assign_exam_center', 'Use excenter_id and exam_center. Existing active centers are included when available; otherwise replace the SAMPLE center. Add missing centers here if needed.'],
            ['g12_exam_center', 'Match an exam_center name on Sheet 2 or an existing system center. Blank is allowed if not yet assigned. Creating a missing center requires Manage Exam Centers permission.'],
            ['Certificate status', 'Use Not Yet for not collected, Withdrew for already collected, or Failed for not applicable. Other values need review.'],
            ['Certificate collection', 'For an already collected certificate, enter the collection date and recipient. Import preserves collections already managed by staff.'],
            ['Portraits and documents', 'Enter original filenames only. The workbook does not upload images. Use Bulk Student Photos or the student document upload screen afterwards.'],
            ['Scores and letter grades', 'Enter actual MoEYS values. Overall grades A-E mean passed, F means failed. Leave values blank if results are not available.'],
            ['Import preview', 'Upload this workbook in Excel Imports / History, resolve mappings and review all rows before confirming. Use values, not formulas.'],
        ];
        $labels = G12NationalExamFields::labels();
        foreach ($headers as $header) $instructions[] = [$header, $labels[$header] ?? ucwords(str_replace('_', ' ', $header))];
        $sheets = [
            'g12_national_exam' => [$headers, array_map(fn ($key) => $sample[$key] ?? '', $headers)],
            'Assign_exam_center' => array_merge([['excenter_id', 'exam_center']], array_map(fn ($c) => [(string) ($c['source_id'] ?? ''), $c['name']], $centers)),
            'Instructions' => $instructions,
        ];

        $path = tempnam(sys_get_temp_dir(), 'g12-template-');
        if ($path === false) throw new \RuntimeException('Unable to prepare the Excel template.');
        try {
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('Unable to create the Excel template.');
            $types = '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
            $workbook = $relations = '';
            $id = 0;
            foreach ($sheets as $name => $rows) {
                $id++;
                $types .= '<Override PartName="/xl/worksheets/sheet'.$id.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
                $workbook .= '<sheet name="'.$name.'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
                $relations .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';
                $zip->addFromString('xl/worksheets/sheet'.$id.'.xml', $this->sheet($rows, $name === 'Instructions'));
            }
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.$types.'</Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$workbook.'</sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relations.'<Relationship Id="styles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml', '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF2D3B58"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="3"><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="49" fontId="1" fillId="2" borderId="0" xfId="0" applyNumberFormat="1" applyFill="1" applyFont="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
            if (!$zip->close()) throw new \RuntimeException('Unable to finish the Excel template.');
            return file_get_contents($path);
        } finally {
            if (is_file($path)) unlink($path);
        }
    }

    private function column(int $index): string
    {
        $name = '';
        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) $name = chr(65 + (($index - 1) % 26)).$name;
        return $name;
    }

    private function sheet(array $rows, bool $instructions): string
    {
        $last = $this->column(count($rows[0]) - 1).count($rows);
        $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:'.$last.'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>';
        foreach ($rows[0] as $i => $header) {
            $width = $instructions ? ($i === 0 ? 38 : 105) : min(40, max(22, strlen($header) + 2));
            $xml .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$width.'" style="0" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';
        foreach ($rows as $r => $row) {
            $xml .= '<row r="'.($r + 1).'" ht="'.($r === 0 ? 34 : ($instructions ? 48 : 44)).'" customHeight="1">';
            foreach ($row as $c => $value) $xml .= '<c r="'.$this->column($c).($r + 1).'" s="'.($r === 0 ? 1 : 0).'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';
        if (!$instructions) $xml .= '<autoFilter ref="A1:'.$last.'"/>';
        return $xml.'</worksheet>';
    }
}
