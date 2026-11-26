<?php

namespace App\Http\Controllers;

use App\Models\{GepEnrollment,GepStudent,GepAcademicYear,GepTermAssignment,GepLevel,GepClassType,GepClass,GepGroup,Student};
use App\Services\{StudentPhotoProcessor,StudentPhotoBackground};
use App\Support\{GepEnrollmentPermissions as Access,PhoneNumber};
use App\Support\GepStudentProfile as Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule,ValidationException};

class GepEnrollmentController
{
    private const FIELDS = ['term_assignment_id','campus_id','level_id','class_type_id','class_id','group_id','student_type','enrollment_status','enrolled_on','notes','guardian_name','guardian_phone','parent_information'];
    private const PARENT_FIELDS = ['name_en','name_kh','occupation','nationality','phone','workplace','occupation_id','nationality_country_id'];
    private const RELATIONS = ['gepStudent', 'termAssignment.academicYear','termAssignment.term','campus:id,campus_name_en','level','classType','schoolClass','group'];

    private function authorize(Request $request,string $action='view',?int $campus=null): void
    {
        abort_unless(Access::allows($request->user(),$action,$campus),403,'You do not have GEP enrollment '.$action.' permission for this campus.');
    }

    public function index(Request $request)
    {
        $this->authorize($request);
        return view('gep.enrollments', ['options'=>$this->options($request),
            'permissions'=>collect(['create','edit','delete','history'])->mapWithKeys(fn($a)=>[$a=>Access::allows($request->user(),$a)])->all()]);
    }

    private function options(Request $request): array
    {
        return [
            'campuses'=>Access::campuses($request->user()),'createCampuses'=>Access::campuses($request->user(),'create'),
            'editCampuses'=>Access::campuses($request->user(),'edit'),
            'years'=>GepAcademicYear::orderByDesc('academic_year')->get(),
            'assignments'=>GepTermAssignment::with(['academicYear','term'])->get()->sort(fn($a,$b)=>strcmp($b->academicYear->academic_year,$a->academicYear->academic_year) ?: $b->sort_order <=> $a->sort_order)->values(),
            'levels'=>GepLevel::orderBy('sort_order')->orderBy('level_name')->get(),
            'classTypes'=>GepClassType::orderBy('sort_order')->get(),
            'classes'=>GepClass::orderBy('sort_order')->orderBy('class_name')->get(),
            'groups'=>GepGroup::orderBy('sort_order')->get(),
            'countries'=>DB::table('tb_country')->where('status',1)->orderBy('country_name_en')->get(['id','country_name_en','country_name_kh','nationality_name_en','nationality_name_kh','flag_path']),
            'occupations'=>DB::table('tb_occupation')->where('status',1)->whereNull('deleted_at')->orderBy('occupation_name_en')->get(['id','occupation_name_en','occupation_name_kh']),
            'families'=>GepStudent::whereHas('enrollments',fn($q)=>$q->whereIn('campus_id',Access::campuses($request->user())->pluck('id')))->whereNotNull('profile->family_number')->orderByDesc('id')->get()->filter(fn($m)=>filled($m->profile['family_number']??null))->map(fn($m)=>['id'=>$m->id,'label'=>$m->profile['family_number'].' · '.($m->profile['full_name_en']??$m->gep_code),'family_number'=>$m->profile['family_number']])->values(),
            'lookupYears'=>DB::table('tb_academic_year')->where('period_type','regular')->whereIn('id',DB::table('tb_student_enrollment')->whereIn('campus_id',Access::campuses($request->user())->pluck('id'))->select('academic_year_id'))->orderByDesc('academic_year')->get(['id','academic_year']),
            // Use the enrollment's year and grade. Imported records can use a class defined in another year.
            'lookupClasses'=>DB::table('tb_student_enrollment as e')->join('tb_academic_year as y','y.id','=','e.academic_year_id')->join('tb_class as c','c.id','=','e.class_id')->join('tb_grade as g','g.id','=','e.grade_id')
                ->where('y.period_type','regular')->whereIn('e.campus_id',Access::campuses($request->user())->pluck('id'))->distinct()->orderBy('g.grade_order')->orderBy('c.class_name')->get(['c.id','e.academic_year_id','e.campus_id','e.grade_id','g.grade','g.grade_short_name','g.grade_order','c.class_name']),
            'today'=>now('Asia/Phnom_Penh')->toDateString(),
        ];
    }

