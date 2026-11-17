<?php

namespace App\Http\Controllers;

use App\Models\{AcademicYear, SchoolInfo};
use App\Services\AttendanceTransfer;
use App\Support\AttendancePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceTransferController
{
    private function allow(Request $request, string $action='transfer'): void
    {
        abort_unless(AttendancePermissions::allows($request->user(),$action),403);
    }

    public function index(Request $request)
    {
        $this->allow($request);
        return view('attendance.transfer',[
            'years'=>AcademicYear::orderByDesc('academic_year')->get(),
            'campuses'=>SchoolInfo::whereIn('id',AttendancePermissions::writeCampuses($request->user(),'export'))->orderBy('campus_name_en')->get(),
            'canImport'=>AttendancePermissions::allows($request->user(),'import'),
            'canExport'=>AttendancePermissions::allows($request->user(),'export'),
        ]);
    }

    public function template(Request $request)
    {
        $this->allow($request,'import');
        return $this->csv('attendance-template.csv',AttendanceTransfer::COLUMNS,[['00123','2022-2023','2022-10-13','E','8:40-9:30 AM','Sick','','1']]);
    }

    public function preview(Request $request, AttendanceTransfer $service)
    {
        $this->allow($request,'import');
        $request->validate(['file'=>['required','file','max:12288','extensions:csv']]);
        set_time_limit(300);
        $file=$request->file('file');
        $id=$service->preview($request->user(),$file->getRealPath(),$file->getClientOriginalName());
        // Originals and row-level review information remain private to the application.
        $file->storeAs('attendance-imports/'.$id,'source.csv','local');
        return $this->inspect($request,$id);
    }

    private function batch(Request $request, string $batch)
    {
        $this->allow($request);
        return DB::table('attendance_import_batches')->where('id',$batch)->where('user_id',$request->user()->id)->firstOrFail();
    }

    public function inspect(Request $request, string $batch)
    {
        $record=$this->batch($request,$batch);
        $query=DB::table('attendance_import_rows')->where('batch_id',$batch);
        $counts=(clone $query)->select('outcome')->selectRaw('COUNT(*) as total')->groupBy('outcome')->pluck('total','outcome');
        $rows=(clone $query)->orderBy('csv_row')->paginate(25);
        $rows->getCollection()->transform(function($row){$row->source=json_decode($row->source,true);unset($row->normalized);return $row;});
        return response()->json(['batch'=>$record,'counts'=>$counts,'total'=>$counts->sum(),'rows'=>$rows,'review_url'=>route('attendance.transfer.review',$batch)]);
    }

    public function confirm(Request $request, string $batch, AttendanceTransfer $service)
    {
        $this->allow($request,'import');
        $this->batch($request,$batch);
        $service->confirm($request->user(),$batch);
        return $this->inspect($request,$batch);
    }

    public function history(Request $request)
    {
        $this->allow($request);
        return response()->json(DB::table('attendance_import_batches')->where('user_id',$request->user()->id)->orderByDesc('created_at')->paginate(10));
    }

    public function review(Request $request, string $batch)
    {
        $this->batch($request,$batch);
        $query=DB::table('attendance_import_rows')->where('batch_id',$batch)->whereIn('outcome',['review','duplicate'])->orderBy('csv_row');
        $columns=AttendanceTransfer::COLUMNS;
        foreach((clone $query)->cursor() as $row)$columns=array_values(array_unique(array_merge($columns,array_diff(array_keys(json_decode($row->source,true)),['csv_row','import_result','review_reason']))));
        return $this->csv('attendance-review-'.$batch.'.csv',array_merge($columns,['csv_row','import_result','review_reason']), (function()use($query,$columns){
            foreach($query->cursor() as $row){$source=json_decode($row->source,true);yield array_merge(array_map(fn($key)=>$source[$key]??'',$columns),[$row->csv_row,$row->outcome,$row->reason]);}
        })());
    }

    public function export(Request $request)
    {
        $this->allow($request,'export');
        $data=$request->validate(['academic_year_id'=>['required','integer','exists:tb_academic_year,id'],'campus_id'=>['nullable','integer'],'date_from'=>['nullable','date_format:Y-m-d'],'date_to'=>['nullable','date_format:Y-m-d',...($request->filled('date_from')?['after_or_equal:date_from']:[])],'attendance'=>['nullable','in:P,EX,UX,T']]);
        $campuses=AttendancePermissions::writeCampuses($request->user(),'export');
        if(!empty($data['campus_id']))abort_unless(in_array((int)$data['campus_id'],$campuses,true),403);
        $query=DB::table('attendance_daily_marks as m')->join('tb_student as s','s.id','=','m.student_id')->join('tb_academic_year as y','y.id','=','m.academic_year_id')->join('attendance_daily_registers as r','r.id','=','m.register_id')->join('tb_school_info as c','c.id','=','r.campus_id')
            ->where('m.academic_year_id',$data['academic_year_id'])->whereIn('r.campus_id',$campuses)
            ->when($data['campus_id']??null,fn($q,$v)=>$q->where('r.campus_id',$v))
            ->when($data['date_from']??null,fn($q,$v)=>$q->where('m.attendance_date','>=',$v))
            ->when($data['date_to']??null,fn($q,$v)=>$q->where('m.attendance_date','<=',$v))
            ->when($data['attendance']??null,fn($q,$v)=>$q->where('m.status',$v))
            ->orderBy('m.attendance_date')->orderBy('m.id')->select(['s.student_id','y.academic_year','m.attendance_date','m.status','m.remarks','c.campus_name_en','m.missed_sessions','m.absent_periods']);
        return $this->csv('daily-attendance-'.now('Asia/Bangkok')->format('Ymd-His').'.csv',array_merge(AttendanceTransfer::COLUMNS,['recorded_periods']), (function()use($query){
            foreach($query->cursor() as $row)yield [$row->student_id,$row->academic_year,$row->attendance_date,$row->status,'',$row->remarks,$row->campus_name_en,$row->missed_sessions,$row->absent_periods];
        })());
    }

    private function csv(string $filename, array $columns, iterable $rows)
    {
        return response()->streamDownload(function()use($columns,$rows){
            $stream=fopen('php://output','wb');fwrite($stream,"\xEF\xBB\xBF");
            fputcsv($stream,$columns,',','"','');
            foreach($rows as $row){$row=array_map(fn($value)=>preg_match('/^[=+@\-\t\r]/',(string)$value)?"'".$value:$value,$row);fputcsv($stream,$row,',','"','');}
            fclose($stream);
        },$filename,['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'no-store']);
    }
}
