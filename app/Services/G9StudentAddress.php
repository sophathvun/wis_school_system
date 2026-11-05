<?php

namespace App\Services;

use App\Models\Student;

class G9StudentAddress
{
    public function students(int $year,int $campus)
    {
        return Student::whereHas('enrollments',fn($query)=>$query->where('academic_year_id',$year)->where('campus_id',$campus));
    }

    /** Resolve all card addresses together; never guess a match from a name or remove ID zeros. */
    public function forCandidates($records): array
    {
        if($records->isEmpty())return [];
        $codes=$records->pluck('student_code')->unique();$manual=$records->map(fn($r)=>$r->data['sis_student_id']??null)->filter()->unique();
        $students=Student::where(fn($q)=>$q->whereIn('student_id',$codes)->orWhereIn('id',$manual))
            ->whereHas('enrollments',fn($q)=>$q->whereIn('academic_year_id',$records->pluck('academic_year_id')->unique())->whereIn('campus_id',$records->pluck('campus_id')->unique()))
            ->with(['enrollments'=>fn($q)=>$q->whereIn('academic_year_id',$records->pluck('academic_year_id')->unique())->whereIn('campus_id',$records->pluck('campus_id')->unique())])
            ->get(['id','student_id','current_address_kh']);
        $addresses=[];
        foreach($records as $record){
            $linked=$record->data['sis_student_id']??null;
            $matches=$students->filter(fn($student)=>($linked?(int)$student->id===(int)$linked:(string)$student->student_id===(string)$record->student_code)
                &&$student->enrollments->contains(fn($enrollment)=>(int)$enrollment->academic_year_id===(int)$record->academic_year_id&&(int)$enrollment->campus_id===(int)$record->campus_id));
            $addresses[$record->id]=$matches->count()===1?trim((string)$matches->first()->current_address_kh):'';
        }
        return $addresses;
    }
}
