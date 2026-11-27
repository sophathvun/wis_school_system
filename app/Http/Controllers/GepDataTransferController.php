<?php
namespace App\Http\Controllers;

use App\Services\GepDataTransfer;
use App\Support\{GepStudentPermissions,GepEnrollmentPermissions as Access,GepTransferWorkbook};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;

class GepDataTransferController
{
    private $progress=null;
    private function authorize(Request $request): void { abort_unless(GepStudentPermissions::allows($request->user(),'import-export'),403); }
    public function index(Request $request)
    {
        $this->authorize($request);
        $preview=null;$path=$request->session()->get('gep-transfer-preview-details');
        if($path && Storage::disk('local')->exists($path))$preview=json_decode(Storage::disk('local')->get($path),true,512,JSON_THROW_ON_ERROR);
        return view('gep.data-transfer',['canImport'=>Access::allows($request->user(),'create'),'preview'=>$preview]);
    }
    private function type(GepDataTransfer $transfer,string $type): array { $columns=$transfer->columns($type);abort_unless($columns,404);return $columns; }
    public function download(Request $request,string $type,string $format,GepDataTransfer $transfer)
    {
        $this->authorize($request);$columns=$this->type($transfer,$type);abort_unless(in_array($format,['template','csv','xlsx']),404);
        $rows=$format==='template'?$transfer->samples($type):$transfer->export($type,$request->user());
        $filename='gep-'.$type.'-'.($format==='template'?'sample':now()->format('Ymd-His'));
        if($format==='xlsx'){
            $directory=storage_path('app/private/gep-exports');if(!is_dir($directory))mkdir($directory,0750,true);
            $path=tempnam($directory,'export-');
            try{GepTransferWorkbook::write($path,$columns,$rows);}catch(\Throwable $error){@unlink($path);throw $error;}
            return response()->download($path,$filename.'.xlsx',['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Cache-Control'=>'private, no-store'])->deleteFileAfterSend(true);
        }
        return response()->streamDownload(function()use($rows,$columns){$file=fopen('php://output','wb');fwrite($file,"\xEF\xBB\xBF");fputcsv($file,$columns,escape:'');foreach($rows as $row)fputcsv($file,array_map(fn($key)=>$row[$key]??'',$columns),escape:'');fclose($file);},$filename.'.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store']);
    }
    public function upload(Request $request,string $type,GepDataTransfer $transfer)
    {
        $this->authorize($request);abort_unless(Access::allows($request->user(),'create'),403);
        if($request->header('X-GEP-Progress')==='1')return $this->streamUpload($request,$type,$transfer);
        $columns=$this->type($transfer,$type);
        if($request->boolean('use_preview'))return $this->applySavedPreview($request,$type,$transfer);
        $request->validate(['file'=>'required|file|mimes:csv,txt|max:25600','apply'=>'nullable|boolean']);
        $file=$request->file('file');$hash=hash_file('sha256',$file->getRealPath());
        $sessionKey='gep-transfer-preview.'.$type;
        if($request->boolean('apply') && $request->session()->get($sessionKey)!==$hash)throw ValidationException::withMessages(['file'=>'Preview this exact CSV file first, then choose it again to apply.']);
        $handle=fopen($file->getRealPath(),'rb');$headers=fgetcsv($handle,escape:'')?:[];$headers[0]=preg_replace('/^\xEF\xBB\xBF/','',$headers[0]??'');$headers=array_map('trim',$headers);
        $acceptedColumns=$type==='families'?[...$columns,'student_identifier','academic_year','term']:$columns;
        $hasLink=$type==='families'?(in_array('family_number',$headers,true)||in_array('student_identifier',$headers,true)):in_array('student_identifier',$headers,true);
        if(count(array_unique($headers))!==count($headers)||array_diff($headers,$acceptedColumns)||!$hasLink){fclose($handle);throw ValidationException::withMessages(['file'=>'Use the sample column headers without duplicate or unknown columns. Family imports require Family Number.']);}
        $rows=[];while(($values=fgetcsv($handle,escape:''))!==false){if(count(array_filter($values,fn($v)=>filled($v)))===0)continue;if(count($values)!==count($headers)){fclose($handle);throw ValidationException::withMessages(['file'=>'CSV row '.(count($rows)+2).' has the wrong number of columns.']);}$rows[]=array_map(fn($v)=>trim((string)$v),array_combine($headers,$values));}fclose($handle);
        if(!$rows)throw ValidationException::withMessages(['file'=>'The CSV contains no records.']);
        DB::beginTransaction();
        try{$counts=$transfer->import($type,$rows,$request->user(),$this->progress);if($request->boolean('apply'))DB::commit();else DB::rollBack();}
        catch(\Throwable $error){DB::rollBack();throw $error;}
        $result=['type'=>$type,'applied'=>$request->boolean('apply'),...$counts];
        $previous=$request->session()->get('gep-transfer-preview-details');
        if($request->boolean('apply')){
            $request->session()->forget([$sessionKey,'gep-transfer-preview-details']);
            if($previous)Storage::disk('local')->delete($previous);
        }else{
            $previewRows=array_slice($rows,0,200);$previewColumns=$headers;
            if($type==='enrollments'){
                $names=\App\Models\GepStudent::whereIn('student_identifier',array_column($previewRows,'student_identifier'))->get()->mapWithKeys(fn($student)=>[$student->student_identifier=>$student->profile['full_name_en']??$student->profile['full_name_kh']??'']);
                array_splice($previewColumns,1,0,['student_name']);
                $previewRows=array_map(fn($row)=>$row+['student_name'=>$names[$row['student_identifier']]??''],$previewRows);
            }
            $token=bin2hex(random_bytes(16));
            $path='gep-transfer-previews/'.$request->user()->id.'/'.$token.'.json';
            $saved=Storage::disk('local')->put($path,json_encode(['result'=>$result,'filename'=>$file->getClientOriginalName(),'columns'=>$previewColumns,'rows'=>$previewRows,'all_rows'=>$rows,'source_sha256'=>$hash,'preview_token'=>$token,'user_id'=>$request->user()->id],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
            if(!$saved)throw new \RuntimeException('Unable to save the import preview. Please try again.');
            $request->session()->put($sessionKey,$hash);$request->session()->put('gep-transfer-preview-details',$path);
            if($previous)Storage::disk('local')->delete($previous);
        }
        return redirect()->route('gep.students.section',['studentSection'=>'import-export'])->with('gep_transfer_result',$result);
    }
    private function applySavedPreview(Request $request,string $type,GepDataTransfer $transfer)
    {
        $request->validate(['preview_token'=>'required|string|size:32']);
        $disk=Storage::disk('local');$path=$request->session()->get('gep-transfer-preview-details');
        $preview=$path&&$disk->exists($path)?json_decode($disk->get($path),true,512,JSON_THROW_ON_ERROR):null;
        if(!$preview || empty($preview['all_rows']) || ($preview['user_id']??null)!==$request->user()->id
            || ($preview['result']['type']??null)!==$type
            || !hash_equals($preview['preview_token']??'',$request->input('preview_token'))
            || ($preview['source_sha256']??null)!==$request->session()->get('gep-transfer-preview.'.$type))
            throw ValidationException::withMessages(['file'=>'This preview is no longer available. Select the CSV and preview it again before applying.']);
        // Revalidate current settings and permissions; import every row, including those beyond the display limit.
        $counts=DB::transaction(fn()=>$transfer->import($type,$preview['all_rows'],$request->user(),$this->progress));
        $request->session()->forget(['gep-transfer-preview.'.$type,'gep-transfer-preview-details']);$disk->delete($path);
        return redirect()->route('gep.students.section',['studentSection'=>'import-export'])->with('gep_transfer_result',['type'=>$type,'applied'=>true,...$counts]);
    }
    private function streamUpload(Request $request,string $type,GepDataTransfer $transfer)
    {
        $request->headers->remove('X-GEP-Progress');
        return response()->stream(function()use($request,$type,$transfer){
            // Finish transactions and report the outcome even if the browser disconnects.
            ignore_user_abort(true);
            $send=function(array $message){echo json_encode($message,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";if(ob_get_level())@ob_flush();flush();};
            $lastPercent=-1;
            $this->progress=function(int $processed,int $total)use($send,&$lastPercent){
                $percent=$total?(int)floor($processed*100/$total):0;
                if($percent===$lastPercent&&$processed!==$total)return;
                $lastPercent=$percent;$send(['event'=>'progress','processed'=>$processed,'total'=>$total,'percent'=>$percent]);
            };
            try{
                $response=$this->upload($request,$type,$transfer);
                $result=$request->session()->get('gep_transfer_result');
                // Stream callbacks run after middleware has saved the session; persist the preview/result here.
                $request->session()->save();
                $send(['event'=>'complete','result'=>$result,'redirect'=>$response->getTargetUrl()]);
            }catch(ValidationException $error){
                $send(['event'=>'error','message'=>implode(' ',array_merge(...array_values($error->errors()))).' No rows from this upload were saved.']);
            }catch(\Symfony\Component\HttpKernel\Exception\HttpException $error){
                $send(['event'=>'error','message'=>'You do not have permission to import these records. No rows were saved.']);
            }catch(\Throwable $error){
                report($error);$send(['event'=>'error','message'=>'The request could not finish. Refresh the page and check the import result before trying again.']);
            }finally{$this->progress=null;}
        },200,['Content-Type'=>'application/x-ndjson','Cache-Control'=>'no-cache, no-store','X-Accel-Buffering'=>'no']);
    }
}
