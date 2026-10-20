<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ScholarshipScoreReportScope
{
    /** Only candidates the user may score/review and export belong in these options. */
    public function options(Collection $candidates): Collection
    {
        return $candidates->whereNotNull('room_number')->groupBy(fn($candidate)=>(int)($candidate->exam_center_id??0))
            ->map(fn($group,$id)=>['id'=>(int)$id,'label'=>$group->first()->exam_center_name?:'Main Exam Center',
                'rooms'=>$group->groupBy('room_number')->map(fn($room,$number)=>['id'=>(int)$number,
                    'label'=>'Room '.$number.' - Grade '.$room->pluck('grade_number')->unique()->sort()->implode(', ')])->sortBy('id',SORT_NUMERIC)->values()->all()])
            ->sortBy('label',SORT_NATURAL|SORT_FLAG_CASE)->values();
    }

    public function apply(Request $request,Collection $candidates): Collection
    {
        $data=$request->validate([
            'report_center'=>['required','integer','min:0'],
            'report_scope'=>['required','in:single-room,selected-rooms,all-rooms'],
            'report_room'=>['required_if:report_scope,single-room','nullable','integer','min:1'],
            'report_rooms'=>['required_if:report_scope,selected-rooms','nullable','array','min:1','max:1000'],
            'report_rooms.*'=>['integer','min:1','distinct'],
        ]);
        $rows=$candidates->whereNotNull('room_number')->filter(fn($candidate)=>(int)($candidate->exam_center_id??0)===(int)$data['report_center']);
        if($rows->isEmpty())throw ValidationException::withMessages(['report_center'=>'Select an available exam center with confirmed rooms.']);
        $rooms=match($data['report_scope']){
            'single-room'=>[(int)$data['report_room']],
            'selected-rooms'=>array_map('intval',$data['report_rooms']),
            default=>[],
        };
        if(array_diff($rooms,$rows->pluck('room_number')->unique()->all()))
            throw ValidationException::withMessages([$data['report_scope']==='single-room'?'report_room':'report_rooms'=>'Choose rooms available to you in the selected exam center.']);
        if($rooms)$rows=$rows->whereIn('room_number',$rooms);
        return $rows->sort(fn($left,$right)=>[$left->room_number,$left->room_seat]<=>[$right->room_number,$right->room_seat])->values();
    }
}
