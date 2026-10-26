<?php
namespace App\Services;
use App\Models\{G12ExamRecord,G12ExamCenter,G12ExamCertificate,G12ExamImport,Student,SchoolInfo,User};
use App\Support\{G12NationalExamFields as Fields,G12NationalExamPermissions as Access,G12NationalExamWorkbook};
use Illuminate\Support\Facades\{DB,Storage,Validator};
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
class G12NationalExams {
    public function fail(string $message): never {throw ValidationException::withMessages(['record'=>$message]);}
    public function authorize(User $user,string $action,?int $campus=null): void {abort_unless(Access::allows($user,$action,$campus),403);}
    public function event(User $user,string $action,array $details,?int $record=null): void {DB::table('tb_g12_exam_event')->insert(['record_id'=>$record,'user_id'=>$user->id,'action'=>$action,'details'=>json_encode($details,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);}
    public function year(string $year): string {
        if(!preg_match('/^(\d{4})-(\d{4})$/',$year,$m)||(int)$m[2]!==((int)$m[1]+1))$this->fail('Use an academic year such as 2025-2026.');return $year;
    }
    public function status(string $grade): string {
        $grade=mb_strtoupper(trim($grade));return in_array($grade,['A','B','C','D','E'],true)?'passed':(in_array($grade,['F','FAILED','ធ្លាក់'],true)?'failed':'pending');
    }
    private function folder(string $name,string $code): string {
        $name=trim(preg_replace('/[^A-Za-z0-9_-]+/','_',$name),'_');$id=preg_replace('/[^A-Za-z0-9_-]+/','_',$code);
        return substr($name?:'Student',0,120).'_'.substr($id,0,70).'_'.substr(hash('sha256',$code),0,8);
    }
    public function save(User $user,array $input,?G12ExamRecord $record=null,bool $import=false): G12ExamRecord {
        $data=Validator::make($input,['student_id'=>'required|string|max:80','academic_year'=>'required|string|max:9','student_name_en'=>'required|string|max:255','student_name_kh'=>'nullable|string|max:255','campus_id'=>'required|integer|exists:tb_school_info,id','center_id'=>'nullable|integer|exists:tb_g12_exam_center,id','grade'=>'nullable|string|max:80','g12_letter_grade'=>'nullable|string|max:40','version'=>'nullable|integer|min:1','photo_filename'=>'nullable|string|max:255'])->validate();
        foreach($input as $key=>$value)if(is_array($value)||is_object($value)||mb_strlen((string)$value)>5000)$this->fail('Enter text values up to 5,000 characters per field.');
        $this->year($data['academic_year']);$this->authorize($user,$import?'import':($record?'edit':'create'),(int)$data['campus_id']);
        if($record)$this->authorize($user,$import?'import':'edit',$record->campus_id);
        return DB::transaction(function()use($user,$input,$data,$record,$import){
            if($record){$record=G12ExamRecord::lockForUpdate()->findOrFail($record->id);if(!$import&&(int)($data['version']??0)!==$record->version)$this->fail('This student was changed in another window. Reopen the record.');}
            if(G12ExamRecord::where('academic_year',$data['academic_year'])->where('student_code',$data['student_id'])->when($record,fn($q)=>$q->where('id','!=',$record->id))->exists())$this->fail('This student already has an exam record for the academic year.');
            if($record&&($record->academic_year!==$data['academic_year']||$record->student_code!==$data['student_id']))$this->fail('Student ID and academic year are fixed after creation to preserve linked documents.');
            $before=$record?->toArray();$values=$input;unset($values['version'],$values['campus_id'],$values['center_id'],$values['_row']);
            $values=array_replace($record?->data??[],$values);
            $student=Student::where('student_id',$data['student_id'])->get(['id']);
            $record??=new G12ExamRecord();$record->fill(['academic_year'=>$data['academic_year'],'student_code'=>$data['student_id'],'student_id'=>$student->count()===1?$student->first()->id:null,
                'campus_id'=>(int)$data['campus_id'],'center_id'=>$data['center_id']??null,'name_en'=>$data['student_name_en'],'name_kh'=>$input['student_name_kh']??null,
                'grade'=>$input['grade']??null,'overall_grade'=>$input['g12_letter_grade']??null,'result_status'=>$this->status($input['g12_letter_grade']??''),
                'photo_filename'=>$input['photo_filename']??$record->photo_filename,'data'=>$values,'document_folder'=>$record->document_folder?:$this->folder($data['student_name_en'],$data['student_id']),
                'version'=>$record->exists?$record->version+1:1])->save();
            foreach(['soft','hard'] as $type){
                $cert=G12ExamCertificate::firstOrCreate(['record_id'=>$record->id,'type'=>$type]);
                if($import&&!$cert->managed){
                    $raw=trim($input['received_'.$type.'_certificate']??'');$status=match(mb_strtolower($raw)){'not yet','not_yet',''=>'awaiting','withdrew'=>'collected','failed'=>'not_applicable',default=>'review'};
                    $date=$input['received_'.$type.'_cert_date']??null;
                    $cert->update(['status'=>$status,'imported_status'=>$raw?:null,'collected_date'=>$status==='collected'&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$date??'')?$date:null,
                        'recipient'=>$status==='collected'?($input['received_'.$type.'_cert_by']??null):null]);
                }
            }
            $this->event($user,$import?'import-record':($before?'edit-record':'create-record'),['before'=>$before,'after'=>$record->toArray()],$record->id);return $record;
        });
    }
    public function preview(User $user,UploadedFile $file): G12ExamImport {
        $this->authorize($user,'import');$book=app(G12NationalExamWorkbook::class)->read($file->getRealPath());
        $path=$file->store('g12_national_exam_imports','local');
        return G12ExamImport::create(['user_id'=>$user->id,'kind'=>'excel','filename'=>$file->getClientOriginalName(),'source_path'=>$path,'payload'=>$book,'expires_at'=>now()->addHours(2)]);
    }
    public function inspect(User $user,G12ExamImport $batch,array $maps=[]): array {
        abort_unless($batch->user_id===$user->id,403);$this->authorize($user,'import');
        if($batch->kind!=='excel'||$batch->status!=='preview'||$batch->expires_at->isPast())$this->fail('This Excel preview has expired or was already imported.');
        $payload=$batch->payload;$campusIds=Access::campusIds($user,'import');$campuses=SchoolInfo::whereIn('id',$campusIds)->get()->keyBy(fn($c)=>Fields::centerKey($c->campus_name_en));
        $centers=G12ExamCenter::get()->keyBy('name_key');$settings=collect($payload['centers'])->filter(fn($c)=>!empty($c['exam_center']))->keyBy(fn($c)=>Fields::centerKey($c['exam_center']));
        $existingRecords=G12ExamRecord::whereIn('academic_year',array_unique(array_column($payload['records'],'academic_year')))->get()->keyBy(fn($r)=>$r->academic_year.'|'.$r->student_code);
        $seen=[];$rows=[];$missingCampuses=[];$missingCenters=[];$errors=0;$new=0;$updates=0;
        foreach($payload['records'] as $r){
            $issues=[];$campusName=$r['campus']??'';$campusId=$maps['campuses'][$campusName]??$campuses->get(Fields::centerKey($campusName))?->id;
            if(!$campusId){$missingCampuses[$campusName]=true;$issues[]='Map campus';}
            elseif(!in_array((int)$campusId,$campusIds,true))$issues[]='Campus access denied';
            $centerName=$r['g12_exam_center']??'';$centerKey=Fields::centerKey($centerName);$center=$centers->get($centerKey);$choice=$maps['centers'][$centerName]??null;
            if($centerName&&!$center&&!$choice&&!$settings->has($centerKey)){$missingCenters[$centerName]=true;$issues[]='Map exam center';}
            if($choice&&$choice!=='new'&&$choice!=='none'&&!(str_starts_with((string)$choice,'@')&&$settings->has(Fields::centerKey(substr($choice,1))))&&!$centers->contains(fn($c)=>(string)$c->id===(string)$choice))$issues[]='Invalid center mapping';
            if($choice==='new'&&!Access::allows($user,'centers-manage'))$issues[]='Manage Exam Centers permission required';
            $key=($r['academic_year']??'').'|'.($r['student_id']??'');
            if(empty($r['student_id'])||empty($r['student_name_en']))$issues[]='Missing Student ID / English name';
            foreach(['student_id'=>80,'student_name_en'=>255,'student_name_kh'=>255,'grade'=>80,'g12_letter_grade'=>40,'photo_filename'=>255] as $field=>$max)if(mb_strlen((string)($r[$field]??''))>$max)$issues[]=$field.' exceeds '.$max.' characters';
            if(!preg_match('/^(\d{4})-(\d{4})$/',$r['academic_year']??'',$m)||(isset($m[2])&&(int)$m[2]!==((int)$m[1]+1)))$issues[]='Invalid academic year';
            if(isset($seen[$key]))$issues[]='Duplicate Student ID / year';$seen[$key]=true;
            $existing=$existingRecords->get($key);
            if($existing&&!in_array($existing->campus_id,$campusIds,true))$issues[]='Existing record is outside your campus';
            if($existing)$updates++;else $new++;if($issues)$errors++;
            $rows[]=['row'=>$r['_row'],'student_id'=>$r['student_id']??'','name'=>$r['student_name_en']??'','year'=>$r['academic_year']??'','campus'=>$campusName,'center'=>$centerName,'action'=>$existing?'Update':'New','issues'=>$issues,'version'=>$existing?->version];
        }
        return ['rows'=>$rows,'total'=>count($rows),'new'=>$new,'updates'=>$updates,'errors'=>$errors,'missing_campuses'=>array_keys($missingCampuses),'missing_centers'=>array_keys($missingCenters),'settings_centers'=>$settings->values()->all()];
    }
    public function confirm(User $user,G12ExamImport $batch,array $maps,array $versions): array {
        return DB::transaction(function()use($user,$batch,$maps,$versions){
            $batch=G12ExamImport::lockForUpdate()->findOrFail($batch->id);abort_unless($batch->user_id===$user->id,403);
            if($batch->kind!=='excel'||$batch->status!=='preview'||$batch->expires_at->isPast())$this->fail('This preview has expired or was already imported. Upload the workbook again.');
            $preview=$this->inspect($user,$batch,$maps);if($preview['errors'])$this->fail('Resolve all import errors and mappings before confirming.');
            foreach($preview['rows'] as $row)if(($versions[(string)$row['row']]??null)!==$row['version'])$this->fail('Existing student data changed after preview. Refresh the preview.');
            $payload=$batch->payload;$centers=G12ExamCenter::get()->keyBy('name_key');
            foreach($payload['centers'] as $setting){$name=trim($setting['exam_center']??'');if(!$name)continue;$key=Fields::centerKey($name);
                if(!$centers->has($key)){$this->authorize($user,'centers-manage');$centers[$key]=G12ExamCenter::create(['source_id'=>$setting['excenter_id']??null,'name'=>$name,'name_key'=>$key]);}}
            $campuses=SchoolInfo::get()->keyBy(fn($c)=>Fields::centerKey($c->campus_name_en));
            foreach($payload['records'] as $r){
                $name=$r['g12_exam_center']??'';$center=$centers->get(Fields::centerKey($name));$choice=$maps['centers'][$name]??null;
                if($choice==='new'){$this->authorize($user,'centers-manage');$center=G12ExamCenter::firstOrCreate(['name_key'=>Fields::centerKey($name)],['name'=>$name]);$centers[$center->name_key]=$center;}
                elseif($choice==='none')$center=null;
                elseif($choice&&str_starts_with((string)$choice,'@'))$center=$centers->get(Fields::centerKey(substr($choice,1)));
                elseif($choice)$center=G12ExamCenter::findOrFail((int)$choice);
                $r['center_id']=$center?->id;$r['campus_id']=$maps['campuses'][$r['campus']]??$campuses->get(Fields::centerKey($r['campus']))?->id;
                $existing=G12ExamRecord::where('academic_year',$r['academic_year'])->where('student_code',$r['student_id'])->lockForUpdate()->first();
                $this->save($user,$r,$existing,true);
            }
            $summary=collect($preview)->only(['total','new','updates'])->all();$batch->update(['status'=>'confirmed','confirmed_at'=>now(),'summary'=>$summary]);
            $this->event($user,'import-confirmed',['batch_id'=>$batch->id,'summary'=>$summary,'mappings'=>$maps]);return $summary;
        });
    }
}
