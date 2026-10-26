<?php
namespace App\Services;
use App\Models\{G12ExamRecord,G12ExamFile,G12ExamImport,G12ExamCertificate,User};
use App\Support\{G12NationalExamFields as Fields,G12NationalExamPermissions as Access};
use Illuminate\Support\Facades\{DB,Storage,Validator};
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use ZipArchive;
class G12NationalExamFiles {
    public const KINDS=['portrait','soft_certificate','soft_certificate_received','hard_certificate','hard_certificate_received','other_documents'];
    public function __construct(private G12NationalExams $exams){}
    private function filename(string $name): string {
        $name=basename(str_replace('\\','/',$name));$name=preg_replace('/[\x00-\x1f<>:"\/\\\\|?*]/u','_',$name);$name=trim($name," .");
        if(!$name||strlen($name)>220)$this->exams->fail('Use a filename up to 220 bytes without directory separators.');return $name;
    }
    public function store(User $user,G12ExamRecord $record,string $kind,string $bytes,string $name,bool $replace=false): G12ExamFile {
        if(!in_array($kind,self::KINDS,true))$this->exams->fail('Select a document type.');
        $this->exams->authorize($user,$kind==='portrait'?'photos-upload':'documents-upload',$record->campus_id);
        $name=$this->filename($name);$size=strlen($bytes);if($size>20*1024*1024||!$size)$this->exams->fail('Each file must be between 1 byte and 20 MB.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $images=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if(!isset($images[$mime])&&($mime!=='application/pdf'||$kind==='portrait'))$this->exams->fail('Choose a JPG, PNG or WebP image'.($kind==='portrait'?'.':' or PDF document.'));
        if(isset($images[$mime])){$dimensions=@getimagesizefromstring($bytes);if(!$dimensions||$dimensions[0]*$dimensions[1]>40000000)$this->exams->fail('Choose an image up to 40 megapixels.');}
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));if(!in_array($ext,['jpg','jpeg','png','webp','pdf'],true))$name.='.'.($images[$mime]??'pdf');
        $storedName=$name;
        if($kind==='portrait'){
            $bytes=app(G12NationalExamPhoto::class)->webp($bytes);$mime='image/webp';$size=strlen($bytes);
            $storedName=pathinfo($name,PATHINFO_FILENAME).'.webp';
        }
        $path=null;
        try{return DB::transaction(function()use($user,$record,$kind,$bytes,$name,$storedName,$replace,$mime,$size,&$path){
            $record=G12ExamRecord::lockForUpdate()->findOrFail($record->id);$this->exams->authorize($user,$kind==='portrait'?'photos-upload':'documents-upload',$record->campus_id);
            if($kind==='portrait'&&$record->photo_path){$this->exams->authorize($user,'photos-replace',$record->campus_id);if(!$replace)$this->exams->fail('A photo already exists. Confirm Replace to keep the old version and upload a replacement.');}
            $hash=hash('sha256',$bytes);$duplicate=$record->files()->where('kind',$kind)->where('sha256',$hash)->first();
            if($duplicate){
                if($kind==='portrait'&&$record->photo_path!==$duplicate->path){$record->update(['photo_path'=>$duplicate->path,'version'=>$record->version+1]);$this->exams->event($user,'photo-version-restored',['file_id'=>$duplicate->id],$record->id);}
                return $duplicate;
            }
            if($kind==='portrait'){
                $path='stuimages_g12_national_exam/'.$record->academic_year.'/'.$storedName;
                Storage::disk('local')->makeDirectory(dirname($path));
                $stream=@fopen(Storage::disk('local')->path($path),'x+b');
                if(!$stream){$path='stuimages_g12_national_exam/'.$record->academic_year.'/versions/'.Str::uuid().'/'.$storedName;Storage::disk('local')->makeDirectory(dirname($path));$stream=@fopen(Storage::disk('local')->path($path),'x+b');}
                if(!$stream){$path=null;$this->exams->fail('The photo could not be stored. Check available storage.');}
                try{$offset=0;while($offset<$size){$written=fwrite($stream,substr($bytes,$offset));if(!$written)$this->exams->fail('The photo could not be stored completely. Try again.');$offset+=$written;}}finally{fclose($stream);}
            }else{
                $path='g12_national_exam_documents/'.$record->academic_year.'/'.$record->document_folder.'/'.$kind.'/'.Str::uuid().'/'.$name;
                if(!Storage::disk('local')->put($path,$bytes))$this->exams->fail('The file could not be stored. Try again.');
            }
            $file=$record->files()->create(['kind'=>$kind,'path'=>$path,'original_name'=>$name,'mime'=>$mime,'size'=>$size,'sha256'=>$hash,'uploaded_by'=>$user->id]);
            if($kind==='portrait')$record->update(['photo_path'=>$path,'version'=>$record->version+1]);
            $this->exams->event($user,'file-uploaded',['file_id'=>$file->id,'kind'=>$kind,'original_name'=>$name,'sha256'=>$hash,'replaced'=>$replace],$record->id);return $file;
        });}catch(\Throwable $e){if($path)Storage::disk('local')->delete($path);throw $e;}
    }
    public function collection(User $user,G12ExamRecord $record,string $type,array $data,?UploadedFile $file=null): G12ExamCertificate {
        $this->exams->authorize($user,'collection',$record->campus_id);
        $data=Validator::make($data,['status'=>'required|in:awaiting,available,collected,not_applicable,review','number'=>'nullable|string|max:100','available_date'=>'nullable|date_format:Y-m-d','collected_date'=>'required_if:status,collected|nullable|date_format:Y-m-d','recipient'=>'required_if:status,collected|nullable|string|max:255','version'=>'required|integer|min:1','reason'=>'nullable|string|max:1000'])->validate();
        if(!in_array($type,['soft','hard'],true))$this->exams->fail('Choose soft or hard certificate.');
        if(!empty($data['available_date'])&&!empty($data['collected_date'])&&$data['collected_date']<$data['available_date'])$this->exams->fail('Collection date cannot be before the school received the certificate.');
        $newFile=null;
        try{return DB::transaction(function()use($user,$record,$type,$data,$file,&$newFile){
            G12ExamRecord::lockForUpdate()->findOrFail($record->id);$cert=G12ExamCertificate::where('record_id',$record->id)->where('type',$type)->lockForUpdate()->firstOrFail();
            if($cert->version!==(int)$data['version'])$this->exams->fail('The certificate record changed. Reopen it before saving.');
            if($cert->status==='collected'){$this->exams->authorize($user,'collection-correct',$record->campus_id);if(mb_strlen(trim($data['reason']??''))<5)$this->exams->fail('Enter a reason of at least five characters to correct a collection.');}
            if($data['status']!=='collected'&&(!empty($data['recipient'])||!empty($data['collected_date'])))$this->exams->fail('Clear recipient and collection date when the certificate is not collected.');
            if($file)$newFile=$this->store($user,$record,$type.'_certificate_received',$file->get(),$file->getClientOriginalName());
            if($data['status']==='collected'&&!$record->files()->where('kind',$type.'_certificate_received')->exists())$this->exams->fail('Upload the signed certificate-copy photo or PDF before recording collection.');
            $before=$cert->toArray();$cert->fill(collect($data)->only(['status','number','available_date','collected_date','recipient'])->all()+['managed'=>true,'recorded_by'=>$user->id,'version'=>$cert->version+1])->save();
            $this->exams->event($user,'certificate-updated',['before'=>$before,'after'=>$cert->toArray(),'reason'=>$data['reason']??null,'receipt_file_id'=>$newFile?->id],$record->id);return $cert;
        });}catch(\Throwable $e){if($newFile?->wasRecentlyCreated)Storage::disk('local')->delete($newFile->path);throw $e;}
    }
    public function preview(User $user,string $year,array $files): G12ExamImport {
        $this->exams->authorize($user,'photos-upload');$this->exams->year($year);$entries=[];$paths=[];
        try{
            foreach($files as $file){
                $path=$file->store('g12_national_exam_uploads','local');$paths[]=$path;
                if(strtolower($file->getClientOriginalExtension())==='zip'){
                    $zip=new ZipArchive();if($zip->open(Storage::disk('local')->path($path),ZipArchive::RDONLY)!==true)$this->exams->fail('The ZIP file cannot be read.');
                    try{$total=0;if($zip->numFiles>10000)$this->exams->fail('A ZIP can contain up to 10,000 entries.');
                        for($i=0;$i<$zip->numFiles;$i++){
                            $stat=$zip->statIndex($i);$name=str_replace('\\','/',$stat['name']);$total+=$stat['size'];
                            if($total>500*1024*1024)$this->exams->fail('The expanded photo archive exceeds 500 MB.');
                            if(str_ends_with($name,'/')||str_starts_with($name,'__MACOSX/'))continue;
                            if(preg_match('~(^/|^[A-Za-z]:|(^|/)\.\.(/|$))~',$name))$this->exams->fail('The ZIP contains unsafe paths.');
                            if(!in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),['jpg','jpeg','png','webp'],true))continue;
                            if($stat['size']>20*1024*1024)$this->exams->fail('A photo exceeds 20 MB.');
                            $entries[]=['name'=>$this->filename($name),'path'=>$path,'entry'=>$i,'size'=>$stat['size']];
                        }
                    }finally{$zip->close();}
                }else $entries[]=['name'=>$this->filename($file->getClientOriginalName()),'path'=>$path,'size'=>$file->getSize()];
            }
            if(!$entries)$this->exams->fail('No JPG, PNG or WebP photos were found.');
            if(count($entries)>5000)$this->exams->fail('Upload up to 5,000 photos per batch.');
            $records=G12ExamRecord::where('academic_year',$year)->whereIn('campus_id',Access::campusIds($user,'photos-upload'))->get();$counts=array_count_values(array_column($entries,'name'));
            foreach($entries as &$entry){
                $key=Fields::filenameKey($entry['name']);$match=$records->filter(fn($r)=>$r->photo_filename&&Fields::filenameKey($r->photo_filename)===$key);
                if($match->isEmpty())$match=$records->filter(fn($r)=>$key===mb_strtolower($r->student_code)||preg_match('/(?<![\pL\pN])'.preg_quote($r->student_code,'/').'(?![\pL\pN])/u',$key));
                $entry['record_id']=$match->count()===1?$match->first()->id:null;$entry['student']=$match->count()===1?$match->first()->name_en:null;
                $entry['status']=$counts[$entry['name']]>1?'duplicate':($match->count()>1?'ambiguous':($match->isEmpty()?'unmatched':($match->first()->photo_path?'existing':'matched')));
            }unset($entry);
            return G12ExamImport::create(['user_id'=>$user->id,'kind'=>'photos','filename'=>count($entries).' student photos','payload'=>['year'=>$year,'entries'=>$entries,'completed'=>[]],'expires_at'=>now()->addHours(4)]);
        }catch(\Throwable $e){Storage::disk('local')->delete($paths);throw $e;}
    }
    public function process(User $user,G12ExamImport $batch,int $index,?int $recordId,bool $replace): array {
        $this->exams->authorize($user,'photos-upload');
        return DB::transaction(function()use($user,$batch,$index,$recordId,$replace){
            $batch=G12ExamImport::lockForUpdate()->findOrFail($batch->id);abort_unless($batch->user_id===$user->id,403);
            if($batch->kind!=='photos'||$batch->expires_at->isPast())$this->exams->fail('This photo preview expired. Upload the photos again.');
            $payload=$batch->payload;$entry=$payload['entries'][$index]??null;if(!$entry)$this->exams->fail('Photo not found in this batch.');
            if(isset($payload['completed'][$index]))return ['message'=>'Already processed.','file_id'=>$payload['completed'][$index]];
            $record=G12ExamRecord::findOrFail($recordId?:$entry['record_id']);$this->exams->authorize($user,'photos-upload',$record->campus_id);
            if($record->academic_year!==$payload['year'])$this->exams->fail('Select a student from the same academic year.');
            if(isset($entry['entry'])){$zip=new ZipArchive();if($zip->open(Storage::disk('local')->path($entry['path']),ZipArchive::RDONLY)!==true)$this->exams->fail('The staged ZIP is missing. Upload it again.');try{$bytes=$zip->getFromIndex($entry['entry']);}finally{$zip->close();}}
            else $bytes=Storage::disk('local')->get($entry['path']);
            if($bytes===false)$this->exams->fail('The photo could not be read.');
            $file=$this->store($user,$record,'portrait',$bytes,$entry['name'],$replace);$payload['completed'][$index]=$file->id;
            $record->refresh();if(!$record->photo_filename){
                $record->update(['photo_filename'=>$entry['name'],'data'=>array_replace($record->data,['photo_filename'=>$entry['name']]),'version'=>$record->version+1]);
                $this->exams->event($user,'photo-filename-linked',['filename'=>$entry['name'],'file_id'=>$file->id],$record->id);
            }
            $count=count($payload['completed']);$batch->update(['payload'=>$payload,'status'=>$count===count($payload['entries'])?'confirmed':'preview','summary'=>['uploaded'=>$count,'total'=>count($payload['entries'])],'confirmed_at'=>now()]);
            return ['message'=>'Photo uploaded.','file_id'=>$file->id];
        });
    }
}
