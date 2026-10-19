<?php

namespace App\Http\Controllers;

use App\Models\ScholarshipExam;
use App\Services\ScholarshipHistoricalTransfer;
use App\Support\ScholarshipPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScholarshipTransferController
{
    public function __construct(private readonly ScholarshipHistoricalTransfer $transfer) {}

    public function sample(Request $request)
    {
        abort_unless(ScholarshipPermissions::allows($request->user(),'tab-data-transfer'),403);
        $values=$request->validate(['format'=>['nullable','in:csv,xlsx']]);
        $exam=$request->filled('exam_id')?ScholarshipExam::findOrFail($request->integer('exam_id')):null;
        if($exam)abort_unless(app(\App\Services\ScholarshipWorkflow::class)->canView($request->user(),$exam),403);
        $rows=$this->transfer->sample($exam);
        if(($values['format']??'csv')==='xlsx')return response(app(\App\Support\ScholarshipExport::class)->xlsx($rows))
            ->header('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition','attachment; filename="scholarship-sample-8-records.xlsx"')
            ->header('Cache-Control','private, no-store');
        return $this->csv('scholarship-sample-8-records.csv',$rows);
    }

    public function export(Request $request,ScholarshipExam $exam)
    {
        // Generator authorization must run before sending a download response.
        $this->transfer->authorize($request->user(),$exam,false);
        return $this->csv('scholarship-'.$exam->academicYear->academic_year.'.csv',$this->transfer->export($request->user(),$exam));
    }

    public function preview(Request $request)
    {
        $values=$request->validate(['exam_id'=>['required','integer'],'file'=>['required','file','mimes:csv,txt','max:5120'],'replace'=>['nullable','boolean']]);
        $exam=ScholarshipExam::findOrFail($values['exam_id']);$this->transfer->authorize($request->user(),$exam,true);
        $path=$request->file('file')->store('scholarship-imports','local');
        try{
            $plan=$this->transfer->preview($request->user(),$exam,Storage::disk('local')->path($path),$request->boolean('replace'));
            $previous=$request->session()->pull('scholarship_import_preview');
            if($previous)Storage::disk('local')->delete($previous['path']);
            $token=null;
            if(!$plan['errors']){
                $token=Str::random(64);
                $request->session()->put('scholarship_import_preview',['token'=>$token,'user_id'=>$request->user()->id,'exam_id'=>$exam->id,'path'=>$path,'hash'=>hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR)),'replace'=>$request->boolean('replace'),'expires'=>now()->addMinutes(30)->timestamp]);
            }else Storage::disk('local')->delete($path);
            return response()->json(['token'=>$token,'created'=>$plan['created'],'updated'=>$plan['updated'],'total'=>count($plan['rows'])+count($plan['errors']),
                'rows'=>array_slice($plan['rows'],0,100),'errors'=>array_slice($plan['errors'],0,100),'error_count'=>count($plan['errors']),'settings_changes'=>$plan['report_settings']['changes']]);
        }catch(\Throwable $e){Storage::disk('local')->delete($path);throw $e;}
    }

    public function import(Request $request)
    {
        $values=$request->validate(['token'=>['required','string','size:64'],'acknowledge'=>['accepted']]);
        $preview=$request->session()->get('scholarship_import_preview');
        if(!$preview || $preview['user_id']!==$request->user()->id || !hash_equals($preview['token'],$values['token']) || $preview['expires']<now()->timestamp || !Storage::disk('local')->exists($preview['path'])){
            throw ValidationException::withMessages(['file'=>'This preview is missing or expired. Upload and preview the CSV again.']);
        }
        $exam=ScholarshipExam::findOrFail($preview['exam_id']);
        $counts=$this->transfer->commit($request->user(),$exam,Storage::disk('local')->path($preview['path']),$preview['replace'],$preview['hash']);
        $request->session()->forget('scholarship_import_preview');Storage::disk('local')->delete($preview['path']);
        return response()->json(['message'=>$counts['created'].' record(s) imported and '.$counts['updated'].' updated. Historical records are protected.',
            'redirect'=>route('western-scholarship.index',['tab'=>'data-transfer','exam_id'=>$exam->id])]);
    }

    private function csv(string $filename,iterable $rows)
    {
        return response()->streamDownload(function()use($rows){
            $stream=fopen('php://output','wb');fwrite($stream,"\xEF\xBB\xBF");
            foreach($rows as $row)fputcsv($stream,array_map(fn($value)=>is_string($value)&&preg_match('/^[\s\x00-\x1f]*[=+@-]/u',$value)?"'".$value:$value,$row),',','"','',"\r\n");
            fclose($stream);
        },$filename,['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store']);
    }
}
