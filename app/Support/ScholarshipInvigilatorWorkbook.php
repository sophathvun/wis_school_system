<?php

namespace App\Support;

use DOMDocument;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/** Two-sheet, literal-text XLSX template and bounded reader; no office installation required. */
class ScholarshipInvigilatorWorkbook
{
    public const HEADERS=[
        'Invigilators'=>['invigilator_code','staff_id','staff_name_english','gender','campus','position','phone','email','organization','notes'],
        'Room Assignments'=>['invigilator_code','exam_center','session','room'],
    ];
    private const NS='http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const REL='http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private function xml(string $text): string{return htmlspecialchars($text,ENT_XML1|ENT_QUOTES,'UTF-8');}
    private function fail(string $message): never{throw ValidationException::withMessages(['file'=>$message]);}

    public function write(array $book): string
    {
        $entries=[];$sheets='';$rels='';$types='';
        foreach(self::HEADERS as $name=>$headers){
            $number=count($entries)+1;$rows=[array_combine($headers,$headers),...($book[$name]??[])];$xml='';
            foreach($rows as $r=>$row){
                $xml.='<row r="'.($r+1).'">';
                foreach($headers as $c=>$header)$xml.='<c r="'.chr(65+$c).($r+1).'" s="'.($r===0?1:0).'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml((string)($row[$header]??'')).'</t></is></c>';
                $xml.='</row>';
            }
            $last=count($rows);$end=chr(64+count($headers));
            $entries['xl/worksheets/sheet'.$number.'.xml']='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="'.self::NS.'"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="'.count($headers).'" width="24" customWidth="1"/></cols><sheetData>'.$xml.'</sheetData><autoFilter ref="A1:'.$end.$last.'"/></worksheet>';
            $sheets.='<sheet name="'.$name.'" sheetId="'.$number.'" r:id="rId'.$number.'"/>';
            $rels.='<Relationship Id="rId'.$number.'" Type="'.self::REL.'/worksheet" Target="worksheets/sheet'.$number.'.xml"/>';
            $types.='<Override PartName="/xl/worksheets/sheet'.$number.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $entries['xl/styles.xml']='<?xml version="1.0"?><styleSheet xmlns="'.self::NS.'"><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="11"/><name val="Arial"/><color rgb="FFFFFFFF"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF293959"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="49" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
        $entries['xl/workbook.xml']='<?xml version="1.0"?><workbook xmlns="'.self::NS.'" xmlns:r="'.self::REL.'"><sheets>'.$sheets.'</sheets></workbook>';
        $entries['xl/_rels/workbook.xml.rels']='<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'<Relationship Id="rId3" Type="'.self::REL.'/styles" Target="styles.xml"/></Relationships>';
        $entries['_rels/.rels']='<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="'.self::REL.'/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        $entries['[Content_Types].xml']='<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$types.'</Types>';
        $path=tempnam(sys_get_temp_dir(),'invigilators-');
        try{
            $zip=new ZipArchive();if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)$this->fail('Unable to create the workbook.');
            foreach($entries as $file=>$content)$zip->addFromString($file,$content);$zip->close();return file_get_contents($path);
        }finally{if(is_file($path))unlink($path);}
    }

    private function document(string|false $xml): DOMDocument
    {
        if($xml===false||preg_match('/<!DOCTYPE|<!ENTITY/i',$xml))$this->fail('The Excel file contains missing or unsupported XML.');
        $doc=new DOMDocument();if(!@$doc->loadXML($xml,LIBXML_NONET))$this->fail('The Excel file cannot be read.');return $doc;
    }

