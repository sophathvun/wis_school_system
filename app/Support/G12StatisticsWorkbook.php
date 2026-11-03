<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

class G12StatisticsWorkbook
{
    public function generate(array $report, array $filters): string
    {
        $all=$report['type']==='statistics-all';
        $grades=$all?['A','B','C','D','E','F']:['A','B','C','D','E'];
        $width=($all?8:10)+count($grades)*2;
        $column=fn($index)=>chr(65+$index);
        $last=$column($width-1);
        $escape=fn($value)=>htmlspecialchars((string)$value,ENT_XML1|ENT_QUOTES,'UTF-8');
        $rows=[];$merges=[];
        $add=function(array $values,int $style=0,int $height=30)use(&$rows,$escape,$column){
            $number=count($rows)+1;$xml='<row r="'.$number.'" ht="'.$height.'" customHeight="1">';
            foreach($values as $i=>$value){
                [$text,$cellStyle]=is_array($value)?$value:[$value,$style];
                $xml.='<c r="'.$column($i).$number.'" s="'.$cellStyle.'" t="inlineStr"><is><t xml:space="preserve">'.$escape($text).'</t></is></c>';
            }
            $rows[]=$xml.'</row>';return $number;
        };
        foreach([
            ['ក្រសួងអប់រំ យុវជន និងកីឡា','ព្រះរាជាណាចក្រកម្ពុជា'],
            ['មន្ទីរអប់រំ យុវជន និងកីឡារាជធានីភ្នំពេញ','ជាតិ សាសនា ព្រះមហាក្សត្រ'],
            [$report['selectedSchoolName']??'វិ. សាលាវេស្ទើនអន្តរជាតិ','──── ◇ ────'],
        ] as [$left,$right]){
            $r=$add([0=>[$left,1],10=>[$right,1]],1,28);
            $merges[]='A'.$r.':J'.$r;$merges[]='K'.$r.':'.$last.$r;
        }
        $title=$all?'លទ្ធផលប្រឡងសញ្ញាបត្រមធ្យមសិក្សាទុតិយភូមិចំណេះទូទៅ និងបំពេញវិជ្ជា (ថ្នាក់ទី១២)':'ស្ថិតិលទ្ធផលប្រឡងសញ្ញាបត្រមធ្យមសិក្សាទុតិយភូមិ';
        foreach([$title,$report['exam_session']?'សម័យប្រឡង៖ '.$report['exam_session']:'ឆ្នាំសិក្សា៖ '.($filters['academic_year']??'All')] as $text){$r=$add([$text],1,30);$merges[]='A'.$r.':'.$last.$r;}
        $headers=[0=>['ល.រ',2],1=>[$all?'បេក្ខជនប្រឡង':'ថ្នាក់',2]];
        $metrics=['បេក្ខជនចុះឈ្មោះ','បេក្ខជនមកប្រឡង','បេក្ខជនប្រឡងជាប់'];
        if(!$all)$metrics[]='% បេក្ខជនប្រឡងជាប់';
        foreach($metrics as $i=>$label)$headers[2+$i*2]=[$label,$all&&$i===2?9:2];
        $gradeStart=2+count($metrics)*2;
        $headers[$gradeStart]=['និទ្ទេស',3];
        $add($headers,2,28);
        $merges[]='A6:A8';$merges[]='B6:B8';
        foreach($metrics as $i=>$label)$merges[]=$column(2+$i*2).'6:'.$column(3+$i*2).'7';
        $merges[]=$column($gradeStart).'6:'.$last.'6';
        $gradeHeaders=[];
        foreach($grades as $i=>$grade){$c=$gradeStart+$i*2;$gradeHeaders[$c]=[$grade,$grade==='F'?4:3];$merges[]=$column($c).'7:'.$column($c+1).'7';}
        $add($gradeHeaders,3,22);
        $subheaders=[];for($i=2;$i<$width;$i++)$subheaders[$i]=[$i%2===0?'សរុប':'ស្រី',$i>=$gradeStart?($i>=$width-2&&$all?4:3):($all&&in_array($i,[6,7],true)?9:2)];
        $add($subheaders,2,24);
        $labels=['Science - English'=>'ប្រឡងជាភាសាអង់គ្លេស (វិទ្យាសាស្ត្រ)','Science - Khmer'=>'ប្រឡងជាភាសាខ្មែរ (វិទ្យាសាស្ត្រ)','Social Science - English'=>'ប្រឡងជាភាសាអង់គ្លេស (វិទ្យាសាស្ត្រសង្គម)','Social Science - Khmer'=>'ប្រឡងជាភាសាខ្មែរ (វិទ្យាសាស្ត្រសង្គម)'];
        $percent=fn($n,$d)=>number_format($d?$n*100/$d:0,2).'%';$campus=null;$number=0;
        foreach($report['statistics']['rows'] as $stat){
            if(!$stat['total']&&array_sum($stat['registered'])===0)continue;
            if($all&&$stat['label']!=='Grand Total'&&$campus!==$stat['campus']){
                $campus=$stat['campus'];$number=0;$r=$add([$campus.' Campus'],5,24);$merges[]='A'.$r.':'.$last.$r;
            }
            $style=$stat['total']?($stat['label']==='Grand Total'?7:6):0;
            $values=[$stat['total']?'':++$number,$stat['total']?($stat['label']==='Grand Total'?'សរុបរួម':'សរុប'):$labels[$stat['label']]];
            foreach(['registered','examined','passed'] as $metric){
                $total=array_sum($stat[$metric]);$female=$stat[$metric]['female'];$base=$metric==='passed'?'examined':'registered';
                $metricStyle=$all&&$metric==='passed'&&$stat['label']==='Grand Total'?9:$style;
                $values[]=[$total.($all&&$metric!=='registered'?"\n".$percent($total,array_sum($stat[$base])):''),$metricStyle];
                $values[]=[$female.($all&&$metric!=='registered'?"\n".$percent($female,$stat[$base]['female']):''),$metricStyle];
            }
            if(!$all){$values[]=$percent(array_sum($stat['passed']),array_sum($stat['examined']));$values[]=$percent($stat['passed']['female'],$stat['examined']['female']);}
            foreach($grades as $grade){
                $s=$grade==='F'?8:$style;
                $values[]=[array_sum($stat['grades'][$grade]).($all?"\n".$percent(array_sum($stat['grades'][$grade]),array_sum($stat['examined'])):''),$s];
                $values[]=[$stat['grades'][$grade]['female'].($all?"\n".$percent($stat['grades'][$grade]['female'],$stat['examined']['female']):''),$s];
            }
            $add($values,$style,$all?40:28);
        }
        foreach($all?['ការិយាល័យសិក្សា',($report['reportDate']??now('Asia/Bangkok'))->format('F d, Y')]:[$report['khmerLunarDate']??'',$report['khmerSolarDate']??'','នាយកសាលា'] as $text){$r=$add([$text],1,28);$merges[]='A'.$r.':'.$last.$r;}
        $sheet='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><sheetViews><sheetView workbookViewId="0" showGridLines="0"/></sheetViews><cols><col min="1" max="1" width="6" customWidth="1"/><col min="2" max="2" width="35" customWidth="1"/><col min="3" max="'.$width.'" width="10" customWidth="1"/></cols><sheetData>'.implode('',$rows).'</sheetData><mergeCells count="'.count($merges).'">';
        foreach($merges as $merge)$sheet.='<mergeCell ref="'.$merge.'"/>';
        $sheet.='</mergeCells><pageMargins left="0.28" right="0.28" top="0.3" bottom="0.3" header="0" footer="0"/><pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="1"/></worksheet>';
        $styles='<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Khmer OS Siemreap"/></font><font><sz val="12"/><name val="Khmer OS Muol Light"/></font><font><sz val="11"/><color rgb="FFB71926"/><name val="Khmer OS Siemreap"/></font></fonts><fills count="8"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>';
        foreach(['DBE5F3','C6E0B4','FCE4D6','FFF2CC','F2F2F2','8FAADC'] as $color)$styles.='<fill><patternFill patternType="solid"><fgColor rgb="FF'.$color.'"/></patternFill></fill>';
        $styles.='</fills><borders count="2"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="10">';
        foreach([[0,0,1],[1,0,0],[0,2,1],[0,3,1],[2,4,1],[0,0,1],[0,5,1],[0,2,1],[2,0,1],[0,7,1]] as [$font,$fill,$border])$styles.='<xf fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" numFmtId="0" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>';
        $styles.='</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
        $path=tempnam(sys_get_temp_dir(),'g12-stat-');if($path===false)throw new RuntimeException('Unable to prepare workbook.');
        try{
            file_put_contents($path,app(ScholarshipExport::class)->xlsx([]));$zip=new ZipArchive;
            if($zip->open($path)!==true)throw new RuntimeException('Unable to create workbook.');
            $zip->addFromString('[Content_Types].xml',str_replace('</Types>','<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',$zip->getFromName('[Content_Types].xml')));
            $zip->addFromString('xl/_rels/workbook.xml.rels',str_replace('</Relationships>','<Relationship Id="styles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',$zip->getFromName('xl/_rels/workbook.xml.rels')));
            $zip->addFromString('xl/workbook.xml','<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Statistics" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm.Print_Area" localSheetId="0">Statistics!$A$1:$'.$last.'$'.count($rows).'</definedName><definedName name="_xlnm.Print_Titles" localSheetId="0">Statistics!$6:$8</definedName></definedNames></workbook>');
            $zip->addFromString('xl/worksheets/sheet1.xml',$sheet);$zip->addFromString('xl/styles.xml',$styles);$zip->close();return file_get_contents($path);
        }finally{if(is_file($path))unlink($path);}
    }
}