    public function fetch(Request $request)
    {
        $this->authorize($request);
        $filters=$request->validate([
            'academic_year_id'=>['nullable','integer'],'term_assignment_id'=>['nullable','integer'],'campus_id'=>['nullable','integer'],
            'level_id'=>['nullable','integer'],'class_type_id'=>['nullable','integer'],'class_id'=>['nullable','integer'],'group_id'=>['nullable','integer'],
            'enrollment_status'=>['nullable',Rule::in(['active','pending','completed','withdrawn','graduated'])],
            'search'=>['nullable','string','max:150'],'perPage'=>['nullable',Rule::in(['10','25','50','100','all'])], 'page'=>['nullable','integer','min:1'],
        ]);
        $query=GepEnrollment::whereIn('campus_id',Access::campuses($request->user())->pluck('id'));
        foreach(['term_assignment_id','campus_id','level_id','class_type_id','class_id','group_id','enrollment_status'] as $field)if(filled($filters[$field]??null))$query->where($field,$filters[$field]);
        if(filled($filters['academic_year_id']??null))$query->whereHas('termAssignment',fn($q)=>$q->where('academic_year_id',$filters['academic_year_id']));
        if($search=trim($filters['search']??''))$query->where(function($q)use($search){
            $q->whereHas('gepStudent',fn($s)=>$s->where('profile->full_name_en','like','%'.$search.'%')->orWhere('profile->full_name_kh','like','%'.$search.'%')->orWhere('student_identifier','like','%'.$search.'%'));
            if(preg_match('/^GEP-0*(\d+)$/i',$search,$match))$q->orWhere('gep_student_id',(int)$match[1]);
        });
        $summary=['total'=>(clone $query)->count()];
        foreach(['active','pending','completed'] as $status)$summary[$status]=(clone $query)->where('enrollment_status',$status)->distinct()->count('gep_student_id');
        $count=$summary['total'];$perPage=($filters['perPage']??'10')==='all'?max(1,$count):(int)($filters['perPage']??10);
        $page=min((int)($filters['page']??1),max(1,(int)ceil($count/$perPage)));
        $result=$query->with(self::RELATIONS)->orderByDesc('enrolled_on')->orderByDesc('id')->paginate($perPage,['*'],'page',$page);
        $result->getCollection()->each(function($row)use($request){
            $row->parent_information=$row->gepStudent->familyInformation();
            if($row->parent_information){$row->guardian_name=$row->parent_information['guardian']['name_en']??null;$row->guardian_phone=$row->parent_information['guardian']['phone']??null;}
            $editable=in_array($row->enrollment_status,['active','pending'],true) && $row->termAssignment->lifecycle_status!=='finished';
            $row->setAttribute('permissions',collect(['edit','delete','history'])->mapWithKeys(fn($a)=>[$a=>($a==='history'||$editable)&&Access::allows($request->user(),$a,(int)$row->campus_id)])->all());
        });
        return response()->json(['records'=>$result,'summary'=>$summary]);
    }

