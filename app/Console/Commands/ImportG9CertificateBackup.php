<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\G9CertificateBackupImport;
use App\Support\G9CertificateWorkbook;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache,DB,Storage};
use Illuminate\Support\Str;

class ImportG9CertificateBackup extends Command
{
    protected $signature='g9:import-certificate-backup {--path=storage/app/imports/G9_National_Exam_Certificates} {--user= : Authorized administrator ID for the audit trail} {--photo-map= : Private JSON of individually reviewed filename corrections} {--commit : Apply the verified import; otherwise preview only}';
    protected $description='Import historical G9 certificate metadata and photos without changing any issued number';

    public function handle(G9CertificateWorkbook $reader,G9CertificateBackupImport $importer): int
    {
        $lock=Cache::lock('g9-certificate-backup-import',86400);
        if(!$lock->get()){$this->error('Another certificate import is running.');return self::FAILURE;}
        try{
            $user=User::findOrFail((int)$this->option('user'));abort_unless($user->status&&$user->isSuperAdmin(),403,'A named active super administrator is required for this maintenance import.');
            $root=realpath(base_path($this->option('path')));if(!$root)throw new \RuntimeException('Backup folder missing.');
            $workbook=$root.DIRECTORY_SEPARATOR.'g9_national_exams_Certificate_from_old_system.xlsx';$hash=hash_file('sha256',$workbook);
            $photoMap=$this->option('photo-map')?json_decode(file_get_contents(base_path($this->option('photo-map'))),true,512,JSON_THROW_ON_ERROR):[];
            $rows=$reader->read($workbook);$plan=$importer->plan($user,$rows,$root,$hash,$photoMap);
            $directory='g9-certificate-import/'.now()->format('Ymd-His').'-'.Str::uuid();
            $write=function(string $name,mixed $data)use($directory){
                if(!Storage::disk('local')->put($directory.'/'.$name,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)))throw new \RuntimeException('Cannot write private import audit.');
            };
            $write('plan.json',['workbook_sha256'=>$hash,'actor'=>$user->id,'rows'=>$plan]);
            $counts=[];foreach($plan as $p){$key=$p['errors']?'blocked':(($p['outcome']??'')==='passed'?'eligible':'ineligible');$counts[$p['sheet']][$key]=($counts[$p['sheet']][$key]??0)+1;}
            $this->line(json_encode($counts,JSON_PRETTY_PRINT));$this->line('Private audit: '.Storage::disk('local')->path($directory));
            if(!$this->option('commit'))return self::SUCCESS;
            // Preserve complete original database rows before creating tracking or documents.
            $ids=array_values(array_filter(array_column($plan,'candidate_id')));$snapshot=[];
            foreach(['tb_g9_exam_candidate'=>'id','tb_g9_exam_certificate_tracking'=>'candidate_id','tb_g9_exam_certificate_file'=>'candidate_id','tb_g9_exam_certificate_number'=>'candidate_id'] as $table=>$field)
                $snapshot[$table]=DB::table($table)->whereIn($field,$ids)->get()->all();
            $write('before.json',$snapshot);$report=[];$totals=[];
            foreach($plan as $i=>$p){
                try{$status=$importer->import($user,$p);$error=null;}catch(\Throwable $e){$status='error';$error=$e->getMessage();}
                $report[]=['candidate_id'=>$p['candidate_id'],'sheet'=>$p['sheet'],'row'=>$p['row'],'student_code'=>$p['student_code'],'status'=>$status,'errors'=>$error?[$error]:$p['errors'],'warnings'=>$p['warnings']];
                $totals[$status]=($totals[$status]??0)+1;
                if(($i+1)%100===0){$write('report.json',$report);$this->line(($i+1).'/'.count($plan).' processed');}
            }
            $write('report.json',$report);$write('summary.json',$totals);$this->info(json_encode($totals));
            return empty($totals['error'])?self::SUCCESS:self::FAILURE;
        }finally{$lock->release();}
    }
}
