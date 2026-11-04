<?php

namespace App\Support;

use DOMDocument;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class G9ExamSpreadsheet
{
    // Keep the MoEYS column names and order, including their original spelling.
    public const COLUMNS=[
        'academic_year'=>'academic_year_id','stu_id'=>'student_code','full_name_khmer'=>'name_kh','full_name_English'=>'name_en',
        'sex'=>'gender','Ethnicity'=>'ethnicity','Nationality_KH'=>'nationality','date_of_birth_english'=>'date_of_birth',
        'place_of_birth'=>'place_of_birth','ForeignLanguage'=>'foreign_language','father_name'=>'father_name','father_job'=>'father_occupation',
        'mother_name'=>'mother_name','mother_job'=>'mother_occupation','exam_date'=>'exam_date','exam_center'=>'exam_center',
        'SchoolName'=>'school_name','campus'=>'campus_id','Grade'=>'grade','Phone'=>'phone_number','exam_language'=>'exam_language',
        'seat_number'=>'seat_number','room_number'=>'room_number','score_sem1'=>'semester_1','score_sem2'=>'semester_2','score_end_of_year'=>'eoy_average',
        'score_khmerEssay_exam'=>'score_khmer_essay','score_chemistry_exam'=>'score_chemistry','score_earthscience_exam'=>'score_earth_science',
        'score_physics_exam'=>'score_physics','score_geography_exam'=>'score_geography','score_value_exam'=>'score_values',
        'score_math_exam'=>'score_math','score_khmerDictation_exam'=>'score_khmer_dictation','score_biology_exam'=>'score_biology',
        'score_history_exam'=>'score_history','score_english_exam'=>'score_english','imageName'=>'photo_filename',
    ];
    public const YEAR_FIELDS=['k_listdate','listDate','k_resultDate','resultDate','k_resultDateDOEYS','resultDateDOEYS','k_cardDate','cardDate','k_certDate','certDate',
        'score_key_by','score_total_by','score_verify_by','score_result_by','score_last_verify_by','score_last_verify_by_en','score_read_eoy_by',
        'score_read_khmeressay_by','score_read_chemistry_by','score_read_earth_by','score_read_physics_by','score_read_geo_by','score_read_values_by',
        'score_read_math_by','score_read_khmerdictation_by','score_read_biology_by','score_read_history_by','score_read_english_by','release'];
    public const TRACKING_FIELDS=['national_id','certificate_number','cert_name','cert_received_name','received_cert','received_cert_Date','received_by','exam_Attendance'];
    public static function allColumns(): array
    {
        return self::COLUMNS + array_combine([...self::YEAR_FIELDS,...self::TRACKING_FIELDS],array_map(fn($key)=>'legacy_'.$key,[...self::YEAR_FIELDS,...self::TRACKING_FIELDS]));
    }

    private function fail(string $message): never {throw ValidationException::withMessages(['file'=>$message]);}
    private function xml(string|false $xml): DOMDocument
    {
        if($xml===false||preg_match('/<!DOCTYPE|<!ENTITY/i',$xml))$this->fail('The workbook contains unsupported XML.');
        $doc=new DOMDocument();if(!@$doc->loadXML($xml,LIBXML_NONET))$this->fail('The workbook XML is invalid.');return $doc;
    }
    public function read(string $path,string $extension): array
    {
        $rows=[];
        if($extension==='csv'){
            $handle=fopen($path,'rb');
            try{
                while(($row=fgetcsv($handle,0,',','"',''))!==false){
                    if(count($rows)>5000)$this->fail('Import at most 5,000 candidates at once.');
                    $rows[]=$row;
                }
            }finally{fclose($handle);}
        }else{
            $zip=new ZipArchive();if($zip->open($path,ZipArchive::RDONLY)!==true)$this->fail('Choose a valid .xlsx workbook.');
            try{
                $size=0;if($zip->numFiles>500)$this->fail('The workbook contains too many components.');
                for($i=0;$i<$zip->numFiles;$i++){ $size+=$zip->statIndex($i)['size'];if($size>50*1024*1024)$this->fail('The expanded workbook exceeds 50 MB.'); }
                $shared=[];if(($part=$zip->getFromName('xl/sharedStrings.xml'))!==false)foreach($this->xml($part)->getElementsByTagName('si') as $si){$text='';foreach($si->getElementsByTagName('t') as $t)$text.=$t->textContent;$shared[]=$text;}
                $targets=[];foreach($this->xml($zip->getFromName('xl/_rels/workbook.xml.rels'))->getElementsByTagName('Relationship') as $rel){
                    $target=ltrim($rel->getAttribute('Target'),'/');if(str_starts_with($target,'xl/'))$target=substr($target,3);
                    if($rel->getAttribute('TargetMode')!=='External'&&preg_match('~^worksheets/[\w.-]+\.xml$~',$target))$targets[$rel->getAttribute('Id')]='xl/'.$target;
                }
                $workbook=$this->xml($zip->getFromName('xl/workbook.xml'));$sheet=$workbook->getElementsByTagName('sheet')->item(0);
                $target=$sheet?$targets[$sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','id')]??null:null;
                if(!$target)$this->fail('No readable worksheet was found.');
                $date1904=$workbook->getElementsByTagName('workbookPr')->item(0)?->getAttribute('date1904')==='1';
                foreach($this->xml($zip->getFromName($target))->getElementsByTagName('row') as $row){
                    if(count($rows)>5000)$this->fail('Import at most 5,000 candidates at once.');$values=[];
                    foreach($row->getElementsByTagName('c') as $cell){
                        if(!preg_match('/^([A-Z]+)\d+$/',$cell->getAttribute('r'),$m))continue;
                        $column=0;foreach(str_split($m[1]) as $c)$column=$column*26+ord($c)-64;if($column>100)$this->fail('The workbook has too many columns.');
                        if($cell->getElementsByTagName('f')->length)$this->fail('Paste values instead of formulas in '.$cell->getAttribute('r').'.');
                        if($cell->getAttribute('t')==='e')$this->fail('Resolve the Excel error in '.$cell->getAttribute('r').'.');
                        $value=$cell->getElementsByTagName('v')->item(0)?->textContent??'';
                        if($cell->getAttribute('t')==='s')$value=$shared[(int)$value]??'';
                        elseif($cell->getAttribute('t')==='inlineStr'){$value='';foreach($cell->getElementsByTagName('t') as $t)$value.=$t->textContent;}
                        $values[$column-1]=$value;
                    }
                    $rows[]=$values;
                }
            }finally{$zip->close();}
        }
        $headers=null;$result=[];$map=array_change_key_case(self::allColumns(),CASE_LOWER);
        $map['exam']='exam_language';
        foreach($rows as $index=>$values){
            if(!array_filter($values,fn($v)=>$v!==null&&trim((string)$v)!==''))continue;
            if($headers===null){
                $headers=[];foreach($values as $column=>$value){$key=mb_strtolower(trim(ltrim((string)$value,"\xEF\xBB\xBF")));if($key==='')continue;if(!isset($map[$key]))$this->fail('Unknown column: '.$value);$field=$map[$key];if(in_array($field,$headers,true))$this->fail('Duplicate column: '.$value);$headers[$column]=$field;}
                foreach(['academic_year_id','student_code','name_en','campus_id'] as $required)if(!in_array($required,$headers,true))$this->fail('Missing required column: '.array_search($required,self::COLUMNS,true));
                continue;
            }
            $record=['_row'=>$index+1];
            foreach($headers as $column=>$field){$value=trim((string)($values[$column]??''));if(!mb_check_encoding($value,'UTF-8'))$this->fail('Save CSV files in UTF-8 encoding.');if(mb_strlen($value)>5000)$this->fail('A cell exceeds 5,000 characters.');
                // Undo only the explicit CSV formula-protection prefix used by our export.
                if(preg_match("/^'[=+\-@\t\r]/",$value))$value=substr($value,1);
                if(in_array($field,['date_of_birth','exam_date','legacy_received_cert_Date'],true)&&is_numeric($value)&&(float)$value>1)$value=\Carbon\CarbonImmutable::parse(($date1904??false)?'1904-01-01':'1899-12-30')->addDays((int)$value)->format('Y-m-d');
                $record[$field]=$value;
            }$result[]=$record;
        }
        if(!$result)$this->fail('There are no candidate rows to import.');return $result;
    }

    public static function csvCell(mixed $value): string
    {
        $value=(string)($value??'');return preg_match('/^[=+\-@\t\r]/',$value)?"'".$value:$value;
    }
}