    public function students(Request $request)
    {
        $this->authorize($request,'create');
        $data=$request->validate(['q'=>['nullable','string','max:100'],'campus_id'=>['nullable','integer'],'academic_year_id'=>['nullable','integer',Rule::exists('tb_academic_year','id')->where('period_type','regular')],'class_id'=>['nullable','integer'],'grade_id'=>['nullable','integer']]);
        if(!empty($data['campus_id']))$this->authorize($request,'view',(int)$data['campus_id']);
        // Existing identities may be selected from permitted campuses. This endpoint never exposes WIS records/history.
        $campuses=Access::campuses($request->user())->pluck('id');
        $rows=Student::where('status',1)->where(function($q)use($campuses,$data){
            $q->whereHas('enrollments',function($e)use($campuses,$data){
                $e->whereIn('campus_id',$campuses);
                $e->whereIn('academic_year_id',DB::table('tb_academic_year')->where('period_type','regular')->select('id'));
                foreach(['campus_id','academic_year_id','class_id','grade_id'] as $field)if(!empty($data[$field]))$e->where($field,$data[$field]);
            });
            if(empty($data['academic_year_id']) && empty($data['class_id']) && empty($data['grade_id']))$q->orWhereIn('id',GepStudent::whereIn('id',GepEnrollment::select('gep_student_id')->whereIn('campus_id',$campuses)->when(!empty($data['campus_id']),fn($e)=>$e->where('campus_id',$data['campus_id'])))->select('student_id'));
        })->when(filled($data['q']??null),fn($q)=>$q->where(fn($q)=>$q->where('full_name_en','like','%'.$data['q'].'%')->orWhere('full_name_kh','like','%'.$data['q'].'%')->orWhere('student_id','like','%'.$data['q'].'%')->orWhere('student_no','like','%'.$data['q'].'%')))
            ->orderBy('full_name_en')->limit(31)->get(['id','student_no','student_id','full_name_en','full_name_kh','gender','date_of_birth','photo_path']);
        $members=GepStudent::whereIn('student_id',$rows->pluck('id'))->get()->keyBy('student_id');
        $rows->each(function($student)use($members){
            $path=$members->get($student->id)?->photo_path ?: $student->photo_path;
            $student->setAttribute('photo_url',$path?Storage::disk('public')->url($path):null);
            $student->makeHidden('photo_path');
        });
        $dataRows=$rows->take(30)->values();
        if(empty($data['academic_year_id']) && empty($data['class_id']) && empty($data['grade_id'])){
            $members=GepStudent::whereNull('student_id')->whereHas('enrollments',fn($e)=>$e->whereIn('campus_id',$campuses)->when(!empty($data['campus_id']),fn($e)=>$e->where('campus_id',$data['campus_id'])))
                ->when(filled($data['q']??null),fn($s)=>$s->where(fn($s)=>$s->where('profile->full_name_en','like','%'.$data['q'].'%')->orWhere('profile->full_name_kh','like','%'.$data['q'].'%')->orWhere('student_identifier','like','%'.$data['q'].'%')))->limit(31)->get();
            $dataRows=$dataRows->concat($members->map(fn($m)=>['id'=>'gep-'.$m->id,'gep_student_id'=>$m->id,'student_id'=>$m->student_identifier,'full_name_en'=>$m->profile['full_name_en']??'','date_of_birth'=>$m->profile['date_of_birth']??null]));
        }
        return response()->json(['data'=>$dataRows->take(30)->values(),'has_more'=>$rows->count()>30 || $dataRows->count()>30]);
    }

    public function profile(Request $request)
    {
        $this->authorizePhoto($request);
        $data=$request->validate(['student_id'=>['nullable','integer','required_without:gep_student_id'],'gep_student_id'=>['nullable','integer','required_without:student_id']]);
        $campuses=Access::campuses($request->user())->pluck('id');
        if(!empty($data['gep_student_id'])){
            $member=GepStudent::whereKey($data['gep_student_id'])->whereHas('enrollments',fn($q)=>$q->whereIn('campus_id',$campuses))->firstOrFail();
            return response()->json(['student_type_default'=>$member->enrollments()->exists()?'old':'new','profile'=>$member->profile,'student_identifier'=>$member->student_identifier,'gep_code'=>$member->gep_code,'photo_url'=>$member->photo_url,
                'parent_information'=>$member->familyInformation()]);
        }
        $student=Student::whereKey($data['student_id'])->whereHas('enrollments',fn($q)=>$q->whereIn('campus_id',$campuses))->firstOrFail();
        $member=GepStudent::where('student_id',$student->id)->whereHas('enrollments',fn($q)=>$q->whereIn('campus_id',$campuses))->first();
        return response()->json(['student_type_default'=>$member && $member->enrollments()->exists()?'old':'new','profile'=>$member?->profile ?? Profile::snapshot($student),'student_identifier'=>$member?->student_identifier,
            'gep_code'=>$member?->gep_code,'photo_url'=>$member?->photo_url ?: ($student->photo_path?Storage::disk('public')->url($student->photo_path):null),
            'parent_information'=>$member?->familyInformation()]);
    }

