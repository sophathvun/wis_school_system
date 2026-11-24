<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Support\{LegacyStudentProfile,StudentAddressResolver,StudentBirthplaceResolver};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairImportedStudentLocations extends Command
{
    protected $signature='students:repair-imported-locations {--workbook= : Original full Student Information workbook} {--create-missing : Create new student profiles without enrollments} {--apply : Apply missing-field repairs; default is preview only}';
    protected $description='Preview or repair missing location fields using saved address text and optional original workbook';

    public function handle(StudentAddressResolver $addresses,StudentBirthplaceResolver $birthplaces): int
    {
        $source=[];
        if($path=$this->option('workbook')){
            if(!is_file($path)){$this->error('Workbook not found.');return self::FAILURE;}
            $rows=(new ImportLegacyStudentWorkbooks)->readWorkbook($path);
            // Preserve every source location cell and reconcile the saved values.
            $result=app(\App\Services\StudentLocationWorkbookImport::class)->run($rows,$path,(bool)$this->option('apply'),(bool)$this->option('create-missing'));
            $this->info(json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        }
        $counts=['scanned'=>0,'students_with_repairs'=>0,'birthplace_students'=>0,'address_students'=>0,'source_name_mismatches'=>0,'fields'=>[],'database_changes'=>0];
        $apply=(bool)$this->option('apply');$journal=null;$directory=null;
        if($apply){
            $directory=storage_path('app/private/student-location-repair-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)));
            if(!mkdir($directory,0750,true))throw new \RuntimeException('Could not create private repair journal.');
            $journal=fopen($directory.'/before-after.jsonl','xb');if(!$journal)throw new \RuntimeException('Could not create private repair journal.');
        }
        try{
            Student::query()->chunkById(200,function($students)use(&$counts,$source,$addresses,$birthplaces,$apply,$journal){
                foreach($students as $student){
                    DB::transaction(function()use($student,&$counts,$source,$addresses,$birthplaces,$apply,$journal){
                        $current=$apply?Student::whereKey($student->id)->lockForUpdate()->firstOrFail():$student;
                        $counts['scanned']++;$row=$source[(string)$current->student_id]??[];
                        if($row && filled($row['name in english']??null) && mb_strtolower(preg_replace('/\s+/u',' ',trim($row['name in english'])))!==mb_strtolower(preg_replace('/\s+/u',' ',trim((string)$current->full_name_en)))){
                            $counts['source_name_mismatches']++;$row=[];
                        }
                        $working=clone $current;
                        $working->fill(LegacyStudentProfile::missingBirthplace($working,$row));
                        $hasBirthplaceToLink=false;
                        foreach(['province','district','commune','village'] as $level)if(blank($working->getAttribute('birth_'.$level.'_id')) && (filled($working->getAttribute('birth_'.$level.'_en')) || filled($working->getAttribute('birth_'.$level.'_kh')))){$hasBirthplaceToLink=true;break;}
                        if($hasBirthplaceToLink)$working->fill($birthplaces->changes($working));
                        $working->fill($addresses->changes($working,$row));
                        // Fill gaps only: keep existing selections and original full address text untouched.
                        $changes=[];
                        foreach($working->getDirty() as $field=>$value)if(blank($current->getAttribute($field)) && filled($value))$changes[$field]=$value;
                        if(!$changes)return;
                        $counts['students_with_repairs']++;
                        if(collect(array_keys($changes))->contains(fn($f)=>str_starts_with($f,'birth_')))$counts['birthplace_students']++;
                        if(collect(array_keys($changes))->contains(fn($f)=>str_starts_with($f,'address_')))$counts['address_students']++;
                        foreach($changes as $field=>$value)$counts['fields'][$field]=($counts['fields'][$field]??0)+1;
                        if($apply){
                            $entry=json_encode(['id'=>$current->id,'student_id'=>$current->student_id,'before'=>$current->only(array_keys($changes)),'after'=>$changes],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE).PHP_EOL;
                            if(fwrite($journal,$entry)!==strlen($entry)||!fflush($journal))throw new \RuntimeException('Could not record repair journal; row unchanged.');
                            $current->fill($changes)->saveQuietly();$counts['database_changes']++;
                        }
                    });
                }
            });
        }finally{if(is_resource($journal))fclose($journal);}
        if($directory){file_put_contents($directory.'/summary.json',json_encode($counts,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));$this->info('Private repair journal: '.$directory);}
        $this->info(($apply?'Applied: ':'Preview only: ').json_encode($counts,JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
