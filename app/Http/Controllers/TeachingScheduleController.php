<?php

namespace App\Http\Controllers;

use App\Models\{AcademicYear, SchoolInfo, StudentEnrollment, User};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeachingScheduleController
{
    private function campuses(Request $request, bool $manage = false): array
    {
        return $request->user()->accessibleCampuses()->get()->filter(fn ($campus) =>
            $request->user()->hasPermission('schedules.view', $campus->id)
            && (!$manage || $request->user()->hasPermission('schedules.manage', $campus->id))
        )->modelKeys();
    }

    public function index(Request $request)
    {
        abort_unless($this->campuses($request),403);
        return view('schedules.index');
    }

    public function meta(Request $request)
    {
        $campuses = $this->campuses($request);
        $manage = $this->campuses($request,true);
        $classes = StudentEnrollment::with(['grade','schoolClass'])->whereIn('campus_id',$campuses)
            ->select('academic_year_id','campus_id','grade_id','class_id')->distinct()->get()->map(function ($enrollment) {
                $grade = trim((string)($enrollment->grade?->grade_short_name ?: $enrollment->grade?->grade));
                $class = trim((string)$enrollment->schoolClass?->class_name);
                return ['academic_year_id'=>$enrollment->academic_year_id,'campus_id'=>$enrollment->campus_id,
                    'grade_id'=>$enrollment->grade_id,'class_id'=>$enrollment->class_id,
                    'label'=>$grade && !str_starts_with($class,$grade) ? $grade.$class : $class];
            })->sortBy('label',SORT_NATURAL)->values();
        return response()->json([
            'years'=>AcademicYear::orderByDesc('academic_year')->get(['id','academic_year']),
            'campuses'=>SchoolInfo::whereIn('id',$campuses)->get(['id','campus_name_en']),
            'manage_campuses'=>$manage,'classes'=>$classes,
            'periods'=>DB::table('schedule_periods')->where('active',true)->whereNull('deleted_at')->orderBy('display_order')->orderBy('starts_at')->get(),
            'teachers'=>User::where('status',true)->where(function ($query) use ($campuses) {
                $query->whereHas('campuses',fn ($q) => $q->whereIn('tb_school_info.id',$campuses))->orWhere('is_global',true);
            })->orderBy('name')->get(['id','name']),
        ]);
    }

    public function listing(Request $request)
    {
        $input = $request->validate(['academic_year_id'=>['required','integer'],'campus_id'=>['nullable','integer']]);
        $manage = $this->campuses($request,true);
        $rows = DB::table('teaching_schedules as s')->whereIn('s.campus_id',$this->campuses($request))
            ->where('s.academic_year_id',$input['academic_year_id'])
            ->when($input['campus_id'] ?? null,fn ($q,$id) => $q->where('s.campus_id',$id))
            ->where(fn ($q) => $q->whereIn('s.campus_id',$manage)->orWhere(fn ($q) => $q->where('s.teacher_id',$request->user()->id)->where('s.status','published')))
            ->join('users as u','u.id','=','s.teacher_id')->join('tb_class as c','c.id','=','s.class_id')
            ->join('tb_school_info as campus','campus.id','=','s.campus_id')->leftJoin('tb_grade as g','g.id','=','s.grade_id')
            ->orderBy('s.weekday')->orderBy('s.starts_at')->get(['s.*','u.name as teacher_name','c.class_name','g.grade_short_name','campus.campus_name_en']);
        return response()->json(['schedules'=>$rows]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'campus_id'=>['required','integer'],'academic_year_id'=>['required','integer','exists:tb_academic_year,id'],
            'class_id'=>['required','integer'],'grade_id'=>['nullable','integer','exists:tb_grade,id'],
            'teacher_id'=>['required','integer','exists:users,id'],'subject'=>['required','string','max:100'],
            'weekday'=>['required','integer','between:0,6'],'period_id'=>['required','integer','exists:schedule_periods,id'],
            'effective_from'=>['required','date_format:Y-m-d'],
            'effective_until'=>['required','date_format:Y-m-d','after_or_equal:effective_from'],
            'replaces_id'=>['nullable','integer','exists:teaching_schedules,id'],
        ]);
        abort_unless(in_array((int)$data['campus_id'],$this->campuses($request,true),true),403);
        $period=DB::table('schedule_periods')->where('id',$data['period_id'])->where('active',true)->whereNull('deleted_at')->first();
        abort_unless($period,422,'Choose an active shared session period.');
        $data['starts_at']=$period->starts_at;$data['ends_at']=$period->ends_at;
        $enrollments = StudentEnrollment::where('campus_id',$data['campus_id'])->where('academic_year_id',$data['academic_year_id'])->where('class_id',$data['class_id']);
        if (empty($data['grade_id'])) {
            $grades = (clone $enrollments)->whereNotNull('grade_id')->distinct()->pluck('grade_id');
            if ($grades->count()>1) throw ValidationException::withMessages(['grade_id'=>'Select a grade and class, such as 1A.']);
            $data['grade_id'] = $grades->first();
        }
        abort_unless($enrollments->when($data['grade_id'],fn ($q,$id) => $q->where('grade_id',$id))->exists(),422,'Select a class enrolled in this campus and academic year.');
        $teacher = User::findOrFail($data['teacher_id']);
        abort_unless($teacher->status && $teacher->canAccessCampus((int)$data['campus_id']),422,'Teacher must be active and have access to this campus.');
        $data['subject'] = trim($data['subject']);
        abort_if($data['subject']==='',422,'Enter a subject.');
        return $data;
    }

    public function save(Request $request)
    {
        $data = $this->validated($request);
        $input = $request->validate(['id'=>['nullable','integer'],'version'=>['required','integer','min:0']]);
        $id = DB::transaction(function () use ($data,$input,$request) {
            $previous = null;
            if (!empty($input['id'])) {
                $previous = DB::table('teaching_schedules')->where('id',$input['id'])->lockForUpdate()->firstOrFail();
                abort_unless(in_array((int)$previous->campus_id,$this->campuses($request,true),true),403);
                abort_if($previous->status!=='draft' || $previous->version!==$input['version'],409,'This schedule changed or is published. Reload before editing.');
            }
            if (!empty($data['replaces_id'])) {
                $replaced = DB::table('teaching_schedules')->where('id',$data['replaces_id'])->firstOrFail();
                abort_unless(in_array((int)$replaced->campus_id,$this->campuses($request,true),true),403);
                abort_unless($replaced->status==='published' && (int)$replaced->campus_id===(int)$data['campus_id'] && (int)$replaced->academic_year_id===(int)$data['academic_year_id'],422,'A revision must stay in the original campus and academic year.');
            }
            $values = $data+['version'=>$input['version']+1,'updated_at'=>now()];
            if ($previous) {
                DB::table('teaching_schedules')->where('id',$previous->id)->update($values);
                return $previous->id;
            }
            return DB::table('teaching_schedules')->insertGetId($values+['created_by'=>$request->user()->id,'created_at'=>now()]);
        });
        return response()->json(['id'=>$id,'message'=>'Draft saved. Publish it to make attendance sessions available.']);
    }

    public function publish(Request $request, int $schedule)
    {
        $input = $request->validate(['version'=>['required','integer','min:0']]);
        DB::transaction(function () use ($request,$schedule,$input) {
            // One publication at a time prevents cross-campus teacher collisions.
            DB::table('access_permissions')->where('code','schedules.manage')->lockForUpdate()->first();
            $row = DB::table('teaching_schedules')->where('id',$schedule)->lockForUpdate()->firstOrFail();
            abort_unless(in_array((int)$row->campus_id,$this->campuses($request,true),true),403);
            abort_if($row->status!=='draft' || $row->version!==$input['version'],409,'This draft changed or has already been published. Reload it.');
            $period=DB::table('schedule_periods')->where('id',$row->period_id)->lockForUpdate()->first();
            abort_unless($period&&$period->active&&!$period->deleted_at,422,'Select an active shared session period before publishing.');
            abort_if(substr($period->starts_at,0,5)!==substr($row->starts_at,0,5)||substr($period->ends_at,0,5)!==substr($row->ends_at,0,5),422,'The shared period changed. Edit and save this draft before publishing.');
            $teacher = User::findOrFail($row->teacher_id);
            abort_unless($teacher->status && $teacher->canAccessCampus((int)$row->campus_id),422,'Teacher must still have access to this campus.');
            if ($row->replaces_id) {
                $original = DB::table('teaching_schedules')->where('id',$row->replaces_id)->lockForUpdate()->firstOrFail();
                abort_unless(in_array((int)$original->campus_id,$this->campuses($request,true),true),403);
                abort_if($original->status!=='published' || $row->effective_from<=$original->effective_from || ($original->effective_until && $row->effective_from>$original->effective_until),422,'The revision must start within the original schedule and after its first day.');
                $assignment = DB::table('attendance_assignments')->where('schedule_id',$original->id)->lockForUpdate()->firstOrFail();
                abort_if(DB::table('attendance_sessions')->where('assignment_id',$assignment->id)->where('attendance_date','>=',$row->effective_from)->exists(),422,'Attendance already exists on or after the revision date. Choose a later start date.');
                $end = CarbonImmutable::parse($row->effective_from)->subDay()->toDateString();
                DB::table('teaching_schedules')->where('id',$original->id)->update(['effective_until'=>$end,'version'=>$original->version+1,'updated_at'=>now()]);
                DB::table('attendance_assignments')->where('id',$assignment->id)->update(['effective_until'=>$end,'updated_at'=>now()]);
            }
            $conflicts = DB::table('attendance_assignments')->where('active',true)->where('weekday',$row->weekday)
                ->where('starts_at','<',$row->ends_at)->where('ends_at','>',$row->starts_at)
                ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until','>=',$row->effective_from))
                ->when($row->effective_until,fn ($q,$end) => $q->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from','<=',$end)))
                ->where(function ($q) use ($row) {
                    $q->where('teacher_id',$row->teacher_id)->orWhere(function ($q) use ($row) {
                        $q->where('campus_id',$row->campus_id)->where('academic_year_id',$row->academic_year_id)->where('class_id',$row->class_id)
                            ->where(fn ($q) => $q->whereNull('grade_id')->orWhere('grade_id',$row->grade_id));
                    });
                })->exists();
            if ($conflicts) throw ValidationException::withMessages(['schedule'=>'This teacher or class already has an overlapping published session.']);
            $columns=['campus_id','academic_year_id','class_id','grade_id','teacher_id','subject','weekday','starts_at','ends_at','effective_from','effective_until'];
            $assignment = array_intersect_key((array)$row,array_flip($columns));
            DB::table('attendance_assignments')->insert($assignment+['schedule_id'=>$row->id,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('teaching_schedules')->where('id',$row->id)->update(['status'=>'published','published_by'=>$request->user()->id,'published_at'=>now(),'version'=>$row->version+1,'updated_at'=>now()]);
        });
        return response()->json(['message'=>'Schedule published. Subject attendance sessions are now available automatically.']);
    }
}