    public function locations(Request $request)
    {
        $this->authorizePhoto($request);
        $data=$request->validate(['type'=>['required',Rule::in(['province','district','commune','village'])],'parent_id'=>['required','integer']]);
        $parent=['province'=>'country_id','district'=>'province_id','commune'=>'district_id','village'=>'commune_id'][$data['type']];
        return response()->json(['data'=>DB::table('tb_'.$data['type'])->where($parent,$data['parent_id'])->where('status',1)->orderBy($data['type'].'_name_en')->get(['id',$data['type'].'_name_en as name_en',$data['type'].'_name_kh as name_kh'])]);
    }

    public function save(Request $request,?GepEnrollment $enrollment=null)
    {
        $this->authorize($request,$enrollment?'edit':'create',$enrollment?(int)$enrollment->campus_id:null);
        $rules=[
            'photo'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:10240','dimensions:width=600,height=800'],
            'term_assignment_id'=>['required','integer'],'campus_id'=>['required','integer',Rule::exists('tb_school_info','id')->where('status',1)->whereNull('deleted_at')],
            'level_id'=>['required','integer'],'class_type_id'=>['required','integer'],'class_id'=>['required','integer'],'group_id'=>['required','integer'],
            'student_type'=>['nullable',Rule::in(['new','old'])],
            'enrollment_status'=>['required',Rule::in(['active','pending'])],'enrolled_on'=>['required','date_format:Y-m-d'],
            'notes'=>['nullable','string','max:2000'],'guardian_name'=>['nullable','string','max:150'],'guardian_phone'=>['nullable','string','max:50'],
        ];
        foreach(['mother','father','guardian'] as $parent)foreach(self::PARENT_FIELDS as $field){
            $rules[$parent.'_'.$field]=['nullable','string','max:'.($field==='phone'?50:150)];
            if(str_ends_with($field,'_id'))$rules[$parent.'_'.$field]=['nullable','integer','exists:tb_'.($field==='occupation_id'?'occupation':'country').',id'];
        }
        $rules += Profile::rules();
        $selectedMemberId=$enrollment?->gep_student_id ?: ($request->input('source')==='existing'?($request->integer('gep_student_id') ?: ($request->filled('student_id')?GepStudent::where('student_id',$request->input('student_id'))->value('id'):null)):null);
        $rules['student_identifier']=['nullable','string','max:30',Rule::unique('tb_gep_student','student_identifier')->ignore($selectedMemberId)];
        $rules['student_identifier'][]=function($attribute,$value,$fail)use($selectedMemberId){if($selectedMemberId && GepStudent::whereKey($selectedMemberId)->value('student_identifier')===$value)return;if(preg_match('/^GEP-0*(\d+)$/i',$value,$match) && (int)$match[1] !== (int)$selectedMemberId)$fail('The GEP-number format is reserved for automatically assigned IDs.');};
        $rules['existing_family_id']=['nullable','integer'];
        if(!$enrollment)$rules += [
            'source'=>['required',Rule::in(['existing','new'])],'student_id'=>['nullable','integer'],'gep_student_id'=>['nullable','integer'],
            'full_name_en'=>['required_if:source,new','nullable','string','max:150'], 'full_name_kh'=>['nullable','string','max:150'],
            'home_phone'=>['nullable','string','max:50'],'email'=>['nullable','email','max:150'],
            'current_address_en'=>['nullable','string','max:1000'],'previous_school'=>['nullable','string','max:150'],
        ];
        if(!$enrollment && $request->input('source')==='new')$rules['full_name_en']=['required','string','max:160'];
        $data=$request->validate($rules);
        if(!$enrollment && $data['source']==='existing' && empty($data['student_id']) && empty($data['gep_student_id']))throw ValidationException::withMessages(['student_id'=>'Select an existing student.']);
        $this->validateLocations($data);
        $this->authorize($request,$enrollment?'edit':'create',(int)$data['campus_id']);
        $created=!$enrollment;
        $newPhoto=null;
        try {
        $saved=DB::transaction(function()use($request,$enrollment,$data,&$newPhoto){
            $assignment=GepTermAssignment::find($data['term_assignment_id']);
            if(!$assignment)throw ValidationException::withMessages(['term_assignment_id'=>'Select an assigned GEP term.']);
            $year=GepAcademicYear::whereKey($assignment->academic_year_id)->lockForUpdate()->firstOrFail();
            $assignment=GepTermAssignment::with('term')->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            if($enrollment){
                $enrollment=GepEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
                if(!in_array($enrollment->enrollment_status,['active','pending'],true) || $enrollment->termAssignment->lifecycle_status==='finished')throw ValidationException::withMessages(['enrollment'=>'Only active or pending enrollments in unfinished terms can be edited.']);
                if((int)$enrollment->term_assignment_id!==(int)$assignment->id)throw ValidationException::withMessages(['term_assignment_id'=>'Use promotion or re-enrollment to change the academic term.']);
            }
            if(!$year->status || !$assignment->term?->status || $assignment->lifecycle_status==='finished')throw ValidationException::withMessages(['term_assignment_id'=>'Select an enabled year and term that has not finished.']);
            if($data['enrollment_status']==='active' && $assignment->lifecycle_status!=='started')throw ValidationException::withMessages(['enrollment_status'=>'Active enrollment requires a started term. Use Pending for a future term.']);
            foreach(['level_id'=>GepLevel::class,'class_type_id'=>GepClassType::class,'class_id'=>GepClass::class,'group_id'=>GepGroup::class] as $field=>$model){
                $value=$model::find($data[$field]);
                if(!$value || (!$value->status && (!$enrollment || (int)$enrollment->$field!==(int)$data[$field])))throw ValidationException::withMessages([$field=>'Select an active setting.']);
            }
            $before=$enrollment?->only(self::FIELDS);
            if(!$enrollment){
                if($data['source']==='existing'){
                    if(!empty($data['gep_student_id'])){
                        $gepStudent=GepStudent::whereKey($data['gep_student_id'])->whereHas('enrollments',fn($q)=>$q->whereIn('campus_id',Access::campuses($request->user())->pluck('id')))->lockForUpdate()->firstOrFail();
                    }else{
                    $student=Student::whereKey($data['student_id'])->where('status',1)->lockForUpdate()->first();
                    $campuses=Access::campuses($request->user())->pluck('id');
                    if(!$student || (! $student->enrollments()->whereIn('campus_id',$campuses)->exists()
                        && !GepEnrollment::whereIn('campus_id',$campuses)->whereHas('gepStudent',fn($q)=>$q->where('student_id',$student->id))->exists()))abort(403);
                    $gepStudent=GepStudent::firstOrCreate(['student_id'=>$student->id],['source'=>'existing','profile'=>array_replace(Profile::snapshot($student),['family_number'=>null])]);
                    }
                }else{
                    $name=trim($data['full_name_en']);
                    if(!empty($data['date_of_birth']) && GepStudent::where('profile->full_name_en','like',$name)->where('profile->date_of_birth',$data['date_of_birth'])->exists())throw ValidationException::withMessages(['full_name_en'=>'This GEP student already exists. Select Existing Student.']);
                    $gepStudent=GepStudent::create(['source'=>'new','profile'=>['full_name_en'=>$name]]);
                }
                if(GepEnrollment::where('gep_student_id',$gepStudent->id)->where('term_assignment_id',$assignment->id)->exists())throw ValidationException::withMessages(['student_id'=>'This student is already enrolled in the selected GEP term.']);
                $enrollment=new GepEnrollment(['gep_student_id'=>$gepStudent->id,'created_by'=>$request->user()->id]);
            }
            $gepStudent=GepStudent::whereKey($enrollment->gep_student_id)->lockForUpdate()->firstOrFail();
            $beforeProfile=$gepStudent->profile;
            $beforeIdentifier=$gepStudent->student_identifier;
            $profile=array_replace($beforeProfile??[],collect($data)->only(Profile::FIELDS)->all());
            $profile=\App\Support\GepTransferNames::clearChanged($beforeProfile??[],$profile);
            $this->validateLocations($profile);
            if(empty($profile['full_name_en']))throw ValidationException::withMessages(['full_name_en'=>'Full Name (English) is required.']);
            $profile['full_name_en']=trim($profile['full_name_en']);
            $profile['home_phone']=Profile::phone($profile['home_phone']??null);
            $profile['gender_kh']=match($profile['gender']??null){'M'=>'ប្រុស','F'=>'ស្រី',default=>$profile['gender_kh']??null};
            if(!empty($data['existing_family_id'])){
                $family=GepStudent::whereKey($data['existing_family_id'])->whereHas('enrollments',fn($q)=>$q->whereIn('campus_id',Access::campuses($request->user())->pluck('id')))->firstOrFail();
                if(empty($family->profile['family_number']))throw ValidationException::withMessages(['existing_family_id'=>'Select a GEP family with a family number.']);
                $profile['family_number']=$family->profile['family_number'];
            }
            $identifier=$gepStudent->student_identifier;
            if(filled($identifier) && filled($data['student_identifier']??null) && $data['student_identifier']!==$identifier)
                throw ValidationException::withMessages(['student_identifier'=>'This student already has a permanent GEP Student ID.']);
            $identifier=$identifier ?: (filled($data['student_identifier']??null)?$data['student_identifier']:app(\App\Services\GepStudentIdAllocator::class)->allocate($year->academic_year));
            $gepStudent->update(['profile'=>$profile,'student_identifier'=>$identifier]);
            $profile=$gepStudent->profile;
            $beforePhoto=$gepStudent->photo_path;
            if($request->hasFile('photo')){
                $bytes=app(StudentPhotoProcessor::class)->portraitWebp($request->file('photo'));
                $newPhoto='gep_student_photos/'.Str::uuid().'.webp';
                if(!Storage::disk('public')->put($newPhoto,$bytes))throw ValidationException::withMessages(['photo'=>'The student photo could not be saved. Please try again.']);
                $gepStudent->update(['photo_path'=>$newPhoto]);
                if($beforePhoto)DB::afterCommit(function()use($beforePhoto){
                    if(!GepStudent::where('photo_path',$beforePhoto)->exists() && !Student::where('photo_path',$beforePhoto)->exists())Storage::disk('public')->delete($beforePhoto);
                });
            }elseif(!$gepStudent->photo_path && $gepStudent->student_id){
                $original=$gepStudent->student?->photo_path;
                if($original && Storage::disk('public')->exists($original)){
                    $newPhoto='gep_student_photos/'.Str::uuid().'.'.pathinfo($original,PATHINFO_EXTENSION);
                    if(!Storage::disk('public')->copy($original,$newPhoto))throw ValidationException::withMessages(['photo'=>'The student photo could not be copied. Please try again.']);
                    $gepStudent->update(['photo_path'=>$newPhoto]);
                }
            }
            $values=collect($data)->only(self::FIELDS)->all();$values['guardian_phone']=PhoneNumber::normalize($values['guardian_phone']??null);
            // Family details belong to the GEP student; enrollments retain their saved snapshots.
            $savedParents=$gepStudent->familyInformation(true);
            if(collect(array_keys($data))->contains(fn($key)=>$key!=='guardian_phone' && preg_match('/^(mother|father|guardian)_(name_en|name_kh|occupation|nationality|phone|workplace)$/',$key))){
                $parents=[];
                foreach(['mother','father','guardian'] as $parent)foreach(self::PARENT_FIELDS as $field){
                    if(!array_key_exists($parent.'_'.$field,$data)){$parents[$parent][$field]=$savedParents[$parent][$field]??null;continue;}
                    $value=trim($data[$parent.'_'.$field]??'');
                    $parents[$parent][$field]=$field==='phone'?Profile::phone($value):($value!==''?$value:null);
                }
                foreach(['mother','father','guardian'] as $parent)foreach(['occupation'=>'occupation_id','nationality'=>'nationality_country_id'] as $text=>$key){
                    if(!array_key_exists($parent.'_'.$text,$data) && (string)($savedParents[$parent][$key]??'')===(string)($parents[$parent][$key]??''))
                        $parents[$parent][$text]=$savedParents[$parent][$text]??null;
                }
                $values['guardian_name']=$parents['guardian']['name_en'];
                $values['guardian_phone']=$parents['guardian']['phone'];
                $enrollment->parent_information=$parents;
                $gepStudent->saveFamilyInformation($parents,$request->user());
                $profile=$gepStudent->profile;
            }elseif($savedParents){
                $enrollment->parent_information=$savedParents;
                $values['guardian_name']=$savedParents['guardian']['name_en']??null;
                $values['guardian_phone']=$savedParents['guardian']['phone']??null;
            }
            $enrollment->fill([...$values,'updated_by'=>$request->user()->id])->save();
            if($before){$before['photo_path']=$beforePhoto;$before['student_profile']=$beforeProfile;$before['student_identifier']=$beforeIdentifier;}
            $this->event($enrollment,$before?'updated':'created',$before,[...$enrollment->only(self::FIELDS),'photo_path'=>$gepStudent->photo_path,'student_profile'=>$profile,'student_identifier'=>$gepStudent->student_identifier],$request->user()->id);
            return $enrollment;
        });
        } catch(\Throwable $error) {
            if($newPhoto && !GepStudent::where('photo_path',$newPhoto)->exists())Storage::disk('public')->delete($newPhoto);
            throw $error;
        }
        return response()->json(['message'=>$created?'GEP student enrolled successfully.':'GEP enrollment updated.','data'=>$saved->load(self::RELATIONS)],$created?201:200);
    }

