<?php

namespace App\Support;

use DOMDocument;
use RuntimeException;
use ZipArchive;

/** Read the six legacy worksheets as literal values; never regenerate or normalize issued numbers. */
class G9CertificateWorkbook
{
    private function xml(string|false $bytes): DOMDocument
    {
        if($bytes===false||preg_match('/<!DOCTYPE|<!ENTITY/i',$bytes))throw new RuntimeException('Unsupported workbook XML.');
        $doc=new DOMDocument();if(!@$doc->loadXML($bytes,LIBXML_NONET))throw new RuntimeException('Invalid workbook XML.');return $doc;
    }

    public function read(string $path): array
    {
        $zip=new ZipArchive();if($zip->open($path,ZipArchive::RDONLY)!==true)throw new RuntimeException('Cannot read the certificate workbook.');
        try{
            $size=0;for($i=0;$i<$zip->numFiles;$i++)$size+=$zip->statIndex($i)['size'];
            if($zip->numFiles>500||$size>100*1024*1024)throw new RuntimeException('Workbook exceeds the import limit.');
            $shared=[];
            if(($part=$zip->getFromName('xl/sharedStrings.xml'))!==false)foreach($this->xml($part)->getElementsByTagName('si') as $si){
                $text='';foreach($si->getElementsByTagName('t') as $t)$text.=$t->textContent;$shared[]=$text;
            }
            $targets=[];
            foreach($this->xml($zip->getFromName('xl/_rels/workbook.xml.rels'))->getElementsByTagName('Relationship') as $rel){
                $target=ltrim($rel->getAttribute('Target'),'/');if(str_starts_with($target,'xl/'))$target=substr($target,3);
                if($rel->getAttribute('TargetMode')!=='External'&&preg_match('~^worksheets/[\w.-]+\.xml$~',$target))$targets[$rel->getAttribute('Id')]='xl/'.$target;
            }
            $workbook=$this->xml($zip->getFromName('xl/workbook.xml'));
            $date1904=$workbook->getElementsByTagName('workbookPr')->item(0)?->getAttribute('date1904')==='1';$result=[];
            foreach($workbook->getElementsByTagName('sheet') as $sheet){
                $year=$sheet->getAttribute('name');if(!preg_match('/^20\d{2}-20\d{2}$/',$year))throw new RuntimeException('Unexpected academic-year worksheet.');
                $target=$targets[$sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','id')]??null;
                if(!$target)throw new RuntimeException('Worksheet missing.');$headers=null;
                foreach($this->xml($zip->getFromName($target))->getElementsByTagName('row') as $row){
                    $values=[];
                    foreach($row->getElementsByTagName('c') as $cell){
                        if(!preg_match('/^([A-Z]+)\d+$/',$cell->getAttribute('r'),$m))continue;
                        if($cell->getElementsByTagName('f')->length||$cell->getAttribute('t')==='e')throw new RuntimeException('Replace formulas/errors with original values before importing.');
                        $column=0;foreach(str_split($m[1]) as $c)$column=$column*26+ord($c)-64;
                        $value=$cell->getElementsByTagName('v')->item(0)?->textContent??'';
                        if($cell->getAttribute('t')==='s')$value=$shared[(int)$value]??'';
                        elseif($cell->getAttribute('t')==='inlineStr'){$value='';foreach($cell->getElementsByTagName('t') as $t)$value.=$t->textContent;}
                        $values[$column]=$value;
                    }
                    if(!array_filter($values,fn($v)=>$v!==''))continue;
                    if($headers===null){
                        $headers=array_map('trim',$values);
                        foreach(['academic_year','stu_id','full_name_khmer','certificate_number','cert_name','received_cert','cert_received_name','received_cert_Date','received_by','certDate'] as $required)
                            if(!in_array($required,$headers,true))throw new RuntimeException('Missing column: '.$required);
                        if(count($headers)!==count(array_unique($headers)))throw new RuntimeException('Duplicate workbook headers.');continue;
                    }
                    $record=['_sheet'=>$year,'_row'=>(int)$row->getAttribute('r'),'_date1904'=>$date1904];
                    foreach($headers as $column=>$header)if($header!=='')$record[$header]=$values[$column]??'';
                    if(trim($record['stu_id'])==='')continue;
                    if(trim($record['academic_year'])!==$year)throw new RuntimeException('Worksheet and row academic year disagree.');
                    $result[]=$record;if(count($result)>10000)throw new RuntimeException('Too many certificate rows.');
                }
            }
            if(!$result)throw new RuntimeException('No certificate rows found.');return $result;
        }finally{$zip->close();}
    }
}
