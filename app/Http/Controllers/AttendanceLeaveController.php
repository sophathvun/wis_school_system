<?php

namespace App\Http\Controllers;

use App\Models\StudentEnrollment;
use App\Services\AttendanceLeave;
use App\Support\AttendanceStudyGroup;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;

class AttendanceLeaveController
{
    private function authorize(Request $request,int $campus): void
    {
        abort_unless($request->user()->canAccessCampus($campus)&&$request->user()->hasPermission('attendance.view',$campus)&&$request->user()->hasPermission('attendance.manage',$campus),403);
    }

    public function calendar(Request $request,AttendanceLeave $service)
    {
        $scope=$request->validate(['academic_year_id'=>['required','integer','exists:tb_academic_year,id'],'campus_id'=>['required']]);
        $all=$scope['campus_id']==='all';
        if($all){
            $ids=$request->user()->accessibleCampuses()->get()->filter(fn($campus)=>$request->user()->hasPermission('attendance.view',$campus->id)&&$request->user()->hasPermission('attendance.manage',$campus->id))->modelKeys();
            abort_unless($ids,403);
        }else{
            $request->validate(['campus_id'=>['integer']]);$this->authorize($request,(int)$scope['campus_id']);$ids=[(int)$scope['campus_id']];
        }
        sort($ids);$calendars=[];$versions=[];
        foreach($ids as $id){$calendars[$id]=$service->calendar($scope['academic_year_id'],$id);$versions[$id]=$calendars[$id]['version'];}
        if($request->isMethod('get')){
            $first=reset($calendars);$signatures=[];
            foreach($calendars as $calendar){$days=$calendar['weekdays'];$holidays=$calendar['holidays'];$descriptions=$calendar['holiday_descriptions'];sort($days);sort($holidays);ksort($descriptions);$signatures[]=json_encode([$days,$holidays,$descriptions]);}
            return response()->json($first+['versions'=>$versions,'campus_count'=>count($ids),'mixed'=>count(array_unique($signatures))>1]);
        }
        $data=$request->validate(['weekdays'=>['required','array','min:1'],'weekdays.*'=>['integer','between:0,6'],'holidays'=>['present','array'],'holidays.*'=>['date_format:Y-m-d'],'holiday_descriptions'=>['sometimes','array'],'holiday_descriptions.*'=>['nullable','string','max:200'],'version'=>['required','integer','min:0'],'versions'=>['sometimes','array'],'versions.*'=>['integer','min:0']]);
        DB::transaction(function()use($request,$scope,$data,$ids,$all){
            DB::table('tb_school_info')->whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get();
            foreach($ids as $id){
                $target=['academic_year_id'=>$scope['academic_year_id'],'campus_id'=>$id];
                $row=DB::table('attendance_calendars')->where($target)->lockForUpdate()->first();
                $expected=$all?($data['versions'][$id]??-1):$data['version'];
                abort_if(($row->version??0)!==$expected,409,'A campus calendar changed. Reload before saving. No calendars were updated.');
                $descriptions=$data['holiday_descriptions']??(json_decode($row?->holiday_descriptions??'{}',true)?:[]);
                $descriptions=array_map(fn($description)=>trim((string)$description),array_intersect_key($descriptions,array_flip($data['holidays'])));
                DB::table('attendance_calendars')->updateOrInsert($target,['weekdays'=>json_encode(array_values(array_unique($data['weekdays']))),'holidays'=>json_encode(array_values(array_unique($data['holidays']))),'holiday_descriptions'=>json_encode((object)$descriptions),'version'=>$expected+1,'updated_by'=>$request->user()->id,'created_at'=>$row?->created_at??now(),'updated_at'=>now()]);
            }
        });
        return response()->json(['message'=>'School weekdays and holidays saved.','campus_count'=>count($ids)]);
    }

    public function listing(Request $request)
    {
        $scope=$request->validate(['academic_year_id'=>['required','integer'],'campus_id'=>['required','integer'],'student_id'=>['nullable','integer']]);
        $this->authorize($request,$scope['campus_id']);
        $rows=DB::table('attendance_leaves as l')->join('tb_student as s','s.id','=','l.student_id')->where('l.academic_year_id',$scope['academic_year_id'])->where('l.campus_id',$scope['campus_id'])
            ->when($scope['student_id']??null,fn($q,$id)=>$q->where('l.student_id',$id))->orderByDesc('l.id')->limit(100)->get(['l.*','s.full_name_en','s.full_name_kh']);
        $days=DB::table('attendance_leave_days')->whereIn('leave_id',$rows->pluck('id'))->orderBy('attendance_date')->get(['leave_id','attendance_date','status'])->groupBy('leave_id');
        $events=DB::table('attendance_leave_events as e')->join('users as u','u.id','=','e.user_id')->whereIn('e.leave_id',$rows->pluck('id'))->orderByDesc('e.id')->get(['e.leave_id','e.action','e.details','e.created_at','u.name'])->groupBy('leave_id');
        foreach($rows as $row){unset($row->attachment_path);$row->days=$days->get($row->id,collect())->values();$row->history=$events->get($row->id,collect())->values();}
        return response()->json(['leaves'=>$rows]);
    }

