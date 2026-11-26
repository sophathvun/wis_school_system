<?php
namespace App\Http\Controllers;

use App\Services\GepDataTransfer;
use App\Support\{GepStudentPermissions,GepEnrollmentPermissions as Access,GepTransferWorkbook};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GepDataTransferController
{
    private function authorize(Request $request): void { abort_unless(GepStudentPermissions::allows($request->user(),'import-export'),403); }
    public function index(Request $request)
    {
        $this->authorize($request);
        return view('gep.data-transfer',['canImport'=>Access::allows($request->user(),'create')]);
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
        $columns=$this->type($transfer,$type);
        $request->validate(['file'=>'required|file|mimes:csv,txt|max:25600','apply'=>'nullable|boolean']);
        $file=$request->file('file');$hash=hash_file('sha256',$file->getRealPath());
        $sessionKey='gep-transfer-preview.'.$type;
        if($request->boolean('apply') && $request->session()->get($sessionKey)!==$hash)throw ValidationException::withMessages(['file'=>'Preview this exact CSV file first, then choose it again to apply.']);
        $handle=fopen($file->getRealPath(),'rb');$headers=fgetcsv($handle,escape:'')?:[];$headers[0]=preg_replace('/^\xEF\xBB\xBF/','',$headers[0]??'');$headers=array_map('trim',$headers);
        if(count(array_unique($headers))!==count($headers)||array_diff($headers,$columns)||!in_array('student_identifier',$headers,true)){fclose($handle);throw ValidationException::withMessages(['file'=>'Use the sample column headers without duplicate or unknown columns.']);}
        $rows=[];while(($values=fgetcsv($handle,escape:''))!==false){if(count(array_filter($values,fn($v)=>filled($v)))===0)continue;if(count($values)!==count($headers)){fclose($handle);throw ValidationException::withMessages(['file'=>'CSV row '.(count($rows)+2).' has the wrong number of columns.']);}$rows[]=array_map(fn($v)=>trim((string)$v),array_combine($headers,$values));}fclose($handle);
        if(!$rows)throw ValidationException::withMessages(['file'=>'The CSV contains no records.']);
        DB::beginTransaction();
        try{$counts=$transfer->import($type,$rows,$request->user());if($request->boolean('apply'))DB::commit();else DB::rollBack();}
        catch(\Throwable $error){DB::rollBack();throw $error;}
        if($request->boolean('apply'))$request->session()->forget($sessionKey);else $request->session()->put($sessionKey,$hash);
        return back()->with('gep_transfer_result',['type'=>$type,'applied'=>$request->boolean('apply'),...$counts]);
    }
}
