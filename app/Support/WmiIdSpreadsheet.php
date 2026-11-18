<?php

namespace App\Support;

use DOMDocument;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class WmiIdSpreadsheet
{
    private function fail(string $message): never { throw ValidationException::withMessages(['file'=>$message]); }
    private function xml(string $xml): DOMDocument
    {
        if(stripos($xml,'<!DOCTYPE')!==false)$this->fail('Unsupported workbook XML.');
        $doc=new DOMDocument();$previous=libxml_use_internal_errors(true);
        try {if(!$doc->loadXML($xml,LIBXML_NONET))$this->fail('The workbook could not be read.');}
        finally {libxml_clear_errors();libxml_use_internal_errors($previous);}
        return $doc;
    }
    public function read(string $path,string $extension): array
    {
        $rows=[];
        if($extension==='csv'){
            $stream=fopen($path,'rb');
            try {while(($row=fgetcsv($stream,0,',','"',''))!==false){$rows[]=$row;if(count($rows)>5001)$this->fail('Import at most 5,000 candidates at once.');}}
            finally {fclose($stream);}
        }elseif($extension==='xlsx'){
            $zip=new ZipArchive();if($zip->open($path)!==true)$this->fail('Choose a valid XLSX workbook.');
            try {
                $expanded=0;for($i=0;$i<$zip->numFiles;$i++){$stat=$zip->statIndex($i);$expanded+=$stat['size'];if($expanded>30*1024*1024)$this->fail('The expanded workbook is too large.');}
                $shared=[];$xml=$zip->getFromName('xl/sharedStrings.xml');
                if($xml!==false)foreach($this->xml($xml)->getElementsByTagName('si') as $item){$value='';foreach($item->getElementsByTagName('t') as $text)$value.=$text->textContent;$shared[]=$value;}
                $workbook=$this->xml($zip->getFromName('xl/workbook.xml')?:'');$sheet=$workbook->getElementsByTagName('sheet')->item(0);
                $sheetId=$sheet?->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','id');$target=null;
                foreach($this->xml($zip->getFromName('xl/_rels/workbook.xml.rels')?:'')->getElementsByTagName('Relationship') as $relationship){
                    if($relationship->getAttribute('Id')===$sheetId){$target=$relationship->getAttribute('Target');break;}
                }
                if(!$target||str_contains($target,'..'))$this->fail('The workbook worksheet could not be read.');
                $target=str_starts_with($target,'/')?ltrim($target,'/'):'xl/'.$target;
                foreach($this->xml($zip->getFromName($target)?:'')->getElementsByTagName('row') as $row){
                    $values=[];foreach($row->getElementsByTagName('c') as $cell){
                        if(!preg_match('/^([A-Z]+)\d+$/',$cell->getAttribute('r'),$match))continue;
                        $col=0;foreach(str_split($match[1]) as $char)$col=$col*26+ord($char)-64;if($col>60)$this->fail('The workbook has too many columns.');
                        if($cell->getElementsByTagName('f')->length)$this->fail('Paste values instead of formulas before importing.');
                        $value=$cell->getElementsByTagName('v')->item(0)?->textContent??'';
                        if($cell->getAttribute('t')==='s')$value=$shared[(int)$value]??'';
                        elseif($cell->getAttribute('t')==='inlineStr'){$value='';foreach($cell->getElementsByTagName('t') as $text)$value.=$text->textContent;}
                        elseif($cell->getAttribute('t')==='e')$this->fail('Fix Excel errors before importing.');
                        $values[$col-1]=$value;
                    }$rows[]=$values;if(count($rows)>5001)$this->fail('Import at most 5,000 candidates at once.');
                }
            } finally {$zip->close();}
        }else $this->fail('Choose a CSV or XLSX file.');
        $headers=null;$result=[];
        foreach($rows as $line=>$row){
            if(!array_filter($row,fn($v)=>trim((string)$v)!==''))continue;
            if($headers===null){
                $headers=[];foreach($row as $col=>$value){$key=strtolower(trim(ltrim((string)$value,"\xEF\xBB\xBF")));if(in_array($key,['registration_reference','wmi_id_number'],true)){if(in_array($key,$headers,true))$this->fail('Duplicate column: '.$key);$headers[$col]=$key;}}
                if(count($headers)!==2)$this->fail('Keep the registration_reference and wmi_id_number columns from the exported list.');continue;
            }
            $record=['_row'=>$line+1];foreach($headers as $col=>$key){$value=trim((string)($row[$col]??''));if(!mb_check_encoding($value,'UTF-8'))$this->fail('Save CSV files in UTF-8 format.');$record[$key]=$value;}$result[]=$record;
        }
        if(!$result)$this->fail('The file contains no candidate rows.');return $result;
    }
}
