<?php

namespace App\Services;

use App\Models\Student;
use App\Support\{LegacyStudentProfile,StudentAddressResolver,StudentBirthplaceResolver};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StudentLocationWorkbookImport
{
    private function normalizedName($value): string {return mb_strtolower(preg_replace('/\s+/u',' ',trim((string)$value)));}

    private function sameStudent(Student $student,array $row): bool
    {
        foreach(['full_name_en'=>'name in english','full_name_kh'=>'name in khmer'] as $field=>$source){
            $name=$this->normalizedName($row[$source]??'');
            if($name!=='' && $name===$this->normalizedName($student->$field))return true;
        }
        return false;
    }

    public function run(array $rows,string $path,bool $apply=false,bool $createMissing=false): array
    {
        $unique=[];
        foreach($rows as $row){$id=trim((string)($row['student id']??''));if($id!=='' && isset($unique[$id]))throw new \RuntimeException('Duplicate Student ID in source workbook.');$unique[$id]=true;}
        $hash=hash_file('sha256',$path);
        $directory=storage_path('app/private/student-location-workbook-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)));
        if(!mkdir($directory,0750,true))throw new \RuntimeException('Cannot create private import directory.');
        if(!copy($path,$directory.'/source.xlsx') || hash_file('sha256',$directory.'/source.xlsx')!==$hash)throw new \RuntimeException('Source backup verification failed.');
        $sourceLog=fopen($directory.'/source-locations.jsonl','xb');$journal=fopen($directory.'/before-after.jsonl','xb');
        $review=fopen($directory.'/review.csv','xb');$created=fopen($directory.'/new-student-profiles.csv','xb');
        foreach([$sourceLog,$journal,$review,$created] as $stream)if(!$stream)throw new \RuntimeException('Cannot create private import reports.');
        fwrite($review,"\xEF\xBB\xBF");fputcsv($review,['source_row','student_id','issue','field','source_value','stored_value']);
        fwrite($created,"\xEF\xBB\xBF");fputcsv($created,['source_row','student_id','student_no','full_name_en','full_name_kh','gender','date_of_birth','family_number','home_phone','email','previous_school','birth_province_en','birth_province_kh','current_address_en','current_address_kh','family_follow_up','enrollment_follow_up']);
        $students=($apply?Student::get(['id','student_id','full_name_en','full_name_kh']):Student::all())->keyBy('student_id');$addresses=new StudentAddressResolver;$birthplaces=new StudentBirthplaceResolver;
        $counts=['mode'=>$apply?'applied':'preview','source_rows'=>count($rows),'source_sha256'=>$hash,'matched'=>0,'new_profiles'=>0,'normalized_student_ids'=>0,'identity_review'=>0,'unmatched'=>0,'students_changed'=>0,'source_nonempty_cells'=>0,'archived_nonempty_cells'=>0,'unresolved_fields'=>[],'fields_updated'=>[],'database_changes'=>0];
        $seen=[];$expectations=[];
        try{
            foreach($rows as $i=>$row){
                $sourceId=trim((string)($row['student id']??''));
                $raw=LegacyStudentProfile::locationSource($row);
                $entry=['row'=>$i+2,'student_id'=>$sourceId,'values'=>$raw];
                $this->write($sourceLog,json_encode($entry,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE).PHP_EOL);
                $counts['source_nonempty_cells']+=count(array_filter($raw,fn($v)=>filled($v)));
                if($sourceId===''){fputcsv($review,[$i+2,'','missing_student_id','','','']);$counts['unmatched']++;continue;}
                if(isset($seen[$sourceId]))throw new \RuntimeException('Duplicate Student ID in source workbook.');$seen[$sourceId]=true;
                $student=$students->get($sourceId);
                $asciiId=strtr($sourceId,array_combine(preg_split('//u','០១២៣៤៥៦៧៨៩',-1,PREG_SPLIT_NO_EMPTY),range(0,9)));
                if(!$student && $asciiId!==$sourceId && ($candidate=$students->get($asciiId)) && $this->sameStudent($candidate,$row)){
                    $student=$candidate;$counts['normalized_student_ids']++;
                    fputcsv($review,[$i+2,$sourceId,'normalized_khmer_digit_id','student_id',$sourceId,$asciiId]);
                }
                if($student && !$this->sameStudent($student,$row)){
                    fputcsv($review,[$i+2,$sourceId,'identity_requires_review','name',$row['name in english']??'',$student->full_name_en]);$counts['identity_review']++;continue;
                }
                if(!$student && !$createMissing){fputcsv($review,[$i+2,$sourceId,'student_not_found','','','']);$counts['unmatched']++;continue;}
                if(!$student && blank($row['name in english']??'')){fputcsv($review,[$i+2,$sourceId,'new_student_missing_name','','','']);$counts['unmatched']++;continue;}
                $isNew=!$student;
                if($isNew){
                    $student=new Student($this->identity($row));$student->student_id=$sourceId;$counts['new_profiles']++;
                }else $counts['matched']++;
                $process=function()use(&$student,$row,$raw,$sourceId,$i,$isNew,$hash,$apply,$addresses,$birthplaces,$journal,$review,$created,&$counts,&$expectations){
                    $current=$student;
                    if($apply && !$isNew)$current=Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
                    $working=clone $current;
                    $working->fill(LegacyStudentProfile::missingBirthplace($working,$row));
                    $working->fill(LegacyStudentProfile::missingAddress($working,$row));
                    $birth=$birthplaces->changes($working);
                    foreach($birth as $field=>$value){
                        if(blank($working->getAttribute($field)) && filled($value))$working->setAttribute($field,$value);
                        elseif($field==='birth_province_en' && $value===null && blank($current->birth_province_en))$working->birth_province_en=null;
                    }
                    $working->fill($addresses->changes($working,$row));
                    $archive=['source_sha256'=>$hash,'source_row'=>$i+2,'student_id_as_in_source'=>$sourceId,'values'=>$raw];
                    $previous=$current->imported_location_data;
                    if(($previous['source_sha256']??null)===$hash){
                        $otherRows=$previous['additional_source_rows']??[];
                        if(($previous['source_row']??null)!==$i+2)$otherRows[]=['source_row'=>$previous['source_row'],'student_id_as_in_source'=>$previous['student_id_as_in_source'],'values'=>$previous['values']];
                        $byRow=[];foreach($otherRows as $other)if($other['source_row']!==$i+2)$byRow[$other['source_row']]=$other;
                        if($byRow){ksort($byRow);$archive['additional_source_rows']=array_values($byRow);}
                    }
                    if($current->imported_location_data!=$archive)$working->imported_location_data=$archive;
                    $changes=[];
                    foreach($working->getDirty() as $field=>$value){
                        if($field==='imported_location_data' || blank($current->getAttribute($field)))$changes[$field]=$value;
                    }
                    if($isNew)$changes=$working->getAttributes();
                    if(array_key_exists('imported_location_data',$changes))$changes['imported_location_data']=$archive;
                    foreach(['birth'=>LegacyStudentProfile::birthplace($row),'address'=>LegacyStudentProfile::address($row)] as $prefix=>$values){
                        foreach(['province','district','commune','village'] as $level){
                            $names=array_filter([$values[$prefix.'_'.$level.'_en']??null,$values[$prefix.'_'.$level.'_kh']??null],fn($v)=>filled($v));
                            if($names && !$working->getAttribute($prefix.'_'.$level.'_id')){
                                $field=$prefix.'_'.$level.'_id';$counts['unresolved_fields'][$field]=($counts['unresolved_fields'][$field]??0)+1;
                                fputcsv($review,[$i+2,$sourceId,'preserved_text_without_location_match',$field,implode(' / ',$names),'']);
                            }
                        }
                        foreach($values as $field=>$value)if(filled($current->getAttribute($field)) && (string)$current->getAttribute($field)!==(string)$value)fputcsv($review,[$i+2,$sourceId,'existing_value_preserved',$field,$value,$current->getAttribute($field)]);
                    }
                    if($changes){
                        $counts['students_changed']++;
                        foreach($changes as $field=>$value)$counts['fields_updated'][$field]=($counts['fields_updated'][$field]??0)+1;
                        if($apply){
                            $before=$isNew?null:$current->only(array_keys($changes));
                            if($isNew){$working->student_no=app(StudentNumberAllocator::class)->allocate();$changes['student_no']=$working->student_no;}
                            $this->write($journal,json_encode(['source_row'=>$i+2,'student_id'=>$current->student_id,'id'=>$current->id,'created'=>$isNew,'before'=>$before,'after'=>$changes],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE).PHP_EOL);
                            if($isNew){$working->save();$current=$working;}else{$current->fill($changes)->saveQuietly();}
                            $counts['database_changes']++;
                        }
                    }
                    if($isNew)fputcsv($created,[$i+2,$current->student_id,$current->student_no,$current->full_name_en,$current->full_name_kh,$current->gender,$current->date_of_birth?->format('Y-m-d'),$current->family_number,$current->home_phone,$current->email,$current->previous_school,$working->birth_province_en,$working->birth_province_kh,$working->current_address_en,$working->current_address_kh,'Find or link family','Create enrollment']);
                    $expectedCells=count(array_filter($raw,fn($v)=>filled($v)));
                    if($apply)$expectations[$current->id]=['archive'=>$archive,'cells'=>($expectations[$current->id]['cells']??0)+$expectedCells];
                    else $counts['archived_nonempty_cells']+=$expectedCells;
                    $student=$current;
                };
                if($apply)DB::transaction($process);else $process();
            }
            if($apply){
                foreach(array_chunk(array_keys($expectations),300) as $ids)foreach(Student::whereIn('id',$ids)->get(['id','imported_location_data']) as $student){
                    $expected=$expectations[$student->id];
                    if($student->imported_location_data!=$expected['archive'])throw new \RuntimeException('Saved source data reconciliation failed for student record '.$student->id);
                    $counts['archived_nonempty_cells']+=$expected['cells'];
                }
            }
            $counts['all_source_rows_accounted_for']=$counts['matched']+$counts['new_profiles']+$counts['unmatched']+$counts['identity_review']===$counts['source_rows'];
            $counts['all_location_source_cells_preserved']=$counts['archived_nonempty_cells']===$counts['source_nonempty_cells'];
            $counts['private_directory']=$directory;
            file_put_contents($directory.'/summary.json',json_encode($counts,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
            return $counts;
        }finally{foreach([$sourceLog,$journal,$review,$created] as $stream)if(is_resource($stream))fclose($stream);}
    }

    private function write($stream,string $value): void
    {
        if(fwrite($stream,$value)!==strlen($value) || !fflush($stream))throw new \RuntimeException('Could not preserve import journal; current row unchanged.');
    }

    private function identity(array $row): array
    {
        $date=null;$value=trim((string)($row['date of birth']??''));
        if($value!==''){
            if(is_numeric($value))$date=Carbon::create(1899,12,30)->addDays((int)round((float)$value))->format('Y-m-d');
            else foreach(['Y-m-d','d/m/Y','d-m-Y','m/d/Y'] as $format){try{$parsed=Carbon::createFromFormat('!'.$format,$value);if($parsed->format($format)===$value){$date=$parsed->format('Y-m-d');break;}}catch(\Throwable){}}
        }
        $gender=match(mb_strtolower(trim((string)($row['sex']??'')))){'m','male'=>'Male','f','female'=>'Female',default=>null};
        $values=['full_name_en'=>trim($row['name in english']),'full_name_kh'=>trim((string)($row['name in khmer']??''))?:null,
            'gender'=>$gender,'gender_kh'=>match($gender){'Male'=>'ប្រុស','Female'=>'ស្រី',default=>null},'date_of_birth'=>$date,'family_number'=>trim((string)($row['family_number']??$row['family number']??''))?:null,'status'=>1];
        foreach(['home_phone'=>'home phone','email'=>'e-mail address','previous_school'=>'previous school','experienced_english'=>'experienced english','test_result'=>'testresult','tested_by'=>'testby','remarks'=>'remarks'] as $field=>$key)$values[$field]=trim((string)($row[$key]??''))?:null;
        $nationality=trim((string)($row['nationality']??''));
        if($nationality!==''){
            $matches=DB::table('tb_country')->where(fn($q)=>$q->where('country_name_en',$nationality)->orWhere('country_name_kh',$nationality)->orWhere('nationality_name_en',$nationality)->orWhere('nationality_name_kh',$nationality))->pluck('id');
            if($matches->count()===1)$values['nationality_country_id']=$matches->first();
        }
        return $values;
    }
}