    private function authorizePhoto(Request $request): void
    {
        abort_unless(Access::allows($request->user(),'create') || Access::allows($request->user(),'edit'),403);
    }

    private function validateLocations(array $data): void
    {
        foreach(['birth','address'] as $prefix){
            foreach(['province'=>'country','district'=>'province','commune'=>'district','village'=>'commune'] as $child=>$parent){
                if(!empty($data[$prefix.'_'.$child.'_id']) && (empty($data[$prefix.'_'.$parent.'_id']) || !DB::table('tb_'.$child)->where('id',$data[$prefix.'_'.$child.'_id'])->where($parent.'_id',$data[$prefix.'_'.$parent.'_id'])->exists()))throw ValidationException::withMessages([$prefix.'_'.$child.'_id'=>'Select a location belonging to the selected '. $parent.'.']);
            }
        }
    }

    public function photoSize(Request $request,StudentPhotoProcessor $processor)
    {
        $this->authorizePhoto($request);
        $request->validate(['photo'=>['required','image','mimes:png','max:10240','dimensions:width=600,height=800']]);
        $bytes=$processor->portraitWebp($request->file('photo'));
        return response()->json(['bytes'=>strlen($bytes),'width'=>600,'height'=>800],200,['Cache-Control'=>'private, no-store']);
    }

