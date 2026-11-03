<?php

namespace App\Http\Controllers;

use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\AttendanceStudyGroup;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule,ValidationException};

class DailyAttendanceController
{
    private function scope(Request $request): array
    {
        $scope=$request->validate(['academic_year_id'=>['required','integer','exists:tb_academic_year,id'],'campus_id'=>['required','integer'],'class_id'=>['required','integer','exists:tb_class,id'],'date'=>['required','date_format:Y-m-d']]);
        $grade=$request->validate(['grade_id'=>['nullable','integer','exists:tb_grade,id']]);
        $scope['grade_id']=(int)($grade['grade_id']??0);
        if(!$scope['grade_id']){
            $grades=StudentEnrollment::where('academic_year_id',$scope['academic_year_id'])->where('campus_id',$scope['campus_id'])->where('class_id',$scope['class_id'])->whereNotNull('grade_id')->distinct()->pluck('grade_id');
            if($grades->count()>1)throw ValidationException::withMessages(['grade_id'=>'Select a grade and class, such as 1A.']);
            $scope['grade_id']=(int)($grades->first()??0);
        }
        $user=$request->user();$campus=(int)$scope['campus_id'];
        abort_unless($user->canAccessCampus($campus)&&$user->hasPermission('attendance.view',$campus)&&$user->hasPermission('attendance.manage',$campus),403);
        return $scope;
    }
    private function roster(array $scope)
    {
        return StudentEnrollment::with(['student.familyMembers','session','schoolGroup','grade'])->where('academic_year_id',$scope['academic_year_id'])->where('campus_id',$scope['campus_id'])->where('class_id',$scope['class_id'])
            ->when($scope['grade_id'],fn($q,$grade)=>$q->where('grade_id',$grade))
            ->whereNotIn('enrollment_status',['pending','cancelled','promotion_cancelled'])
            ->where(fn($q)=>$q->whereNull('enrolled_on')->orWhereDate('enrolled_on','<=',$scope['date']))
            ->where(fn($q)=>$q->whereNull('ended_on')->orWhereDate('ended_on','>=',$scope['date']))
            ->get()->unique('student_id')->sortBy(fn($e)=>$e->student?->full_name_kh?:$e->student?->full_name_en)->values();
    }
    private function registerScope(array $scope): array
    {
        return ['academic_year_id'=>$scope['academic_year_id'],'campus_id'=>$scope['campus_id'],'grade_id'=>$scope['grade_id'],'class_id'=>$scope['class_id'],'attendance_date'=>$scope['date']];
    }
    public function show(Request $request)
    {
        $scope=$this->scope($request);
        $register=DB::table('attendance_daily_registers')->where($this->registerScope($scope))->first();
        $roster=$this->roster($scope);
        $marks=DB::table('attendance_daily_marks')->where('academic_year_id',$scope['academic_year_id'])->where('attendance_date',$scope['date'])->whereIn('student_id',$roster->pluck('student_id'))->get()->keyBy('student_id');
        $marks->each(function($mark){$mark->absent_periods=json_decode($mark->absent_periods??'[]',true)?:[];});
        $students=$roster->map(fn($e)=>['id'=>$e->student_id,'code'=>$e->student?->student_id,'name'=>$e->student?->full_name_en,'name_kh'=>$e->student?->full_name_kh,'mark'=>isset($marks[$e->student_id])&&$marks[$e->student_id]->register_id===$register?->id?$marks[$e->student_id]:null,
            'other_class'=>isset($marks[$e->student_id])&&$marks[$e->student_id]->register_id!==$register?->id]);
        $authors=User::with('position')->whereIn('id',$marks->pluck('created_by')->filter()->unique())->get()->keyBy('id');
        $periods=DB::table('schedule_periods')->where('active',true)->whereNull('deleted_at')->get();
        $students=$students->map(function($student)use($roster,$authors,$periods){
            $enrollment=$roster->firstWhere('student_id',$student['id']);
            $record=$enrollment->student;
            $student['study_group']=AttendanceStudyGroup::code($enrollment);
            $student['allowed_period_ids']=$periods->filter(fn($period)=>AttendanceStudyGroup::allowsForEnrollment($period,$enrollment))->pluck('id')->values();
            $student['photo_url']=$record?->photo_path?asset('storage/'.ltrim($record->photo_path,'/')):null;
            foreach(['mother','father'] as $relationship){
                $parent=$record?->familyMembers->first(fn($member)=>($member->pivot->relationship_type?:$member->relationship_type)===$relationship);
                $student[$relationship]=['name'=>$parent?->full_name_kh?:$parent?->full_name_en,'phone'=>$parent?->phone];
            }
            $author=$authors->get($student['mark']?->created_by);
            $student['added_by']=['name'=>$author?->name,'position'=>$author?->position?->name,'date'=>$student['mark']?->created_at?CarbonImmutable::parse($student['mark']->created_at)->format('d-M-Y H:i'):null];
            return $student;
        });
        $history=$register?DB::table('attendance_daily_events as e')->join('users as u','u.id','=','e.user_id')->where('e.register_id',$register->id)->orderByDesc('e.id')->limit(20)->get(['u.name','e.created_at']):[];
        return response()->json(['register'=>$register,'students'=>$students,'history'=>$history]);
    }
    public function save(Request $request)
    {
        $scope=$this->scope($request);
        abort_if($scope['date']>now('Asia/Bangkok')->toDateString(),422,'Daily attendance cannot be saved for a future date.');
        $input=$request->validate(['version'=>['required','integer','min:0'],'partial'=>['sometimes','boolean'],'marks'=>['required','array','min:1'],
            'marks.*.student_id'=>['required','integer','distinct'],'marks.*.status'=>['required',Rule::in(['P','EX','UX','L'])],
            'marks.*.missed_sessions'=>['required','integer','between:0,99'],'marks.*.remarks'=>['nullable','string','max:500'],
            'marks.*.absent_period_ids'=>['sometimes','array','max:99'],'marks.*.absent_period_ids.*'=>['integer']]);
        $roster=$this->roster($scope)->keyBy('student_id');
        if(!($input['partial']??false)&&count($input['marks'])!==$roster->count())throw ValidationException::withMessages(['marks'=>'Mark every student in the class before saving.']);
        foreach($input['marks'] as $mark)if(!isset($roster[$mark['student_id']]))throw ValidationException::withMessages(['marks'=>'A student is not enrolled in the selected class on this date.']);
        DB::transaction(function()use($scope,$input,$roster,$request){
            DB::table('tb_school_info')->where('id',$scope['campus_id'])->lockForUpdate()->first();
            DB::table('tb_student')->whereIn('id',$roster->keys())->orderBy('id')->lockForUpdate()->get();
            $register=DB::table('attendance_daily_registers')->where($this->registerScope($scope))->lockForUpdate()->first();
            abort_if(($register->version??0)!==$input['version'],409,'Another staff member updated this register. Reload before saving.');
            $existing=DB::table('attendance_daily_marks')->where('academic_year_id',$scope['academic_year_id'])->where('attendance_date',$scope['date'])->whereIn('student_id',$roster->keys())->lockForUpdate()->get();
            $periods=DB::table('schedule_periods')->whereIn('id',collect($input['marks'])->pluck('absent_period_ids')->flatten()->unique())->get()->keyBy('id');
            if($existing->contains(fn($mark)=>$mark->register_id!==$register?->id))throw ValidationException::withMessages(['marks'=>'A student already has daily attendance in another class. Update the original register to prevent duplicate daily records.']);
            $id=$register?->id??DB::table('attendance_daily_registers')->insertGetId($this->registerScope($scope)+['updated_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
            foreach($input['marks'] as $mark){
                $previous=$existing->firstWhere('student_id',$mark['student_id']);
                $snapshots=json_decode($previous?->absent_periods??'[]',true)?:[];
                if(array_key_exists('absent_period_ids',$mark)){
                    $saved=collect($snapshots)->keyBy('id');$snapshots=[];
                    foreach(array_unique($mark['absent_period_ids']) as $periodId){
                        if($mark['status']==='P')break;
                        $period=$periods->get($periodId);
                        if(!$period||((!$period->active||$period->deleted_at||!AttendanceStudyGroup::allowsForEnrollment($period,$roster[$mark['student_id']]))&&!$saved->has($periodId)))throw ValidationException::withMessages(['marks'=>'Choose an active absent period assigned to this student’s study group and grade.']);
                        $snapshots[]=$saved->get($periodId)??['id'=>$period->id,'name'=>$period->name,'session'=>$period->session,'starts_at'=>substr($period->starts_at,0,5),'ends_at'=>substr($period->ends_at,0,5)];
                    }
                }
                if($mark['status']==='P')$snapshots=[];
                $missed=$mark['status']==='P'?0:($snapshots?count($snapshots):$mark['missed_sessions']);
                DB::table('attendance_daily_marks')->updateOrInsert(['academic_year_id'=>$scope['academic_year_id'],'student_id'=>$mark['student_id'],'attendance_date'=>$scope['date']],
                    ['register_id'=>$id,'enrollment_id'=>$roster[$mark['student_id']]->id,'status'=>$mark['status'],'missed_sessions'=>$missed,'absent_periods'=>json_encode($snapshots),'remarks'=>$mark['remarks']??null,'created_by'=>$previous?->created_by??($previous?null:$request->user()->id),'created_at'=>$previous?->created_at??now(),'updated_at'=>now()]);
            }
            DB::table('attendance_daily_registers')->where('id',$id)->update(['version'=>$input['version']+1,'updated_by'=>$request->user()->id,'updated_at'=>now()]);
            DB::table('attendance_daily_events')->insert(['register_id'=>$id,'user_id'=>$request->user()->id,'details'=>json_encode(['before'=>$existing,'after'=>$input['marks']]),'created_at'=>now(),'updated_at'=>now()]);
        });
        return response()->json(['message'=>'Daily attendance saved. Each student is counted once for this date.','version'=>$input['version']+1]);
    }
}
