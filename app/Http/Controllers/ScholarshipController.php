<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\ScholarshipStudentRank;
use App\Models\ScholarshipPrintSetting;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\{ScholarshipExam, ScholarshipCandidate, ScholarshipCommittee, User};
use App\Services\ScholarshipWorkflow;
use App\Services\ScholarshipExamCenters;
use App\Support\ScholarshipPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScholarshipController
{
    public function __construct(private readonly ScholarshipWorkflow $workflow) {}
    public const TABS = [
        'assign-rank' => ['Assign Student Rank', 'ti-list-numbers'],
        'settings' => ['Scholarship Settings', 'ti-settings'],
        'seat-plan' => ['Seat Plan', 'ti-layout-grid'],
        'scoring' => ['Scoring', 'ti-pencil-check'],
        'result' => ['Result', 'ti-chart-bar'],
        'exam-committee' => ['Exam Committee', 'ti-users-group'],
    ];

    private function campuses(Request $request, string $action='view')
    {
        abort_unless(ScholarshipPermissions::allows($request->user(),$action), 403);
        return ScholarshipPermissions::campuses($request->user())->orderBy('campus_name_en')->get()
            ->filter(fn ($campus) => ScholarshipPermissions::allows($request->user(), $action, $campus->id))->values();
    }

    private function filters(Request $request, $campuses, bool $required = false): array
    {
        $filters=$request->validate([
            'academic_year_id' => [$required ? 'required' : 'nullable', 'integer', Rule::exists('tb_academic_year', 'id')->whereNull('deleted_at')->where('period_type','regular')],
            'campus_id' => [$required ? 'required' : 'nullable', 'integer', Rule::in($campuses->pluck('id')->all())],
            'grade_id' => ['nullable', 'integer', Rule::exists('tb_grade', 'id')->whereNull('deleted_at')],
            'class_id' => ['nullable', 'integer', Rule::exists('tb_class', 'id')->whereNull('deleted_at')],
            'grade_class' => ['nullable','string','max:48','regex:/^[1-9]\d*:[1-9]\d*$/'],
            'enrollment_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable',Rule::in(['all',10,25,50,75,100])],
        ]);
        $filters['per_page']=($filters['per_page']??10)==='all'?'all':(int)($filters['per_page']??10);
        if (!empty($filters['grade_class'])) [$filters['grade_id'],$filters['class_id']]=array_map('intval',explode(':',$filters['grade_class']));
        elseif (!empty($filters['grade_id']) && !empty($filters['class_id'])) $filters['grade_class']=$filters['grade_id'].':'.$filters['class_id'];
        return $filters;
    }

    private function enrollments(array $filters)
    {
        return StudentEnrollment::query()->where('academic_year_id', $filters['academic_year_id'])
            ->whereIn('grade_id',Grade::all()->filter(fn($grade)=>$this->workflow->gradeNumber($grade))->pluck('id'))
            ->where('campus_id', $filters['campus_id'])
            ->when($filters['grade_id'] ?? null, fn ($query, $id) => $query->where('grade_id', $id))
            ->when($filters['class_id'] ?? null, fn ($query, $id) => $query->where('class_id', $id))
            ->whereHas('student')->whereHas('academicYear')->whereHas('campus')->whereHas('grade')->whereHas('schoolClass');
    }

    private function rankEnrollments(array $filters, $campuses)
    {
        return StudentEnrollment::query()->where('academic_year_id',$filters['academic_year_id']??null)
            ->whereIn('campus_id',$campuses->pluck('id'))
            ->whereIn('grade_id',Grade::all()->filter(fn($grade)=>$this->workflow->gradeNumber($grade))->pluck('id'))
            ->when($filters['campus_id']??null,fn($q,$id)=>$q->where('campus_id',$id))
            ->when($filters['grade_id']??null,fn($q,$id)=>$q->where('grade_id',$id))
            ->when($filters['class_id']??null,fn($q,$id)=>$q->where('class_id',$id))
            ->whereHas('student')->whereHas('academicYear')->whereHas('campus')->whereHas('grade')->whereHas('schoolClass');
    }

    private function rankFilterOptions(array $filters, $allowedCampuses): array
    {
        $yearOnly=['academic_year_id'=>$filters['academic_year_id']??null];
        $campuses=$allowedCampuses->whereIn('id',$this->rankEnrollments($yearOnly,$allowedCampuses)->distinct()->pluck('campus_id'))->values();
        if (!$campuses->contains('id',$filters['campus_id']??null)) unset($filters['campus_id'],$filters['grade_class'],$filters['grade_id'],$filters['class_id'],$filters['enrollment_id']);
        $grades=empty($filters['campus_id'])?collect():$this->rankEnrollments($yearOnly+['campus_id'=>$filters['campus_id']],$allowedCampuses)
            ->select('grade_id','class_id')->distinct()->with(['grade','schoolClass'])->get()
            ->sort(fn($a,$b)=>((int)$a->grade->grade_order<=>(int)$b->grade->grade_order)?:((int)$a->schoolClass->class_order<=>(int)$b->schoolClass->class_order)?:strnatcasecmp($a->schoolClass->class_name,$b->schoolClass->class_name))
            ->map(function($row){
                $number=$this->workflow->gradeNumber($row->grade);$name=trim($row->schoolClass->class_name);
                $label=preg_match('/^'.$number.'\s*[A-Za-z]/u',$name)?$name:$number.$name;
                return ['id'=>$row->grade_id.':'.$row->class_id,'label'=>$label];
            })->values();
        if (!$grades->contains('id',$filters['grade_class']??null)) unset($filters['grade_class'],$filters['grade_id'],$filters['class_id'],$filters['enrollment_id']);
        return compact('filters','campuses','grades');
    }

    private function rankQuery(array $filters, $campuses)
    {
        $ids=$this->rankEnrollments($filters,$campuses)->select('id');
        return ScholarshipStudentRank::with(['enrollment.student','enrollment.grade','enrollment.schoolClass','enrollment.schoolGroup','enrollment.session','enrollment.academicYear','enrollment.campus','assignedBy'])
            ->join('tb_student_enrollment as rank_enrollment','rank_enrollment.id','=','tb_scholarship_student_rank.student_enrollment_id')
            ->join('tb_school_info as rank_campus','rank_campus.id','=','rank_enrollment.campus_id')
            ->join('tb_grade as rank_grade','rank_grade.id','=','rank_enrollment.grade_id')
            ->join('tb_class as rank_class','rank_class.id','=','rank_enrollment.class_id')
            ->whereIn('student_enrollment_id',$ids)->when($filters['enrollment_id']??null,fn($q,$id)=>$q->where('student_enrollment_id',$id))
            ->orderByRaw("LOWER(COALESCE(NULLIF(rank_campus.campus_name_en, ''), rank_campus.campus_name_kh, ''))")
            ->orderBy('rank_grade.grade_order')->orderByRaw('LOWER(rank_class.class_name)')
            ->orderBy('tb_scholarship_student_rank.rank')->orderBy('tb_scholarship_student_rank.id');
    }

    private function rankSummary(array $filters, $campuses): array
    {
        $counts=$this->rankQuery($filters,$campuses)->reorder()->selectRaw('tb_scholarship_student_rank.rank, COUNT(*) as aggregate')->groupBy('tb_scholarship_student_rank.rank')->pluck('aggregate','rank');
        return ['total'=>(int)$counts->sum(),1=>(int)$counts->get(1,0),2=>(int)$counts->get(2,0),3=>(int)$counts->get(3,0)];
    }

    private function canExportRanks(Request $request, $campuses, array $filters): bool
    {
        $scope=empty($filters['campus_id'])?$campuses:$campuses->where('id',$filters['campus_id']);
        return $scope->isNotEmpty() && $scope->every(fn($campus)=>ScholarshipPermissions::allows($request->user(),'export',$campus->id));
    }

    private function rankRecords(array $filters, $campuses, int $page=1)
    {
        $query=$this->rankQuery($filters,$campuses);
        $all=($filters['per_page']??10)==='all';
        $perPage=$all?max(1,(clone $query)->count()):(int)($filters['per_page']??10);
        return $query->paginate($perPage,['tb_scholarship_student_rank.*'],'page',$all?1:max(1,$page))
            ->withPath(route('western-scholarship.index'))->appends(array_filter($filters)+['tab'=>'assign-rank']);
    }

    private function ranksLocked(?int $year): bool
    {
        return $year && ScholarshipExam::where('academic_year_id',$year)->where(fn($q)=>$q->whereNotNull('seats_locked_at')->orWhereNotNull('published_at'))->exists();
    }

    public function rankFilters(Request $request)
    {
        $allowedCampuses=$this->campuses($request,'tab-assign-rank');
        $options=$this->rankFilterOptions($this->filters($request,$allowedCampuses),$allowedCampuses);
        $filters=$options['filters'];$ranksLocked=$this->ranksLocked($filters['academic_year_id']??null);
        $records=$this->rankRecords($filters,$allowedCampuses,$request->integer('page',1));$rankUser=$request->user();
        $rankSummary=$this->rankSummary($filters,$allowedCampuses);$canExportRanks=$this->canExportRanks($request,$allowedCampuses,$filters);
        $canManageRankPrintSettings=$allowedCampuses->contains(fn($campus)=>ScholarshipPermissions::allows($rankUser,'assign-rank',$campus->id));
        return response()->json(['filters'=>$filters,'ranks_locked'=>$ranksLocked,
            'campuses'=>$options['campuses']->map(fn($c)=>['id'=>$c->id,'label'=>$c->campus_name_en?:$c->campus_name_kh,'can_assign'=>ScholarshipPermissions::allows($rankUser,'assign-rank',$c->id)]),
            'grades'=>$options['grades'],
            'html'=>view('western-scholarship._ranks',compact('records','filters','ranksLocked','rankUser','rankSummary','canExportRanks','canManageRankPrintSettings'))->render()]);
    }

    public function index(Request $request)
    {
        $tabs=array_filter(self::TABS,fn($key)=>ScholarshipPermissions::allows($request->user(),ScholarshipPermissions::TABS[$key]),ARRAY_FILTER_USE_KEY);
        abort_unless($tabs,403);
        $tab=$request->string('tab')->toString();
        if(array_key_exists($tab,self::TABS))abort_unless(isset($tabs[$tab]),403);
        else $tab=array_key_first($tabs);
        $allowedCampuses = $this->campuses($request,$tab==='assign-rank'?'tab-assign-rank':'view');
        $filters = $this->filters($request, $allowedCampuses);
        $exams = ScholarshipExam::with('academicYear')->whereHas('academicYear',fn($q)=>$q->regular())->latest('id')->get()->filter(fn ($exam) => $this->workflow->canView($request->user(), $exam));
        $exam = $request->integer('exam_id') ? $exams->firstWhere('id',$request->integer('exam_id')) : null;
        if ($request->integer('exam_id')) abort_unless($exam,403);
        if ($exam) $filters['academic_year_id']=$exam->academic_year_id;
        $academicYears = AcademicYear::regular()->orderByDesc('academic_year')->get();
        $filters['academic_year_id'] ??= $academicYears->firstWhere('lifecycle_status', 'started')?->id ?? $academicYears->first()?->id;
        if (!$exam && !$request->boolean('new_exam')) $exam=$exams->firstWhere('academic_year_id',$filters['academic_year_id']);
        $selected = null;
        $enrollmentId = $request->old('enrollment_id', $request->integer('enrollment_id'));
        if ($enrollmentId && !empty($filters['academic_year_id'])) {
            $selected = $this->rankEnrollments($filters,$allowedCampuses)->with('student')->find($enrollmentId);
            if ($selected) { $filters['campus_id']=$selected->campus_id;$filters['grade_id']=$selected->grade_id;$filters['class_id']=$selected->class_id;$filters['grade_class']=$selected->grade_id.':'.$selected->class_id;$filters['enrollment_id']=$selected->id; }
        }
        $options=$this->rankFilterOptions($filters,$allowedCampuses);
        $filters=$options['filters'];$campuses=$options['campuses'];$grades=$options['grades'];
        $records=$this->rankRecords($filters,$allowedCampuses,$request->integer('page',1));
        $rankSummary=$this->rankSummary($filters,$allowedCampuses);$canExportRanks=$this->canExportRanks($request,$allowedCampuses,$filters);
        $rankUser=$request->user();$ranksLocked=$this->ranksLocked($filters['academic_year_id']??null);
        $canManageRankPrintSettings=$allowedCampuses->contains(fn($campus)=>ScholarshipPermissions::allows($rankUser,'assign-rank',$campus->id));
        $selectedRecord=$selected?ScholarshipStudentRank::where('student_enrollment_id',$selected->id)->first():null;
        $selectedRank=$selectedRecord?->rank;$selectedEoyAverage=$selectedRecord?->eoy_average;
        if ($exam) $filters['exam_id']=$exam->id;
        $workspace=$this->examWorkspace($request,$exam,$tab,(int)($filters['academic_year_id']??0));
        $viewData=compact('tab', 'campuses', 'academicYears', 'filters', 'records', 'selected', 'selectedRank','selectedEoyAverage','exam','exams') + $workspace + [
            'tabs' => $tabs, 'grades'=>$grades,'rankUser'=>$rankUser,'ranksLocked'=>$ranksLocked,'selectedRankId'=>$selectedRecord?->id,'rankSummary'=>$rankSummary,'canExportRanks'=>$canExportRanks,'canManageRankPrintSettings'=>$canManageRankPrintSettings,
            'canAssign' => !empty($filters['campus_id']) && ScholarshipPermissions::allows($request->user(), 'assign-rank', (int) $filters['campus_id']),
        ];
        if($exam && $tab==='seat-plan' && $request->boolean('seat_list') && $request->expectsJson()){
            return response()->json(['html'=>view('western-scholarship._seat-table',$viewData)->render()]);
        }
        return view('western-scholarship.index',$viewData);
    }

    private function rankSignatureCampus(Request $request, $campuses, ?int $selected=null): ?int
    {
        if($selected)return $selected;
        return $campuses->first(fn($campus)=>in_array(strtoupper(trim($campus->campus_name_en)),['CEN','CENTRAL','CENTRAL OFFICE'],true))?->id
            ??$campuses->firstWhere('id',$request->user()->active_campus_id)?->id??$campuses->first()?->id;
    }

    private function rankSignerStaff(int $campusId)
    {
        return Staff::with('position')->where('status',true)->where('employment_status','active')
            ->where(fn($q)=>$q->where('primary_campus_id',$campusId)->orWhereHas('campuses',fn($q)=>$q->where('tb_school_info.id',$campusId)->where('hrm_staff_campuses.status',true)))
            ->orderBy('name_en')->orderBy('id')->get(['id','staff_code','name_en','name_kh','position_id']);
    }

    public function rankPrintSettings(Request $request)
    {
        $campuses=$this->campuses($request,'assign-rank');
        $filters=$this->filters($request,$campuses);
        $request->validate(['academic_year_id'=>['required']]);
        $campusId=$this->rankSignatureCampus($request,$campuses,isset($filters['campus_id'])?(int)$filters['campus_id']:null);
        abort_unless($campusId,403);
        $settings=ScholarshipPrintSetting::where('campus_id',$campusId)->where('academic_year_id',$filters['academic_year_id'])->first();
        $values=[];
        foreach(['prepared','checked','approved'] as $role){
            $values[$role.'_staff_id']=$settings?->getAttribute($role.'_staff_id');
            $values[$role.'_by_name']=$settings?->getAttribute($role.'_by_name')??($role==='prepared'?$request->user()->name:'');
            $values[$role.'_position']=$settings?->getAttribute($role.'_position')??'';
            $values[$role.'_date']=$settings?->getAttribute($role.'_date')?->format('Y-m-d')??now()->toDateString();
        }
        return response()->json([
            'academic_year'=>['id'=>(int)$filters['academic_year_id'],'label'=>AcademicYear::findOrFail($filters['academic_year_id'])->academic_year],
            'campuses'=>$campuses->map(function($campus){
                $central=in_array(strtoupper(trim($campus->campus_name_en)),['CEN','CENTRAL','CENTRAL OFFICE'],true);
                return ['id'=>$campus->id,'label'=>$central?'Central — All Campuses ('.$campus->campus_name_en.')':($campus->campus_name_en?:$campus->campus_name_kh),'is_central'=>$central];
            }),'campus_id'=>(int)$campusId,
            'values'=>$values,'requires_verifier'=>ScholarshipPrintSetting::requiresVerifier((int)$campusId),
            'staff'=>$this->rankSignerStaff((int)$campusId)->map(fn($staff)=>['id'=>$staff->id,'code'=>$staff->staff_code,'name'=>$staff->name_en?:$staff->name_kh,'name_kh'=>$staff->name_kh,'position'=>$staff->position?->name??'']),
        ]);
    }

    public function saveRankPrintSettings(Request $request)
    {
        $campuses=$this->campuses($request,'assign-rank');
        $filters=$this->filters($request,$campuses,true);
        $staff=$this->rankSignerStaff((int)$filters['campus_id'])->keyBy('id');
        $requiresVerifier=ScholarshipPrintSetting::requiresVerifier((int)$filters['campus_id']);
        $roles=$requiresVerifier?['prepared'=>'Prepared by','checked'=>'Checked and Verified by','approved'=>'Approved by']:['prepared'=>'Prepared by','approved'=>'Approved by'];
        $rules=[];$labels=[];
        foreach($roles as $role=>$label){
            $rules[$role.'_staff_id']=['required','integer',Rule::in($staff->keys()->all())];
            $rules[$role.'_date']=['required','date_format:Y-m-d'];
            $labels[$role.'_staff_id']=$label.' (HRM Staff)';$labels[$role.'_date']=$label.' Date';
        }
        $data=$request->validate($rules,[],$labels);
        foreach(array_keys($roles) as $role){
            $signer=$staff->get($data[$role.'_staff_id']);
            $data[$role.'_by_name']=$signer->name_en?:$signer->name_kh;
            $data[$role.'_position']=$signer->position?->name;
        }
        if(!$requiresVerifier)$data+=['checked_staff_id'=>null,'checked_by_name'=>null,'checked_position'=>null,'checked_date'=>null];
        ScholarshipPrintSetting::updateOrCreate(['campus_id'=>$filters['campus_id'],'academic_year_id'=>$filters['academic_year_id']],$data+['updated_by'=>$request->user()->id]);
        $scope=$campuses->firstWhere('id',$filters['campus_id']);
        $central=in_array(strtoupper(trim($scope->campus_name_en)),['CEN','CENTRAL','CENTRAL OFFICE'],true);
        return response()->json(['message'=>($central?'Central':'Campus').' print settings saved.']);
    }

    public function rankReport(Request $request, string $format)
    {
        abort_unless(in_array($format,['print','xlsx','pdf'],true),404);
        $campuses=$this->campuses($request,'tab-assign-rank');
        $filters=$this->filters($request,$campuses);
        abort_unless($this->canExportRanks($request,$campuses,$filters),403);
        $records=$this->rankQuery($filters,$campuses)->get(['tb_scholarship_student_rank.*']);
        $rankSummary=$this->rankSummary($filters,$campuses);
        $year=AcademicYear::find($filters['academic_year_id']??null)?->academic_year;
        $headers=['No.','Student ID','Khmer Name','English Name','Academic Year','Campus-Grade-Group','Class Rank','EOY Avg.','Assigned By','Assigned Date'];
        $reportRows=$records->map(function($record,$index){
            $e=$record->enrollment;$s=$e->student;
            $grade=preg_replace('/^(?:grade\s*|g)(?=\d)/i','',trim((string)($e->grade?->grade_short_name?:$e->grade?->grade)));
            $class=trim((string)$e->schoolClass?->class_name);
            $gradeClass=$grade!=='' && str_starts_with(strtolower($class),strtolower($grade))?$class:$grade.$class;
            $placement=collect([$e->campus?->campus_name_en,$gradeClass,$e->session?->session_short_name?:$e->schoolGroup?->group_name])->filter(fn($part)=>filled($part))->implode('-');
            $gender=match(strtolower(trim((string)$s->gender))){'m','male'=>'M','f','female'=>'F',default=>'—'};
            return ['number'=>$index+1,'id'=>$s->student_id?:$s->student_no,'name_kh'=>$s->full_name_kh,'name_en'=>$s->full_name_en,'year'=>$e->academicYear?->academic_year,'placement'=>$placement,'rank'=>$record->rank,
                'average'=>$record->eoy_average===null?'—':number_format($record->eoy_average,2),'assigned_by'=>$record->assignedBy?->name?:'—','assigned_date'=>$record->created_at?->format('d-m-Y H:i'),
                'gender'=>$gender,'grade'=>$gradeClass,'group'=>$e->session?->session_short_name?:$e->schoolGroup?->group_name,'campus'=>$e->campus?->campus_name_en?:$e->campus?->campus_name_kh,'campus_id'=>$e->campus_id];
        })->all();
        $rows=array_map(fn($row)=>array_values(\Illuminate\Support\Arr::only($row,['number','id','name_kh','name_en','year','placement','rank','average','assigned_by','assigned_date'])),$reportRows);
        $export=app(\App\Support\ScholarshipExport::class);
        $reportCampus=isset($filters['campus_id'])?$campuses->firstWhere('id',(int)$filters['campus_id']):($campuses->count()===1?$campuses->first():null);
        $campusLabel=$reportCampus&&!ScholarshipPrintSetting::requiresVerifier((int)$reportCampus->id)?($reportCampus->campus_name_en?:$reportCampus->campus_name_kh).' Campus':'All Campuses';
        $data=compact('headers','rows','rankSummary','year','campusLabel')+['pdfExport'=>$format==='pdf','pages'=>$export->rankPages($reportRows),'logoSrc'=>$export->rankLogo(),'preparedBy'=>$request->user()->name,'reportDate'=>now()->format('F j, Y')];
        $data['reportSections']=$export->rankSections($reportRows,(int)($filters['academic_year_id']??0),$request->user()->name,
            $this->rankSignatureCampus($request,$campuses,isset($filters['campus_id'])?(int)$filters['campus_id']:null));
        if($format==='print')return view('western-scholarship.ranks-print',$data);
        $bytes=$format==='xlsx'?$export->ranksXlsx($data):$export->ranksPdf($data);
        return response($bytes)->header('Content-Type',$format==='xlsx'?'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':'application/pdf')
            ->header('Content-Disposition','attachment; filename="wis-scholarship-assigned-ranks.'.$format.'"')->header('Cache-Control','private, no-store');
    }

    public function students(Request $request)
    {
        $campuses = $this->campuses($request,'tab-assign-rank');
        $filters = $this->filters($request, $campuses, true);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = trim($data['q'] ?? '');
        $assignedStudents=StudentEnrollment::where('academic_year_id',$filters['academic_year_id'])
            ->whereIn('id',ScholarshipStudentRank::select('student_enrollment_id'))->select('student_id');
        $rows = $this->enrollments($filters)->whereNotIn('student_id',$assignedStudents)->with(['student', 'grade','schoolClass'])->when($search !== '', fn ($query) => $query->whereHas('student', fn ($student) =>
            $student->where(function ($names) use ($search) {
                foreach (['full_name_en', 'full_name_kh', 'student_id', 'student_no'] as $index => $column) {
                    $index ? $names->orWhere($column, 'like', '%'.$search.'%') : $names->where($column, 'like', '%'.$search.'%');
                }
            })))
            ->orderBy('student_id')->orderBy('id')->limit(31)->get();
        return response()->json(['has_more' => $rows->count() > 30, 'data' => $rows->take(30)->map(fn ($row) => [
            'id' => $row->id,
            'label' => implode(' · ', array_filter([$row->student->student_id ?: $row->student->student_no, $row->student->full_name_en])),
            'rank' => null,'eoy_average'=>null,
        ])->values()]);
    }

    public function saveRank(Request $request)
    {
        $campuses = $this->campuses($request,'tab-assign-rank');
        $filters = $this->filters($request, $campuses, true);
        abort_unless(ScholarshipPermissions::allows($request->user(), 'assign-rank', (int) $filters['campus_id']), 403);
        $data = $request->validate(['enrollment_id' => ['required', 'integer'], 'rank' => ['required', 'integer', Rule::in([1,2,3])],
            'eoy_average'=>['required','numeric','between:0,100','decimal:0,2'],'rank_id'=>['nullable','integer','min:1']],[],
            ['enrollment_id'=>'Student','rank'=>'Class Rank','eoy_average'=>'EOY Avg.']);
        $enrollment = $this->enrollments($filters)->find($data['enrollment_id']);
        if (!$enrollment) throw ValidationException::withMessages(['enrollment_id' => 'Select a student matching the academic year, campus, and grade.']);
        $existing=ScholarshipStudentRank::where('student_enrollment_id',$enrollment->id)->first();
        if(!empty($data['rank_id']) && (!$existing || $existing->id!==(int)$data['rank_id']))
            throw ValidationException::withMessages(['enrollment_id'=>'Select the assigned student using Edit Rank in the list.']);
        $this->workflow->saveRank($request->user(),$enrollment,(int)$data['rank'],(float)$data['eoy_average'],!empty($data['rank_id']));
        if($request->expectsJson())return response()->json(['message'=>'Class rank saved successfully.']);
        unset($filters['enrollment_id']);
        return redirect()->route('western-scholarship.index', $filters)
            ->with('scholarship_rank_saved', $enrollment->id)
            ->with('success', 'Class rank saved successfully.');
    }

    private function examWorkspace(Request $request, ?ScholarshipExam $exam, string $tab, int $year=0): array
    {
        $user=$request->user();
        $data=['workflow'=>$this->workflow,'examPermissions'=>[], 'committeeRoles'=>ScholarshipWorkflow::ROLES];
        $participating=app(ScholarshipExamCenters::class)->campuses($exam?->academic_year_id??(int)old('academic_year_id',$year));
        $data['examCenterCampuses']=$participating->every(fn($campus)=>ScholarshipPermissions::allows($user,'settings',$campus['id']))?$participating:collect();
        if (!$exam) return $data;
        $exam->load(['subjects','committee.user','committee.examCenter','centers.campuses']);
        foreach (array_keys(ScholarshipPermissions::catalog()) as $action) $data['examPermissions'][$action]=$this->workflow->central($user,$exam,$action);
        $candidates=$this->workflow->candidates($user,$exam)->with('scores')
            ->orderBy('grade_number')->orderBy('grade_exam_number')->orderBy('id')->get();
        $data['candidateCount']=$exam->candidates()->count();
        $data['candidates']=$candidates;
        $data['seatPlan']=$data['examPermissions']['seat-plan']?$this->workflow->plan($exam):null;
        if($tab==='seat-plan'){
            $paging=$request->validate(['seat_per_page'=>['nullable',Rule::in(['all',15,25,50,75,100])],'seat_page'=>['nullable','integer','min:1','max:1000000']]);
            $size=($paging['seat_per_page']??15)==='all'?'all':(int)($paging['seat_per_page']??15);
            $rows=!$exam->seats_locked_at && $data['examPermissions']['seat-plan']?collect($data['seatPlan']['rows']):$this->workflow->orderedSeats($exam,$candidates)->map(fn($c)=>$c->only(['id','exam_center_name','grade_number','room_number','room_seat','candidate_code','snapshot']));
            $total=$rows->count();$perPage=$size==='all'?max(1,$total):$size;
            $page=$size==='all'?1:min(max(1,(int)($paging['seat_page']??1)),max(1,(int)ceil($total/$perPage)));
            $pageRows=$rows->slice(($page-1)*$perPage,$perPage)->values();
            $candidates->whereIn('id',$pageRows->pluck('id'))->load(['student:id,photo_path','enrollment.grade','enrollment.schoolClass','enrollment.schoolGroup','enrollment.session']);
            $data['seatPageSize']=$size;
            $data['seatRows']=new \Illuminate\Pagination\LengthAwarePaginator($pageRows,$total,$perPage,$page,['path'=>url()->current(),'pageName'=>'seat_page','query'=>array_replace($request->query(),['tab'=>'seat-plan','exam_id'=>$exam->id,'seat_per_page'=>$size])]);
        }
        $allResults=$this->workflow->results($exam);
        $data['results']=($data['examPermissions']['publish-results'] || $data['examPermissions']['awards'])?$allResults:$allResults->filter(fn($row)=>$candidates->contains('id',$row['candidate']->id));
        $data['awardGrades']=$data['examPermissions']['awards']?$allResults->groupBy(fn($row)=>$row['candidate']->grade_number):collect();
        $data['savedAwards']=$exam->awards()->get()->groupBy('grade_number');
        $scoring=$candidates->filter(fn($candidate)=>$this->workflow->canScore($user,$exam,$candidate) || $this->workflow->canScore($user,$exam,$candidate,true))
            ->filter(fn($candidate)=>(!$request->integer('exam_grade') || $candidate->grade_number===$request->integer('exam_grade')) && (!$request->integer('room') || $candidate->room_number===$request->integer('room'))
                && (!$request->integer('exam_center') || $candidate->exam_center_id===$request->integer('exam_center'))
                && (!$request->filled('search') || mb_stripos(implode(' ',$candidate->snapshot).' '.$candidate->candidate_code,mb_substr($request->string('search')->toString(),0,100))!==false))->values();
        $page=max(1,$request->integer('score_page',1));
        $data['scoringCandidates']=new \Illuminate\Pagination\LengthAwarePaginator($scoring->slice(($page-1)*15,15),$scoring->count(),15,$page,['path'=>url()->current(),'pageName'=>'score_page','query'=>$request->query()]);
        $data['staffOptions']=$data['examPermissions']['committee']?User::where('status',1)->orderBy('name')->get(['id','name']):collect();
        $data['events']=$data['examPermissions']['settings']?$exam->events()->with('user')->limit(20)->get():collect();
        return $data;
    }

    private function back(ScholarshipExam $exam, string $tab, string $message)
    {
        $context=$tab==='scoring'?collect(request()->query())->only(['exam_grade','room','search','score_page','exam_center'])->filter(fn($value)=>is_scalar($value))->map(fn($value)=>mb_substr((string)$value,0,100))->all():[];
        return redirect()->route('western-scholarship.index',['exam_id'=>$exam->id,'tab'=>$tab]+$context)->with('success',$message);
    }

    public function saveExam(Request $request)
    {
        $this->campuses($request,'settings');
        $data=$request->validate([
            'exam_id'=>['nullable','integer','exists:tb_scholarship_exam,id'], 'version'=>['required','integer','min:1'],
            'academic_year_id'=>['required','integer',Rule::exists('tb_academic_year','id')->whereNull('deleted_at')->where('period_type','regular')],
            'exam_date'=>['required','date_format:Y-m-d'],'room_capacity'=>['required','integer','min:1','max:200'],
            'subjects'=>['required','array','min:1','max:100'],'subjects.*.name'=>['required','string','max:100'],
            'subjects.*.grade_from'=>['required','integer','between:1,12'],'subjects.*.grade_to'=>['required','integer','between:1,12','gte:subjects.*.grade_from'],
            'subjects.*.max_score'=>['required','numeric','gt:0','max:10000','decimal:0,2'],'subjects.*.weight'=>['required','numeric','gt:0','max:10000','decimal:0,2'],
            'award_scheme'=>['sometimes','array:total_1,total_2,total_3,subject'],
            'award_scheme.total_1'=>['required_with:award_scheme','numeric','between:0,100','decimal:0,2'],
            'award_scheme.total_2'=>['required_with:award_scheme','numeric','between:0,100','decimal:0,2'],
            'award_scheme.total_3'=>['required_with:award_scheme','numeric','between:0,100','decimal:0,2'],
            'award_scheme.subject'=>['required_with:award_scheme','numeric','between:0,100','decimal:0,2'],
        ]+ScholarshipExamCenters::rules());
        $exam=$this->workflow->saveExam($request->user(),empty($data['exam_id'])?null:ScholarshipExam::findOrFail($data['exam_id']),$data);
        return $this->back($exam,'settings','Exam settings saved.');
    }

    public function examCenterCampuses(Request $request)
    {
        $this->campuses($request,'settings');
        $data=$request->validate(['academic_year_id'=>['required','integer',Rule::exists('tb_academic_year','id')->whereNull('deleted_at')->where('period_type','regular')]]);
        $campuses=app(ScholarshipExamCenters::class)->campuses((int)$data['academic_year_id']);
        abort_unless($campuses->every(fn($campus)=>ScholarshipPermissions::allows($request->user(),'settings',$campus['id'])),403);
        return response()->json(['campuses'=>$campuses->values()]);
    }

    public function saveAwards(Request $request, ScholarshipExam $exam, int $grade)
    {
        abort_unless($grade>=1 && $grade<=12,404);
        $data=$request->validate(['version'=>['required','integer','min:1'],'awards'=>['required','array','max:103'],
            'awards.*'=>['array:candidate_id,award_percent,reason'],'awards.*.candidate_id'=>['nullable','integer','min:1'],
            'awards.*.award_percent'=>['required','numeric','between:0,100','decimal:0,2'],'awards.*.reason'=>['nullable','string','max:1000']]);
        $this->workflow->saveAwards($request->user(),$exam,$grade,$data['awards'],(int)$data['version']);
        return $this->back($exam,'result','Scholarship winners saved for Grade '.$grade.'.');
    }

    public function removeRank(Request $request, ScholarshipStudentRank $rank)
    {
        $enrollment=$rank->enrollment;abort_unless($enrollment,404);
        $this->workflow->removeRank($request->user(),$rank);
        return redirect()->route('western-scholarship.index',['academic_year_id'=>$enrollment->academic_year_id,'campus_id'=>$enrollment->campus_id,'tab'=>'assign-rank'])->with('success','Class rank and scholarship candidate removed.');
    }

    public function lockPlan(Request $request, ScholarshipExam $exam)
    {
        $data=$request->validate(['plan_token'=>['required','string','size:64'],'confirm'=>['accepted']]);
        $this->workflow->lockPlan($request->user(),$exam,$data['plan_token']); return $this->back($exam,'seat-plan','Seat plan confirmed and locked.');
    }

    public function reopenPlan(Request $request, ScholarshipExam $exam)
    {
        $data=$request->validate(['version'=>['required','integer'],'reason'=>['required','string','min:5','max:1000'],'confirm'=>['accepted']]);
        $this->workflow->reopenPlan($request->user(),$exam,(int)$data['version'],$data['reason']); return $this->back($exam,'seat-plan','Seat plan reopened. Review and confirm a new preview before scoring.');
    }

    public function saveScores(Request $request, ScholarshipExam $exam, ScholarshipCandidate $candidate)
    {
        $data=$request->validate(['version'=>['required','integer'],'scores'=>['nullable','array','max:100'],'absent'=>['nullable','boolean']]);
        $this->workflow->saveScores($request->user(),$exam,$candidate,$data['scores']??[],$request->boolean('absent'),(int)$data['version']);
        return $this->back($exam,'scoring','Score draft saved. A reviewer must review these scores before publication.');
    }

    public function review(Request $request, ScholarshipExam $exam, ScholarshipCandidate $candidate)
    {
        $data=$request->validate(['version'=>['required','integer'],'confirm'=>['accepted']]);
        $this->workflow->review($request->user(),$exam,$candidate,(int)$data['version']); return $this->back($exam,'scoring','Candidate scores reviewed.');
    }

    public function publish(Request $request, ScholarshipExam $exam)
    {
        $data=$request->validate(['version'=>['required','integer'],'confirm'=>['accepted']]);
        $this->workflow->publish($request->user(),$exam,(int)$data['version']); return $this->back($exam,'result','Results published and locked.');
    }

    public function reopenResults(Request $request, ScholarshipExam $exam)
    {
        $data=$request->validate(['version'=>['required','integer'],'reason'=>['required','string','min:5','max:1000'],'confirm'=>['accepted']]);
        $this->workflow->reopenResults($request->user(),$exam,(int)$data['version'],$data['reason']); return $this->back($exam,'result','Results reopened for correction. Changes must be reviewed and published again.');
    }

    public function addCommittee(Request $request, ScholarshipExam $exam)
    {
        $data=$request->validate(['user_id'=>['required','integer',Rule::exists('users','id')->where('status',1)],'role'=>['required',Rule::in(array_keys(ScholarshipWorkflow::ROLES))],
            'grade_number'=>['nullable','integer','between:1,12'],'room_number'=>['nullable','integer','min:1','max:100000'],
            'exam_center_id'=>['nullable','integer','min:1']]);
        $data+=['grade_number'=>null,'room_number'=>null,'exam_center_id'=>null];
        $this->workflow->addCommittee($request->user(),$exam,$data); return $this->back($exam,'exam-committee','Committee assignment saved.');
    }

    public function removeCommittee(Request $request, ScholarshipExam $exam, ScholarshipCommittee $member)
    {
        $this->workflow->removeCommittee($request->user(),$exam,$member); return $this->back($exam,'exam-committee','Committee assignment removed.');
    }

    public function report(Request $request, ScholarshipExam $exam, string $kind, string $format)
    {
        abort_unless(ScholarshipPermissions::allows($request->user(),ScholarshipPermissions::TABS[$kind==='seats'?'seat-plan':'result']),403);
        abort_unless($this->workflow->canView($request->user(),$exam) && $this->workflow->central($request->user(),$exam,'export'),403);
        $exam->load('subjects','academicYear');
        $candidates=$this->workflow->candidates($request->user(),$exam)->with('scores')->orderBy('grade_number')->orderBy('grade_exam_number')->get();
        $rows=[]; $headers=[];
        if($kind==='seats') {
            if(!$exam->seats_locked_at) throw ValidationException::withMessages(['exam'=>'Confirm the seat plan before printing or exporting assignments.']);
            $candidates=$this->workflow->orderedSeats($exam,$candidates);
            $headers=['Exam Center','Grade','Room','Seat','Exam Code','Student ID','Khmer Name','English Name','Campus','Class','Class Rank'];
            foreach($candidates as $candidate) $rows[]=[$candidate->exam_center_name??'—',$candidate->grade_number,$candidate->room_number,$candidate->room_seat,$candidate->candidate_code,
                $candidate->snapshot['student_code'],$candidate->snapshot['name_kh'],$candidate->snapshot['name_en'],$candidate->snapshot['campus'],$candidate->snapshot['class'],$candidate->class_rank];
        } else {
            $headers=['Grade','Exam Rank','Exam Code','Student ID','Khmer Name','English Name','Campus','Status','Publication',...$exam->subjects->map(fn($s)=>$s->name.' (G'.$s->grade_from.'–'.$s->grade_to.') / '.$s->max_score)->all(),'Total','Percentage','Scholarship %','Award'];
            $visible=$candidates->pluck('id');
            foreach($this->workflow->results($exam) as $row) {
                $candidate=$row['candidate']; if(!$visible->contains($candidate->id)) continue;
                $scores=$candidate->scores->keyBy('subject_id');
                $rows[]=[$candidate->grade_number,$row['rank'],$candidate->candidate_code,$candidate->snapshot['student_code'],$candidate->snapshot['name_kh'],$candidate->snapshot['name_en'],$candidate->snapshot['campus'],$row['status'],$exam->published_at?'Published':'DRAFT - Not Published',
                    ...$exam->subjects->map(fn($s)=>$candidate->grade_number<$s->grade_from||$candidate->grade_number>$s->grade_to?'Not applicable':($scores->get($s->id)?->score??'Not Entered'))->all(),$row['total'],$row['percentage'],$row['award'],$row['award_label']];
            }
        }
        if($format==='print') return view('western-scholarship.print',compact('exam','headers','rows','kind'));
        $filename='wis-scholarship-'.$exam->id.'-'.$kind.($exam->published_at?'':'-draft').'.'.$format;
        if($format==='xlsx') return response(app(\App\Support\ScholarshipExport::class)->xlsx([$headers,...$rows]))->header('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->header('Content-Disposition','attachment; filename="'.$filename.'"');
        return response()->streamDownload(function() use($headers,$rows) {
            $stream=fopen('php://output','w'); fwrite($stream,"\xEF\xBB\xBF");
            foreach([$headers,...$rows] as $row) fputcsv($stream,array_map(fn($value)=>is_string($value)&&preg_match('/^[\s\x00-\x1f]*[=+@-]/u',$value)?"'".$value:$value,$row),',','"','');
            fclose($stream);
        },$filename,['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
