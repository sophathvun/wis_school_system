<?php

namespace App\Http\Controllers;

use App\Models\{AcademicYear,G9ExamCandidate};
use App\Services\{G9ExamImports,G9ExamPhoto,G12NationalExamPhoto};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class G9BulkPhotoController
{
    private function campuses(Request $request,string $action='photos'): array
    {
        return array_values(array_intersect(app(G9ExamImports::class)->campusIds($request->user(),'photos'),app(G9ExamImports::class)->campusIds($request->user(),$action)));
    }
    private function key(string $name): string
    {
        return mb_strtolower(pathinfo(basename(str_replace('\\','/',$name)),PATHINFO_FILENAME));
    }
    private function fail(string $message): never {throw ValidationException::withMessages(['files'=>$message]);}
    public function index(Request $request)
    {
        $ids=$this->campuses($request);abort_unless($ids,403);
        return view('g9-national-exams.bulk-photos',['years'=>AcademicYear::orderByDesc('academic_year')->get(),
            'canUpload'=>(bool)$this->campuses($request,'photos-upload'),'canReplace'=>(bool)$this->campuses($request,'photos-replace')]);
    }
    public function preview(Request $request)
    {
        abort_unless($this->campuses($request,'photos-upload'),403);
        if(str_contains($request->header('Accept',''),'application/x-ndjson'))return $this->streamProgress(fn($emit)=>$this->preparePreview($request,$emit));
        return response()->json($this->preparePreview($request));
    }
    private function preparePreview(Request $request,?callable $progress=null): array
    {
        $ids=$this->campuses($request,'photos-upload');abort_unless($ids,403);
        // Expired previews cannot be confirmed; remove this user's temporary images.
        foreach(DB::table('tb_g9_photo_batch')->where('user_id',$request->user()->id)->where('status','preview')->where('expires_at','<',now())->get() as $expired){
            Storage::disk('local')->deleteDirectory($expired->folder);
            DB::table('tb_g9_photo_batch')->where('id',$expired->id)->update(['status'=>'expired','payload'=>'[]','updated_at'=>now()]);
        }
        $input=$request->validate(['academic_year_id'=>['required','integer','exists:tb_academic_year,id'],'replace_existing'=>['sometimes','boolean'],
            'files'=>['required','array','min:1','max:200'],'files.*'=>['required','file','max:51200']]);
        $replace=$request->boolean('replace_existing');$replaceIds=$this->campuses($request,'photos-replace');
        if($replace)abort_unless($replaceIds,403);
        $candidates=G9ExamCandidate::where('academic_year_id',$input['academic_year_id'])->whereIn('campus_id',$ids)->get();
        abort_if($candidates->isEmpty(),422,'Import candidate records for this academic year before uploading photos.');
        $matches=$candidates->filter(fn($r)=>!empty($r->data['photo_filename']))->groupBy(fn($r)=>$this->key($r->data['photo_filename']));
        if($progress)$progress(['type'=>'progress','stage'=>'reading','processed'=>0,'total'=>0,'percent'=>0]);
        // Count supported images before optimization, including ZIP subfolders.
        $sources=[];$imageCount=0;$expandedBytes=0;
        foreach($request->file('files') as $file){
            $name=$file->getClientOriginalName();$extension=strtolower($file->getClientOriginalExtension());$images=[];
            if($extension!=='zip'){
                if(!in_array($extension,['jpg','jpeg','png','webp'],true))$this->fail('Choose JPG, PNG, WebP images or a ZIP archive.');
                $images[]=['name'=>$name,'index'=>null];$expandedBytes+=$file->getSize();
            }else{
                $zip=new ZipArchive();if($zip->open($file->getRealPath(),ZipArchive::RDONLY)!==true)$this->fail('The ZIP archive is invalid.');
                try{
                    if($zip->numFiles>10000)$this->fail('The ZIP archive contains too many entries.');
                    for($i=0;$i<$zip->numFiles;$i++){
                        $stat=$zip->statIndex($i);$name=$stat['name'];if(str_ends_with($name,'/')||str_starts_with($name,'__MACOSX/')||str_starts_with(basename($name),'._'))continue;
                        if(!in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),['jpg','jpeg','png','webp'],true))continue;
                        if(($stat['size']??0)>5*1024*1024)$this->fail('A ZIP image exceeds 5 MB: '.basename($name));
                        $expandedBytes+=$stat['size']??0;$images[]=['name'=>$name,'index'=>$i];
                    }
                }finally{$zip->close();}
            }
            $imageCount+=count($images);if($imageCount>5000)$this->fail('Upload at most 5,000 images per batch.');
            if($expandedBytes>200*1024*1024)$this->fail('Upload at most 200 MB of expanded image data per batch.');
            $sources[]=['file'=>$file,'images'=>$images,'zip'=>$extension==='zip'];
        }
        if(!$imageCount)$this->fail('No supported images were found.');
        $folder='g9-photo-previews/'.Str::uuid();$entries=[];$totalBytes=0;$lastPercent=-1;
        $notify=function(string $stage,int $processed)use($progress,$imageCount,&$lastPercent){
            $percent=min(99,(int)floor((($stage==='matching'?$imageCount:0)+$processed)/($imageCount*2)*100));
            if($progress&&($percent!==$lastPercent||$processed===$imageCount)){$progress(['type'=>'progress','stage'=>$stage,'processed'=>$processed,'total'=>$imageCount,'percent'=>$percent]);$lastPercent=$percent;}
        };
        $notify('optimizing',0);
        $add=function(string $name,string $bytes)use(&$entries,&$totalBytes,$folder,$notify){
            $totalBytes+=strlen($bytes);if($totalBytes>200*1024*1024)$this->fail('Upload at most 200 MB of expanded image data per batch.');
            if(count($entries)>=5000)$this->fail('Upload at most 5,000 images per batch.');
            $name=basename(str_replace('\\','/',$name));
            if(!in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),['jpg','jpeg','png','webp'],true))return;
            $entry=['filename'=>mb_substr($name,0,255),'key'=>$this->key($name),'status'=>'uploaded','candidate_id'=>null,'message'=>''];
            if(strlen($bytes)>5*1024*1024){$entry['status']='invalid';$entry['message']='Image exceeds 5 MB.';}
            else{
                try{$optimized=app(G12NationalExamPhoto::class)->webp($bytes);$entry['staged']=$folder.'/'.Str::uuid().'.webp';
                    if(!Storage::disk('local')->put($entry['staged'],$optimized))throw new \RuntimeException('Cannot stage the optimized image.');
                }catch(ValidationException $error){$entry['status']='invalid';$entry['message']=implode(' ',array_merge(...array_values($error->errors())));}
            }
            $entries[]=$entry;
            $notify('optimizing',count($entries));
        };
        try{
            foreach($sources as $source){
                $zip=null;
                if($source['zip']){$zip=new ZipArchive();if($zip->open($source['file']->getRealPath(),ZipArchive::RDONLY)!==true)$this->fail('The ZIP archive is invalid.');}
                try{
                    foreach($source['images'] as $image){
                        $bytes=$zip?$zip->getFromIndex($image['index']):$source['file']->get();
                        if($bytes===false)$this->fail('An image in the ZIP cannot be read.');$add($image['name'],$bytes);
                    }
                }finally{if($zip)$zip->close();}
            }
            if(!$entries)$this->fail('No supported images were found.');
            $duplicates=collect($entries)->countBy('key');$matched=[];
            $matchedCount=0;
            foreach($entries as &$entry){try{
                if($entry['status']==='invalid')continue;
                if($duplicates[$entry['key']]>1){$entry['status']='duplicate';$entry['message']='Multiple uploaded files have the same photo name.';continue;}
                $records=$matches->get($entry['key'],collect());
                if($records->isEmpty()){$entry['status']='unmatched';$entry['message']='No candidate imageName matches this filename.';continue;}
                if($records->count()>1){$entry['status']='ambiguous';$entry['message']='More than one candidate has this photo name.';continue;}
                $record=$records->first();$matched[$record->id]=true;
                $entry+=['student_code'=>$record->student_code,'name_kh'=>$record->name_kh,'name_en'=>$record->name_en,'version'=>$record->version,'old_path'=>$record->photo_path];$entry['candidate_id']=$record->id;
                if($record->photo_path&&(!$replace||!in_array($record->campus_id,$replaceIds,true))){$entry['status']='existing';$entry['message']='Existing photo preserved.';}
                else{$entry['status']='ready';$entry['message']=$record->photo_path?'Replace existing photo':'Add photo';}
            }finally{$matchedCount++;$notify('matching',$matchedCount);}}unset($entry);
            $missing=$candidates->filter(fn($r)=>!isset($matched[$r->id]))->map(fn($r)=>['student_code'=>$r->student_code,'name_en'=>$r->name_en,'photo_filename'=>$r->data['photo_filename']??''])->values();
            $summary=['images'=>count($entries),'ready'=>collect($entries)->where('status','ready')->count(),'existing'=>collect($entries)->where('status','existing')->count(),
                'unmatched'=>collect($entries)->where('status','unmatched')->count(),'duplicates'=>collect($entries)->whereIn('status',['duplicate','ambiguous'])->count(),'invalid'=>collect($entries)->where('status','invalid')->count(),'missing'=>$missing->count()];
            $token=bin2hex(random_bytes(32));
            DB::table('tb_g9_photo_batch')->insert(['user_id'=>$request->user()->id,'academic_year_id'=>$input['academic_year_id'],'token_hash'=>hash('sha256',$token),'folder'=>$folder,
                'payload'=>json_encode($entries,JSON_THROW_ON_ERROR),'summary'=>json_encode($summary,JSON_THROW_ON_ERROR),'replace_existing'=>$replace,'expires_at'=>now()->addHour(),'created_at'=>now(),'updated_at'=>now()]);
            return ['token'=>$token,'summary'=>$summary,'rows'=>collect($entries)->take(200)->map(fn($row)=>collect($row)->except(['staged','key','version','old_path'])),'missing'=>$missing->take(100)];
        }catch(\Throwable $error){Storage::disk('local')->deleteDirectory($folder);throw $error;}
    }
    public function confirm(Request $request,G9ExamPhoto $photos)
    {
        abort_unless($this->campuses($request,'photos-upload'),403);
        $request->validate(['token'=>['required','string','size:64']]);
        if(str_contains($request->header('Accept',''),'application/x-ndjson'))return $this->streamProgress(fn($emit)=>$this->storePhotos($request,$photos,$emit),true);
        return response()->json($this->storePhotos($request,$photos));
    }
    private function storePhotos(Request $request,G9ExamPhoto $photos,?callable $progress=null): array
    {
        $ids=$this->campuses($request,'photos-upload');abort_unless($ids,403);
        $input=$request->validate(['token'=>['required','string','size:64']]);$written=[];$folder=null;
        try{
            $summary=DB::transaction(function()use($request,$input,$photos,$ids,&$written,&$folder,$progress){
                $batch=DB::table('tb_g9_photo_batch')->where('token_hash',hash('sha256',$input['token']))->where('user_id',$request->user()->id)->lockForUpdate()->first();
                abort_unless($batch,404);abort_if($batch->status!=='preview'||now()->greaterThan($batch->expires_at),409,'This photo preview expired or was already confirmed. Preview again.');
                $folder=$batch->folder;$entries=collect(json_decode($batch->payload,true))->where('status','ready')->sortBy('candidate_id');
                abort_if($entries->isEmpty(),422,'No matched photos are ready to upload.');
                $directory=$photos->directory($batch->academic_year_id);
                $processed=0;$total=$entries->count();$lastPercent=-1;
                $notify=function()use($progress,&$processed,$total,&$lastPercent){$percent=min(99,(int)floor($processed/$total*100));if($progress&&($percent!==$lastPercent||$processed===$total)){$progress(['type'=>'progress','processed'=>$processed,'total'=>$total,'percent'=>$percent,'finalizing'=>$processed===$total]);$lastPercent=$percent;}};
                $notify();
                foreach($entries as $entry){
                    $record=G9ExamCandidate::whereIn('campus_id',$ids)->where('academic_year_id',$batch->academic_year_id)->lockForUpdate()->findOrFail($entry['candidate_id']);
                    abort_if($record->version!==$entry['version']||$record->photo_path!==$entry['old_path'],409,'A candidate changed after preview. Preview the photos again.');
                    if($record->photo_path)abort_unless($batch->replace_existing&&in_array($record->campus_id,$this->campuses($request,'photos-replace'),true),403);
                    abort_unless(Storage::disk('local')->exists($entry['staged']),409,'A staged photo is missing. Preview again.');
                    $path=$directory.'/'.Str::uuid().'.webp';
                    if(!Storage::disk('public')->put($path,Storage::disk('local')->get($entry['staged'])))throw new \RuntimeException('A student photo could not be stored.');$written[]=$path;
                    $before=$record->photo_path;$record->update(['photo_path'=>$path,'version'=>$record->version+1,'updated_by'=>$request->user()->id]);
                    DB::table('tb_g9_exam_event')->insert(['candidate_id'=>$record->id,'user_id'=>$request->user()->id,'action'=>'bulk-photo','details'=>json_encode(['before'=>$before,'after'=>$path,'filename'=>$entry['filename']],JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
                    $processed++;$notify();
                }
                DB::table('tb_g9_photo_batch')->where('id',$batch->id)->update(['status'=>'confirmed','payload'=>'[]','updated_at'=>now()]);
                return json_decode($batch->summary,true);
            });
        }catch(\Throwable $error){foreach($written as $path)Storage::disk('public')->delete($path);throw $error;}
        if($folder)try{Storage::disk('local')->deleteDirectory($folder);}catch(\Throwable $error){report($error);}
        return ['message'=>'Matched student photos uploaded.','summary'=>$summary];
    }
    private function streamProgress(callable $operation,bool $rollback=false)
    {
        return response()->stream(function()use($operation,$rollback){
            $emit=function(array $message){echo json_encode($message,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";if(ob_get_level()>0)ob_flush();flush();};
            try{$result=$operation($emit);}
            catch(\Throwable $error){
                if($error instanceof ValidationException){$status=422;$message=implode(' ',array_merge(...array_values($error->errors())));}
                elseif($error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface){$status=$error->getStatusCode();$message=$error->getMessage()?:'This photo batch is unavailable. Preview the photos again.';}
                else{report($error);$status=500;$message='The photos could not be processed. Preview the files again.';}
                $emit(['type'=>'error','status'=>$status,'message'=>$message]+($rollback?['rolled_back'=>true]:[]));return;
            }
            $emit(['type'=>'complete','percent'=>100]+$result);
        },200,['Content-Type'=>'application/x-ndjson; charset=UTF-8','Cache-Control'=>'private, no-store','X-Accel-Buffering'=>'no']);
    }
}