    public function read(string $path): array
    {
        $zip=new ZipArchive();if($zip->open($path,ZipArchive::RDONLY)!==true)$this->fail('Upload a valid .xlsx workbook using the supplied template.');
        try{
            $size=0;if($zip->numFiles>200)$this->fail('The workbook contains too many files.');
            for($i=0;$i<$zip->numFiles;$i++){$size+=$zip->statIndex($i)['size'];if($size>20*1024*1024)$this->fail('The expanded workbook exceeds 20 MB.');}
            $rels=$this->document($zip->getFromName('xl/_rels/workbook.xml.rels'));$targets=[];
            foreach($rels->getElementsByTagName('Relationship') as $rel){
                $target=$rel->getAttribute('Target');if(str_starts_with($target,'/xl/'))$target=substr($target,4);
                if($rel->getAttribute('TargetMode')!=='External' && preg_match('~^worksheets/[A-Za-z0-9_.-]+\.xml$~',$target))$targets[$rel->getAttribute('Id')]='xl/'.$target;
            }
            $shared=[];$sharedXml=$zip->getFromName('xl/sharedStrings.xml');
            if($sharedXml!==false)foreach($this->document($sharedXml)->getElementsByTagName('si') as $si){$text='';foreach($si->getElementsByTagName('t') as $t)$text.=$t->textContent;$shared[]=$text;}
            $book=[];$workbook=$this->document($zip->getFromName('xl/workbook.xml'));$total=0;
            foreach($workbook->getElementsByTagName('sheet') as $sheet){
                $name=trim($sheet->getAttribute('name'));if(!isset(self::HEADERS[$name]))continue;
                if(isset($book[$name]))$this->fail('Duplicate worksheet: '.$name);
                $target=$targets[$sheet->getAttributeNS(self::REL,'id')]??null;if(!$target)$this->fail('Invalid worksheet relationship.');
                $doc=$this->document($zip->getFromName($target));$rows=[];$lastRow=0;$hasHeader=false;
                foreach($doc->getElementsByTagName('row') as $row){
                    $rowNumber=(int)$row->getAttribute('r');if($rowNumber<1||$rowNumber>5001||$rowNumber<=$lastRow)$this->fail('Use consecutive worksheet rows, with at most 5,000 records.');$lastRow=$rowNumber;
                    $values=[];
                    foreach($row->getElementsByTagName('c') as $cell){
                        if($cell->getElementsByTagName('f')->length)$this->fail($name.' row '.$rowNumber.': formulas are not supported. Paste values instead.');
                        if(!preg_match('/^([A-Z]+)'.$rowNumber.'$/',$cell->getAttribute('r'),$m))$this->fail('Invalid Excel cell reference.');
                        $column=0;foreach(str_split($m[1]) as $char)$column=$column*26+ord($char)-64;$column--;
                        if($column>=count(self::HEADERS[$name]))continue;
                        $type=$cell->getAttribute('t');$value='';
                        if($type==='inlineStr'){foreach($cell->getElementsByTagName('t') as $t)$value.=$t->textContent;}
                        else{$value=$cell->getElementsByTagName('v')->item(0)?->textContent??'';if($type==='s')$value=$shared[(int)$value]??'';if($type==='e')$this->fail('Replace Excel error values before importing.');}
                        if(mb_strlen($value)>2500)$this->fail($name.' row '.$rowNumber.': a cell is too long.');$values[$column]=trim($value);
                    }
                    $ordered=[];foreach(self::HEADERS[$name] as $column=>$header)$ordered[$header]=$values[$column]??'';
                    if($rowNumber===1){if(array_values($ordered)!==self::HEADERS[$name])$this->fail('Keep the template column headers and their order in '.$name.'.');$hasHeader=true;continue;}
                    if(!$hasHeader)$this->fail('Keep the template headers in the first row of '.$name.'.');
                    if(!array_filter($ordered,fn($v)=>$v!==''))continue;
                    if(++$total>5000)$this->fail('Import at most 5,000 records at a time.');$rows[]=$ordered;
                }
                if(!$hasHeader)$this->fail('Missing template headers in '.$name.'.');$book[$name]=$rows;
            }
            foreach(self::HEADERS as $name=>$headers)if(!array_key_exists($name,$book))$this->fail('The workbook must contain the '.$name.' worksheet.');
            if(!$total)$this->fail('The workbook has no invigilators or assignments to import.');return $book;
        }finally{$zip->close();}
    }
}
