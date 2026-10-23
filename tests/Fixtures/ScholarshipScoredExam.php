<?php

function scoredScholarshipFixture(bool $secondCampus=false): array
{
    $user=answerSheetUser();$workflow=app(\App\Services\ScholarshipWorkflow::class);$workflow->saveRank($user,\App\Models\StudentEnrollment::find(1),1,99);
    if($secondCampus)$workflow->saveRank($user,\App\Models\StudentEnrollment::find(3),1,98);
    $exam=$workflow->saveExam($user,null,['academic_year_id'=>1,'exam_date'=>'2026-11-20','room_capacity'=>2,'version'=>1,
        'subjects'=>[['name'=>'English','grade_from'=>1,'grade_to'=>6,'max_score'=>100,'weight'=>1]],
        'exam_centers'=>$secondCampus?[['key'=>'north','name'=>'North Exam Center'],['key'=>'south','name'=>'BKK Center']]:[['key'=>'north','name'=>'North Exam Center']],
        'center_assignments'=>$secondCampus?[1=>'north',2=>'south']:[1=>'north']]);
    $workflow->lockPlan($user,$exam,$workflow->plan($exam)['token']);$exam=$exam->fresh();
    foreach(['score-entry','score-reviewer'] as $role)$exam->committee()->create(['user_id'=>2,'role'=>$role]);
    $sheets=app(\App\Services\ScholarshipAnswerSheets::class);$paper=$sheets->savePaper($user,$exam,['subject_id'=>$exam->subjects()->first()->id,'grade'=>3,
        'paper_version'=>'A','question_count'=>20,'choice_style'=>'letters','choice_count'=>5,'points'=>5,'version'=>0]);
    $sheets->saveKey($user,$paper,array_fill(0,20,'A'),$paper->version);$sheets->approveKey($user,$paper->fresh(),$paper->fresh()->version);$paper=$paper->fresh();
    $sheet=$sheets->issue($user,$paper,[$exam->candidates()->first()->id])->first();$scan=answerSheetUpload($user,$exam,$paper,$sheet,array_fill(1,20,['A']));
    $sheets->verify($user,$scan,$scan->detected,$scan->candidate_version,false,'');$workflow->review($user,$exam,$sheet->candidate->fresh(),$sheet->candidate->fresh()->version);
    return [$user,$workflow,$exam->fresh(),$paper,$sheet,$scan->fresh()];
}
