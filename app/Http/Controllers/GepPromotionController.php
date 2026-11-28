<?php

namespace App\Http\Controllers;

use App\Models\{GepEnrollment,GepStudent,GepTermAssignment,GepLevel,GepClass,GepClassType,GepGroup};
use App\Support\GepPromotionPermissions as Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GepPromotionController
{
    private function authorize(Request $request): void
    {
        abort_unless(Access::allows($request->user()),403);
    }

    public function index(Request $request)
    {
        $this->authorize($request);
        $filters=$request->validate(['source_term'=>['nullable','integer'],'campus_id'=>['nullable','integer'],'level_id'=>['nullable','integer'],'class_id'=>['nullable','integer'],'group_id'=>['nullable','integer'],'search'=>['nullable','string','max:100']]);
        $campuses=Access::campuses($request->user());
        $terms=GepTermAssignment::with(['academicYear','term'])->get()->sortByDesc(fn($t)=>sprintf('%s-%06d',$t->academicYear->academic_year,$t->sort_order))->values();
        $sourceTerm=$terms->firstWhere('id',$filters['source_term']??null);
        $targetTerms=$terms->filter(fn($t)=>$sourceTerm && in_array($t->lifecycle_status,['pending','started'],true) && $t->academicYear->status && [$t->academicYear->academic_year,(int)$t->sort_order]>[$sourceTerm->academicYear->academic_year,(int)$sourceTerm->sort_order]);
        $query=GepEnrollment::with(['gepStudent','level','schoolClass','group','campus'])->whereIn('campus_id',$campuses->pluck('id'))->whereIn('enrollment_status',['active','completed']);
        if(empty($filters['source_term']))$query->whereRaw('1=0');
        else $query->where('term_assignment_id',$filters['source_term']);
        // Source options follow actual enrollments, not unrelated global settings.
        // Resolve parents first so stale child selections never leave an empty, invalid filter chain.
        $sourceOptions=[];$resetChildren=false;
        foreach(['campus_id','level_id','class_id','group_id'] as $field){
            $ids=(clone $query)->distinct()->pluck($field);
            $sourceOptions[$field]=match($field){
                'campus_id'=>$campuses->whereIn('id',$ids)->values(),
                'class_id'=>GepClass::whereIn('id',$ids)->orderBy('sort_order')->orderBy('class_name')->get(),
                'level_id'=>GepLevel::whereIn('id',$ids)->orderBy('sort_order')->orderBy('level_name')->get(),
                'group_id'=>GepGroup::whereIn('id',$ids)->orderBy('sort_order')->orderBy('session_name')->get(),
            };
            if($resetChildren || (!empty($filters[$field]) && !$sourceOptions[$field]->contains('id',(int)$filters[$field]))){
                unset($filters[$field]);$resetChildren=true;
            }
            if(!empty($filters[$field]))$query->where($field,$filters[$field]);
        }
        if($search=trim($filters['search']??''))$query->whereHas('gepStudent',fn($q)=>$q->where(fn($q)=>$q->where('student_identifier','like','%'.$search.'%')->orWhere('profile->full_name_en','like','%'.$search.'%')->orWhere('profile->full_name_kh','like','%'.$search.'%')));
        $records=$query->orderBy('gep_student_id')->paginate(50)->withQueryString();
        $canPromote=Access::allows($request->user(),'promote');
        if($request->expectsJson())return response()->json([
            'total'=>$records->total(),
            'students'=>$records->getCollection()->take(8)->map(fn($row)=>[
                'id'=>$row->gepStudent->student_identifier,'name'=>$row->gepStudent->profile['full_name_en']??'Student',
                'name_kh'=>$row->gepStudent->profile['full_name_kh']??'', 'photo'=>$row->gepStudent->photo_url,
                'detail'=>$row->campus->campus_name_en.' · '.$row->level->level_name.' · '.$row->schoolClass->class_name,
            ])->values(),
            'rows'=>view('gep._promotion-rows',compact('records','canPromote','filters'))->render(),
            'pagination'=>$records->links()->toHtml(),
        ]);
        return view('gep.promotions',[
            'filters'=>$filters,'campuses'=>$campuses,'sourceOptions'=>$sourceOptions,'targetCampuses'=>Access::campuses($request->user(),'promote'),'terms'=>$terms,'targetTerms'=>$targetTerms,
            'levels'=>GepLevel::where('status',1)->orderBy('sort_order')->get(),'classes'=>GepClass::where('status',1)->orderBy('sort_order')->get(),
            'types'=>GepClassType::where('status',1)->orderBy('sort_order')->get(),'groups'=>GepGroup::where('status',1)->orderBy('sort_order')->get(),
            'records'=>$records,'canPromote'=>$canPromote,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize($request);
        $data=$request->validate([
            'source_term'=>['required','integer'],'enrollments'=>['required','array','min:1','max:50'],'enrollments.*'=>['required','integer','distinct'],
            'term_assignment_id'=>['required','integer'],'campus_id'=>['required','integer'],'level_id'=>['required','integer'],
            'class_id'=>['required','integer'],'class_type_id'=>['required','integer'],'group_id'=>['required','integer'],
            'enrolled_on'=>['required','date_format:Y-m-d'],'notes'=>['nullable','string','max:2000'],
        ]);
        abort_unless(Access::allows($request->user(),'promote',(int)$data['campus_id']),403);
        abort_unless(Access::campuses($request->user(),'promote')->contains('id',(int)$data['campus_id']),403);
        DB::transaction(function()use($request,$data){
            // Serialize against term lifecycle changes and concurrent promotions of the same students.
            $assignments=GepTermAssignment::with('academicYear')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source=$assignments->get($data['source_term']);$target=$assignments->get($data['term_assignment_id']);
            $fail=fn($message)=>throw ValidationException::withMessages(['promotion'=>$message]);
            if(!$source || !$target || !in_array($target->lifecycle_status,['pending','started'],true))$fail('Choose an upcoming or started destination term.');
            if([$target->academicYear->academic_year,(int)$target->sort_order] <= [$source->academicYear->academic_year,(int)$source->sort_order])$fail('The destination must be later than the source term.');
            if(!$target->academicYear->status || !DB::table('tb_gep_term')->where('id',$target->term_id)->where('status',1)->exists())$fail('The destination academic year and term must be enabled.');
            foreach(['level_id'=>GepLevel::class,'class_id'=>GepClass::class,'class_type_id'=>GepClassType::class,'group_id'=>GepGroup::class] as $key=>$model)
                if(!$model::whereKey($data[$key])->where('status',1)->exists())$fail('Choose enabled destination settings.');
            $rows=GepEnrollment::whereIn('id',$data['enrollments'])->orderBy('gep_student_id')->get();
            if($rows->count()!==count($data['enrollments']))$fail('Some selected enrollments no longer exist. Refresh the list.');
            GepStudent::whereIn('id',$rows->pluck('gep_student_id'))->orderBy('id')->lockForUpdate()->get();
            $rows=GepEnrollment::whereIn('id',$data['enrollments'])->orderBy('id')->lockForUpdate()->get();
            if($rows->count()!==count($data['enrollments']))$fail('Some selected enrollments no longer exist. Refresh the list.');
            foreach($rows as $row){
                abort_unless(Access::allows($request->user(),'promote',(int)$row->campus_id),403);
                if((int)$row->campus_id!==(int)$data['campus_id'])$fail('Promotion must stay within the student’s current campus. Select students from the destination campus. Use Student Transfer to change campuses.');
                if((int)$row->term_assignment_id!==(int)$source->id || !in_array($row->enrollment_status,['active','completed'],true))$fail('Only active or completed students in the source term can be promoted. Refresh the list.');
                if(GepEnrollment::where('gep_student_id',$row->gep_student_id)->where('term_assignment_id',$target->id)->exists())$fail('Student '.$row->gepStudent->student_identifier.' already has an enrollment in the destination term. No students were promoted.');
                $new=GepEnrollment::create([
                    ...collect($data)->only(['term_assignment_id','campus_id','level_id','class_id','class_type_id','group_id','enrolled_on','notes'])->all(),
                    'gep_student_id'=>$row->gep_student_id,'student_type'=>'old','enrollment_status'=>$target->lifecycle_status==='started'?'active':'pending',
                    'created_by'=>$request->user()->id,'updated_by'=>$request->user()->id,'active_slot'=>1,
                ]);
                $before=$row->toArray();
                if($target->lifecycle_status==='started' && $row->enrollment_status==='active'){$row->enrollment_status='completed';$row->updated_by=$request->user()->id;$row->save();}
                foreach([[$row,'promoted_from',$before,[...$row->toArray(),'destination_enrollment_id'=>$new->id]],[$new,'promoted',null,[...$new->toArray(),'source_enrollment_id'=>$row->id]]] as [$record,$action,$old,$after])
                    DB::table('tb_gep_enrollment_history')->insert(['enrollment_id'=>$record->id,'action'=>$action,'before_values'=>$old?json_encode($old):null,'after_values'=>json_encode($after),'changed_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
            }
        });
        return redirect()->route('gep.students.section',['studentSection'=>'promotion','source_term'=>$data['source_term']])->with('promotion_success',count($data['enrollments']).' students promoted successfully.');
    }
}
