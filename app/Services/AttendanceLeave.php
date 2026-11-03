<?php

namespace App\Services;

use App\Models\StudentEnrollment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AttendanceLeave
{
    public function calendar(int $year,int $campus): array
    {
        $row=DB::table('attendance_calendars')->where('academic_year_id',$year)->where('campus_id',$campus)->first();
        return ['weekdays'=>$row?json_decode($row->weekdays,true):[1,2,3,4,5],
            'holidays'=>$row?json_decode($row->holidays,true):[],'holiday_descriptions'=>json_decode($row?->holiday_descriptions??'{}',true)?:[],'version'=>$row?->version??0];
    }

    public function schoolDay(string $date,array $calendar): bool
    {
        return in_array(CarbonImmutable::parse($date)->dayOfWeek,$calendar['weekdays'],true)&&!in_array($date,$calendar['holidays'],true);
    }

    /** Materialize due EX marks only; never overwrite any attendance already entered. */
    public function applyDue(?int $year=null,?int $campus=null,?string $date=null): void
    {
        $query=DB::table('attendance_leave_days as d')->join('attendance_leaves as l','l.id','=','d.leave_id')
            ->where('d.status','scheduled')->where('l.status','approved')->where('d.attendance_date','<=',now('Asia/Bangkok')->toDateString())
            ->when($year,fn($q)=>$q->where('l.academic_year_id',$year))->when($campus,fn($q)=>$q->where('l.campus_id',$campus))
            ->when($date,fn($q)=>$q->where('d.attendance_date',$date))->select('d.id')->orderBy('d.id');
        $query->chunkById(100,function($days){foreach($days as $day)$this->applyDay($day->id);},'d.id','id');
    }

    private function applyDay(int $id): void
    {
        $day=DB::table('attendance_leave_days')->find($id);$leave=DB::table('attendance_leaves')->find($day->leave_id);
        DB::transaction(function()use($id,$leave){
            DB::table('tb_school_info')->where('id',$leave->campus_id)->lockForUpdate()->first();
            DB::table('tb_student')->where('id',$leave->student_id)->lockForUpdate()->first();
            $leave=DB::table('attendance_leaves')->where('id',$leave->id)->lockForUpdate()->first();
            $day=DB::table('attendance_leave_days')->where('id',$id)->lockForUpdate()->first();
            if($day->status!=='scheduled'||$leave->status!=='approved')return;
            $enrollment=StudentEnrollment::find($leave->enrollment_id);
            $valid=$enrollment&&$enrollment->campus_id===$leave->campus_id&&$enrollment->academic_year_id===$leave->academic_year_id
                &&$enrollment->class_id===$leave->class_id&&($enrollment->grade_id??0)===($leave->grade_id??0)
                &&!in_array($enrollment->enrollment_status,['pending','cancelled','promotion_cancelled','withdrawn'],true)
                &&(!$enrollment->enrolled_on||$enrollment->enrolled_on->toDateString()<=$day->attendance_date)
                &&(!$enrollment->ended_on||$enrollment->ended_on->toDateString()>=$day->attendance_date)
                &&$this->schoolDay($day->attendance_date,$this->calendar($leave->academic_year_id,$leave->campus_id));
            $existing=DB::table('attendance_daily_marks')->where('academic_year_id',$leave->academic_year_id)->where('student_id',$leave->student_id)->where('attendance_date',$day->attendance_date)->first();
            if(!$valid||$existing){
                DB::table('attendance_leave_days')->where('id',$id)->update(['status'=>$existing?'conflict':'skipped','updated_at'=>now()]);
                return;
            }
            $scope=['academic_year_id'=>$leave->academic_year_id,'campus_id'=>$leave->campus_id,'grade_id'=>$enrollment->grade_id??0,'class_id'=>$enrollment->class_id,'attendance_date'=>$day->attendance_date];
            $register=DB::table('attendance_daily_registers')->where($scope)->lockForUpdate()->first();
            $registerId=$register?->id??DB::table('attendance_daily_registers')->insertGetId($scope+['version'=>0,'updated_by'=>$leave->created_by,'created_at'=>now(),'updated_at'=>now()]);
            $periods=json_decode($leave->periods,true);
            DB::table('attendance_daily_marks')->insert(['register_id'=>$registerId,'academic_year_id'=>$leave->academic_year_id,'student_id'=>$leave->student_id,
                'enrollment_id'=>$enrollment->id,'attendance_date'=>$day->attendance_date,'status'=>'EX','missed_sessions'=>count($periods),
                'absent_periods'=>$leave->periods,'remarks'=>$leave->reason,'created_by'=>$leave->created_by,'leave_id'=>$leave->id,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('attendance_daily_registers')->where('id',$registerId)->update(['version'=>($register?->version??0)+1,'updated_at'=>now(),'updated_by'=>$leave->created_by]);
            DB::table('attendance_leave_days')->where('id',$id)->update(['status'=>'applied','updated_at'=>now()]);
            DB::table('attendance_daily_events')->insert(['register_id'=>$registerId,'user_id'=>$leave->created_by,'details'=>json_encode(['action'=>'approved_leave','leave_id'=>$leave->id,'student_id'=>$leave->student_id,'status'=>'EX']),'created_at'=>now(),'updated_at'=>now()]);
        });
    }
}
