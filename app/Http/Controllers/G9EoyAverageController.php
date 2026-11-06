<?php

namespace App\Http\Controllers;

use App\Models\{AcademicYear,G9ExamCandidate};
use App\Services\G9ExamImports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class G9EoyAverageController
{
    private function campuses(Request $request,bool $assign=false): array
    {
        $key='g9.eoy.campuses.'.($assign?'assign':'view');
        if(!$request->attributes->has($key)){
            $ids=app(G9ExamImports::class)->campusIds($request->user(),'eoy');
            if($assign)$ids=array_values(array_intersect($ids,app(G9ExamImports::class)->campusIds($request->user(),'eoy-assign')));
            $request->attributes->set($key,$ids);
        }
        return $request->attributes->get($key);
    }
    public static function semester(mixed $score): ?string
    {
        if($score===null||$score==='')return null;
        return number_format(intdiv((int)round((float)$score*1000)+5,10)/100,2,'.','');
    }
    public static function total(mixed $first,mixed $second): ?string
    {
        if($first===null||$first===''||$second===null||$second==='')return null;
        $cents=(int)round((float)self::semester($first)*100)+(int)round((float)self::semester($second)*100);
        return (string) intdiv($cents+50,100);
    }
    public static function effectiveTotal(mixed $first,mixed $second,mixed $saved): ?string
    {
        $calculated=self::total($first,$second);
        if($calculated!==null)return $calculated;
        return $saved!==null&&$saved!==''&&is_numeric($saved)?(string)round((float)$saved):null;
    }
    public function index(Request $request)
    {
        $ids=$this->campuses($request);abort_unless($ids,403);
        return view('g9-national-exams.eoy-average',['years'=>AcademicYear::whereIn('id',G9ExamCandidate::whereIn('campus_id',$ids)->select('academic_year_id'))->orderByDesc('academic_year')->get(),
            'reportFormats'=>collect(['print','pdf'])->filter(fn($format)=>\App\Support\G9NationalExamPermissions::allows($request->user(),'eoy-'.$format))->values()->all(),
            'reportTypes'=>collect(['cover','card'])->filter(fn($type)=>\App\Support\G9NationalExamPermissions::allows($request->user(),'eoy-report-'.$type))->values()->all()]);
    }
    private function query(Request $request)
    {
        $input=$request->validate(['academic_year'=>['required','integer','exists:tb_academic_year,id'],'campus'=>['nullable','integer'],'grade'=>['nullable','string','max:255'],'student'=>['nullable','integer'],
            'per_page'=>['nullable',Rule::in(['all','15','25','50','75','100'])]]);
        $ids=$this->campuses($request);abort_unless($ids,403);
        return G9ExamCandidate::whereIn('campus_id',$ids)->where('academic_year_id',$input['academic_year'])
            ->when($input['campus']??null,fn($q,$v)=>$q->where('campus_id',$v))
            ->when($input['grade']??null,fn($q,$v)=>$q->where('data->grade',$v))
            ->when($input['student']??null,fn($q,$v)=>$q->whereKey($v));
    }
    public function options(Request $request)
    {
        $base=clone $request;$base->replace($request->only('academic_year'));
        $rows=$this->query($base)->with('campus')->get(['id','campus_id','student_code','name_kh','name_en','data']);
        $campuses=$rows->unique('campus_id')->map(fn($r)=>['id'=>$r->campus_id,'label'=>$r->campus?->campus_name_en??''])->sortBy('label',SORT_NATURAL)->values();
        if($request->filled('campus')&&$campuses->contains(fn($c)=>(string)$c['id']===(string)$request->input('campus')))$rows=$rows->where('campus_id',(int)$request->input('campus'));
        $grades=$rows->map(fn($r)=>(string)($r->data['grade']??''))->filter(fn($v)=>$v!=='')->unique()->sort(SORT_NATURAL)->values();
        if($request->filled('grade')&&$grades->containsStrict((string)$request->input('grade')))$rows=$rows->filter(fn($r)=>(string)($r->data['grade']??'')===(string)$request->input('grade'));
        return response()->json(['campus'=>$campuses,'grade'=>$grades,'student'=>$rows->map(fn($r)=>['id'=>$r->id,'label'=>implode(' | ',array_filter([$r->student_code,$r->name_kh,$r->name_en]))])->values()]);
    }
    public function listing(Request $request)
    {
        $query=$this->query($request);$size=$request->input('per_page','15');$allowed=$this->campuses($request,true);
        $result=$query->with('campus')->orderBy('name_kh')->orderBy('name_en')->orderBy('id')->paginate($size==='all'?max(1,(clone $query)->count()):(int)$size);
        $result->getCollection()->transform(fn($r)=>['id'=>$r->id,'version'=>$r->version,'student_code'=>$r->student_code,'name_kh'=>$r->name_kh,'name_en'=>$r->name_en,
            'photo_url'=>$r->photo_path?asset('storage/'.$r->photo_path):null,'campus'=>$r->campus?->campus_name_en,'grade'=>$r->data['grade']??'',
            'exam_center'=>$r->data['exam_center']??'','exam_date'=>$r->data['exam_date']??'','room'=>$r->data['room_number']??'','seat'=>$r->data['seat_number']??'',
            'semester_1'=>self::semester($r->data['semester_1']??null),'semester_2'=>self::semester($r->data['semester_2']??null),'saved_eoy'=>$r->data['eoy_average']??null,
            'scores_normalized'=>collect(['semester_1','semester_2'])->every(fn($field)=>!isset($r->data[$field])||(float)$r->data[$field]===(float)self::semester($r->data[$field])),
            'calculated_eoy'=>self::total($r->data['semester_1']??null,$r->data['semester_2']??null),'can_assign'=>in_array($r->campus_id,$allowed,true)]);
        return response()->json($result);
    }
    public function save(Request $request,int $candidate)
    {
        $ids=$this->campuses($request,true);abort_unless($ids,403);
        $input=$request->validate(['version'=>['required','integer','min:1'],'semester_1'=>['present','nullable','numeric','min:0','max:10000','decimal:0,3'],'semester_2'=>['present','nullable','numeric','min:0','max:10000','decimal:0,3']]);
        $total=DB::transaction(function()use($request,$candidate,$ids,$input){
            $record=G9ExamCandidate::whereIn('campus_id',$ids)->lockForUpdate()->findOrFail($candidate);
            abort_if($record->version!==(int)$input['version'],409,'This candidate changed. Reload the list before assigning scores.');
            $data=$record->data;$before=array_intersect_key($data,array_flip(['semester_1','semester_2','eoy_average']));
            $data['semester_1']=self::semester($input['semester_1']);$data['semester_2']=self::semester($input['semester_2']);
            // Historical candidates may have only an imported annual total.
            $calculated=self::total($input['semester_1'],$input['semester_2']);
            $data['eoy_average']=$calculated??($data['eoy_average']??null);
            $record->update(['data'=>$data,'version'=>$record->version+1,'updated_by'=>$request->user()->id]);
            DB::table('tb_g9_exam_event')->insert(['candidate_id'=>$record->id,'user_id'=>$request->user()->id,'action'=>'assign-eoy','details'=>json_encode(['before'=>$before,'after'=>array_intersect_key($data,array_flip(['semester_1','semester_2','eoy_average'])),'formula'=>$calculated!==null?'SEM1 + SEM2':null,'eoy_source'=>$calculated!==null?'semester-sum':'saved-total'],JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
            return ['semester_1'=>$data['semester_1'],'semester_2'=>$data['semester_2'],'eoy_average'=>$data['eoy_average'],'version'=>$record->version];
        });
        return response()->json($total+['message'=>'Semester scores and EOY total saved.']);
    }

    public function report(Request $request,string $type,string $format,\App\Support\G9EoyReport $reports)
    {
        abort_unless(isset(\App\Support\G9EoyReport::TITLES[$type])&&in_array($format,['print','pdf'],true),404);
        $access=\App\Support\G9NationalExamPermissions::class;
        $formatIds=$access::campusIds($request->user(),'eoy-'.$format);$typeIds=$access::campusIds($request->user(),'eoy-report-'.$type);
        $ids=array_values(array_intersect($formatIds,$typeIds));abort_unless($ids,403);
        $room=DB::connection()->getQueryGrammar()->wrap('data->room_number');$seat=DB::connection()->getQueryGrammar()->wrap('data->seat_number');
        $cast=DB::connection()->getDriverName()==='sqlite'?'INTEGER':'UNSIGNED';
        $records=$this->query($request)->whereIn('campus_id',$ids)->orderBy('data->exam_center')->orderByRaw("CAST($room AS $cast)")->orderByRaw("CAST($seat AS $cast)")->orderBy('id')->get();
        abort_if($records->isEmpty(),422,'No candidates match the selected filters.');
        $setting=DB::table('tb_g9_exam_year_setting')->where('academic_year_id',$request->integer('academic_year'))->first();
        $pages=$reports->pages($records,json_decode($setting?->data??'{}',true),$type);
        if($format==='print')return response()->view('g9-national-exams.eoy-report',['pages'=>$pages,'type'=>$type])->header('Cache-Control','private, no-store');
        return response($reports->pdf($pages,$type))->header('Content-Type','application/pdf')->header('Content-Disposition','attachment; filename="g9-eoy-'.$type.'.pdf"')->header('Cache-Control','private, no-store');
    }
}