    public function create(Request $request,AttendanceLeave $service)
    {
        $data=$request->validate(['academic_year_id'=>['required','integer'],'campus_id'=>['required','integer'],'student_id'=>['required','integer'],'class_id'=>['required','integer'],
            'grade_id'=>['nullable','integer'],'starts_on'=>['required','date_format:Y-m-d','after_or_equal:'.now('Asia/Bangkok')->toDateString()],'ends_on'=>['required','date_format:Y-m-d','after_or_equal:starts_on'],
            'reason'=>['required','string','max:500'],'approved_by'=>['required','string','max:150'],'mode'=>['required','in:full,partial'],
            'period_ids'=>['sometimes','array'],'period_ids.*'=>['integer'],'preview'=>['sometimes','boolean'],
            'attachment'=>['nullable','file','mimes:pdf,jpg,jpeg,png,webp','max:5120']]);
        $this->authorize($request,$data['campus_id']);
        abort_if(CarbonImmutable::parse($data['starts_on'])->diffInDays(CarbonImmutable::parse($data['ends_on']))>90,422,'Choose at most 91 calendar days.');
        $enrollment=StudentEnrollment::with(['session','schoolGroup','grade'])->where('academic_year_id',$data['academic_year_id'])->where('campus_id',$data['campus_id'])->where('class_id',$data['class_id'])->where('student_id',$data['student_id'])
            ->when($data['grade_id']??null,fn($q,$id)=>$q->where('grade_id',$id))->whereNotIn('enrollment_status',['pending','cancelled','promotion_cancelled','withdrawn'])
            ->where(fn($q)=>$q->whereNull('enrolled_on')->orWhereDate('enrolled_on','<=',$data['starts_on']))->where(fn($q)=>$q->whereNull('ended_on')->orWhereDate('ended_on','>=',$data['ends_on']))->latest('id')->firstOrFail();
        $periods=DB::table('schedule_periods')->where('active',true)->whereNull('deleted_at')->orderBy('display_order')->get()->filter(fn($period)=>AttendanceStudyGroup::allowsForEnrollment($period,$enrollment));
        if($data['mode']==='partial'){
            $ids=array_map('intval',$data['period_ids']??[]);
            abort_if(!$ids||array_diff($ids,$periods->pluck('id')->all()),422,'Choose applicable periods for this student.');
            $periods=$periods->whereIn('id',$ids);
        }
        abort_if($periods->isEmpty(),422,'Assign the student’s study group and configure its periods before recording leave.');
        $calendar=$service->calendar($data['academic_year_id'],$data['campus_id']);$dates=[];$skipped=[];
        for($date=CarbonImmutable::parse($data['starts_on']);$date->toDateString()<=$data['ends_on'];$date=$date->addDay()){
            if($service->schoolDay($date->toDateString(),$calendar))$dates[]=$date->toDateString();else $skipped[]=$date->toDateString();
        }
        abort_if(!$dates,422,'This date range contains no school days. Check Attendance Settings.');
        $snapshots=$periods->map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'session'=>$p->session,'starts_at'=>substr($p->starts_at,0,5),'ends_at'=>substr($p->ends_at,0,5)])->values()->all();
        $conflicts=function()use($data,$dates){
            return DB::table('attendance_daily_marks')->where('academic_year_id',$data['academic_year_id'])->where('student_id',$data['student_id'])->whereIn('attendance_date',$dates)->pluck('attendance_date')
                ->merge(DB::table('attendance_leave_days')->where('academic_year_id',$data['academic_year_id'])->where('student_id',$data['student_id'])->whereIn('attendance_date',$dates)->whereIn('status',['scheduled','applied'])->pluck('attendance_date'))->unique()->values()->all();
        };
        if($data['preview']??false)return response()->json(['dates'=>$dates,'skipped'=>$skipped,'conflicts'=>$conflicts(),'periods'=>$snapshots]);
        $path=null;
        try{
            if($request->hasFile('attachment'))$path=$request->file('attachment')->store('attendance-leave','local');
            $id=DB::transaction(function()use($request,$data,$enrollment,$dates,$snapshots,$conflicts,$path){
                DB::table('tb_school_info')->where('id',$data['campus_id'])->lockForUpdate()->first();
                DB::table('tb_student')->where('id',$data['student_id'])->lockForUpdate()->first();
                if($blocked=$conflicts())throw ValidationException::withMessages(['dates'=>'Attendance or leave already exists on: '.implode(', ',$blocked).'. No records were replaced.']);
                $id=DB::table('attendance_leaves')->insertGetId(array_intersect_key($data,array_flip(['academic_year_id','campus_id','student_id','starts_on','ends_on','reason','approved_by','mode']))+
                    ['enrollment_id'=>$enrollment->id,'class_id'=>$enrollment->class_id,'grade_id'=>$enrollment->grade_id,'created_by'=>$request->user()->id,'periods'=>json_encode($snapshots),'attachment_path'=>$path,'attachment_name'=>$request->file('attachment')?->getClientOriginalName(),'created_at'=>now(),'updated_at'=>now()]);
                foreach($dates as $date)DB::table('attendance_leave_days')->insert(['leave_id'=>$id,'student_id'=>$data['student_id'],'academic_year_id'=>$data['academic_year_id'],'attendance_date'=>$date,'created_at'=>now(),'updated_at'=>now()]);
                $this->event($id,$request,'approved',['dates'=>$dates,'reason'=>$data['reason'],'approved_by'=>$data['approved_by']]);return $id;
            });
        }catch(\Throwable $error){if($path)Storage::disk('local')->delete($path);throw $error;}
        $service->applyDue($data['academic_year_id'],$data['campus_id']);
        return response()->json(['id'=>$id,'message'=>'Leave recorded. School days are marked EX when due.','dates'=>$dates,'skipped'=>$skipped]);
    }

    public function shorten(Request $request,int $leave)
    {
        $data=$request->validate(['ends_on'=>['required','date_format:Y-m-d','after_or_equal:'.now('Asia/Bangkok')->toDateString()],'version'=>['required','integer'],'reason'=>['required','string','max:500']]);
        $this->change($request,$leave,$data,'shortened');return response()->json(['message'=>'Future leave shortened. Existing attendance is retained.']);
    }
    public function cancel(Request $request,int $leave)
    {
        $data=$request->validate(['version'=>['required','integer'],'reason'=>['required','string','max:500']]);
        $this->change($request,$leave,$data,'cancelled');return response()->json(['message'=>'Remaining leave cancelled. Existing attendance is retained; correct it through the daily register if needed.']);
    }
    private function change(Request $request,int $id,array $data,string $action): void
    {
        $leave=DB::table('attendance_leaves')->find($id);abort_unless($leave,404);$this->authorize($request,$leave->campus_id);
        DB::transaction(function()use($request,$leave,$data,$action){
            DB::table('tb_school_info')->where('id',$leave->campus_id)->lockForUpdate()->first();
            DB::table('tb_student')->where('id',$leave->student_id)->lockForUpdate()->first();
            $row=DB::table('attendance_leaves')->where('id',$leave->id)->lockForUpdate()->first();
            abort_if($row->version!==$data['version']||$row->status!=='approved',409,'Leave changed. Reload before updating.');
            $cutoff=$action==='cancelled'?now('Asia/Bangkok')->subDay()->toDateString():$data['ends_on'];
            abort_if($action==='shortened'&&($cutoff<$row->starts_on||$cutoff>=$row->ends_on),422,'Choose a shorter end date within the existing leave range.');
            DB::table('attendance_leave_days')->where('leave_id',$row->id)->where('status','scheduled')->where('attendance_date','>',$cutoff)->update(['status'=>'cancelled','updated_at'=>now()]);
            DB::table('attendance_leaves')->where('id',$row->id)->update(['status'=>$action==='cancelled'?'cancelled':'approved','ends_on'=>$action==='shortened'?$cutoff:$row->ends_on,'version'=>$row->version+1,'updated_at'=>now()]);
            $this->event($row->id,$request,$action,['reason'=>$data['reason'],'old_ends_on'=>$row->ends_on,'cutoff'=>$cutoff]);
        });
    }
    private function event(int $id,Request $request,string $action,array $details): void
    {
        DB::table('attendance_leave_events')->insert(['leave_id'=>$id,'user_id'=>$request->user()->id,'action'=>$action,'details'=>json_encode($details),'created_at'=>now(),'updated_at'=>now()]);
    }
    public function attachment(Request $request,int $leave)
    {
        $row=DB::table('attendance_leaves')->find($leave);abort_unless($row,404);$this->authorize($request,$row->campus_id);
        abort_unless($row->attachment_path&&Storage::disk('local')->exists($row->attachment_path),404);
        return Storage::disk('local')->download($row->attachment_path,'leave-'.$row->id.'.'.pathinfo($row->attachment_path,PATHINFO_EXTENSION),['Cache-Control'=>'no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
