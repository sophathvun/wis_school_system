<?php

namespace App\Services;

use App\Models\{G9ExamCandidate,G9ExamCertificateTracking,User};
use App\Support\G9NationalExamPermissions as Access;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
use RuntimeException;

class G9CertificateBackupImport
{
    private function name(string $value): string
    {
        return \Normalizer::normalize(preg_replace('/[\s\x{200B}\x{FEFF}]+/u','',$value),\Normalizer::FORM_C);
    }

    public function date(string $value,bool $date1904=false): ?string
    {
        if(trim($value)==='')return null;
        if(is_numeric($value))return CarbonImmutable::parse($date1904?'1904-01-01':'1899-12-30')->addDays((int)$value)->format('Y-m-d');
        if(preg_match('/^\d{4}-\d{2}-\d{2}(?:T00:00:00)?$/',$value)){
            $iso=substr($value,0,10);$parsed=app(G9ExamImports::class)->date($iso);return preg_match('/^\d{4}-\d{2}-\d{2}$/',$parsed)&&checkdate((int)substr($iso,5,2),(int)substr($iso,8,2),(int)substr($iso,0,4))?$iso:null;
        }
        // The legacy certificate date includes its city and Khmer day/month/year labels.
        if(preg_match('/ថ្ងៃទី\s*(.+?)\s*ខែ\s*(.+?)\s*ឆ្នាំ\s*(.+)$/u',$value,$m))$value=$m[1].' '.$m[2].' '.$m[3];
        $parsed=app(G9ExamImports::class)->date(trim($value));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/',$parsed)?$parsed:null;
    }

