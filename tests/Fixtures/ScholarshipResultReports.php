<?php

use App\Models\ScholarshipExam;

function scholarshipResultReportExam(int $perGrade=8): ScholarshipExam
{
    $exam=scholarshipSeatReportExam(1);$exam->candidates()->delete();$workflow=app(\App\Services\ScholarshipWorkflow::class);
    foreach([1,2,3] as $grade){
        $subjects=$workflow->applicableSubjects($exam,$grade);$candidates=[];
        foreach(range(1,$perGrade) as $number){
            $candidate=$exam->candidates()->create(['grade_number'=>$grade,'campus_id'=>$number%2?1:2,'class_id'=>1,'class_rank'=>1,
                'exam_center_id'=>$number%2?1:2,'exam_center_name'=>$number%2?'North Exam Center':'South Exam Center',
                'room_number'=>$grade,'room_seat'=>$number,'grade_exam_number'=>$number,'candidate_code'=>'TEST-'.$grade.'-'.$number,
                'reviewed_at'=>now(),'snapshot'=>['student_code'=>'ST-'.$grade.'-'.$number,'name_en'=>'GRADE '.$grade.' STUDENT '.sprintf('%02d',$number),
                    'name_kh'=>'សិស្ស','gender'=>$number%2?'M':'F','class'=>'A / III','campus'=>$number%2?'BCH':'BKK']]);
            foreach($subjects as $subject)$candidate->scores()->create(['subject_id'=>$subject->id,'score'=>100-$number]);
            $candidates[]=$candidate;
        }
        foreach($workflow->awardSlots($exam,$grade)->take($perGrade)->values() as $index=>$slot)
            $exam->awards()->create(['grade_number'=>$grade,'slot'=>$slot['key'],'candidate_id'=>$candidates[$perGrade-1-$index]->id,'award_percent'=>$slot['percent']]);
    }
    return $exam->fresh();
}