    public function photoBackground(Request $request,StudentPhotoBackground $background)
    {
        $this->authorizePhoto($request);
        $request->validate(['photo'=>['required','image','mimes:jpg,jpeg,png,webp','max:10240','dimensions:max_width=6000,max_height=6000']]);
        [$width,$height]=getimagesize($request->file('photo')->getRealPath());
        abort_if($width*$height>24000000,422,'Please use a photo smaller than 24 megapixels.');
        return response($background->remove($request->file('photo')),200,['Content-Type'=>'image/png','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }

    private function event(GepEnrollment $enrollment,string $action,?array $before,?array $after,int $user): void
    {
        DB::table('tb_gep_enrollment_history')->insert(['enrollment_id'=>$enrollment->id,'action'=>$action,
            'before_values'=>$before===null?null:json_encode($before,JSON_THROW_ON_ERROR),'after_values'=>$after===null?null:json_encode($after,JSON_THROW_ON_ERROR),
            'changed_by'=>$user,'created_at'=>now(),'updated_at'=>now()]);
    }

    public function delete(Request $request,GepEnrollment $enrollment)
    {
        $this->authorize($request,'delete',(int)$enrollment->campus_id);
        DB::transaction(function()use($request,$enrollment){
            $row=GepEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            if(!in_array($row->enrollment_status,['active','pending'],true)||$row->termAssignment->lifecycle_status==='finished')throw ValidationException::withMessages(['enrollment'=>'Finished or historical enrollment records cannot be deleted.']);
            $this->event($row,'deleted',$row->only(self::FIELDS),null,$request->user()->id);
            $row->active_slot=null;$row->save();$row->delete();
        });
        return response()->json(['message'=>'GEP enrollment deleted. Student identity and activity history have been preserved.']);
    }

    public function history(Request $request,GepEnrollment $enrollment)
    {
        $this->authorize($request,'history',(int)$enrollment->campus_id);
        return response()->json(['data'=>DB::table('tb_gep_enrollment_history as h')->leftJoin('users as u','u.id','=','h.changed_by')
            ->where('enrollment_id',$enrollment->id)->orderByDesc('h.id')->get(['h.action','h.before_values','h.after_values','h.created_at','u.name as changed_by'])]);
    }
}