    private function files(string $root,string $year,string $folder): array
    {
        $index=[];
        foreach(glob($root.DIRECTORY_SEPARATOR.$year.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.'*')?:[] as $path){
            if(!is_file($path)||!in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['jpg','jpeg','png','webp','pdf'],true))continue;
            $real=realpath($path);if(!$real||!str_starts_with($real,$root.DIRECTORY_SEPARATOR))throw new RuntimeException('Photo is outside the backup folder.');
            $index[mb_strtolower(pathinfo($path,PATHINFO_FILENAME))][]=$real;
        }
        return $index;
    }

    public function plan(User $user,array $rows,string $root,string $workbookHash,array $photoMap=[]): array
    {
        $root=realpath($root);if(!$root)throw new RuntimeException('Backup folder missing.');
        $candidates=G9ExamCandidate::with(['academicYear','certificateTracking','certificateNumber','certificateFiles'])->get()
            ->groupBy(fn($c)=>$c->academicYear?->academic_year.'|'.$c->student_code);
        $seen=[];$numbers=[];$indexes=[];$plans=[];
        foreach($rows as $r)if(($r['certificate_number']??'')!=='')$numbers[$r['certificate_number']][]=[$r['_sheet'],$r['stu_id']];
        foreach($rows as $r){
            $year=$r['_sheet'];$key=$year.'|'.trim($r['stu_id']);$matches=$candidates[$key]??collect();
            $p=['sheet'=>$year,'row'=>$r['_row'],'student_code'=>$r['stu_id'],'candidate_id'=>null,'errors'=>[],'warnings'=>[],'files'=>[],
                'source'=>$r,'workbook_sha256'=>$workbookHash];
            if(isset($seen[$key]))$p['errors'][]='Duplicate student in workbook.';$seen[$key]=true;
            if($matches->count()!==1){$p['errors'][]='Student identity is missing or ambiguous.';$plans[]=$p;continue;}
            $c=$matches->first();$p['candidate_id']=$c->id;$p['academic_year_id']=$c->academic_year_id;
            if(!Access::allows($user,'import',$c->campus_id)||!Access::allows($user,'certificates-manage',$c->campus_id))$p['errors'][]='Campus import/manage permission missing.';
            if($this->name($c->name_kh)!==$this->name($r['full_name_khmer']))$p['errors'][]='Khmer name does not agree with the matched student.';
            $p['outcome']=app(G9CertificateTracking::class)->outcome($c);
            foreach(['ledger'=>$c->certificateNumber?->number,'tracking'=>$c->certificateTracking?->number,'legacy'=>$c->legacy_data['certificate_number']??''] as $source=>$number)
                if($number!==null&&$number!==''&&$number!==$r['certificate_number'])$p['errors'][]='Issued number conflict in '.$source.'; existing number will not be changed.';
            if(count($numbers[$r['certificate_number']]??[])>1)$p['warnings'][]='Duplicate issued number in the source; retained literally without renumbering.';
            if(mb_strlen($r['certificate_number'])>100)$p['errors'][]='Certificate number is too long for the tracking record.';
            if(trim($r['certificate_number'])===''&&$p['outcome']==='passed')$p['errors'][]='Passed student has no issued number in backup.';
            foreach(['provisional'=>'certificates','receipt'=>'received_certificates'] as $kind=>$folder){
                $indexes[$year][$folder]??=$this->files($root,$year,$folder);
                $ref=$r[$kind==='provisional'?'cert_name':'cert_received_name'];$stem=pathinfo(basename(str_replace('\\','/',$ref)),PATHINFO_FILENAME);
                $mapping=$photoMap[$key][$kind]??null;
                if($mapping){
                    if(($mapping['certificate_number']??null)!==$r['certificate_number']||empty($mapping['reason'])||basename($mapping['filename'])!==$mapping['filename'])throw new RuntimeException('Invalid reviewed photo mapping.');
                    $stem=pathinfo($mapping['filename'],PATHINFO_FILENAME);
                    if(!preg_match('/_'.preg_quote(trim($r['stu_id']),'/').'(?:G9)?_/i',$stem))throw new RuntimeException('Reviewed photo must contain the correct student ID.');
                    $p['photo_corrections'][$kind]=$mapping;
                }
                $files=$indexes[$year][$folder][mb_strtolower($stem)]??[];
                if(preg_match('/_(\d+)(?:G9)?_(?:Certificate|Cert|RCert)/i',$stem,$m)&&$m[1]!==trim($r['stu_id'])){
                    $p['warnings'][]=$kind.': workbook photo reference contains another student ID; photo was not imported.';$files=[];
                }
                if($mapping&&(count($files)!==1||hash_file('sha256',$files[0])!==($mapping['sha256']??null)))throw new RuntimeException('Reviewed photo is missing, ambiguous or changed.');
                if(count($files)===1)$p['files'][$kind]=['path'=>$files[0],'sha256'=>hash_file('sha256',$files[0])];
                elseif($ref!=='')$p['warnings'][]=$kind.': '.(count($files)>1?'ambiguous photo reference; no file chosen.':'referenced photo is missing.');
            }
            $received=mb_strtolower(trim($r['received_cert']))==='withdrew';
            $receivedDate=$received?$this->date($r['received_cert_Date'],$r['_date1904']??false):null;
            $certificateDate=$this->date($r['certDate']);
            if($receivedDate&&$certificateDate&&$receivedDate<$certificateDate)$p['warnings'][]='Historical receipt date is before the source certificate issue date; both dates were preserved for review.';
            if($received&&(!$receivedDate||trim($r['received_by'])===''||!isset($p['files']['receipt'])))$p['warnings'][]='Historical receipt is incomplete; verify date, recipient and signed photo.';
            if(!$received&&isset($p['files']['receipt']))$p['warnings'][]='Receipt image exists without a historical received status; status was not inferred from the image.';
            $p['values']=['number'=>$r['certificate_number'],'status'=>$received?'received':(isset($p['files']['provisional'])?'printed_signed':'awaiting'),
                'printed_signed_date'=>null,'received_date'=>$receivedDate,'recipient'=>$received?($r['received_by']?:null):null];
            if($c->certificateTracking){
                if(!empty($c->certificateTracking->import_source['managed_after_import'])||($c->certificateTracking->import_source['workbook_sha256']??null)!==$workbookHash||$c->certificateTracking->only(array_keys($p['values']))!==$p['values'])
                    $p['errors'][]='Tracking was already edited or belongs to another source; no overwrite allowed.';
            }
            $p['candidate_fingerprint']=$this->fingerprint($c);
            $p['import_source']=['workbook_sha256'=>$workbookHash,'sheet'=>$year,'row'=>$r['_row'],'certificate_date_raw'=>$r['certDate'],
                'certificate_date'=>$certificateDate,'certificate_filename'=>$r['cert_name'],'receipt_filename'=>$r['cert_received_name'],
                'received_status_raw'=>$r['received_cert'],'received_date_raw'=>$r['received_cert_Date'],'recipient_raw'=>$r['received_by'],
                'warnings'=>$p['warnings'],'photo_corrections'=>$p['photo_corrections']??[]];
            $plans[]=$p;
        }
        return $plans;
    }

    private function fingerprint(G9ExamCandidate $c): string
    {
        return hash('sha256',json_encode([$c->getAttributes(),$c->certificateTracking?->getAttributes(),$c->certificateNumber?->getAttributes(),$c->certificateFiles->map->getAttributes()->all()],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }

    public function import(User $user,array $p): string
    {
        if($p['errors'])return 'blocked';
        if(($p['outcome']??'')!=='passed')return 'ineligible';
        $stored=[];
        try{
            return DB::transaction(function()use($user,$p,&$stored){
                DB::table('tb_academic_year')->where('id',$p['academic_year_id'])->lockForUpdate()->firstOrFail();
                $c=G9ExamCandidate::lockForUpdate()->findOrFail($p['candidate_id']);$c->load(['certificateTracking','certificateNumber','certificateFiles']);
                if($this->fingerprint($c)!==$p['candidate_fingerprint'])throw new RuntimeException('Student or tracking changed since preview; rerun preflight.');
                if(!Access::allows($user,'import',$c->campus_id))abort(403);
                $tracking=app(G9CertificateTracking::class);$tracking->authorize($user,'certificates-manage',$c->campus_id);
                if($tracking->outcome($c)!=='passed')throw new RuntimeException('Student is no longer eligible.');
                $before=$c->certificateTracking?->getAttributes();$count=$c->certificateFiles->count();
                foreach($p['files'] as $kind=>$file){
                    if(!is_file($file['path'])||hash_file('sha256',$file['path'])!==$file['sha256'])throw new RuntimeException('Source photo changed after preflight.');
                    $tracking->store($c,$user,$kind,new UploadedFile($file['path'],basename($file['path']),null,null,true),$stored);
                }
                $changed=!$before||$count!==$c->certificateFiles()->count()||$c->certificateTracking->import_source!=$p['import_source'];
                if(!$changed)return 'unchanged';
                $saved=G9ExamCertificateTracking::updateOrCreate(['candidate_id'=>$c->id],$p['values']+[
                    'import_source'=>$p['import_source'],'version'=>($c->certificateTracking?->version??0)+1,'updated_by'=>$user->id]);
                DB::table('tb_g9_exam_event')->insert(['candidate_id'=>$c->id,'user_id'=>$user->id,'action'=>'certificate-backup-imported',
                    'details'=>json_encode(['source'=>$p['import_source'],'before'=>$before,'after'=>$saved->getAttributes(),'files'=>array_keys($p['files'])],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                    'created_at'=>now(),'updated_at'=>now()]);
                return 'imported';
            });
        }catch(\Throwable $e){if($stored)Storage::disk('local')->delete($stored);throw $e;}
    }
}
