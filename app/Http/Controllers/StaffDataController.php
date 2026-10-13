<?php

namespace App\Http\Controllers;

use App\Services\StaffCsvImport;
use App\Services\StaffDataExport;
use App\Support\HrmPermissions;
use App\Support\StaffDataColumns;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

class StaffDataController
{
    public static function allows(Request $request,string $action): bool
    {
        return HrmPermissions::allows($request->user(),'hrm-staff-data',$action)
            && HrmPermissions::allows($request->user(),'staff',$action==='import'?'create':'view');
    }
    public function index(Request $request)
    {
        abort_unless(self::allows($request,'view'),403);
        $report=$this->report($request);
        $page=max(1,$request->integer('error_page',1));
        $errors=$report['errors']??$request->session()->get('staff_import_result.errors',[]);
        return view('hrm-settings._staff-data',[
            'canTemplateStaffData'=>self::allows($request,'template'),'canExportStaffData'=>self::allows($request,'export'),
            'canImportStaffData'=>self::allows($request,'import'),
            'importIssues'=>(new LengthAwarePaginator(array_slice($errors,($page-1)*100,100),count($errors),100,$page,[
                'path'=>route('hrm.settings.index'),'pageName'=>'error_page',
            ]))->appends(['tab'=>'import-export']),
        ]);
    }
    public function template(Request $request,StaffDataExport $export)
    {
        abort_unless(self::allows($request,'template'),403);
        return response()->streamDownload(function()use($export) {
            $stream=fopen('php://output','wb');fwrite($stream,"\xEF\xBB\xBF");
            $fields=StaffDataColumns::fields();fputcsv($stream,array_values($fields),',','"','');
            foreach($export->samples() as $row) fputcsv($stream,array_map(fn($field)=>Arr::get($row,$field,''),array_keys($fields)),',','"','');
            fclose($stream);
        },'staff-import-template.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
    public function excel(Request $request,StaffDataExport $export)
    {
        abort_unless(self::allows($request,'export'),403);
        @set_time_limit(300);
        return response()->download($export->xlsx(),'staff-information-'.now()->format('Ymd-His').'.xlsx',[
            'Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
    public function import(Request $request,StaffCsvImport $import)
    {
        abort_unless(self::allows($request,'import'),403);
        $request->validate(['file'=>['required','file','mimes:csv,txt','max:10240']]);
        @set_time_limit(300);
        $result=$import->import($request->file('file')->getRealPath(),$request->user());
        $previous=$this->reportPath($request);
        $reportId=(string)Str::uuid();
        $reportPath='staff-import-reports/'.$request->user()->id.'/'.$reportId.'.json';
        Storage::disk('local')->put($reportPath,json_encode(Arr::only($result,['errors','failed_headers','failed_records']),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        if ($previous) Storage::disk('local')->delete($previous);
        $summary=Arr::except($result,['failed_headers','failed_records']);
        $summary['errors']=array_slice($result['errors'],0,100);
        $summary['report_id']=$reportId;
        $request->session()->forget('success');
        $request->session()->put('staff_import_result',$summary);
        $redirect=redirect()->route('hrm.settings.index',['tab'=>'import-export']);
        if ($result['created']) $redirect->with('success',number_format($result['created']).' staff profiles imported successfully.');
        return $redirect;
    }
    public function errors(Request $request)
    {
        abort_unless(self::allows($request,'import'),403);
        $errors=$this->report($request)['errors']??$request->session()->get('staff_import_result.errors',[]);
        abort_if(empty($errors),404);
        return response()->streamDownload(function()use($errors) {
            $stream=fopen('php://output','wb');fwrite($stream,"\xEF\xBB\xBF");fputcsv($stream,['CSV Row','Staff ID','Field','Problem','Suggested Correction'],',','"','');
            foreach($errors as $error) fputcsv($stream,array_map(function($text) { $text=(string)$text;return preg_match('/^[\s]*[=+@-]/u',$text)?"'".$text:$text; },[$error['row'],$error['code'],$error['field'],$error['message'],$error['suggestion']??'']),',','"','');
            fclose($stream);
        },'staff-import-errors.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
    public function failedRecords(Request $request)
    {
        abort_unless(self::allows($request,'import'),403);
        $report=$this->report($request);
        abort_if(empty($report['failed_records']),404);
        return response()->streamDownload(function()use($report) {
            $stream=fopen('php://output','wb');fwrite($stream,"\xEF\xBB\xBF");
            fputcsv($stream,$report['failed_headers'],',','"','');
            foreach($report['failed_records'] as $row) fputcsv($stream,$row,',','"','');
            fclose($stream);
        },'staff-failed-records.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
    private function reportPath(Request $request): ?string
    {
        $id=$request->session()->get('staff_import_result.report_id');
        return is_string($id) && Str::isUuid($id)?'staff-import-reports/'.$request->user()->id.'/'.$id.'.json':null;
    }
    private function report(Request $request): ?array
    {
        $path=$this->reportPath($request);
        return $path && Storage::disk('local')->exists($path)?json_decode(Storage::disk('local')->get($path),true,512,JSON_THROW_ON_ERROR):null;
    }
}
