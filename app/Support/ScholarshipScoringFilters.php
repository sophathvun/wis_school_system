<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ScholarshipScoringFilters
{
    /** Options and rows must both come from the user's authorized scoring assignments. */
    public function apply(Request $request, Collection $candidates): array
    {
        $values=$request->validate([
            'score_center'=>['nullable','integer','min:0'],
            'score_room'=>['nullable','integer','min:1'],
            'score_student'=>['nullable','integer','min:1'],
        ]);
        $center=$request->filled('score_center')?(int)$values['score_center']:null;
        $room=$request->filled('score_room')?(int)$values['score_room']:null;
        $student=$request->filled('score_student')?(int)$values['score_student']:null;
        $centers=$candidates->groupBy(fn($candidate)=>(int)($candidate->exam_center_id??0))
            ->map(fn($group,$id)=>['id'=>(int)$id,'label'=>$group->first()->exam_center_name?:'Main Exam Center'])->sortBy('label',SORT_NATURAL|SORT_FLAG_CASE)->values();
        if($center!==null&&!$centers->contains('id',$center))
            throw ValidationException::withMessages(['score_center'=>'Select an available exam center.']);
        if($room!==null&&$center===null)
            throw ValidationException::withMessages(['score_center'=>'Select an exam center before choosing a room.']);
        $withinCenter=$center===null?$candidates:$candidates->filter(fn($candidate)=>(int)($candidate->exam_center_id??0)===$center);
        $rooms=$center===null?collect():$withinCenter->whereNotNull('room_number')->groupBy('room_number')
            ->map(fn($group,$number)=>['id'=>(int)$number,'label'=>'Room '.$number.' - Grade '.$group->pluck('grade_number')->unique()->sort()->implode(', ')])->sortBy('id')->values();
        if($room!==null&&!$rooms->contains('id',$room))
            throw ValidationException::withMessages(['score_room'=>'Select a room belonging to the selected exam center.']);
        $withinRoom=$room===null?$withinCenter:$withinCenter->where('room_number',$room);
        $students=$room===null?collect():$withinRoom->sortBy('room_seat')->map(fn($candidate)=>[
            'id'=>(int)$candidate->id,'label'=>($candidate->room_seat??'Pending').' - '.($candidate->snapshot['name_en']??''),
        ])->values();
        if($student!==null&&($room===null||!$students->contains('id',$student)))
            throw ValidationException::withMessages(['score_student'=>'Select a student belonging to the selected exam center and room.']);
        $rows=($student===null?$withinRoom:$withinRoom->where('id',$student))->values();
        return ['rows'=>$rows,'filters'=>compact('center','room','student'),'options'=>compact('centers','rooms','students')];
    }
}
