<?php

namespace App\Services;

use App\Models\{G9ExamCandidate,G9ExamCertificateFile,G9ExamCertificateTracking,User};
use App\Support\{G9NationalExamPermissions as Access,G9ScoreSummary};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage,Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class G9CertificateTracking
{
    public function authorize(User $user,string $action,int $campus): void
    {
        abort_unless(Access::allows($user,$action,$campus),403);
    }

    public function outcome(G9ExamCandidate $candidate): string
    {
        if($candidate->attendance_status==='absent')return 'absent';
        $score=G9ScoreSummary::calculate($candidate->data);
        if($score['complete'])return $score['total']<260?'failed':'passed';
        return mb_strtolower(trim($candidate->legacy_data['received_cert']??''))==='failed'?'failed':'pending';
    }

    /** Read historical imports without creating or changing records on a GET. */
    public function state(G9ExamCandidate $candidate): array
    {
        $number=$candidate->certificateNumber?->number?:($candidate->certificateTracking?->number?:($candidate->legacy_data['certificate_number']??''));
        if($saved=$candidate->certificateTracking)return array_merge($saved->only(['status','number','printed_signed_date','received_date','recipient','version']),[
            'number'=>$number,'legacy'=>(bool)$saved->import_source,
            'certificate_date'=>$saved->import_source['certificate_date']??null,
            'review_notes'=>$saved->import_source['warnings']??[],
            'needs_review'=>!empty($saved->import_source['warnings'])]);
        $legacy=$candidate->legacy_data??[];$received=mb_strtolower(trim($legacy['received_cert']??''))==='withdrew';
        return ['status'=>$received?'received':'awaiting','number'=>$number,
            'printed_signed_date'=>null,'received_date'=>$received?($legacy['received_cert_Date']??null):null,
            'recipient'=>$received?($legacy['received_by']??''):'','version'=>0,'legacy'=>$received,
            'needs_review'=>$received&&(empty($legacy['received_cert_Date'])||empty($legacy['received_by']))];
    }

    public function summary(G9ExamCandidate $candidate,User $user,bool $history=false): array
    {
        $d=$candidate->data;$score=G9ScoreSummary::calculate($d);$outcome=$this->outcome($candidate);$eligible=$outcome==='passed';
        $tracking=$this->state($candidate);$view=Access::allows($user,'certificates-documents-view',$candidate->campus_id);
        $files=$view?$candidate->certificateFiles->sortByDesc('id'):collect();
        $documents=$files->unique('kind')->mapWithKeys(fn($file)=>[$file->kind=>$this->fileSummary($file)])->all();
        $result=['id'=>$candidate->id,'student_code'=>$candidate->student_code,'name_kh'=>$candidate->name_kh,'name_en'=>$candidate->name_en,
            'photo_url'=>$candidate->photo_path?asset('storage/'.$candidate->photo_path):null,'gender'=>$d['gender']??'',
            'year'=>$candidate->academicYear?->academic_year??'','campus'=>$candidate->campus?->campus_name_en??'',
            'grade'=>$d['grade']??'','exam_center'=>$d['exam_center']??'','exam_date'=>$d['exam_date']??'',
            'room'=>$d['room_number']??'','seat'=>$d['seat_number']??'','exam_language'=>$d['exam_language']??'',
            'outcome'=>$outcome,'letter_grade'=>$eligible?$score['grade']:null,'eligible'=>$eligible,
            'tracking'=>$tracking,'documents'=>$documents,'can_view_documents'=>$view,
            'can_manage'=>$eligible&&Access::allows($user,'certificates-manage',$candidate->campus_id),
            'can_correct'=>Access::allows($user,'certificates-correct',$candidate->campus_id),
            'legacy_source_name'=>$view?($candidate->certificateTracking?->import_source['certificate_filename']??$candidate->legacy_data['cert_name']??''):'',
            'legacy_receipt_name'=>$view?($candidate->certificateTracking?->import_source['receipt_filename']??$candidate->legacy_data['cert_received_name']??''):''];
        if($history)$result['document_history']=$files->map(fn($f)=>$this->fileSummary($f))->values()->all();
        return $result;
    }

    public function fileSummary(G9ExamCertificateFile $file): array
    {
        $url=route('g9-national-exams.certificates.file',['file'=>$file->id]);
        return ['id'=>$file->id,'kind'=>$file->kind,'original_name'=>$file->original_name,'mime'=>$file->mime,'size'=>$file->size,
            'is_image'=>str_starts_with($file->mime,'image/'),'url'=>$url,'thumbnail_url'=>$file->thumbnail_path?$url.'?thumbnail=1':$url,
            'download'=>$url.'?download=1','uploaded_at'=>$file->created_at?->toIso8601String()];
    }

    public function save(User $user,G9ExamCandidate $candidate,array $input,array $uploads): void
    {
        $this->authorize($user,'certificates-manage',$candidate->campus_id);
        foreach(['number','recipient','reason'] as $field)if(isset($input[$field])&&is_string($input[$field]))$input[$field]=trim($input[$field]);
        $data=Validator::make($input,[
            'status'=>'required|in:awaiting,printed_signed,received','number'=>'nullable|string|max:100',
            'printed_signed_date'=>'nullable|date_format:Y-m-d',
            'received_date'=>'nullable|required_if:status,received|date_format:Y-m-d',
            'recipient'=>'nullable|required_if:status,received|string|max:255',
            'version'=>'required|integer|min:0','reason'=>'nullable|string|max:1000',
        ])->validate();
        $stored=[];
        try{
            DB::transaction(function()use($user,$candidate,$data,$uploads,&$stored){
                // The same year lock serializes tracking number edits and bulk number generation.
                DB::table('tb_academic_year')->where('id',$candidate->academic_year_id)->lockForUpdate()->firstOrFail();
                // Lock the candidate too: the first save has no tracking row to lock yet.
                $candidate=G9ExamCandidate::lockForUpdate()->findOrFail($candidate->id);
                $this->authorize($user,'certificates-manage',$candidate->campus_id);
                if($this->outcome($candidate)!=='passed')$this->fail('status','Only students who passed the examination can receive a certificate or upload certificate documents.');
                $candidate->load('certificateTracking');$before=$this->state($candidate);
                if($before['version']!==(int)$data['version'])$this->fail('version','This certificate changed. Reopen it before saving.');
                app(G9CertificateNumbers::class)->validateTrackingNumber($candidate,(string)($data['number']??$before['number']??''),(string)($before['number']??''));
                if($before['status']==='received'){
                    $this->authorize($user,'certificates-correct',$candidate->campus_id);
                    if(mb_strlen(trim($data['reason']??''))<5)$this->fail('reason','Enter a correction reason of at least five characters.');
                }
                if($data['status']==='awaiting'&&!empty($data['printed_signed_date']))$this->fail('printed_signed_date','Clear the printed and signed date while awaiting printing.');
                if($data['status']!=='received'&&(!empty($data['received_date'])||!empty($data['recipient'])))$this->fail('received_date','Clear the received date and recipient unless the student has received the certificate.');
                if(!empty($data['received_date'])&&!empty($data['printed_signed_date'])&&$data['received_date']<$data['printed_signed_date'])$this->fail('received_date','Student receipt cannot be before the certificate was printed and signed.');
                foreach(['provisional','receipt'] as $kind)if(($uploads[$kind]??null) instanceof UploadedFile)$this->store($candidate,$user,$kind,$uploads[$kind],$stored);
                if($data['status']==='received'&&!$candidate->certificateFiles()->where('kind','receipt')->exists())$this->fail('receipt_file','Upload the student-signed certificate copy before recording receipt.');
                $values=collect($data)->only(['status','number','printed_signed_date','received_date','recipient'])->map(fn($v)=>$v===''?null:$v)->all();
                if(!empty($before['number']))$values['number']=$before['number'];
                if($source=$candidate->certificateTracking?->import_source){
                    // A later backup replay must never replace a document corrected through the normal workflow.
                    $values['import_source']=array_merge($source,['managed_after_import'=>true,'reviewed_at'=>now()->toIso8601String(),'reviewed_by'=>$user->id]);
                }
                $tracking=G9ExamCertificateTracking::updateOrCreate(['candidate_id'=>$candidate->id],$values+['version'=>$before['version']+1,'updated_by'=>$user->id]);
                DB::table('tb_g9_exam_event')->insert(['candidate_id'=>$candidate->id,'user_id'=>$user->id,'action'=>'certificate-updated',
                    'details'=>json_encode(['before'=>$before,'after'=>$tracking->only(array_keys($values)),'reason'=>$data['reason']??null,
                        'files'=>array_keys(array_filter($uploads))],JSON_UNESCAPED_UNICODE),'created_at'=>now(),'updated_at'=>now()]);
            });
        }catch(\Throwable $e){if($stored)Storage::disk('local')->delete($stored);throw $e;}
    }

    /** Shared private storage pipeline for uploads and verified historical imports. Caller owns transaction/cleanup. */
    public function store(G9ExamCandidate $candidate,User $user,string $kind,UploadedFile $upload,array &$stored): void
    {
        $this->authorize($user,'certificates-manage',$candidate->campus_id);
        if(!in_array($kind,['provisional','receipt'],true)||$this->outcome($candidate)!=='passed')$this->fail('status','Only passed students can have certificate documents imported or uploaded.');
        $bytes=$upload->get();if(!$bytes||strlen($bytes)>20*1024*1024)$this->fail($kind.'_file','Choose a file between 1 byte and 20 MB.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if(!in_array($mime,['image/jpeg','image/png','image/webp','application/pdf'],true))$this->fail($kind.'_file','Choose a JPG, PNG, WebP or PDF document.');
        $name=basename(str_replace('\\','/',$upload->getClientOriginalName()));$name=trim(preg_replace('/[\x00-\x1f<>:"\/\\\\|?*]/u','_',$name),' .');
        if($name===''||strlen($name)>220)$this->fail($kind.'_file','Use a filename up to 220 bytes.');
        $thumbnail=null;$hash=hash('sha256',$bytes);
        if($candidate->certificateFiles()->where('kind',$kind)->latest('id')->first()?->sha256===$hash)return;
        if($mime!=='application/pdf'){
            $optimized=app(G12CertificateImage::class)->optimize($bytes);$bytes=$optimized['master'];$thumbnail=$optimized['thumbnail'];$mime='image/webp';
        }elseif(!str_starts_with(ltrim($bytes),'%PDF-'))$this->fail($kind.'_file','Choose a readable PDF document.');
        $directory='g9_national_exam_documents/'.$candidate->academic_year_id.'/'.$candidate->id.'/'.$kind.'/'.Str::uuid();
        $path=$directory.'/document.'.($mime==='application/pdf'?'pdf':'webp');$thumbnailPath=$thumbnail!==null?$directory.'/thumbnail.webp':null;
        foreach(array_filter([$path=>$bytes,$thumbnailPath??''=>$thumbnail]) as $target=>$content){
            $stored[]=$target;if(!Storage::disk('local')->put($target,$content))$this->fail($kind.'_file','The document could not be stored. Try again.');
        }
        $candidate->certificateFiles()->create(['kind'=>$kind,'path'=>$path,'thumbnail_path'=>$thumbnailPath,'original_name'=>$name,
            'mime'=>$mime,'size'=>strlen($bytes),'sha256'=>$hash,'uploaded_by'=>$user->id]);
    }

    private function fail(string $field,string $message): never {throw ValidationException::withMessages([$field=>$message]);}
}
