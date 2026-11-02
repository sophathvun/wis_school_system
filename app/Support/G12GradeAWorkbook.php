<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

class G12GradeAWorkbook
{
    public function generate(array $report, array $filters): string
    {
        $isResult = $report['type']==='result-list';
        $escape = fn ($value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $rows = [];
        $merges = [];
        $add = function (array $cells, float $height = 25) use (&$rows, $escape): int {
            $number = count($rows) + 1;
            $xml = '<row r="'.$number.'" ht="'.$height.'" customHeight="1">';
            foreach ($cells as $column => [$value, $style]) {
                $xml .= '<c r="'.$column.$number.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$escape($value).'</t></is></c>';
            }
            $rows[] = $xml.'</row>';
            return $number;
        };
        $titles = $isResult ? [
            ['បញ្ជីលទ្ធផលប្រឡងសញ្ញាបត្រមធ្យមសិក្សាទុតិយភូមិ',2,30],
            [$report['exam_session'] ? 'សម័យប្រឡង៖ '.$report['exam_session'] : 'ឆ្នាំសិក្សា/Academic Year : '.($filters['academic_year'] ?? 'All'),2,28],
            ['',0,8],
        ] : [
            ['បញ្ជីឈ្មោះសិស្សនិទ្ទេស A ប្រឡងសញ្ញាបត្រមធ្យមសិក្សាទុតិយភូមិ',2,30],
            [$report['type']==='grade-a-campus' ? 'List of Grade A Students for Grade 12 National Exams' : 'List of Students Who Achieved Grade A in the Upper Secondary School Diploma Examination',3,36],
            ['ឆ្នាំសិក្សា/Academic Year : '.($filters['academic_year'] ?? ($report['rows'][0]['academic_year'] ?? 'All')),2,28],
        ];
        foreach ([
            ['ក្រសួងអប់រំ យុវជន និងកីឡា', 'ព្រះរាជាណាចក្រកម្ពុជា'],
            ['មន្ទីរអប់រំ យុវជន និងកីឡារាជធានីភ្នំពេញ', 'ជាតិ សាសនា ព្រះមហាក្សត្រ'],
            ['សាលាវេស្ទើនអន្តរជាតិ', '──────── ◇ ────────'],
        ] as [$left, $right]) {
            $row = $add(['A'=>[$left,1], 'F'=>[$right,2]], 28);
            $merges[] = 'A'.$row.':E'.$row;
            $merges[] = 'F'.$row.':I'.$row;
        }
        $add([], 8);
        foreach ($titles as [$text,$style,$height]) {
            $row = $add(['A'=>[$text,$style]],$height);
            $merges[] = 'A'.$row.':I'.$row;
        }
        $columns = range('A','I');
        $labels = ['No.','Cert. Number','Name in English','Name in Khmer','Gender','Student ID','Grade','Letter Grade','Phone'];
        if ($isResult) $labels=['លរ','គោត្តនាម និងនាមខ្លួន','ភេទ','ថ្ងៃខែឆ្នាំកំណើត','មណ្ឌលប្រឡង','ល.បន្ទប់','លេខតុ','និទ្ទេស','ថ្នាក់ជម្រើស'];
        $add(array_combine($columns,array_map(fn ($label) => [$label,4],$labels)),32);
        $groups = in_array($report['type'],['grade-a-campus','result-list'],true) ? $report['groups'] : [''=>$report['rows']];
        foreach ($groups as $campus=>$students) {
            if (in_array($report['type'],['grade-a-campus','result-list'],true)) {
                $cells = array_fill_keys($columns, ['',11]);
                $cells['A'] = [$campus.($isResult ? '' : ' Campus'),11];
                $row = $add($cells,28);
                $merges[] = 'A'.$row.':I'.$row;
            }
            foreach ($students as $index=>$student) {
            $number = $report['type']==='grade-a-campus' ? $index+1 : $student['number'];
            $values = [$number,$student['certificate_number'],$student['name_en'],$student['name_kh'],$student['gender'],$student['student_code'],$student['grade']."\n".$student['campus'],'A'.($student['score_rank']!==''?"\n".$student['score_rank']:''),$student['phone']];
            if ($isResult) $values=[$index+1,$student['name_kh'],$student['gender'],$student['dob_display'],$student['center'],$student['room'],$student['seat'],$student['letter_grade'],$student['selected']==='Social Science'?'វិទ្យាសាស្ត្រសង្គម':'វិទ្យាសាស្ត្រ'];
            $cells = [];
            foreach ($values as $i=>$value) $cells[$columns[$i]] = [$value,$isResult ? (in_array($i,[1,4],true)?7:8) : match($i){2=>6,3=>7,4=>8,default=>5}];
            $add($cells,32);
            }
        }
        if (!$report['rows']) {
            $row=$add(['A'=>['No Grade A students match the selected filters.',5]]);
            $merges[]='A'.$row.':I'.$row;
        }
        foreach ($isResult ? [] : [
            ['ចំនួនសិស្សសរុប៖ '.$report['summary']['total'].'នាក់ / ស្រី '.$report['summary']['female'].'នាក់',9],
            ["Registrar's Office",10],
            [now('Asia/Bangkok')->format('F d, Y'),0],
        ] as [$text,$style]) {
            $row=$add(['A'=>[$text,$style]],24);
            $merges[]='A'.$row.':I'.$row;
        }
        $last=count($rows);
        $sheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="8" topLeftCell="A9" state="frozen"/></sheetView></sheetViews><cols>';
        foreach (($isResult ? [7,28,7,19,34,10,9,10,24] : [6,15,27,27,8,16,8,11,17]) as $index=>$width) $sheet.='<col min="'.($index+1).'" max="'.($index+1).'" width="'.$width.'" customWidth="1"/>';
        $sheet.='</cols><sheetData>'.implode('',$rows).'</sheetData><mergeCells count="'.count($merges).'">';
        foreach ($merges as $merge) $sheet.='<mergeCell ref="'.$merge.'"/>';
        $sheet.='</mergeCells><printOptions horizontalCentered="1"/><pageMargins left="0.2756" right="0.2756" top="0.3937" bottom="0.3937" header="0" footer="0.15"/><pageSetup paperSize="9" orientation="portrait" fitToWidth="1" fitToHeight="0"/></worksheet>';

        $path=tempnam(sys_get_temp_dir(),'g12-grade-a-');
        if ($path===false) throw new RuntimeException('Unable to prepare the Excel report.');
        try {
            file_put_contents($path,app(ScholarshipExport::class)->xlsx([]));
            $zip=new ZipArchive;
            if ($zip->open($path)!==true) throw new RuntimeException('Unable to format the Excel report.');
            $types=$zip->getFromName('[Content_Types].xml');
            $zip->addFromString('[Content_Types].xml',str_replace('</Types>','<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',$types));
            $rels=$zip->getFromName('xl/_rels/workbook.xml.rels');
            $zip->addFromString('xl/_rels/workbook.xml.rels',str_replace('</Relationships>','<Relationship Id="styles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',$rels));
            $zip->addFromString('xl/workbook.xml','<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Grade A Students" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm.Print_Area" localSheetId="0">\'Grade A Students\'!$A$1:$I$'.$last.'</definedName><definedName name="_xlnm.Print_Titles" localSheetId="0">\'Grade A Students\'!$8:$8</definedName></definedNames></workbook>');
            $zip->addFromString('xl/worksheets/sheet1.xml',$sheet);
            $zip->addFromString('xl/styles.xml',$this->styles($isResult));
            $zip->close();
            return file_get_contents($path);
        } finally {
            if (is_file($path)) unlink($path);
        }
    }

    private function styles(bool $isResult = false): string
    {
        $xml='<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="5">';
        foreach ([['Arial',$isResult?11:9],['Khmer OS Muol Light',12],['Arial',12],['Khmer OS Siemreap',$isResult?11:9],['Arial',11]] as [$name,$size]) $xml.='<font><sz val="'.$size.'"/><name val="'.$name.'"/></font>';
        $xml.='</fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="12">';
        foreach ([[0,0,0,'left'],[1,0,0,'left'],[1,0,0,'center'],[2,0,0,'center'],[0,2,1,'center'],[0,0,1,'center'],[0,0,1,'left'],[3,0,1,'left'],[3,0,1,'center'],[3,0,0,'left'],[4,0,0,'left'],[0,3,1,'left']] as [$font,$fill,$border,$align]) $xml.='<xf numFmtId="0" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="'.$align.'" vertical="center" wrapText="1"/></xf>';
        if ($isResult) {
            $xml=str_replace('fontId="0" fillId="2"','fontId="3" fillId="2"',$xml);
            $xml=str_replace('fontId="0" fillId="3"','fontId="1" fillId="3"',$xml);
        }
        return $xml.'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
