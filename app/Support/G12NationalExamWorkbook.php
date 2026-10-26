<?php
namespace App\Support;
use DOMDocument;
use ZipArchive;
use Illuminate\Validation\ValidationException;
class G12NationalExamWorkbook {
    private function fail(string $message): never {throw ValidationException::withMessages(['file'=>$message]);}
    private function xml(string|false $xml): DOMDocument {
        if($xml===false||preg_match('/<!DOCTYPE|<!ENTITY/i',$xml))$this->fail('The workbook contains missing or unsupported XML.');
        $doc=new DOMDocument();if(!@$doc->loadXML($xml,LIBXML_NONET))$this->fail('The workbook XML is invalid.');return $doc;
    }
    public function read(string $path): array {
        $zip=new ZipArchive();if($zip->open($path,ZipArchive::RDONLY)!==true)$this->fail('Choose a valid .xlsx workbook.');
        try {
            $size=0;if($zip->numFiles>500)$this->fail('This workbook contains too many components.');
            for($i=0;$i<$zip->numFiles;$i++){ $size+=$zip->statIndex($i)['size'];if($size>100*1024*1024)$this->fail('The expanded workbook exceeds 100 MB.'); }
            $shared=[];if(($part=$zip->getFromName('xl/sharedStrings.xml'))!==false)foreach($this->xml($part)->getElementsByTagName('si') as $si){$text='';foreach($si->getElementsByTagName('t') as $t)$text.=$t->textContent;$shared[]=$text;}
            $targets=[];foreach($this->xml($zip->getFromName('xl/_rels/workbook.xml.rels'))->getElementsByTagName('Relationship') as $rel){
                $target=ltrim($rel->getAttribute('Target'),'/');if(str_starts_with($target,'xl/'))$target=substr($target,3);
                if($rel->getAttribute('TargetMode')!=='External'&&preg_match('~^worksheets/[\w.-]+\.xml$~',$target))$targets[$rel->getAttribute('Id')]='xl/'.$target;
            }
            $sheets=[];$workbook=$this->xml($zip->getFromName('xl/workbook.xml'));
            $date1904=$workbook->getElementsByTagName('workbookPr')->item(0)?->getAttribute('date1904')==='1';
            foreach($workbook->getElementsByTagName('sheet') as $index=>$sheet){
                if($index>1)break;$target=$targets[$sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','id')]??null;
                if(!$target)$this->fail('A worksheet relationship is invalid.');$rows=[];$headers=null;
                foreach($this->xml($zip->getFromName($target))->getElementsByTagName('row') as $row){
                    $number=(int)$row->getAttribute('r');if($number>20001)$this->fail('Import at most 20,000 rows at once.');$values=[];
                    foreach($row->getElementsByTagName('c') as $cell){
                        if(!preg_match('/^([A-Z]+)\d+$/',$cell->getAttribute('r'),$m))continue;
                        $column=0;foreach(str_split($m[1]) as $c)$column=$column*26+ord($c)-64;if($column>100)continue;
                        $type=$cell->getAttribute('t');$value=$cell->getElementsByTagName('v')->item(0)?->textContent??'';
                        if($type==='s')$value=$shared[(int)$value]??'';
                        elseif($type==='inlineStr'){$value='';foreach($cell->getElementsByTagName('t') as $t)$value.=$t->textContent;}
                        if($type==='e')$this->fail('Replace the Excel error in '.$cell->getAttribute('r').' before importing.');
                        if($cell->getElementsByTagName('f')->length)$this->fail('Paste values instead of formulas in '.$cell->getAttribute('r').'.');
                        if(mb_strlen($value)>5000)$this->fail('A cell exceeds 5,000 characters.');$values[$column-1]=G12NationalExamFields::clean($value);
                    }
                    if(!array_filter($values,fn($v)=>$v!==''))continue;
                    if($headers===null){$headers=array_map(fn($v)=>mb_strtolower(trim($v)),$values);if(count($headers)!==count(array_unique($headers)))$this->fail('Duplicate column headers are not supported.');continue;}
                    $record=['_row'=>$number];foreach($headers as $column=>$header)$record[$header]=$values[$column]??'';
                    foreach(['dob','received_soft_cert_date','received_hard_cert_date'] as $field)if(isset($record[$field])&&is_numeric($record[$field])&&(float)$record[$field]>1){
                        $record[$field]=\Carbon\CarbonImmutable::parse($date1904?'1904-01-01':'1899-12-30')->addDays((int)$record[$field])->format('Y-m-d');
                    }
                    if(isset($record['image_hard_certreceived_name'])&&!isset($record['inage_hard_certreceived_name']))$record['inage_hard_certreceived_name']=$record['image_hard_certreceived_name'];
                    if(isset($record['moeys_photo_filename']))$record['photo_filename']=$record['moeys_photo_filename'];
                    $rows[]=$record;
                }
                $sheets[]=['name'=>$sheet->getAttribute('name'),'headers'=>array_values($headers??[]),'rows'=>$rows];
            }
            if(count($sheets)<2)$this->fail('Include student records in Sheet 1 and Exam Center settings in Sheet 2.');
            foreach(['student_id','academic_year','student_name_en','campus'] as $header)if(!in_array($header,$sheets[0]['headers'],true))$this->fail('Sheet 1 is missing '.$header.'.');
            if(!in_array('exam_center',$sheets[1]['headers'],true))$this->fail('Sheet 2 is missing exam_center.');
            if(!$sheets[0]['rows'])$this->fail('There are no student exam records in Sheet 1.');
            return ['records'=>$sheets[0]['rows'],'centers'=>$sheets[1]['rows'],'headers'=>$sheets[0]['headers']];
        }finally{$zip->close();}
    }
}
