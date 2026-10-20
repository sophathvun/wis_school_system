<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ScholarshipResultFilters
{
    /** Filter already-authorized results without recalculating their exam ranks. */
    public function apply(Request $request, Collection $results): array
    {
        $values=$request->validate([
            'result_center'=>['nullable','integer','min:0'],
            'result_grade'=>['nullable','integer','between:1,12'],
            'result_student'=>['nullable','integer','min:1'],
        ]);
        $center=$request->filled('result_center')?(int)$values['result_center']:null;
        $grade=$request->filled('result_grade')?(int)$values['result_grade']:null;
        $student=$request->filled('result_student')?(int)$values['result_student']:null;
        $candidates=$results->pluck('candidate');
        $centers=$candidates->groupBy(fn($candidate)=>(int)($candidate->exam_center_id??0))
            ->map(fn($group,$id)=>['id'=>(int)$id,'label'=>$group->first()->exam_center_name?:'Main Exam Center'])
            ->sortBy('label',SORT_NATURAL|SORT_FLAG_CASE)->values();
        if($center!==null&&!$centers->contains('id',$center))
            throw ValidationException::withMessages(['result_center'=>'Select an available exam center.']);
        $withinCenter=$center===null?$candidates:$candidates->filter(fn($candidate)=>(int)($candidate->exam_center_id??0)===$center);
        $grades=$withinCenter->pluck('grade_number')->unique()->sort()->map(fn($number)=>['id'=>(int)$number,'label'=>'Grade '.$number])->values();
        if($grade!==null&&!$grades->contains('id',$grade))
            throw ValidationException::withMessages(['result_grade'=>'Select an available grade for the selected exam center scope.']);
        $withinGrade=$grade===null?$withinCenter:$withinCenter->where('grade_number',$grade);
        $students=$grade===null?collect():$withinGrade->sortBy(fn($candidate)=>$candidate->room_seat??PHP_INT_MAX)->map(fn($candidate)=>[
            'id'=>(int)$candidate->id,'label'=>($candidate->room_seat??'Pending').' - '.($candidate->snapshot['name_en']??''),
        ])->values();
        if($student!==null&&($grade===null||!$students->contains('id',$student)))
            throw ValidationException::withMessages(['result_student'=>'Select a student belonging to the selected exam center and grade.']);
        $ids=($student===null?$withinGrade:$withinGrade->where('id',$student))->pluck('id');
        return [
            'rows'=>$results->filter(fn($row)=>$ids->contains($row['candidate']->id))->values(),
            'filters'=>compact('center','grade','student'),'options'=>compact('centers','grades','students'),
        ];
    }
}
