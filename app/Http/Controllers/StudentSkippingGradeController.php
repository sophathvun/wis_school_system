<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\BrandingSetting;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolInfo;
use App\Models\Session;
use App\Models\SkippingGradeSetting;
use App\Models\SkippingGradeCampusSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentSkippingGrade;
use App\Services\StudentSkippingGradeService;
use App\Services\SkippingGradeTemplateService;
use App\Support\StudentSkippingGradePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StudentSkippingGradeController
{
    public const CRITERIA = [
        'age'=>['kh'=>'ត្រូវមានអាយុស្របតាមថ្នាក់ ដោយមានសំបុត្រកំណើតដើមជាភស្តុតាង។','en'=>'The student meets the legal age requirement for the requested grade, supported by the original birth certificate (for example, 6 years old for Grade 1).'],
        'average'=>['kh'=>'មានពិន្ទុមធ្យមភាគ ៨៥% ឬខ្ពស់ជាងនេះ ក្នុងឆ្នាំសិក្សាមុន។','en'=>'The student maintained an overall average of 85% or higher in the preceding school year.'],
        'documents'=>['kh'=>'មានឯកសារបញ្ជាក់ការបញ្ចប់ថ្នាក់ដែលស្នើសុំរំលង ពីសាលាដើម។','en'=>'Documents from another school confirm successful completion of the grade to be skipped. For example, Grade 3 must be completed before entering Grade 4.'],
        'recommendation'=>['kh'=>'មានការណែនាំពីគ្រូប្រចាំថ្នាក់ ទាក់ទងនឹងការសិក្សា និងអាកប្បកិរិយា។','en'=>'A recommendation from the class teacher addresses both academic performance and behavior.'],
    ];

    public function __construct(private readonly StudentSkippingGradeService $service) {}

    private function shared(Request $request, ?int $campus = null): array
    {
        return [
            'permissions'=>collect(StudentSkippingGradePermissions::catalog())->mapWithKeys(fn($label,$action)=>[$action=>StudentSkippingGradePermissions::allows($request->user(),$action,$campus)])->all(),
            'campuses'=>SchoolInfo::where('status',1)->orderBy('campus_name_en')->get(),
            'academicYears'=>AcademicYear::regular()->orderByDesc('academic_year')->get(),
            'criteriaOptions'=>self::CRITERIA,'committeeRoles'=>SkippingGradeSetting::COMMITTEE,
        ];
    }

    public function index(Request $request)
    {
        $this->service->authorize($request->user(),'view');
        if (!StudentSkippingGradePermissions::allows($request->user(),'requests')) {
            foreach (['campus-settings'=>'student-skipping-grade.campus-settings','settings'=>'student-skipping-grade.settings','request-template'=>'request','approval-template'=>'approval'] as $action=>$destination) {
                if (StudentSkippingGradePermissions::allows($request->user(),$action)) return in_array($action,['campus-settings','settings'],true)
                    ?redirect()->route($destination):redirect()->route('student-skipping-grade.template',$destination);
            }
            abort(403);
        }
        $filters=$request->validate(['academic_year_id'=>['nullable','integer'],'campus_id'=>['nullable','integer'],
            'status'=>['nullable','in:draft,pending,approved,rejected'],'search'=>['nullable','string','max:120'],
            'per_page'=>['nullable','in:all,20,50,75,100']]);
        $shared=$this->shared($request);
        $yearQuery=StudentSkippingGrade::query()
            ->when($filters['academic_year_id']??null,fn($q,$id)=>$q->where('academic_year_id',$id));
        if (!empty($filters['academic_year_id'])) {
            $campusIds=(clone $yearQuery)->distinct()->pluck('campus_id');
            $shared['campuses']=$shared['campuses']->whereIn('id',$campusIds->all())->values();
        }
        $scope=(clone $yearQuery)->when($filters['campus_id']??null,fn($q,$id)=>$q->where('campus_id',$id));
        $availableStatuses=(clone $scope)->distinct()->pluck('status');
        $statusOptions=collect(['draft'=>'Draft','pending'=>'Submitted to Central Office','approved'=>'Approved','rejected'=>'Rejected'])
            ->only($availableStatuses->all())->all();
        $query=$scope->with(['creator','approver','student'])
            ->when($filters['status']??null,fn($q,$status)=>$q->where('status',$status))
            ->when($filters['search']??null,fn($q,$term)=>$q->where(fn($inner)=>$inner->where('parent_name','like','%'.$term.'%')
                ->orWhere('reference_number','like','%'.$term.'%')->orWhere('student_snapshot->name_en','like','%'.$term.'%')
                ->orWhere('student_snapshot->name_kh','like','%'.$term.'%')->orWhere('student_snapshot->student_id','like','%'.$term.'%')));
        $pageSize=$filters['per_page']??'20';
        $showAll=$pageSize==='all';
        $records=$query->orderByDesc('id')->paginate($showAll?max(1,(clone $query)->count()):(int)$pageSize,['*'],'page',$showAll?1:null)->withQueryString();
        $photoUrls=$records->getCollection()->mapWithKeys(fn($record)=>[$record->id=>$this->studentPhotoUrl($record->student)]);
        return view('student-skipping-grade.index',$shared+compact('records','filters','photoUrls','statusOptions'));
    }

    public function create(Request $request)
    {
        $this->service->authorize($request->user(),'create');
        return $this->form($request,new StudentSkippingGrade(['application_date'=>now()->toDateString(),'average_scale'=>100,
            'committee_names'=>[]]));
    }

    public function edit(Request $request, StudentSkippingGrade $skipping)
    {
        $this->service->authorizeEdit($request->user(),$skipping);
        return $this->form($request,$skipping);
    }

    private function form(Request $request, StudentSkippingGrade $record)
    {
        return view('student-skipping-grade.form',$this->shared($request,$record->campus_id)+[
            'record'=>$record,'grades'=>Grade::where('status',1)->orderByRaw('CAST(grade_order AS UNSIGNED)')->get(),
            'campusCommitteeRoles'=>SkippingGradeSetting::CAMPUS_COMMITTEE,
            'groups'=>Session::where('status',1)->orderBy('session_order')->get(),
            'targetClass'=>$record->target_class_id?SchoolClass::find($record->target_class_id):null,
            'previewPhotoUrl'=>$record->exists?$this->studentPhotoUrl($record->student):null,
            'customOptions'=>app(SkippingGradeTemplateService::class)->checkboxOptions(SkippingGradeSetting::current()->request_template??[]),
            'requestedAcademicYears'=>$record->exists?$this->service->targetAcademicYears($record->enrollment->academicYear):AcademicYear::regular()->operational()->orderBy('academic_year')->get(),
        ]);
    }

    private function eligibleStudentEnrollments(Request $request)
    {
        return StudentEnrollment::query()->where('tb_student_enrollment.status',1)->where('enrollment_status','active')
            ->whereHas('academicYear',fn($q)=>$q->regular()->operational())
            ->whereHas('student',fn($q)=>$q->where('status',1));
    }

    public function sourceClasses(Request $request)
    {
        $this->service->authorize($request->user(),'requests');
        $data=$request->validate(['academic_year_id'=>['nullable','integer'],'campus_id'=>['nullable','integer']]);
        if (empty($data['academic_year_id']) && empty($data['campus_id'])) return response()->json(['classes'=>[]]);
        $classes=$this->eligibleStudentEnrollments($request)
            ->when($data['academic_year_id']??null,fn($q,$id)=>$q->where('tb_student_enrollment.academic_year_id',$id))
            ->when($data['campus_id']??null,fn($q,$id)=>$q->where('campus_id',$id))
            ->whereHas('grade',fn($q)=>$q->where('status',1))->whereHas('schoolClass',fn($q)=>$q->where('status',1))
            ->join('tb_grade','tb_grade.id','=','tb_student_enrollment.grade_id')
            ->join('tb_class','tb_class.id','=','tb_student_enrollment.class_id')
            ->select('tb_student_enrollment.grade_id','tb_student_enrollment.class_id','tb_grade.grade','tb_grade.grade_short_name','tb_grade.grade_order','tb_class.class_name','tb_class.class_order')
            ->distinct()->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')->orderByRaw('CAST(tb_class.class_order AS UNSIGNED)')->orderBy('tb_class.class_name')->get();
        return response()->json(['classes'=>$classes->map(fn($row)=>[
            'value'=>$row->grade_id.':'.$row->class_id,'label'=>trim($row->grade_short_name ?: $row->grade).trim($row->class_name),
        ])->values()]);
    }

    public function students(Request $request)
    {
        $this->service->authorize($request->user(),'requests');
        $data=$request->validate(['q'=>['nullable','string','max:120'],'academic_year_id'=>['nullable','integer'],'campus_id'=>['nullable','integer'],'grade_id'=>['nullable','integer'],'grade_class'=>['nullable','string','regex:/^\d+:\d+$/']]);
        if (!empty($data['grade_class'])) [$data['grade_id'],$data['class_id']]=array_map('intval',explode(':',$data['grade_class']));
        $term=trim($data['q']??'');
        if (mb_strlen($term)<2 && empty($data['academic_year_id']) && empty($data['campus_id']) && empty($data['grade_id'])) return response()->json(['students'=>[],'has_more'=>false]);
        $rows=$this->eligibleStudentEnrollments($request)->with('student')
            ->when($term!=='',fn($q)=>$q->whereHas('student',fn($q)=>$q->where(fn($q)=>$q->where('student_id','like','%'.$term.'%')->orWhere('full_name_en','like','%'.$term.'%')->orWhere('full_name_kh','like','%'.$term.'%'))))
            ->when($data['academic_year_id']??null,fn($q,$id)=>$q->where('academic_year_id',$id))
            ->when($data['campus_id']??null,fn($q,$id)=>$q->where('campus_id',$id))
            ->when($data['grade_id']??null,fn($q,$id)=>$q->where('grade_id',$id))
            ->when($data['class_id']??null,fn($q,$id)=>$q->where('class_id',$id))
            ->orderBy(Student::selectRaw('LOWER(TRIM(full_name_en))')->whereColumn('tb_student.id','tb_student_enrollment.student_id'))
            ->orderBy('student_id')->orderBy('id')->limit(31)->get();
        return response()->json(['students'=>$rows->take(30)->map(fn($row)=>[
            'id'=>$row->id,'label'=>trim($row->student->full_name_en),
            'search_text'=>trim($row->student->student_id.' '.$row->student->full_name_en.' '.$row->student->full_name_kh),
        ])->values(),'has_more'=>$rows->count()>30]);
    }

    public function enrollment(Request $request, StudentEnrollment $enrollment)
    {
        $this->service->authorize($request->user(),'requests');
        $enrollment->load(['student.familyMembers','academicYear','grade','schoolClass','campus']);
        return response()->json([
            'id'=>$enrollment->id,'academic_year_id'=>$enrollment->academic_year_id,'campus_id'=>$enrollment->campus_id,
            'can_request'=>StudentSkippingGradePermissions::allows($request->user(),'create',$enrollment->campus_id),
            'can_update'=>StudentSkippingGradePermissions::allows($request->user(),'update',$enrollment->campus_id) || StudentSkippingGradePermissions::allows($request->user(),'central-update'),
            'name_en'=>$enrollment->student->full_name_en,'name_kh'=>$enrollment->student->full_name_kh,
            'student_id'=>$enrollment->student->student_id,'dob'=>$enrollment->student->date_of_birth?->format('d-M-Y'),
            'photo_url'=>$this->studentPhotoUrl($enrollment->student),
            'campus'=>$enrollment->campus->campus_name_en,'year'=>$enrollment->academicYear->academic_year,
            'grade'=>($enrollment->grade->grade_short_name ?: $enrollment->grade->grade).$enrollment->schoolClass->class_name,
            'grade_order'=>(int)$enrollment->grade->grade_order,'session_id'=>$enrollment->session_id,
            'target_academic_years'=>$this->service->targetAcademicYears($enrollment->academicYear)->map(fn($year)=>['id'=>$year->id,'label'=>$year->academic_year])->values(),
            'campus_committee_names'=>SkippingGradeCampusSetting::namesFor($enrollment->campus_id),
            'parents'=>$enrollment->student->familyMembers->filter(fn($member)=>in_array($member->relationship_type ?? $member->pivot?->relationship_type,['father','mother','guardian'],true))->map(fn($member)=>[
                'name'=>$member->full_name_en ?: $member->full_name_kh,'phone'=>$member->phone,'relationship'=>$member->relationship_type ?? $member->pivot?->relationship_type,
            ])->values(),
        ]);
    }

    public function targetClasses(Request $request)
    {
        $this->service->authorize($request->user(),'requests');
        $data=$request->validate(['enrollment_id'=>['required','integer'],'grade_id'=>['required','integer'],'target_academic_year_id'=>['nullable','integer']]);
        $enrollment=StudentEnrollment::findOrFail($data['enrollment_id']);
        $this->service->authorize($request->user(),'requests');
        $year=$this->service->targetYear($enrollment,(int)($data['target_academic_year_id']??$enrollment->academic_year_id));
        return response()->json(['classes'=>SchoolClass::where('status',1)->where(fn($q)=>$q->where('grade_id',$data['grade_id'])->orWhereNull('grade_id'))
            ->where(fn($q)=>$q->where('academic_year_id',$year->id)->orWhereNull('academic_year_id'))
            ->orderByRaw('CAST(class_order AS UNSIGNED)')->orderBy('class_name')->get(['id','class_name','session_id'])]);
    }

    private function draftData(Request $request): array
    {
        $data=$request->validate([
            'enrollment_id'=>['required','integer'],'target_grade_id'=>['required','integer'],'target_class_id'=>['required','integer'],
            'target_academic_year_id'=>['required','integer'],
            'target_session_id'=>['nullable','integer'],'application_date'=>['required','date_format:Y-m-d'],
            'parent_name'=>['required','string','max:200'],'parent_phone'=>['nullable','string','max:50'],'reason'=>['nullable','string','max:1500'],
            'criteria'=>['nullable','array:age,average,documents,recommendation'],'criteria.*'=>['boolean'],
            'custom_options'=>['sometimes','array','max:220'],'custom_options.*'=>['boolean'],
            'average_score'=>['nullable','numeric','min:0','max:100'],'average_scale'=>['required',Rule::in([10,100])],
            'committee_names'=>['nullable','array:0,1,2,3'],'committee_names.*'=>['nullable','string','max:200'],
        ]);
        $data['target_session_id']=$data['target_session_id']??null;
        $data['criteria']=array_replace(array_fill_keys(array_keys(self::CRITERIA),false),array_map(fn($value)=>(bool)$value,$data['criteria']??[]));
        return $data;
    }

    public function store(Request $request)
    {
        $this->service->authorize($request->user(),'create');
        $record=$this->service->saveDraft($request->user(),$this->draftData($request));
        return $this->success($request,'Draft saved. Print the request form for the parent to sign.',route('student-skipping-grade.show',$record));
    }

    public function update(Request $request, StudentSkippingGrade $skipping)
    {
        $this->service->authorizeEdit($request->user(),$skipping);
        $this->service->saveDraft($request->user(),$this->draftData($request),$skipping);
        return $this->success($request,'Request updated.',route('student-skipping-grade.show',$skipping));
    }

    public function show(Request $request, StudentSkippingGrade $skipping)
    {
        $this->service->authorize($request->user(),'requests');
        $skipping->load(['creator','approver','student']);
        return view('student-skipping-grade.show',$this->shared($request,$skipping->campus_id)+[
            'record'=>$skipping,
            'photoUrl'=>$this->studentPhotoUrl($skipping->student),
            'approvalCustomOptions'=>app(SkippingGradeTemplateService::class)->checkboxOptions(SkippingGradeSetting::current()->approval_template??[]),
        ]);
    }

    public function submit(Request $request, StudentSkippingGrade $skipping)
    {
        $this->service->authorize($request->user(),'submit',$skipping->campus_id);
        $data=$request->validate(['parent_signed'=>['required','accepted'],'parent_signed_date'=>['required','date_format:Y-m-d','before_or_equal:today'],
            'signed_request'=>['nullable','file','mimes:pdf,jpg,jpeg,png,webp','max:5120']]);
        $this->service->submit($request->user(),$skipping,$data['parent_signed_date'],$request->file('signed_request'));
        return $this->success($request,'Request submitted to Central Office for committee and VP approval.',route('student-skipping-grade.show',$skipping));
    }

    public function approve(Request $request, StudentSkippingGrade $skipping)
    {
        $this->service->authorize($request->user(),'approve',$skipping->campus_id);
        $effectiveDateRules=['required','date_format:Y-m-d'];
        if (($skipping->target_academic_year_id??$skipping->academic_year_id)==$skipping->academic_year_id) $effectiveDateRules[]='before_or_equal:today';
        $data=$request->validate(['vp_signed'=>['required','accepted'],'approval_date'=>['required','date_format:Y-m-d','before_or_equal:today'],
            'received_date'=>['required','date_format:Y-m-d','before_or_equal:approval_date'],
            'review_date'=>['required','date_format:Y-m-d','after_or_equal:received_date','before_or_equal:approval_date'],
            'effective_date'=>$effectiveDateRules,'approval_notes'=>['nullable','string','max:1500'],
            'age_decision'=>['required','in:standard,exception'],'school_decision'=>['required','in:internal,external'],
            'obligations'=>['nullable','array:conduct,rules,study'],'obligations.*'=>['boolean'],
            'custom_options'=>['sometimes','array','max:220'],'custom_options.*'=>['boolean']]);
        $this->service->approve($request->user(),$skipping,$data);
        return $this->success($request,'Request approved. The student enrollment for the requested academic year is ready. The signed approval form is ready to print.',route('student-skipping-grade.show',$skipping));
    }

    public function reject(Request $request, StudentSkippingGrade $skipping)
    {
        $this->service->authorize($request->user(),'reject',$skipping->campus_id);
        $data=$request->validate(['rejection_reason'=>['required','string','max:1500']]);
        $this->service->reject($request->user(),$skipping,$data['rejection_reason']);
        return $this->success($request,'Request rejected. The student remains in the current grade.',route('student-skipping-grade.show',$skipping));
    }

    public function printForm(Request $request, StudentSkippingGrade $skipping, string $form)
    {
        // Printing is available across campuses with the Print permission.
        $this->service->authorize($request->user(),'print');
        abort_unless(in_array($form,['request','approval'],true),404);
        abort_if($skipping->status==='rejected',403,'Rejected requests cannot be printed.');
        if ($form==='approval') abort_unless($skipping->status==='approved',403,'The approval form is available only after approval.');
        return view('student-skipping-grade.print-'.$form,$this->printData($skipping,$form));
    }

    public function shareApproval(Request $request, StudentSkippingGrade $skipping)
    {
        $this->service->authorize($request->user(),'print');
        abort_unless($skipping->status==='approved',403,'Only approved requests can be shared.');
        $filenameParts=array_map(static fn($value)=>trim(preg_replace('/[^\p{L}\p{N} _-]/u','-',(string)$value)),[
            $skipping->student_snapshot['name_en']??'Student',
            $skipping->student_snapshot['student_id']??$skipping->student_id,
            $skipping->reference_number ?: (string)$skipping->id,
        ]);
        return response()->view('student-skipping-grade.print-approval', $this->printData($skipping,'approval')+[
            'imageExport'=>true,
            'approvalImageFilename'=>implode('-',$filenameParts).'.png',
        ])->header('Cache-Control','private, no-store');
    }

    private function printData(StudentSkippingGrade $skipping, string $form, bool $preview = false): array
    {
        $branding=BrandingSetting::current();
        $logoPath=$branding->report_logo_1_path ?: $branding->sidebar_logo_path;
        $logoFile=$logoPath?public_path('storage/'.$logoPath):public_path('storage/school_logo/student_profile_report_logo.png');
        $settings=SkippingGradeSetting::current();
        $committeeNames=$skipping->status==='draft'?$settings->requestCommitteeNames($skipping->committee_names ?? []):($skipping->committee_names ?? []);
        $templates=app(SkippingGradeTemplateService::class);
        $template=$settings->{$form.'_template'}??[];
        $values=$templates->values($skipping,$committeeNames);
        $logo=is_file($logoFile)?'data:'.mime_content_type($logoFile).';base64,'.base64_encode(file_get_contents($logoFile)):null;
        return [
            'record'=>$skipping,'criteriaOptions'=>self::CRITERIA,'committeeRoles'=>SkippingGradeSetting::COMMITTEE,
            'committeeNames'=>$committeeNames,
            'templatePreview'=>$preview,
            'text'=>fn(string $key)=>$templates->text($form,$key,$template,$values),
            'shape'=>fn(string $key,bool $checked=false)=>$templates->shape($form,$key,$template,$checked),
            'extras'=>$templates->extras($template,$values,$preview?null:($form==='request'?($skipping->criteria['custom_options']??[]):($skipping->approval_snapshot['custom_options']??[])),$templates->checkboxValues($form,$skipping)),
            's'=>$skipping->student_snapshot,
            'logo'=>$logo,'logoObject'=>$templates->logo($form,$template,$logo,$preview),
            'dateKh'=>fn($date)=>$date?strtr($date->format('d-m-Y'),['0'=>'០','1'=>'១','2'=>'២','3'=>'៣','4'=>'៤','5'=>'៥','6'=>'៦','7'=>'៧','8'=>'៨','9'=>'៩']):'',
            'signature'=>$form==='approval'?$this->imageData($skipping->approval_snapshot['signature_path']??null):null,
            'stamp'=>$form==='approval'?$this->imageData($skipping->approval_snapshot['stamp_path']??null):null,
        ];
    }

    public function templateEditor(Request $request, string $form)
    {
        $this->service->authorize($request->user(),$form.'-template');
        abort_unless(in_array($form,['request','approval'],true),404);
        $templates=app(SkippingGradeTemplateService::class);
        $template=SkippingGradeSetting::current()->{$form.'_template'}??[];
        return view('student-skipping-grade.template',[
            'form'=>$form,'template'=>$template,'revision'=>$templates->revision($template),
            'canEdit'=>StudentSkippingGradePermissions::allows($request->user(),'edit-'.$form.'-template'),
            'defaults'=>$templates->defaults($form),'fonts'=>SkippingGradeTemplateService::FONTS,
            'definitions'=>$templates->definitions($form),
            'checkboxSources'=>$templates->checkboxSources($form),'checkboxValues'=>$templates->checkboxValues($form,$this->templateSample()),
            'values'=>$templates->values($this->templateSample(),array_fill(0,8,'COMMITTEE NAME')),
        ]);
    }

    public function templatePreview(Request $request, string $form)
    {
        $this->service->authorize($request->user(),$form.'-template');
        abort_unless(in_array($form,['request','approval'],true),404);
        return response()->view('student-skipping-grade.print-'.$form,$this->printData($this->templateSample(),$form,true))
            ->header('Cache-Control','private, no-store');
    }

    private function templateSample(): StudentSkippingGrade
    {
        $today=now()->toDateString();
        $sampleYear=AcademicYear::regular()->where('academic_year','2026-2027')->first();
        $sampleYearCode=trim((string)$sampleYear?->ay_code);
        $settings=SkippingGradeSetting::current();
        return (new StudentSkippingGrade([
            'status'=>'approved','reference_number'=>$settings->number_prefix.($sampleYearCode!==''?$sampleYearCode:'AY-CODE').'-001',
            'student_snapshot'=>['name_en'=>'STUDENT NAME','name_kh'=>'ឈ្មោះសិស្ស','student_id'=>'2612345',
                'source_grade'=>'3','source_class'=>'A','target_grade'=>'5','target_class'=>'A',
                'academic_year'=>'2025-2026','target_academic_year'=>'2026-2027','date_of_birth'=>'2017-03-02','campus_en'=>'BCH','campus_kh'=>'សាខា'],
            'parent_name'=>'PARENT / GUARDIAN NAME','parent_phone'=>'012345678','application_date'=>$today,
            'criteria'=>array_fill_keys(array_keys(self::CRITERIA),true),'average_score'=>95,'average_scale'=>100,
            'reason'=>'Additional information','committee_names'=>array_fill(0,8,'COMMITTEE NAME'),
            'approval_date'=>$today,'received_date'=>$today,'review_date'=>$today,'effective_date'=>$today,
            'approval_snapshot'=>['notes'=>'Approval notes','signer_title_kh'=>$settings->signer_title_kh??'','signer_name_kh'=>$settings->signer_name_kh??'',
                'signature_path'=>$settings->signature_path,'stamp_path'=>$settings->stamp_path,
                'obligations'=>['conduct'=>true,'rules'=>true,'study'=>true]],
        ]))->forceFill(['id'=>1]);
    }

    public function saveTemplate(Request $request, string $form)
    {
        $this->service->authorize($request->user(),'edit-'.$form.'-template');
        abort_unless(in_array($form,['request','approval'],true),404);
        $templates=app(SkippingGradeTemplateService::class);
        $definitions=$templates->definitions($form);
        $data=$request->validate([
            'revision'=>['required','string','size:64'],
            'blocks'=>['present','array','max:220'],
            'blocks.*'=>['array:text,font,size,color,left,top,bold,bullet,width,height,border_width,border_color,border_style,fill,removed,type,checked,option_label,checkbox_source'],
            'blocks.*.type'=>['sometimes',Rule::in(['text','score','box','line','checkbox','image'])],
            'blocks.*.text'=>['sometimes','nullable','string','max:4000'],
            'blocks.*.font'=>['sometimes',Rule::in(SkippingGradeTemplateService::FONTS)],
            'blocks.*.size'=>['sometimes','numeric','min:6','max:60'],
            'blocks.*.color'=>['sometimes','regex:/^#[0-9a-fA-F]{6}$/'],
            'blocks.*.left'=>['sometimes','numeric','between:-210,210'],
            'blocks.*.top'=>['sometimes','numeric','between:-297,297'],
            'blocks.*.bold'=>['sometimes','boolean'],
            'blocks.*.bullet'=>['sometimes','boolean'],
            'blocks.*.width'=>['sometimes','numeric','min:1','max:210'],
            'blocks.*.height'=>['sometimes','numeric','min:0','max:297'],
            'blocks.*.border_width'=>['sometimes','numeric','min:0','max:8'],
            'blocks.*.border_color'=>['sometimes','regex:/^#[0-9a-fA-F]{6}$/'],
            'blocks.*.border_style'=>['sometimes',Rule::in(['none','solid','dotted','dashed'])],
            'blocks.*.fill'=>['sometimes','regex:/^(transparent|#[0-9a-fA-F]{6})$/'],
            'blocks.*.removed'=>['sometimes','boolean'],
            'blocks.*.checked'=>['sometimes','boolean'],
            'blocks.*.option_label'=>['sometimes','required','string','max:200'],
            'blocks.*.checkbox_source'=>['sometimes','nullable','string',Rule::in(array_merge([''],array_keys($templates->checkboxSources($form))))],
        ]);
        $blocks=$data['blocks'];
        $allowedTokens=array_keys($templates->values($this->templateSample(),[]));
        foreach ($blocks as $key=>&$block) {
            if (!isset($definitions[$key]) && !$templates->isCustom((string)$key)) throw \Illuminate\Validation\ValidationException::withMessages(['blocks'=>'Choose a valid form object.']);
            if ($templates->isCustom((string)$key)) {
                if (!in_array($block['type']??null,['text','box','line','checkbox'],true)) throw \Illuminate\Validation\ValidationException::withMessages(['blocks.'.$key.'.type'=>'Choose Text Box, Box, Line, or Checkbox.']);
                foreach (['left','top','width','height'] as $field) if (!isset($block[$field])) throw \Illuminate\Validation\ValidationException::withMessages(['blocks.'.$key.'.'.$field=>'Set this object’s position and size.']);
            } elseif (isset($block['type']) && $block['type']!==$definitions[$key]['type']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['blocks.'.$key.'.type'=>'The original object type cannot be changed.']);
            }
            if (($definitions[$key]['type']??null)==='image') {
                foreach (array_diff(array_keys($block),['type','left','top','width','height','removed']) as $property) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['blocks.'.$key.'.'.$property=>'Use position and size controls for the school logo.']);
                }
            }
            foreach (['checked','option_label','checkbox_source'] as $property) if (array_key_exists($property,$block) && (!$templates->isCustom((string)$key) || ($block['type']??null)!=='checkbox')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['blocks.'.$key.'.'.$property=>'Set checkbox options only for an added checkbox. Original checkboxes follow the request data.']);
            }
            if (array_key_exists('bullet',$block) && ($templates->isCustom((string)$key)?$block['type']:$definitions[$key]['type'])!=='text') {
                throw \Illuminate\Validation\ValidationException::withMessages(['blocks.'.$key.'.bullet'=>'Bullets can only be applied to text.']);
            }
            if (array_key_exists('text',$block)) {
                $block['text']=$block['text']??'';
                preg_match_all('/\{([^{}]+)\}/u',$block['text'],$matches);
                if (array_diff($matches[1],$allowedTokens)) throw \Illuminate\Validation\ValidationException::withMessages(['blocks.'.$key.'.text'=>'Choose a valid automatic value from the editor.']);
            }
        }
        unset($block);
        DB::transaction(function () use ($request,$form,$templates,$data,$blocks) {
            $settings=SkippingGradeSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($templates->revision($settings->{$form.'_template'}??[]),$data['revision']),409,'This template was changed by another user. Reload the page before saving.');
            $settings->update([$form.'_template'=>$blocks,'updated_by'=>$request->user()->id]);
        });
        return response()->json(['message'=>ucfirst($form).' form template saved. Future prints will use this layout.','revision'=>$templates->revision($blocks)]);
    }

    public function signedRequest(Request $request, StudentSkippingGrade $skipping)
    {
        $this->service->authorize($request->user(),'requests');
        abort_unless($skipping->signed_request_path && Storage::disk('local')->exists($skipping->signed_request_path),404);
        return Storage::disk('local')->response($skipping->signed_request_path,null,['X-Content-Type-Options'=>'nosniff']);
    }

    public function settings(Request $request)
    {
        $this->service->authorize($request->user(),'settings');
        $settings=SkippingGradeSetting::current();
        return view('student-skipping-grade.settings',[
            'settings'=>$settings,'centralCommitteeRoles'=>SkippingGradeSetting::CENTRAL_COMMITTEE,
            'canSave'=>StudentSkippingGradePermissions::allows($request->user(),'save-settings'),
            'signature'=>$this->imageData($settings->signature_path),'stamp'=>$this->imageData($settings->stamp_path),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $this->service->authorize($request->user(),'save-settings');
        $data=$request->validate([
            'signer_name_kh'=>['required','string','max:200'],'signer_name_en'=>['nullable','string','max:200'],
            'signer_title_kh'=>['required','string','max:200'],'signer_title_en'=>['required','string','max:200'],
            'number_prefix'=>['required','string','max:16','regex:/^[A-Za-z0-9-]+$/'],
            'signature'=>['nullable','image','mimes:png,jpg,jpeg,webp','max:2048'],
            'stamp'=>['nullable','image','mimes:png,jpg,jpeg,webp','max:2048'],
            'committee_names'=>['nullable','array:4,5,6,7'],'committee_names.*'=>['nullable','string','max:200'],
        ]);
        $files=[];
        try {
            DB::transaction(function () use ($request,$data,&$files) {
                $settings=SkippingGradeSetting::whereKey(1)->lockForUpdate()->firstOrFail();
                $values=collect($data)->except(['signature','stamp'])->all();
                $values['committee_names']=array_replace(array_fill_keys(array_keys(SkippingGradeSetting::CENTRAL_COMMITTEE),''),array_intersect_key($data['committee_names']??$settings->committee_names??[],SkippingGradeSetting::CENTRAL_COMMITTEE));
                $old=[];
                foreach (['signature','stamp'] as $field) if ($request->hasFile($field)) {
                    $path=$request->file($field)->store('grade-skipping/settings','local');
                    if (!$path) throw \Illuminate\Validation\ValidationException::withMessages([$field=>'Unable to store this image. Please try again.']);
                    $files[]=$path; $old[]=$settings->{$field.'_path'}; $values[$field.'_path']=$path;
                }
                $settings->update($values+['updated_by'=>$request->user()->id]);
                DB::afterCommit(fn()=>Storage::disk('local')->delete(array_filter($old)));
            });
        } catch (\Throwable $e) { Storage::disk('local')->delete($files); throw $e; }
        return $this->success($request,'Approval settings saved. Previously approved forms keep their original signature and stamp.',route('student-skipping-grade.settings'));
    }

    public function campusSettings(Request $request)
    {
        $this->service->authorize($request->user(),'campus-settings');
        $data=$request->validate(['campus_id'=>['nullable','integer']]);
        $settingsCampuses=$request->user()->accessibleCampuses()->orderBy('campus_name_en')->get();
        $campusId=(int)($data['campus_id']??$settingsCampuses->firstWhere('id',$request->user()->active_campus_id)?->id??$settingsCampuses->first()?->id??0);
        if ($campusId) $this->service->authorize($request->user(),'campus-settings',$campusId);
        $selectedCampus=$settingsCampuses->firstWhere('id',$campusId);
        abort_if($campusId && !$selectedCampus,403);
        return view('student-skipping-grade.campus-settings',[
            'settingsCampuses'=>$settingsCampuses,'selectedCampus'=>$selectedCampus,
            'committeeNames'=>$selectedCampus?SkippingGradeCampusSetting::namesFor($campusId):[],
            'campusCommitteeRoles'=>SkippingGradeSetting::CAMPUS_COMMITTEE,
            'canSave'=>$selectedCampus && StudentSkippingGradePermissions::allows($request->user(),'save-campus-settings',$campusId),
        ]);
    }

    public function saveCampusSettings(Request $request)
    {
        $this->service->authorize($request->user(),'save-campus-settings');
        $data=$request->validate([
            'campus_id'=>['required','integer'],
            'committee_names'=>['required','array:0,1,2,3'],
            'committee_names.*'=>['nullable','string','max:200'],
        ]);
        $this->service->authorize($request->user(),'save-campus-settings',(int)$data['campus_id']);
        abort_unless(SchoolInfo::whereKey($data['campus_id'])->where('status',1)->exists(),403);
        $names=array_replace(array_fill_keys(array_keys(SkippingGradeSetting::CAMPUS_COMMITTEE),''),array_map(fn($name)=>trim($name??''),$data['committee_names']));
        SkippingGradeCampusSetting::updateOrCreate(['campus_id'=>$data['campus_id']],['committee_names'=>$names,'updated_by'=>$request->user()->id]);
        return $this->success($request,'Campus committee names saved. New requests will use these names.',route('student-skipping-grade.campus-settings',['campus_id'=>$data['campus_id']]));
    }

    private function imageData(?string $path): ?string
    {
        if (!$path || !Storage::disk('local')->exists($path)) return null;
        return 'data:'.Storage::disk('local')->mimeType($path).';base64,'.base64_encode(Storage::disk('local')->get($path));
    }

    private function studentPhotoUrl(?Student $student): ?string
    {
        $path=str_replace('\\','/',trim($student?->photo_path ?? '', '/\\ '));
        if ($path==='' || in_array('..',explode('/',$path),true) || !Storage::disk('public')->exists($path)) return null;
        $url=asset('storage/'.$path);
        return $student->updated_at?$url.'?v='.$student->updated_at->getTimestamp():$url;
    }

    private function success(Request $request, string $message, string $url)
    {
        return $request->expectsJson()?response()->json(['message'=>$message,'redirect'=>$url]):redirect($url)->with('success',$message);
    }
}
