<?php

namespace App\Http\Controllers;

use App\Models\G9ExamCandidate;
use App\Services\G9ExamImports;
use App\Support\G9ExamSpreadsheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class G9ExamImportController
{
    public function index(Request $request,G9ExamImports $service)
    {
        $service->authorize($request->user(),'imports');
        $canImport=(bool)$service->campusIds($request->user(),'import');$canExport=(bool)$service->campusIds($request->user(),'export');
        return view('g9-national-exams.imports',['canImport'=>$canImport,'canExport'=>$canExport,'canHistory'=>(bool)$service->campusIds($request->user(),'import-history'),'headers'=>array_keys(G9ExamSpreadsheet::COLUMNS)]);
    }
    public function download(Request $request,G9ExamImports $service,string $kind)
    {
        abort_unless(in_array($kind,['template','all'],true),404);$service->authorize($request->user(),'export');
        $ids=$service->campusIds($request->user(),'export');
        return response()->streamDownload(function()use($kind,$ids){
            $columns=$kind==='all'?G9ExamSpreadsheet::allColumns():G9ExamSpreadsheet::COLUMNS;
            $settings=DB::table('tb_g9_exam_year_setting')->get()->mapWithKeys(fn($row)=>[$row->academic_year_id=>json_decode($row->data,true)])->all();
            $handle=fopen('php://output','wb');fwrite($handle,"\xEF\xBB\xBF");fputcsv($handle,array_keys($columns),',','"','');
            if($kind==='all')G9ExamCandidate::whereIn('campus_id',$ids)->with(['academicYear','campus'])->orderBy('id')->chunkById(250,function($rows)use($handle,$columns,$settings){
                foreach($rows as $record){$values=[];foreach($columns as $field){
                    $value=str_starts_with($field,'legacy_')?($record->legacy_data[substr($field,7)]??$settings[$record->academic_year_id][substr($field,7)]??'')
                        :match($field){'academic_year_id'=>$record->academicYear?->academic_year,'campus_id'=>$record->campus?->campus_name_en,'student_code','name_kh','name_en'=>$record->$field,default=>$record->data[$field]??''};
                    $values[]=G9ExamSpreadsheet::csvCell($value);
                }fputcsv($handle,$values,',','"','');}
            });fclose($handle);
        },$kind==='template'?'g9-moeys-template.csv':'g9-all-candidates.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store']);
    }
    public function preview(Request $request,G9ExamImports $service,G9ExamSpreadsheet $reader)
    {
        $service->authorize($request->user(),'import');$request->validate(['file'=>['required','file','max:10240']]);
        $file=$request->file('file');$extension=strtolower($file->getClientOriginalExtension());abort_unless(in_array($extension,['csv','xlsx'],true),422,'Choose a CSV or XLSX file.');
        $preview=function(?callable $progress=null)use($request,$service,$reader,$file,$extension){
            if($progress)$progress(['type'=>'progress','stage'=>'reading','processed'=>0,'total'=>0,'percent'=>0]);
            $result=$service->preview($request->user(),$reader->read($file->getRealPath(),$extension),$progress);
            if($progress)$progress(['type'=>'progress','stage'=>'finalizing','processed'=>$result['summary']['total'],'total'=>$result['summary']['total'],'percent'=>99]);
            $token=bin2hex(random_bytes(32));
            $id=DB::table('tb_g9_exam_import')->insertGetId(['user_id'=>$request->user()->id,'token_hash'=>hash('sha256',$token),'filename'=>mb_substr(basename($file->getClientOriginalName()),0,255),
                'payload'=>json_encode($result['payload'],JSON_THROW_ON_ERROR),'summary'=>json_encode($result['summary'],JSON_THROW_ON_ERROR),'expires_at'=>now()->addHour(),'created_at'=>now(),'updated_at'=>now()]);
            return ['id'=>$id,'token'=>$token,'summary'=>$result['summary'],'rows'=>collect($result['payload'])->take(20)->map(fn($row)=>['row'=>$row['row'],'action'=>$row['id']?'Update':'Add','student_code'=>$row['input']['student_code'],'name_kh'=>$row['input']['name_kh']??'','name_en'=>$row['input']['name_en']])->values()];
        };
        if(str_contains($request->header('Accept',''),'application/x-ndjson'))return $this->streamProgress($preview);
        return response()->json($preview());
    }
    public function confirm(Request $request,G9ExamImports $service,G9NationalExamController $candidates)
    {
        $service->authorize($request->user(),'import');$data=$request->validate(['token'=>['required','string','size:64']]);
        $import=function(?callable $progress=null)use($request,$data,$service,$candidates){return DB::transaction(function()use($request,$data,$service,$candidates,$progress){
            $batch=DB::table('tb_g9_exam_import')->where('token_hash',hash('sha256',$data['token']))->where('user_id',$request->user()->id)->lockForUpdate()->first();
            abort_unless($batch,404);abort_if($batch->status!=='preview'||now()->greaterThan($batch->expires_at),409,'This preview was already imported or expired. Upload the file again.');
            $summary=json_decode($batch->summary,true);abort_if(count($summary['errors'])>0,422,'Fix all preview errors before importing.');
            $allowed=$service->campusIds($request->user(),'import');
            $payload=json_decode($batch->payload,true);
            $processed=0;$total=count($payload);$lastPercent=-1;
            $notify=function()use(&$processed,$total,&$lastPercent,$progress){
                $percent=$total?min(99,(int)floor($processed/$total*100)):0;
                if($progress&&($percent!==$lastPercent||$processed===$total)){
                    $progress(['type'=>'progress','processed'=>$processed,'total'=>$total,'percent'=>$percent,'finalizing'=>$processed===$total]);$lastPercent=$percent;
                }
            };
            $notify();
            DB::table('tb_academic_year')->whereIn('id',collect($payload)->pluck('input.academic_year_id')->unique())->orderBy('id')->lockForUpdate()->get();
            foreach($payload as $row){
                $input=$row['input'];abort_unless(in_array((int)$input['campus_id'],$allowed,true),403);
                $record=G9ExamCandidate::where('academic_year_id',$input['academic_year_id'])->where('student_code',$input['student_code'])->lockForUpdate()->first();
                abort_if(($record?->id)!==$row['id']||($record?->version)!==$row['version'],409,'Candidate data changed after preview. Preview the file again.');
                if($record)abort_unless(in_array($record->campus_id,$allowed,true),403);
                $input['version']=$row['version'];
                $internal=Request::create('/g9-national-exams/candidates','POST',$input);$internal->setUserResolver(fn()=>$request->user());
                $saved=$candidates->save($internal,$row['id'])->getData(true);
                if(!empty($row['legacy'])){
                    $certificate=trim((string)($row['legacy']['certificate_number']??''));
                    abort_if($certificate!==''&&G9ExamCandidate::where('academic_year_id',$input['academic_year_id'])->where('id','!=',$saved['id'])->where('legacy_data->certificate_number',$certificate)->exists(),409,'A certificate number changed since preview. Preview again.');
                    G9ExamCandidate::whereKey($saved['id'])->update(['legacy_data'=>json_encode($row['legacy'],JSON_THROW_ON_ERROR)]);
                    DB::table('tb_g9_exam_event')->insert(['candidate_id'=>$saved['id'],'user_id'=>$request->user()->id,'action'=>'import-tracking','details'=>json_encode($row['legacy'],JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
                }
                if(isset($row['year_setting'])){
                    $setting=DB::table('tb_g9_exam_year_setting')->where('academic_year_id',$input['academic_year_id'])->lockForUpdate()->first();
                    abort_if(($setting?->version??0)!==$row['year_setting']['version'],409,'Academic year settings changed after preview. Preview again.');
                    DB::table('tb_g9_exam_year_setting')->updateOrInsert(['academic_year_id'=>$input['academic_year_id']],['data'=>json_encode($row['year_setting']['data'],JSON_THROW_ON_ERROR),'version'=>($setting?->version??0)+1,'updated_by'=>$request->user()->id,'created_at'=>$setting?->created_at??now(),'updated_at'=>now()]);
                }
                $processed++;$notify();
            }
            DB::table('tb_g9_exam_import')->where('id',$batch->id)->update(['status'=>'confirmed','payload'=>'[]','updated_at'=>now()]);return $summary;
        });};
        if(str_contains($request->header('Accept',''),'application/x-ndjson')){
            return $this->streamProgress(function($emit)use($import){return ['message'=>'Import completed.','summary'=>$import($emit)];},true);
        }
        $summary=$import();
        return response()->json(['message'=>'Import completed.','summary'=>$summary]);
    }
    private function streamProgress(callable $operation,bool $rollback=false)
    {
        return response()->stream(function()use($operation,$rollback){
            $emit=function(array $message){echo json_encode($message,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";if(ob_get_level()>0)ob_flush();flush();};
            try{$result=$operation($emit);}
            catch(\Throwable $error){
                if($error instanceof \Illuminate\Validation\ValidationException){$status=422;$message=implode(' ',array_merge(...array_values($error->errors())));}
                elseif($error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface){$status=$error->getStatusCode();$message=$error->getMessage()?:'This import is unavailable. Preview the file again.';}
                else{report($error);$status=500;$message='The request could not be completed. Please preview the file again.';}
                $emit(['type'=>'error','status'=>$status,'message'=>$message]+($rollback?['rolled_back'=>true]:[]));return;
            }
            // Completion follows preview persistence or the successful import commit.
            $emit(['type'=>'complete','percent'=>100]+$result);
        },200,['Content-Type'=>'application/x-ndjson; charset=UTF-8','Cache-Control'=>'private, no-store','X-Accel-Buffering'=>'no']);
    }
    public function history(Request $request,G9ExamImports $service)
    {
        $service->authorize($request->user(),'import-history');
        return response()->json(DB::table('tb_g9_exam_import')->where('user_id',$request->user()->id)->orderByDesc('id')->limit(30)->get(['id','filename','status','summary','created_at','updated_at']));
    }
}
