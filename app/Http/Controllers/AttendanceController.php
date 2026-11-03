<?php

namespace App\Http\Controllers;

use App\Models\{AcademicYear,SchoolClass,SchoolInfo,StudentEnrollment,User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule,ValidationException};
use Carbon\CarbonImmutable;

class AttendanceController
{
    private const STATUSES=['present','absent','late','excused','sick'];

    private function assertScheduleDate(object $assignment,string $date): void
    {
        abort_if(($assignment->effective_from??null)&&$date<$assignment->effective_from,422,'This schedule has not started on the selected date.');
        abort_if(($assignment->effective_until??null)&&$date>$assignment->effective_until,422,'This schedule has ended. Select the current scheduled session.');
    }

    private function campuses(Request $request,bool $manage=false): array
    {
        return $request->user()->accessibleCampuses()->get()->filter(fn($campus)=>
            $request->user()->hasPermission('attendance.view',$campus->id)&&(!$manage||$request->user()->hasPermission('attendance.manage',$campus->id))
        )->modelKeys();
    }
    private function assignments(Request $request)
    {
        $admin=$this->campuses($request,true);
        return DB::table('attendance_assignments as a')->whereIn('a.campus_id',$this->campuses($request))
            ->where(function($q)use($request,$admin){
                $q->whereIn('a.campus_id',$admin)->orWhere(function($teacher)use($request){
                    $teacher->where('a.teacher_id',$request->user()->id);
                    $ids=$request->user()->accessibleCampuses()->get()->filter(fn($c)=>$request->user()->hasPermission('attendance.take',$c->id))->modelKeys();
                    $teacher->whereIn('a.campus_id',$ids);
                });
            });
    }
    private function assignment(Request $request,int $id)
    {
        return $this->assignments($request)->where('a.id',$id)->firstOrFail();
    }
    public function index(Request $request)
    {
        abort_unless($this->campuses($request),403);
        if(in_array($request->route('workspace'),['daily','settings'],true))abort_unless($this->campuses($request,true),403);
        if($request->route('workspace')==='settings')return view('attendance.settings');
        return view('attendance.index');
    }
    public function meta(Request $request)
    {
        $ids=$this->campuses($request);$manage=$this->campuses($request,true);
        $classes=SchoolClass::with('grade')->whereHas('enrollments',fn($q)=>$q->whereIn('campus_id',$ids))->orderBy('class_name')->get(['id','class_name','academic_year_id','grade_id'])->map(function($class){
            $grade=trim((string)($class->grade?->grade_short_name?:$class->grade?->grade));
            if(preg_match('/\d+/u',$grade,$match))$grade=$match[0];
            $name=trim($class->class_name);
            return ['id'=>$class->id,'academic_year_id'=>$class->academic_year_id,'class_name'=>$grade!==''&&!str_starts_with($name,$grade)?$grade.$name:$name];
        })->sortBy('class_name',SORT_NATURAL)->values();
        return response()->json([
            'campuses'=>SchoolInfo::whereIn('id',$ids)->orderBy('campus_name_en')->get(['id','campus_name_en']),
            'manage_campuses'=>$manage,
            'periods'=>DB::table('schedule_periods')->where('active',true)->whereNull('deleted_at')->orderBy('display_order')->orderBy('starts_at')->get()->map(function($period){$period->applicable_groups=json_decode($period->applicable_groups??'[]',true)?:[];return $period;}),
            'years'=>AcademicYear::orderByDesc('academic_year')->get(['id','academic_year']),
            'classes'=>$classes,
            'class_scopes'=>StudentEnrollment::with(['grade','schoolClass'])->whereIn('campus_id',$ids)->select('academic_year_id','campus_id','class_id','grade_id')->distinct()->get()->map(function($enrollment){
                $grade=trim((string)($enrollment->grade?->grade_short_name?:$enrollment->grade?->grade));
                $name=trim((string)$enrollment->schoolClass?->class_name);
                return ['academic_year_id'=>$enrollment->academic_year_id,'campus_id'=>$enrollment->campus_id,'class_id'=>$enrollment->class_id,'grade_id'=>$enrollment->grade_id,
                    'label'=>$grade!==''&&!str_starts_with($name,$grade)?$grade.$name:$name];
            })->sortBy('label',SORT_NATURAL)->values(),
            'teachers'=>User::where('status',true)->where(function($q)use($manage){$q->whereHas('campuses',fn($c)=>$c->whereIn('tb_school_info.id',$manage))->orWhere('is_global',true);})->orderBy('name')->get(['id','name']),
        ]);
    }
    public function list(Request $request)
    {
        $input=$request->validate(['date'=>['required','date_format:Y-m-d'],'academic_year_id'=>['required','integer'],'campus_id'=>['nullable','integer'],'mode'=>['nullable',Rule::in(['mine','campus'])]]);
        $date=CarbonImmutable::parse($input['date']);
        $query=$this->assignments($request)->where('a.academic_year_id',$input['academic_year_id'])->where('a.active',true)
            ->where(fn($q)=>$q->whereNull('a.effective_from')->orWhere('a.effective_from','<=',$input['date']))
            ->where(fn($q)=>$q->whereNull('a.effective_until')->orWhere('a.effective_until','>=',$input['date']))
            ->where('a.weekday',$date->dayOfWeek)->when($input['campus_id']??null,fn($q,$v)=>$q->where('a.campus_id',$v));
        if(($input['mode']??'mine')==='mine')$query->where('a.teacher_id',$request->user()->id);
        else $query->whereIn('a.campus_id',$this->campuses($request,true));
        $sessions=$query->join('tb_class as c','c.id','=','a.class_id')->join('tb_school_info as campus','campus.id','=','a.campus_id')->join('users as u','u.id','=','a.teacher_id')
            ->leftJoin('attendance_sessions as s',fn($join)=>$join->on('s.assignment_id','=','a.id')->where('s.attendance_date',$date->toDateString()))
            ->orderBy('campus.campus_name_en')->orderBy('a.starts_at')->orderBy('c.class_name')
            ->leftJoin('tb_grade as g','g.id','=','a.grade_id')
            ->get(['a.*','c.class_name','g.grade_short_name','campus.campus_name_en','u.name as teacher_name','s.id as session_id','s.status as session_status']);
        $sessions->each(function($session){
            $grade=(string)$session->grade_short_name;
            if($grade&&!str_starts_with($session->class_name,$grade))$session->class_name=$grade.$session->class_name;
        });
        return response()->json(['sessions'=>$sessions]);
    }
    public function assign(Request $request)
    {
        $data=$request->validate(['campus_id'=>['required','integer'],'academic_year_id'=>['required','integer','exists:tb_academic_year,id'],'class_id'=>['required','integer'],'teacher_id'=>['required','integer','exists:users,id'],
            'subject'=>['required','string','max:100'],'weekday'=>['required','integer','between:0,6'],'starts_at'=>['required','date_format:H:i'],'ends_at'=>['required','date_format:H:i','after:starts_at']]);
        abort_unless(in_array((int)$data['campus_id'],$this->campuses($request,true),true),403);
        abort_unless(StudentEnrollment::where('campus_id',$data['campus_id'])->where('academic_year_id',$data['academic_year_id'])->where('class_id',$data['class_id'])->exists(),422,'Choose a class with enrollments in this campus and year.');
        $teacher=User::findOrFail($data['teacher_id']);
        abort_unless($teacher->status&&$teacher->canAccessCampus((int)$data['campus_id']),422,'Teacher must have access to this campus.');
        $data['subject']=trim($data['subject']);
        DB::transaction(function()use($data){
            // Serialize concurrent assignment creation per campus.
            DB::table('users')->where('id',$data['teacher_id'])->lockForUpdate()->first();
            DB::table('tb_school_info')->where('id',$data['campus_id'])->lockForUpdate()->first();
            $conflict=DB::table('attendance_assignments')->where('active',true)->where('academic_year_id',$data['academic_year_id'])->where('weekday',$data['weekday'])
                ->where('starts_at','<',$data['ends_at'])->where('ends_at','>',$data['starts_at'])
                ->where(fn($q)=>$q->where('teacher_id',$data['teacher_id'])->orWhere(fn($q)=>$q->where('class_id',$data['class_id'])->where('campus_id',$data['campus_id'])))->exists();
            if($conflict)throw ValidationException::withMessages(['starts_at'=>'This class or teacher already has an overlapping subject session.']);
            DB::table('attendance_assignments')->insert($data+['active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        });
        return response()->json(['message'=>'Subject session assigned.']);
    }
    public function assignmentList(Request $request)
    {
        $data=$request->validate(['academic_year_id'=>['required','integer'],'campus_id'=>['nullable','integer']]);
        return response()->json(['assignments'=>DB::table('attendance_assignments as a')->whereIn('a.campus_id',$this->campuses($request,true))
            ->where('a.academic_year_id',$data['academic_year_id'])->when($data['campus_id']??null,fn($q,$v)=>$q->where('a.campus_id',$v))
            ->join('tb_class as c','c.id','=','a.class_id')->join('users as u','u.id','=','a.teacher_id')->orderBy('a.weekday')->orderBy('a.starts_at')
            ->get(['a.*','c.class_name','u.name as teacher_name'])]);
    }
    public function assignmentStatus(Request $request,int $assignment)
    {
        $a=$this->assignment($request,$assignment);
        abort_unless(in_array((int)$a->campus_id,$this->campuses($request,true),true),403);
        $request->validate(['active'=>['required','boolean']]);
        // Reactivation uses the same collision checks as creating a weekly period.
        if($request->boolean('active')){
            $conflict=DB::table('attendance_assignments')->where('id','!=',$assignment)->where('active',true)->where('academic_year_id',$a->academic_year_id)->where('weekday',$a->weekday)
                ->where('starts_at','<',$a->ends_at)->where('ends_at','>',$a->starts_at)
                ->where(fn($q)=>$q->where('teacher_id',$a->teacher_id)->orWhere(fn($q)=>$q->where('class_id',$a->class_id)->where('campus_id',$a->campus_id)))->exists();
            if($conflict)throw ValidationException::withMessages(['active'=>'Another active subject session overlaps this class or teacher.']);
        }
        DB::table('attendance_assignments')->where('id',$assignment)->update(['active'=>$request->boolean('active'),'updated_at'=>now()]);
        return response()->json(['message'=>'Weekly assignment updated. Existing attendance records are retained.']);
    }
    public function history(Request $request)
    {
        $input=$request->validate(['academic_year_id'=>['required','integer'],'campus_id'=>['nullable','integer'],'from'=>['required','date_format:Y-m-d'],'to'=>['required','date_format:Y-m-d','after_or_equal:from'],'format'=>['nullable',Rule::in(['csv'])]]);
        if(CarbonImmutable::parse($input['from'])->diffInDays(CarbonImmutable::parse($input['to']))>93)throw ValidationException::withMessages(['to'=>'Choose a date range of at most 93 days.']);
        $query=$this->assignments($request)->where('a.academic_year_id',$input['academic_year_id'])->when($input['campus_id']??null,fn($q,$v)=>$q->where('a.campus_id',$v))
            ->join('attendance_sessions as s','s.assignment_id','=','a.id')->whereBetween('s.attendance_date',[$input['from'],$input['to']])
            ->join('tb_class as c','c.id','=','a.class_id')->join('tb_school_info as campus','campus.id','=','a.campus_id')->join('users as u','u.id','=','a.teacher_id')
            ->leftJoin('attendance_marks as m','m.session_id','=','s.id')
            ->select(['s.id','s.assignment_id','s.attendance_date','s.status','a.subject','c.class_name','campus.campus_name_en','u.name as teacher_name'])
            ->selectRaw("COUNT(m.id) as marked, SUM(CASE WHEN m.status='present' THEN 1 ELSE 0 END) as present, SUM(CASE WHEN m.status='absent' THEN 1 ELSE 0 END) as absent, SUM(CASE WHEN m.status='late' THEN 1 ELSE 0 END) as late, SUM(CASE WHEN m.status='excused' THEN 1 ELSE 0 END) as excused, SUM(CASE WHEN m.status='sick' THEN 1 ELSE 0 END) as sick")
            ->groupBy('s.id','s.assignment_id','s.attendance_date','s.status','a.subject','c.class_name','campus.campus_name_en','u.name')->orderByDesc('s.attendance_date')->orderBy('c.class_name');
        $rows=$query->get();
        if(($input['format']??'')==='csv')return response()->streamDownload(function()use($rows){
            $stream=fopen('php://output','w');fwrite($stream,"\xEF\xBB\xBF");
            fputcsv($stream,['Date','Campus','Class','Subject','Teacher','Register Status','Present','Absent','Late','Excused','Sick'],',','"','');
            foreach($rows as $row){$values=[$row->attendance_date,$row->campus_name_en,$row->class_name,$row->subject,$row->teacher_name,$row->status,$row->present,$row->absent,$row->late,$row->excused,$row->sick];
                fputcsv($stream,array_map(fn($v)=>preg_match('/^[=+\-@\t\r]/',(string)$v)?"'".$v:$v,$values),',','"','');}
            fclose($stream);
        },'student-attendance.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
        return response()->json(['history'=>$rows]);
    }
    private function roster($assignment,string $date)
    {
        return StudentEnrollment::with('student')->where('academic_year_id',$assignment->academic_year_id)->where('campus_id',$assignment->campus_id)->where('class_id',$assignment->class_id)
            ->when($assignment->grade_id??null,fn($q,$grade)=>$q->where('grade_id',$grade))
            ->where(fn($q)=>$q->whereNull('enrolled_on')->orWhereDate('enrolled_on','<=',$date))
            ->where(fn($q)=>$q->whereNull('ended_on')->orWhereDate('ended_on','>=',$date))
            ->whereNotIn('enrollment_status',['pending','cancelled','promotion_cancelled'])->get()->unique('student_id')->values();
    }
    public function show(Request $request,int $assignment)
    {
        $a=$this->assignment($request,$assignment);$date=$request->validate(['date'=>['required','date_format:Y-m-d']])['date'];
        $this->assertScheduleDate($a,$date);
        abort_unless(CarbonImmutable::parse($date)->dayOfWeek===$a->weekday,422,'This session is not scheduled on that day.');
        $session=DB::table('attendance_sessions')->where('assignment_id',$assignment)->where('attendance_date',$date)->first();
        $marks=$session?DB::table('attendance_marks')->where('session_id',$session->id)->get()->keyBy('student_id'):collect();
        $roster=$this->roster($a,$date)->map(fn($enrollment)=>['enrollment_id'=>$enrollment->id,'student_id'=>$enrollment->student_id,'code'=>$enrollment->student?->student_id,
            'name'=>$enrollment->student?->full_name_en,'name_kh'=>$enrollment->student?->full_name_kh,'mark'=>$marks[$enrollment->student_id]??null]);
        $history=$session?DB::table('attendance_events as e')->join('users as u','u.id','=','e.user_id')->where('session_id',$session->id)->orderByDesc('e.id')->limit(20)->get(['e.action','e.details','e.created_at','u.name']):[];
        return response()->json(['students'=>$roster,'session'=>$session,'history'=>$history,'can_reopen'=>in_array((int)$a->campus_id,$this->campuses($request,true),true)]);
    }
    public function save(Request $request,int $assignment)
    {
        $a=$this->assignment($request,$assignment);
        $input=$request->validate(['date'=>['required','date_format:Y-m-d','before_or_equal:today'],'version'=>['required','integer','min:0'],'submit'=>['required','boolean'],
            'marks'=>['required','array','min:1'],'marks.*.student_id'=>['required','integer','distinct'],'marks.*.status'=>['nullable',Rule::in(self::STATUSES)],'marks.*.late_minutes'=>['required','integer','between:0,480'],'marks.*.note'=>['nullable','string','max:500']]);
        abort_unless(CarbonImmutable::parse($input['date'])->dayOfWeek===$a->weekday,422);
        $this->assertScheduleDate($a,$input['date']);
        $roster=$this->roster($a,$input['date'])->keyBy('student_id');
        foreach($input['marks'] as $mark)if(!isset($roster[$mark['student_id']]))throw ValidationException::withMessages(['marks'=>'A student is not enrolled in this class on that date.']);
        if($input['submit']&&(count($input['marks'])!==$roster->count()||collect($input['marks'])->contains(fn($m)=>empty($m['status']))))throw ValidationException::withMessages(['marks'=>'Mark every student before submitting.']);
        DB::transaction(function()use($input,$a,$roster,$request){
            $currentAssignment=DB::table('attendance_assignments')->where('id',$a->id)->lockForUpdate()->first();
            $this->assertScheduleDate($currentAssignment,$input['date']);
            $session=DB::table('attendance_sessions')->where('assignment_id',$a->id)->where('attendance_date',$input['date'])->lockForUpdate()->first();
            abort_if($session&&$session->status==='submitted',409,'Submitted attendance is locked. Ask a campus administrator to reopen it.');
            abort_if(($session->version??0)!==$input['version'],409,'This register was updated by another user. Reload it before saving.');
            $id=$session?->id??DB::table('attendance_sessions')->insertGetId(['assignment_id'=>$a->id,'attendance_date'=>$input['date'],'created_at'=>now(),'updated_at'=>now()]);
            $before=DB::table('attendance_marks')->where('session_id',$id)->get()->all();
            foreach($input['marks'] as $mark){
                if(empty($mark['status'])){DB::table('attendance_marks')->where('session_id',$id)->where('student_id',$mark['student_id'])->delete();continue;}
                DB::table('attendance_marks')->updateOrInsert(['session_id'=>$id,'student_id'=>$mark['student_id']],['enrollment_id'=>$roster[$mark['student_id']]->id,'status'=>$mark['status'],'late_minutes'=>$mark['status']==='late'?$mark['late_minutes']:0,'note'=>$mark['note']??null,'updated_at'=>now(),'created_at'=>now()]);
            }
            DB::table('attendance_sessions')->where('id',$id)->update(['status'=>$input['submit']?'submitted':'draft','version'=>$input['version']+1,'updated_by'=>$request->user()->id,'submitted_at'=>$input['submit']?now():null,'updated_at'=>now()]);
            DB::table('attendance_events')->insert(['session_id'=>$id,'user_id'=>$request->user()->id,'action'=>$input['submit']?'submitted':'draft_saved','details'=>json_encode(['before'=>$before,'after'=>$input['marks']]),'created_at'=>now(),'updated_at'=>now()]);
        });
        return response()->json(['message'=>$input['submit']?'Attendance submitted and locked.':'Draft saved.']);
    }
    public function reopen(Request $request,int $assignment)
    {
        $a=$this->assignment($request,$assignment);abort_unless(in_array((int)$a->campus_id,$this->campuses($request,true),true),403);
        $data=$request->validate(['date'=>['required','date_format:Y-m-d'],'reason'=>['required','string','min:5','max:500']]);
        DB::transaction(function()use($data,$assignment,$request){
            $session=DB::table('attendance_sessions')->where('assignment_id',$assignment)->where('attendance_date',$data['date'])->lockForUpdate()->firstOrFail();
            abort_unless($session->status==='submitted',409);
            DB::table('attendance_sessions')->where('id',$session->id)->update(['status'=>'draft','version'=>$session->version+1,'updated_at'=>now(),'updated_by'=>$request->user()->id]);
            DB::table('attendance_events')->insert(['session_id'=>$session->id,'user_id'=>$request->user()->id,'action'=>'reopened','details'=>json_encode(['reason'=>$data['reason']]),'created_at'=>now(),'updated_at'=>now()]);
        });return response()->json(['message'=>'Attendance reopened for correction.']);
    }
}
