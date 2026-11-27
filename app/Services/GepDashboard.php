<?php
namespace App\Services;

use App\Models\{GepAcademicYear,GepTermAssignment,GepEnrollment};
use App\Support\GepEnrollmentPermissions as Access;
use Illuminate\Http\Request;

class GepDashboard
{
    public function data(Request $request): array
    {
        $filters=$request->validate(['academic_year_id'=>'nullable|integer','term_assignment_id'=>'nullable|integer','campus_id'=>'nullable|integer']);
        $user=$request->user();$canView=Access::allows($user,'view');
        if(!$canView)return ['canView'=>false];
        $campuses=Access::campuses($user);$ids=$campuses->pluck('id');
        if(filled($filters['campus_id']??null))abort_unless($ids->contains((int)$filters['campus_id']),403);
        $years=GepAcademicYear::orderByDesc('academic_year')->get();
        $assignments=GepTermAssignment::with(['academicYear','term'])->get()->sort(fn($a,$b)=>strcmp($b->academicYear->academic_year,$a->academicYear->academic_year)?:$b->sort_order<=>$a->sort_order)->values();
        $base=GepEnrollment::whereIn('campus_id',$ids);
        $used=(clone $base)->distinct()->pluck('term_assignment_id');
        $preferred=$assignments->first(fn($a)=>$a->lifecycle_status==='started'&&$used->contains($a->id))
            ??$assignments->first(fn($a)=>$used->contains($a->id))??$assignments->first(fn($a)=>$a->lifecycle_status==='started')??$assignments->first();
        $yearId=$request->has('academic_year_id')?($filters['academic_year_id']??null):$preferred?->academic_year_id;
        if($yearId)abort_unless($years->contains('id',(int)$yearId),422,'Select a valid GEP academic year.');
        $terms=$assignments->filter(fn($a)=>!$yearId||(int)$a->academic_year_id===(int)$yearId)->values();
        $termId=$request->has('term_assignment_id')?($filters['term_assignment_id']??null):($preferred&&(!$yearId||(int)$preferred->academic_year_id===(int)$yearId)?$preferred->id:null);
        $term=$termId?$terms->firstWhere('id',(int)$termId):null;
        abort_if($termId&&!$term,422,'Select a term within the selected academic year.');
        $campusId=$filters['campus_id']??null;
        if($yearId)$base->whereHas('termAssignment',fn($q)=>$q->where('academic_year_id',$yearId));
        if($campusId)$base->where('campus_id',$campusId);
        $trendQuery=clone $base;
        if($termId)$base->where('term_assignment_id',$termId);
        $rows=$base->with(['gepStudent:id,student_identifier,profile','campus:id,campus_name_en','level','group','termAssignment.term'])->orderByDesc('enrolled_on')->orderByDesc('id')->get();
        $people=$rows;$gender=fn($row)=>strtoupper($row->gepStudent->profile['gender']??'');
        $metrics=['enrollments'=>$rows->count(),'students'=>$people->count(),'male'=>$people->filter(fn($r)=>in_array($gender($r),['M','MALE'],true))->count(),'female'=>$people->filter(fn($r)=>in_array($gender($r),['F','FEMALE'],true))->count()];
        foreach(['active','completed','withdrawn','pending','graduated'] as $status)$metrics[$status]=$rows->where('enrollment_status',$status)->count();
        // Sum enrollment records across the selected terms, matching the three status cards.
        $metrics['students']=$metrics['active']+$metrics['completed']+$metrics['withdrawn'];
        $metrics['male']=0;$metrics['female']=0;
        foreach(['active','completed','withdrawn'] as $status){
            $statusPeople=$rows->where('enrollment_status',$status);
            $metrics['male']+=$statusPeople->filter(fn($r)=>in_array($gender($r),['M','MALE'],true))->count();
            $metrics['female']+=$statusPeople->filter(fn($r)=>in_array($gender($r),['F','FEMALE'],true))->count();
        }
        $new=$rows->where('student_type','new');
        $metrics['new']=$new->count();$metrics['new_male']=$new->filter(fn($r)=>in_array($gender($r),['M','MALE'],true))->count();$metrics['new_female']=$new->filter(fn($r)=>in_array($gender($r),['F','FEMALE'],true))->count();
        $metrics['unknown_gender']=$metrics['students']-$metrics['male']-$metrics['female'];
        $chartRows=$rows->where('enrollment_status','!=','withdrawn');
        $chartPeople=$chartRows;
        $genderMetrics=['students'=>$chartPeople->count(),
            'male'=>$chartPeople->filter(fn($r)=>in_array($gender($r),['M','MALE'],true))->count(),
            'female'=>$chartPeople->filter(fn($r)=>in_array($gender($r),['F','FEMALE'],true))->count()];
        $genderMetrics['unknown_gender']=$genderMetrics['students']-$genderMetrics['male']-$genderMetrics['female'];
        $breakdown=fn($relation,$label)=>$rows->groupBy($relation.'_id')->map(fn($items)=>['label'=>$items->first()->$relation?->$label??'Unassigned','total'=>$items->count(),'active'=>$items->where('enrollment_status','active')->count(),'completed'=>$items->where('enrollment_status','completed')->count(),'new'=>$items->where('student_type','new')->count(),'withdrawn'=>$items->where('enrollment_status','withdrawn')->count()])->sortByDesc('total')->values();
        $trend=$trendQuery->where('enrollment_status','!=','withdrawn')->select('term_assignment_id')->selectRaw('COUNT(*) as total')->selectRaw('COUNT(DISTINCT gep_student_id) as students')->groupBy('term_assignment_id')->get()->keyBy('term_assignment_id');
        $trend=$terms->sortBy(fn($a)=>sprintf('%s-%05d',$a->academicYear->academic_year,$a->sort_order))->map(fn($a)=>['label'=>($yearId?'':$a->academicYear->academic_year.' · ').$a->term->term_name,'total'=>(int)($trend[$a->id]->students??0),'selected'=>(int)$a->id===(int)$termId])->values();
        return compact('canView','campuses','years','terms','yearId','termId','term','campusId','metrics','genderMetrics','trend')+[
            'campusRows'=>$breakdown('campus','campus_name_en'),
            'levelRows'=>$chartRows->groupBy('level_id')->sort(function($a,$b){
                $left=$a->first()->level;$right=$b->first()->level;
                return ($left?->sort_order??PHP_INT_MAX)<=>($right?->sort_order??PHP_INT_MAX)
                    ?: strnatcasecmp($left?->level_name??'Unassigned',$right?->level_name??'Unassigned');
            })->map(fn($items)=>['label'=>$items->first()->level?->level_name??'Unassigned','total'=>$items->count()])->values(),
            'recent'=>$rows->take(6),
            'yearLabel'=>$years->firstWhere('id',(int)$yearId)?->academic_year??'All academic years',
            'campusLabel'=>$campuses->firstWhere('id',(int)$campusId)?->campus_name_en??'All permitted campuses',
        ];
    }
}
