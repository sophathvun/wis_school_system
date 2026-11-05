<?php

namespace App\Http\Controllers;

use App\Models\{AcademicYear,G9ExamCandidate,SchoolInfo};
use App\Support\G9ExamFields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\{Rule,ValidationException};

class G9NationalExamController
{
    private function campuses(Request $request,string $action='view'): array
    {
        $key='g9.campuses.'.$request->user()->id.'.'.$action;
        if($request->attributes->has($key))return $request->attributes->get($key);
        $campusKey='g9.accessible-campus-records.'.$request->user()->id;
        if(!$request->attributes->has($campusKey))$request->attributes->set($campusKey,$request->user()->accessibleCampuses()->get());
        $campuses=$request->attributes->get($campusKey);
        $visible=$action==='view'?null:$this->campuses($request);
        $ids=$campuses->filter(fn($c)=>$action==='view'?$request->user()->hasPermission('g9-national-exams.view',$c->id)
            :(in_array($c->id,$visible,true)&&$request->user()->hasPermission('g9-national-exams.'.$action,$c->id)))->modelKeys();
        $request->attributes->set($key,$ids);return $ids;
    }
    public function index(Request $request)
    {
        abort_unless($this->campuses($request),403);
        return view('g9-national-exams.candidates',['years'=>AcademicYear::orderByDesc('academic_year')->get(),
            'filterYears'=>AcademicYear::whereIn('id',G9ExamCandidate::whereIn('campus_id',$this->campuses($request))->select('academic_year_id'))->orderByDesc('academic_year')->get(),
            'campuses'=>SchoolInfo::whereIn('id',$this->campuses($request))->orderBy('campus_name_en')->get(),
            'fields'=>G9ExamFields::GROUPS,'canExport'=>(bool)$this->campuses($request,'export'),'createCampuses'=>$this->campuses($request,'create'),'editCampuses'=>$this->campuses($request,'edit')]);
    }
    private function query(Request $request)
    {
        $data=$request->validate(['academic_year'=>['nullable','integer'],'exam_center'=>['nullable','string','max:255'],'campus'=>['nullable','integer'],'room'=>['nullable','string','max:255'],'seat'=>['nullable','string','max:255'],'student'=>['nullable','integer'],'search'=>['nullable','string','max:200'],'per_page'=>['nullable',Rule::in(['all','15','25','50','75','100'])]]);
        $ids=$this->campuses($request);abort_unless($ids,403);
        return G9ExamCandidate::whereIn('campus_id',$ids)->when($data['academic_year']??null,fn($q,$v)=>$q->where('academic_year_id',$v))
            ->when($data['exam_center']??null,fn($q,$v)=>$q->where('data->exam_center',$v))
            ->when($data['campus']??null,fn($q,$v)=>$q->where('campus_id',$v))
            ->when($data['room']??null,fn($q,$v)=>$q->where('data->room_number',$v))
            ->when($data['seat']??null,fn($q,$v)=>$q->where('data->seat_number',$v))
            ->when($data['student']??null,fn($q,$v)=>$q->whereKey($v))
            ->when($data['search']??null,fn($q,$v)=>$q->where(fn($q)=>$q->where('student_code','like','%'.$v.'%')->orWhere('name_kh','like','%'.$v.'%')->orWhere('name_en','like','%'.$v.'%')));
    }
    private function orderByRoom($query)
    {
        $room=DB::connection()->getQueryGrammar()->wrap('data->room_number');
        $cast=DB::connection()->getDriverName()==='sqlite'?'INTEGER':'UNSIGNED';
        return $query->orderByRaw("CASE WHEN $room IS NULL OR $room = '' THEN 1 ELSE 0 END")
            ->orderByRaw("CAST($room AS $cast) ASC")->orderBy('data->room_number');
    }
    public function listing(Request $request)
    {
        $query=$this->query($request);$size=$request->input('per_page','15');
        $summaryRows=(clone $query)->with('campus')->get(['id','campus_id','data']);
        $gender=fn($row)=>mb_strtolower(trim((string)($row->data['gender']??'')));
        $female=fn($rows)=>$rows->filter(fn($row)=>in_array($gender($row),['female','f','ស្រី'],true))->count();
        $counts=fn($rows)=>['total'=>$rows->count(),'male'=>$rows->filter(fn($row)=>in_array($gender($row),['male','m','ប្រុស'],true))->count(),'female'=>$female($rows)];
        $languages=$summaryRows->groupBy(function($row){
            $language=trim((string)($row->data['exam_language']??''));
            return match(mb_strtolower($language)){'english','en','អង់គ្លេស'=>'អង់គ្លេស','khmer','kh','ខ្មែរ'=>'ខ្មែរ',default=>$language?:'មិនបានបញ្ជាក់'};
        });
        // Keep both standard examination languages visible even when a filter returns no candidates.
        foreach(['អង់គ្លេស','ខ្មែរ'] as $language)if(!$languages->has($language))$languages->put($language,collect());
        $summary=['total'=>$summaryRows->count(),'male'=>$summaryRows->filter(fn($row)=>in_array($gender($row),['male','m','ប្រុស'],true))->count(),'female'=>$female($summaryRows),
            'languages'=>$languages->map(fn($rows,$label)=>['label'=>$label]+$counts($rows))->values(),
            'campuses'=>$summaryRows->groupBy('campus_id')->map(fn($rows)=>['label'=>$rows->first()->campus?->campus_name_en?:'មិនបានបញ្ជាក់']+$counts($rows))->sortBy('label',SORT_NATURAL)->values()];
        $result=$this->orderByRoom($query)->with(['academicYear','campus'])->orderBy('name_kh')->orderBy('name_en')->orderBy('id')->paginate($size==='all'?max(1,(clone $query)->count()):(int)$size);
        $editable=$this->campuses($request,'edit');
        $result->getCollection()->each(function($row)use($editable){$row->setAttribute('can_edit',in_array($row->campus_id,$editable,true));$row->setAttribute('photo_url',$row->photo_path?asset('storage/'.$row->photo_path):null);});
        return response()->json(array_merge($result->toArray(),['summary'=>$summary]));
    }
    public function options(Request $request)
    {
        if($request->boolean('cascade')){
            $base=clone $request;$base->replace($request->only('academic_year'));
            $rows=$this->query($base)->with('campus')->get(['id','campus_id','student_code','name_kh','name_en','data']);$result=[];
            foreach(['exam_center'=>'exam_center','campus'=>'campus_id','room'=>'room_number','seat'=>'seat_number'] as $filter=>$field){
                $value=fn($r)=>(string)($filter==='campus'?$r->campus_id:($r->data[$field]??''));
                $values=$rows->map($value)->filter(fn($v)=>$v!=='')->unique()->sort(SORT_NATURAL)->values();
                $result[$filter]=$filter==='campus'?$rows->unique('campus_id')->map(fn($r)=>['id'=>$r->campus_id,'label'=>$r->campus?->campus_name_en??''])->sortBy('label',SORT_NATURAL)->values():$values;
                $selected=(string)$request->input($filter,'');
                if($selected!==''&&$values->containsStrict($selected))$rows=$rows->filter(fn($r)=>$value($r)===$selected);
            }
            $result['student']=$rows->map(fn($r)=>['id'=>$r->id,'label'=>implode(' | ',array_filter([$r->student_code,$r->name_kh,$r->name_en]))])->values();
            return response()->json($result);
        }
        $rows=$this->query($request)->get();
        $values=fn($key)=>$rows->map(fn($r)=>$r->data[$key]??'')->filter(fn($v)=>$v!=='')->unique()->sort(SORT_NATURAL)->values();
        return response()->json(['exam_center'=>$values('exam_center'),'room'=>$values('room_number'),'seat'=>$values('seat_number'),
            'student'=>$rows->map(fn($r)=>['id'=>$r->id,'label'=>implode(' | ',array_filter([$r->student_code,$r->name_kh,$r->name_en]))])]);
    }
    public function report(Request $request,string $type,string $format,\App\Support\G9CandidateReport $reports)
    {
        abort_unless(isset(\App\Support\G9CandidateReport::TITLES[$type])&&in_array($format,['print','excel','pdf'],true),404);
        abort_if(in_array($type,\App\Support\G9CandidateReport::PRINT_ONLY_TYPES,true)&&$format==='excel',404);
        $request->validate(['academic_year'=>['required','integer','exists:tb_academic_year,id']]);
        $ids=$this->campuses($request,'export');abort_unless($ids,403);
        $records=$this->orderByRoom($this->query($request)->whereIn('campus_id',$ids)->orderBy('data->exam_center'))->get();
        abort_if($records->isEmpty(),422,'No candidates match the selected filters.');
        $setting=DB::table('tb_g9_exam_year_setting')->where('academic_year_id',$request->integer('academic_year'))->first();
        $data=['type'=>$type,'pages'=>$reports->pages($records,json_decode($setting?->data??'{}',true),$type)];
        if($format==='print')return response()->view('g9-national-exams.candidate-report',$data)->header('Cache-Control','private, no-store');
        $bytes=$format==='pdf'?$reports->pdf($data):$reports->xlsx($data);
        return response($bytes)->header('Content-Type',$format==='pdf'?'application/pdf':'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition','attachment; filename="g9-'.$type.'.'.($format==='pdf'?'pdf':'xlsx').'"')->header('Cache-Control','private, no-store');
    }
    public function show(Request $request,int $candidate)
    {
        return response()->json(G9ExamCandidate::whereIn('campus_id',$this->campuses($request))->findOrFail($candidate));
    }
    public function studentLinks(Request $request)
    {
        $input=$request->validate(['academic_year_id'=>['required','integer','exists:tb_academic_year,id'],'campus_id'=>['required','integer','exists:tb_school_info,id']]);
        $allowed=array_unique(array_merge($this->campuses($request,'create'),$this->campuses($request,'edit')));
        abort_unless(in_array((int)$input['campus_id'],$allowed,true),403);
        return response()->json(app(\App\Services\G9StudentAddress::class)->students($input['academic_year_id'],$input['campus_id'])
            ->orderBy('full_name_en')->get(['id','student_id','full_name_en'])->map(fn($student)=>['id'=>$student->id,'label'=>$student->student_id.' | '.$student->full_name_en]));
    }
    public function save(Request $request,?int $candidate=null)
    {
        $input=$request->validate(G9ExamFields::rules());
        $action=$candidate?'edit':'create';$allowed=$this->campuses($request,$action);
        abort_unless(in_array((int)$input['campus_id'],$allowed,true),403);
        if(!empty($input['sis_student_id'])&&!app(\App\Services\G9StudentAddress::class)->students($input['academic_year_id'],$input['campus_id'])->whereKey($input['sis_student_id'])->exists())
            throw ValidationException::withMessages(['sis_student_id'=>'Choose a Student Info record enrolled in this academic year and campus.']);
        $record=$candidate?G9ExamCandidate::whereIn('campus_id',$allowed)->findOrFail($candidate):null;
        $photo=null;
        $photos=app(\App\Services\G9ExamPhoto::class);
        if($request->hasFile('photo'))$photo=$photos->store((int)$input['academic_year_id'],$request->file('photo')->get());
        elseif($record?->photo_path&&$record->academic_year_id!==(int)$input['academic_year_id'])$photo=$photos->copyToYear((int)$input['academic_year_id'],$record->photo_path);
        try{
            $result=DB::transaction(function()use($request,$candidate,$input,$photo,$action){
                DB::table('tb_academic_year')->where('id',$input['academic_year_id'])->lockForUpdate()->first();
                $record=$candidate?G9ExamCandidate::lockForUpdate()->findOrFail($candidate):new G9ExamCandidate();
                if($candidate){abort_unless(in_array($record->campus_id,$this->campuses($request,'edit'),true),403);abort_if($record->version!==($input['version']??0),409,'This record changed. Reopen it before saving.');}
                if(G9ExamCandidate::where('academic_year_id',$input['academic_year_id'])->where('student_code',$input['student_code'])->when($candidate,fn($q)=>$q->where('id','!=',$candidate))->exists())throw ValidationException::withMessages(['student_code'=>'This student already has a G9 record for the academic year.']);
                $before=$record->exists?$record->toArray():null;$details=$input;
                foreach(['academic_year_id','campus_id','student_code','name_kh','name_en','version','photo'] as $key)unset($details[$key]);
                $record->fill(['academic_year_id'=>$input['academic_year_id'],'campus_id'=>$input['campus_id'],'student_code'=>$input['student_code'],'name_kh'=>$input['name_kh']??null,'name_en'=>$input['name_en'],
                    'data'=>$details,'version'=>$candidate?$record->version+1:1,'photo_path'=>$photo?:$record->photo_path,'created_by'=>$record->created_by?:$request->user()->id,'updated_by'=>$request->user()->id])->save();
                DB::table('tb_g9_exam_event')->insert(['candidate_id'=>$record->id,'user_id'=>$request->user()->id,'action'=>$action,'details'=>json_encode(['before'=>$before,'after'=>$record->toArray()],JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
                return $record;
            });
        }catch(\Throwable $error){if($photo)Storage::disk('public')->delete($photo);throw $error;}
        return response()->json(['message'=>'G9 candidate saved.','id'=>$result->id]);
    }
}
