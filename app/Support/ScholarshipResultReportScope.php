<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ScholarshipResultReportScope
{
    public function apply(Request $request,Collection $results): array
    {
        $data=$request->validate([
            'report_center'=>['nullable','integer','min:0'],
            'report_scope'=>['required','in:single-grade,selected-grades,all-grades'],
            'report_grade'=>['required_if:report_scope,single-grade','nullable','integer','between:1,12'],
            'report_grades'=>['required_if:report_scope,selected-grades','nullable','array','min:1','max:12'],
            'report_grades.*'=>['integer','between:1,12','distinct'],
            'report_students'=>['required','in:winners,all'],
            'pdf_mode'=>['nullable','in:combined,separate'],
        ]);
        if($request->filled('report_center')){
            $center=(int)$data['report_center'];
            $results=$results->filter(fn($row)=>(int)($row['candidate']->exam_center_id??0)===$center);
            if($results->isEmpty())throw ValidationException::withMessages(['report_center'=>'Select an available exam center.']);
        }
        $available=$results->pluck('candidate.grade_number')->unique()->sort()->values();
        $grades=match($data['report_scope']){
            'single-grade'=>[(int)$data['report_grade']],
            'selected-grades'=>array_map('intval',$data['report_grades']),
            default=>$available->all(),
        };
        if(array_diff($grades,$available->all()))throw ValidationException::withMessages(['report_grades'=>'Choose grades available in the selected exam center scope.']);
        $results=$results->filter(fn($row)=>in_array($row['candidate']->grade_number,$grades,true));
        if($data['report_students']==='winners')$results=$results->filter(fn($row)=>filled($row['award_label']));
        if($results->isEmpty())throw ValidationException::withMessages(['report_students'=>'No '.($data['report_students']==='winners'?'assigned award winners':'students').' match the selected grades.']);
        return ['rows'=>$results->values(),'pdf_mode'=>$data['pdf_mode']??'combined','students'=>$data['report_students']];
    }
}
