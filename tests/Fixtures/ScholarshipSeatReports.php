<?php

use App\Models\ScholarshipExam;
use App\Support\ScholarshipSeatReports;

function scholarshipSeatReportExam(int $northTotal=26): ScholarshipExam
{
    $exam=ScholarshipExam::create(['academic_year_id'=>1,'name'=>'2025-2026','exam_date'=>'2026-11-20','room_capacity'=>40,'campus_ids'=>[1,2],'eligible_ranks'=>[],'seats_locked_at'=>now()]);
    foreach(['Khmer','Science','Mathematics','English','General Knowledge'] as $index=>$name)$exam->subjects()->create(['name'=>$name,'grade_from'=>1,'grade_to'=>6,'max_score'=>100,'weight'=>1,'position'=>$index]);
    $exam->subjects()->create(['name'=>'Secondary Only','grade_from'=>7,'grade_to'=>12,'max_score'=>100,'weight'=>1,'position'=>5]);
    foreach([1=>'North Exam Center',2=>'South Exam Center'] as $campus=>$name){
        $center=$exam->centers()->create(['name'=>$name,'position'=>$campus-1]);
        $center->campuses()->attach($campus,['exam_id'=>$exam->id]);
        for($number=1;$number<=($campus===1?$northTotal:2);$number++)$exam->candidates()->create([
            'campus_id'=>$campus,'class_id'=>1,'grade_number'=>$campus,'class_rank'=>1,'exam_center_id'=>$center->id,'exam_center_name'=>$name,
            'seat_scope'=>$center->id,'room_number'=>1,'room_seat'=>$number,'grade_exam_number'=>$number,'candidate_code'=>sprintf('G%02d-%03d',$campus,$number),
            'snapshot'=>['student_code'=>'001'.sprintf('%03d',$number),'name_en'=>($campus===1?'NORTH':'SOUTH').' STUDENT '.sprintf('%02d',$number),'name_kh'=>'ចាន់ សុភា','gender'=>$number%2?'M':'F','campus'=>$campus===1?'BCH':'BKK','class'=>'A / III'],
        ]);
    }
    return $exam->fresh();
}

function scholarshipSeatReportData(ScholarshipExam $exam,string $kind): array
{
    return app(ScholarshipSeatReports::class)->data($exam,app(\App\Services\ScholarshipWorkflow::class)->orderedSeats($exam,$exam->candidates()->get()),$kind);
}
