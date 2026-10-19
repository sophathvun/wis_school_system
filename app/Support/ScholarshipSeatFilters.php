<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ScholarshipSeatFilters
{
    public function apply(Request $request, Collection $rows): array
    {
        $values=$request->validate(['seat_center'=>['nullable','integer','min:0'],'seat_room'=>['nullable','array','max:1000'],'seat_room.*'=>['integer','min:1','distinct'],
            'seat_student'=>['nullable','integer','min:1'],'seat_search'=>['nullable','string','max:150']]);
        $center=$request->filled('seat_center')?(int)$values['seat_center']:null;
        $centers=$rows->groupBy(fn($row)=>(int)($row['exam_center_id']??0))->map(fn($group,$id)=>['id'=>(int)$id,'label'=>$group->first()['exam_center_name']?:'Main Exam Center'])->values();
        if($center!==null&&!$centers->contains('id',$center))throw ValidationException::withMessages(['seat_center'=>'Select an available exam center.']);
        $withinCenter=$center===null?$rows:$rows->filter(fn($row)=>(int)($row['exam_center_id']??0)===$center);
        $roomOptions=$center===null?collect():$withinCenter->filter(fn($row)=>(int)$row['room_number']>0)->groupBy('room_number')->map(fn($group,$number)=>['id'=>(int)$number,'label'=>'Room '.$number.' - Grade '.$group->first()['grade_number']])->sortBy('id')->values();
        $rooms=array_map('intval',$values['seat_room']??[]);
        if($rooms&&$center===null)throw ValidationException::withMessages(['seat_center'=>'Select an exam center before choosing rooms.']);
        if(array_diff($rooms,$roomOptions->pluck('id')->all()))throw ValidationException::withMessages(['seat_room'=>'Choose rooms belonging to the selected exam center.']);
        $withinRooms=$rooms?$withinCenter->whereIn('room_number',$rooms):$withinCenter;
        $students=$rooms?$withinRooms->map(fn($row)=>['id'=>(int)$row['id'],'label'=>($row['room_seat']??'Pending').' - '.($row['snapshot']['name_en']??'')])->values():collect();
        $student=$request->filled('seat_student')?(int)$values['seat_student']:null;
        if($student!==null&&!$rooms)throw ValidationException::withMessages(['seat_student'=>'Select a room before choosing a student.']);
        if($student!==null&&!$students->contains('id',$student))throw ValidationException::withMessages(['seat_student'=>'Select a student from the selected center and rooms.']);
        $search=trim($values['seat_search']??'');
        $filtered=$withinRooms->filter(function($row)use($student,$search){
            if($student!==null&&(int)$row['id']!==$student)return false;
            $text=implode(' ',[$row['exam_center_name']??'Main Exam Center','Room '.$row['room_number'],'Seat '.($row['room_seat']??''),$row['snapshot']['name_en']??'',$row['snapshot']['name_kh']??'',$row['snapshot']['campus']??'']);
            return $search===''||mb_stripos($text,$search)!==false;
        })->values();
        return ['rows'=>$filtered,'filters'=>compact('center','rooms','student','search'),'options'=>['centers'=>$centers,'rooms'=>$roomOptions,'students'=>$students]];
    }

    /** Exports deliberately use center/rooms, independently of student/search/page filters. */
    public function report(Request $request, Collection $candidates): Collection
    {
        $data=$request->validate(['report_center'=>['required','integer','min:0'],'report_scope'=>['required','in:all-rooms,selected-rooms'],
            'report_rooms'=>['required_if:report_scope,selected-rooms','array','min:1','max:1000'],'report_rooms.*'=>['integer','min:1','distinct']]);
        $center=(int)$data['report_center'];
        $rows=$candidates->filter(fn($candidate)=>(int)($candidate->exam_center_id??0)===$center);
        if($rows->isEmpty())throw ValidationException::withMessages(['report_center'=>'Select an exam center with confirmed candidates.']);
        if($data['report_scope']==='selected-rooms'){
            $rooms=array_map('intval',$data['report_rooms']);
            if(array_diff($rooms,$rows->pluck('room_number')->unique()->all()))throw ValidationException::withMessages(['report_rooms'=>'Choose rooms belonging to the selected exam center.']);
            $rows=$rows->whereIn('room_number',$rooms);
        }
        return $rows->values();
    }
}
