<?php

namespace App\Http\Controllers;

use App\Models\{AcademicYear,G9ExamCandidate};
use App\Support\{G9NationalExamPermissions as Access,G9ScoreSummary,G9ResultReport};
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class G9ExamResultController
{
    public const FILTERS=['exam_center','campus','room','grade','student'];

    private function campuses(Request $request): array
    {
        $ids=Access::campusIds($request->user(),'results');
        abort_unless($ids,403);
        return $ids;
    }

    public function index(Request $request)
    {
        $ids=$this->campuses($request);
        return view('g9-national-exams.results',[
            'years'=>AcademicYear::whereIn('id',G9ExamCandidate::whereIn('campus_id',$ids)->select('academic_year_id'))->orderByDesc('academic_year')->get(),
            'tabs'=>['results'=>true,'graph'=>Access::allows($request->user(),'results-graph'),'statistics'=>Access::allows($request->user(),'results-statistics')],
        ]);
    }

    private function query(Request $request,bool $filtered=true)
    {
        $input=$request->validate([
            'academic_year'=>['required','integer','exists:tb_academic_year,id'],
            'exam_center'=>['nullable','string','max:255'],'campus'=>['nullable','integer'],
            'room'=>['nullable','string','max:255'],'grade'=>['nullable','string','max:255'],'student'=>['nullable','integer'],
            'result'=>['nullable',Rule::in(['passed','failed','pending','absent'])],
            'search'=>['nullable','string','max:255'],'per_page'=>['nullable',Rule::in(['25','50','75','100'])],
            'page'=>['nullable','integer','min:1'],
        ]);
        $query=G9ExamCandidate::whereIn('campus_id',$this->campuses($request))->where('academic_year_id',$input['academic_year']);
        if($filtered){
            foreach(self::FILTERS as $filter)$this->applyFilter($query,$filter,$input[$filter]??null);
            if($search=trim($input['search']??''))$query->where(function($q)use($search){
                foreach(['student_code','name_kh','name_en'] as $column)$q->orWhere($column,'like','%'.$search.'%');
            });
        }
        return $query;
    }

    private function applyFilter($query,string $filter,mixed $value): void
    {
        if($value===null||$value==='')return;
        $query->where(['campus'=>'campus_id','student'=>'id','room'=>'data->room_number'][$filter]??'data->'.$filter,$value);
    }

    public function options(Request $request)
    {
        $query=$this->query($request,false);$choices=[];
        foreach(self::FILTERS as $filter){
            if($filter==='campus')$choices[$filter]=(clone $query)->with('campus')->get(['campus_id'])->unique('campus_id')
                ->map(fn($r)=>['id'=>$r->campus_id,'label'=>$r->campus?->campus_name_en??''])->sortBy('label',SORT_NATURAL)->values();
            elseif($filter==='student')$choices[$filter]=(clone $query)->orderBy('name_kh')->orderBy('name_en')->get(['id','student_code','name_kh','name_en'])
                ->map(fn($r)=>['id'=>$r->id,'label'=>implode(' | ',array_filter([$r->student_code,$r->name_kh,$r->name_en]))]);
            else{
                $column='data->'.($filter==='room'?'room_number':$filter);
                $choices[$filter]=(clone $query)->select($column.' as choice')->distinct()->pluck('choice')->filter(fn($v)=>$v!==null&&$v!=='')->sort(SORT_NATURAL)->values();
            }
            $this->applyFilter($query,$filter,$request->input($filter));
        }
        return response()->json($choices)->header('Cache-Control','private, no-store');
    }

    public function data(Request $request)
    {
        $view=$request->validate(['view'=>['nullable',Rule::in(['results','graph','statistics'])]])['view']??'results';
        if($view!=='results')abort_unless(Access::allows($request->user(),'results-'.$view),403);
        $records=$this->records($request);
        $summary=$this->counts($records);
        $summary['absent_languages']=collect(['english','khmer'])->mapWithKeys(fn($language)=>[$language=>$records->where('outcome','absent')->where('language',$language)->count()])->all();
        $summary['candidate_languages']=collect(['english','khmer'])->mapWithKeys(function($language)use($records){
            $total=$records->where('language',$language)->count();
            return [$language=>['total'=>$total,'percentage'=>$this->percentage($total,$records->count())]];
        })->all();
        $attended=$records->where('outcome','!=','absent');
        $summary['passed_percentage']=$this->percentage($summary['passed'],$attended->count());
        $summary['failed_percentage']=$this->percentage($summary['failed'],$attended->count());
        $summary['failed_languages']=collect(['english','khmer'])->mapWithKeys(function($language)use($attended){
            $candidates=$attended->where('language',$language);
            $failed=$candidates->where('outcome','failed')->count();
            return [$language=>['total'=>$failed,'percentage'=>$this->percentage($failed,$candidates->count())]];
        })->all();
        $summary['passed_languages']=collect(['english','khmer'])->mapWithKeys(function($language)use($attended){
            $candidates=$attended->where('language',$language);$passed=$candidates->where('outcome','passed');
            $female=$passed->where('female',true)->count();$femaleCandidates=$candidates->where('female',true)->count();
            return [$language=>['total'=>$passed->count(),'female'=>$female,'candidates'=>$candidates->count(),'female_candidates'=>$femaleCandidates,
                'percentage'=>$this->percentage($passed->count(),$candidates->count()),'female_percentage'=>$this->percentage($female,$femaleCandidates)]];
        })->all();
        $summary['grades']=collect(['ធ្លាក់','មធ្យម','ល្អបង្គួរ','ល្អ'])->map(fn($grade)=>['label'=>$grade,'total'=>$records->where('letter_grade',$grade)->count()])->all();
        $size=(int)$request->input('per_page',25);$page=max(1,min($request->integer('page',1),(int)ceil(max(1,$records->count())/$size)));
        $response=['summary'=>$summary];
        if($view==='results')$response['records']=(new LengthAwarePaginator($records->forPage($page,$size)->values(),$records->count(),$size,$page))->toArray();
        else{
            $response['campuses']=$records->groupBy('campus_id')->map(fn($group)=>['label'=>$group->first()['campus']]+$this->counts($group))->sortBy('label',SORT_NATURAL)->values();
            $response['centers']=$records->groupBy('exam_center')->map(fn($group,$label)=>['label'=>$label?:'Not assigned']+$this->counts($group))->sortBy('label',SORT_NATURAL)->values();
        }
        return response()->json($response)->header('Cache-Control','private, no-store');
    }

    private function records(Request $request)
    {
        return $this->query($request)->with('campus')->orderBy('data->exam_center')->get()->sort(function($a,$b){
            return strnatcasecmp($a->data['exam_center']??'',$b->data['exam_center']??'')
                ?:strnatcasecmp($a->data['room_number']??'',$b->data['room_number']??'')
                ?:strnatcasecmp($a->data['seat_number']??'',$b->data['seat_number']??'')?:($a->id<=>$b->id);
        })->map(function($record){
            $d=$record->data;$score=G9ScoreSummary::calculate($d);
            $outcome=$record->attendance_status==='absent'?'absent':(!$score['complete']?'pending':($score['total']<260?'failed':'passed'));
            return ['id'=>$record->id,'student_code'=>$record->student_code,'name_kh'=>$record->name_kh,'name_en'=>$record->name_en,
                'photo_url'=>$record->photo_path?asset('storage/'.$record->photo_path):null,
                'gender'=>$d['gender']??'','female'=>in_array(mb_strtolower(trim($d['gender']??'')),['female','f','ស្រី'],true),
                'campus_id'=>$record->campus_id,'campus'=>$record->campus?->campus_name_en??'Unassigned','grade'=>$d['grade']??'',
                'exam_center'=>$d['exam_center']??'','exam_language'=>$d['exam_language']??'','language'=>$score['language'],'room'=>$d['room_number']??'','seat'=>$d['seat_number']??'',
                'eoy'=>$score['eoy'],'total'=>$score['complete']?$score['total']:null,'outcome'=>$outcome,
                'result'=>$outcome==='absent'?'អវត្តមាន':$score['result'],'letter_grade'=>$outcome==='absent'?null:$score['grade'],
                'attendance_reason'=>$record->attendance_reason,
                'dob'=>$d['date_of_birth']??'','school'=>$d['school_name']??'','exam_date'=>$d['exam_date']??'',
                'scores'=>array_intersect_key($d,\App\Support\G9ScoringReport::SUBJECTS)];
        })->filter(fn($r)=>!$request->filled('result')||$r['outcome']===$request->input('result'))->values();
    }

    public function report(Request $request,string $type,string $format,G9ResultReport $reports)
    {
        abort_unless(isset(G9ResultReport::TITLES[$type])&&in_array($format,['print','excel','pdf'],true),404);
        $records=$this->records($request);
        if($type!=='list'){
            $ids=Access::campusIds($request->user(),'results-statistics');abort_unless($ids,403);
            $records=$records->whereIn('campus_id',$ids)->values();
        }
        abort_if($records->isEmpty(),422,'No candidates match the selected filters.');
        $setting=DB::table('tb_g9_exam_year_setting')->where('academic_year_id',$request->integer('academic_year'))->first();
        $pages=$reports->pages($records,json_decode($setting?->data??'{}',true),$type,AcademicYear::findOrFail($request->integer('academic_year'))->academic_year);
        if($format==='print')return response()->view('g9-national-exams.result-report',compact('pages','type'))->header('Cache-Control','private, no-store');
        $bytes=$format==='pdf'?$reports->pdf($pages,$type):$reports->xlsx($pages,$type);
        return response($bytes)->header('Content-Type',$format==='pdf'?'application/pdf':'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition','attachment; filename="g9-result-'.$type.'.'.($format==='pdf'?'pdf':'xlsx').'"')->header('Cache-Control','private, no-store');
    }

    private function percentage(int $count,int $total): ?float
    {
        return $total?round($count*100/$total,2):null;
    }

    private function counts($records): array
    {
        $counts=['total'=>$records->count(),'female'=>$records->where('female',true)->count()];
        foreach(['passed','failed','pending','absent'] as $outcome){
            $group=$records->where('outcome',$outcome);$counts[$outcome]=$group->count();$counts[$outcome.'_female']=$group->where('female',true)->count();
        }
        $completed=$counts['passed']+$counts['failed'];
        return $counts+['completed'=>$completed,'pass_rate'=>$completed?round($counts['passed']*100/$completed,2):null];
    }
}
