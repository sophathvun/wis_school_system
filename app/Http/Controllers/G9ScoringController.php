<?php

namespace App\Http\Controllers;

use App\Models\{AcademicYear,G9ExamCandidate};
use App\Support\{G9ExamFields,G9NationalExamPermissions as Access,G9ScoringReport};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class G9ScoringController
{
    public const FILTERS=['exam_center','campus','room','grade','student'];

    private function campuses(Request $request,bool $edit=false): array
    {
        $ids=Access::campusIds($request->user(),$edit?'scoring-edit':'scoring');
        abort_unless($ids,403);
        return $ids;
    }

    public function index(Request $request)
    {
        $ids=$this->campuses($request);
        return view('g9-national-exams.scoring',[
            'years'=>AcademicYear::whereIn('id',G9ExamCandidate::whereIn('campus_id',$ids)->select('academic_year_id'))->orderByDesc('academic_year')->get(),
            'subjects'=>G9ExamFields::GROUPS['National Examination Scores'],
            'reportFormats'=>collect(['print','pdf'])->filter(fn($format)=>Access::allows($request->user(),'scoring-'.$format))->values()->all(),
        ]);
    }

    private function query(Request $request,bool $filtered=true)
    {
        $input=$request->validate([
            'academic_year'=>['required','integer','exists:tb_academic_year,id'],
            'exam_center'=>['nullable','string','max:255'],'campus'=>['nullable','integer'],
            'room'=>['nullable','string','max:255'],'grade'=>['nullable','string','max:255'],'student'=>['nullable','integer'],
            'per_page'=>['nullable',Rule::in(['25','50','75','100'])],
        ]);
        $query=G9ExamCandidate::whereIn('campus_id',$this->campuses($request))->where('academic_year_id',$input['academic_year']);
        if($filtered)foreach(self::FILTERS as $filter)$this->applyFilter($query,$filter,$input[$filter]??null);
        return $query;
    }

    private function applyFilter($query,string $filter,mixed $value): void
    {
        if($value===null||$value==='')return;
        $column=['campus'=>'campus_id','student'=>'id','room'=>'data->room_number'][$filter]??'data->'.$filter;
        $query->where($column,$value);
    }

    public function options(Request $request)
    {
        $query=$this->query($request,false);$choices=[];
        foreach(self::FILTERS as $filter){
            if($filter==='campus'){
                $choices[$filter]=(clone $query)->with('campus')->get(['campus_id'])->unique('campus_id')
                    ->map(fn($r)=>['id'=>$r->campus_id,'label'=>$r->campus?->campus_name_en??''])->sortBy('label',SORT_NATURAL)->values();
            }elseif($filter==='student'){
                $choices[$filter]=(clone $query)->orderBy('name_kh')->orderBy('name_en')->get(['id','student_code','name_kh','name_en'])
                    ->map(fn($r)=>['id'=>$r->id,'label'=>implode(' | ',array_filter([$r->student_code,$r->name_kh,$r->name_en]))]);
            }else{
                $column='data->'.($filter==='room'?'room_number':$filter);
                $choices[$filter]=(clone $query)->select($column.' as choice')->distinct()->pluck('choice')->filter(fn($v)=>$v!==null&&$v!=='')->sort(SORT_NATURAL)->values();
            }
            $this->applyFilter($query,$filter,$request->input($filter));
        }
        return response()->json($choices);
    }

    public function listing(Request $request)
    {
        $query=$this->query($request);$size=$request->input('per_page','25');
        $allowed=Access::campusIds($request->user(),'scoring-edit');
        $room=DB::connection()->getQueryGrammar()->wrap('data->room_number');$seat=DB::connection()->getQueryGrammar()->wrap('data->seat_number');
        $cast=DB::connection()->getDriverName()==='sqlite'?'INTEGER':'UNSIGNED';
        $result=$query->with('campus')->orderByRaw("CAST($seat AS $cast)")->orderBy('data->exam_center')->orderByRaw("CAST($room AS $cast)")->orderBy('id')
            ->paginate((int)$size);
        $result->getCollection()->transform(fn($r)=>[
            'id'=>$r->id,'version'=>$r->version,'student_code'=>$r->student_code,'name_kh'=>$r->name_kh,'name_en'=>$r->name_en,
            'photo_url'=>$r->photo_path?asset('storage/'.$r->photo_path):null,'campus'=>$r->campus?->campus_name_en,'grade'=>$r->data['grade']??'',
            'exam_center'=>$r->data['exam_center']??'','room'=>$r->data['room_number']??'','seat'=>$r->data['seat_number']??'',
            'eoy_average'=>$r->data['eoy_average']??null,'exam_language'=>$r->data['exam_language']??'','attendance_status'=>$r->attendance_status,'attendance_reason'=>$r->attendance_reason,
            'scores'=>array_replace(array_fill_keys(array_keys(G9ExamFields::GROUPS['National Examination Scores']),null),array_intersect_key($r->data,G9ExamFields::GROUPS['National Examination Scores'])),
            'can_edit'=>in_array($r->campus_id,$allowed,true),
        ]);
        return response()->json($result)->header('Cache-Control','private, no-store');
    }

    public function attendance(Request $request,int $candidate)
    {
        $allowed=$this->campuses($request,true);abort_unless($allowed,403);
        $input=$request->validate(['version'=>['required','integer','min:1'],'attendance_status'=>['present','nullable',Rule::in(['absent'])],
            'attendance_reason'=>['sometimes','nullable','string','max:1000']]);
        $record=DB::transaction(function()use($request,$candidate,$allowed,$input){
            $record=G9ExamCandidate::whereIn('campus_id',$allowed)->lockForUpdate()->findOrFail($candidate);
            abort_if($record->version!==(int)$input['version'],409,'This candidate changed in another window. Refresh the list before marking attendance.');
            $reason=$input['attendance_status']==='absent'?(array_key_exists('attendance_reason',$input)?trim($input['attendance_reason']??''):($record->attendance_reason??'')):'';
            $reason=$reason===''?null:$reason;
            if($record->attendance_status!==$input['attendance_status']||$record->attendance_reason!==$reason){
                $before=$record->attendance_status;$beforeReason=$record->attendance_reason;
                $record->update(['attendance_status'=>$input['attendance_status'],'attendance_reason'=>$reason,'version'=>$record->version+1,'updated_by'=>$request->user()->id]);
                DB::table('tb_g9_exam_event')->insert(['candidate_id'=>$record->id,'user_id'=>$request->user()->id,'action'=>'set-exam-attendance',
                    'details'=>json_encode(['before'=>$before,'after'=>$record->attendance_status,'reason_before'=>$beforeReason,'reason_after'=>$reason],JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
            }
            return $record;
        });
        return response()->json(['id'=>$record->id,'version'=>$record->version,'attendance_status'=>$record->attendance_status,'attendance_reason'=>$record->attendance_reason]);
    }

    public function save(Request $request,int $candidate)
    {
        $ids=$this->campuses($request,true);$subjects=G9ExamFields::GROUPS['National Examination Scores'];
        $rules=['version'=>['required','integer','min:1'],'scores'=>['required','array:'.implode(',',array_keys($subjects)),'min:1']];
        foreach($subjects as $field=>$label)$rules['scores.'.$field]=['sometimes','nullable','numeric','min:0','max:9999.999','decimal:0,3'];
        $input=$request->validate($rules);
        $result=DB::transaction(function()use($request,$candidate,$ids,$input){
            $record=G9ExamCandidate::whereIn('campus_id',$ids)->lockForUpdate()->findOrFail($candidate);
            abort_if($record->version!==(int)$input['version'],409,'This candidate changed. Reload the list before editing scores.');
            $data=$record->data;$before=[];$after=[];
            foreach($input['scores'] as $field=>$score){
                $value=$score===null||$score===''?null:number_format((float)$score,3,'.','');
                $old=$data[$field]??null;
                if(($old===null||$old==='')?($value===null):($value!==null&&(float)$old===(float)$value))continue;
                $before[$field]=$old;$after[$field]=$value;$data[$field]=$value;
            }
            if($after){
                $record->update(['data'=>$data,'version'=>$record->version+1,'updated_by'=>$request->user()->id]);
                DB::table('tb_g9_exam_event')->insert(['candidate_id'=>$record->id,'user_id'=>$request->user()->id,'action'=>'save-scores',
                    'details'=>json_encode(['before'=>$before,'after'=>$after],JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
            }
            return ['version'=>$record->version,'scores'=>array_replace(array_fill_keys(array_keys(G9ExamFields::GROUPS['National Examination Scores']),null),array_intersect_key($data,G9ExamFields::GROUPS['National Examination Scores']))];
        });
        return response()->json($result+['message'=>'Exam scores saved.']);
    }

    public function collectionReport(Request $request,string $format,G9ScoringReport $reports)
    {
        return $this->report($request,$format,$reports,'scores');
    }

    public function report(Request $request,string $format,G9ScoringReport $reports,string $type='verify')
    {
        abort_unless(in_array($format,['print','pdf'],true),404);
        $ids=Access::campusIds($request->user(),'scoring-'.$format);abort_unless($ids,403);
        $room=DB::connection()->getQueryGrammar()->wrap('data->room_number');$seat=DB::connection()->getQueryGrammar()->wrap('data->seat_number');
        $cast=DB::connection()->getDriverName()==='sqlite'?'INTEGER':'UNSIGNED';
        $records=$this->query($request)->whereIn('campus_id',$ids)->orderBy('data->exam_center')->orderByRaw("CAST($room AS $cast)")->orderByRaw("CAST($seat AS $cast)")->orderBy('id')->get();
        abort_if($records->isEmpty(),422,'No candidates match the selected filters.');
        $setting=DB::table('tb_g9_exam_year_setting')->where('academic_year_id',$request->integer('academic_year'))->first();
        $pages=$reports->pages($records,json_decode($setting?->data??'{}',true),$type);
        if($format==='print')return response()->view('g9-national-exams.scoring-report',['pages'=>$pages,'type'=>$type])->header('Cache-Control','private, no-store');
        return response($reports->pdf($pages,$type))->header('Content-Type','application/pdf')->header('Content-Disposition','attachment; filename="'.($type==='scores'?'g9-score-collection.pdf':'g9-scoring-verification.pdf').'"')->header('Cache-Control','private, no-store');
    }
}
