<?php

namespace App\Support;

use App\Models\{ScholarshipExam,User};
use App\Services\ScholarshipWorkflow;
use Illuminate\Support\{Arr,Collection};

/** A score-free projection shared by the published view and all its exports. */
class ScholarshipPublishedResults
{
    public function rows(User $user,ScholarshipExam $exam): Collection
    {
        abort_unless(ScholarshipPermissions::allows($user,'tab-scholarship-result') && $exam->published_at,403);
        $workflow=app(ScholarshipWorkflow::class);
        $candidates=$workflow->candidates($user,$exam)->select([
            'id','exam_id','student_id','student_enrollment_id','campus_id','grade_number','exam_center_id',
            'exam_center_name','room_number','room_seat','grade_exam_number','candidate_code','snapshot','award_percent',
        ])->with(['student:id,photo_path','enrollment.grade','enrollment.schoolClass','enrollment.schoolGroup','enrollment.session'])->get();
        $awards=$exam->awards()->whereIn('candidate_id',$candidates->modelKeys())->get(['candidate_id','grade_number','slot','award_percent'])->keyBy('candidate_id');
        $slots=$candidates->pluck('grade_number')->unique()->mapWithKeys(fn($grade)=>[$grade=>$workflow->awardSlots($exam,(int)$grade)->keyBy('key')]);
        return $candidates->map(function($candidate)use($exam,$awards,$slots){
            $snapshot=$candidate->snapshot;
            if($exam->isHistorical()){
                $category=$snapshot['historical_award_category']??'';
                $label=match($category){'total_1'=>'1st Place','total_2'=>'2nd Place','total_3'=>'3rd Place',
                    'subject'=>filled($snapshot['historical_award_subject']??null)?'Best in '.$snapshot['historical_award_subject']:'',default=>$category};
                $percent=$candidate->award_percent;
                $order=match($category){'total_1'=>0,'total_2'=>1,'total_3'=>2,default=>3};
            }else{
                $award=$awards->get($candidate->id);$gradeSlots=$slots->get($candidate->grade_number,collect());
                $slot=$award?$gradeSlots->get($award->slot):null;
                $label=$slot?preg_replace('/\s*—\s*Total Score$/u','',$slot['label']):'';
                $percent=$award?->award_percent;$order=$slot?$gradeSlots->keys()->search($award->slot):PHP_INT_MAX;
            }
            if(!filled($label) || $percent===null)return null;
            // Never pass raw snapshots, numeric score ranks, totals or score relations to this view.
            $candidate->snapshot=Arr::only($snapshot,['student_code','name_kh','name_en','gender','gender_kh','campus','class','grade_class']);
            return ['candidate'=>$candidate,'award_label'=>$label,'award'=>(float)$percent,'award_order'=>$order,'rank'=>null];
        })->filter()->sort(fn($a,$b)=>($a['candidate']->grade_number<=>$b['candidate']->grade_number)
            ?:($a['award_order']<=>$b['award_order'])?:($a['candidate']->room_seat<=>$b['candidate']->room_seat))->values();
    }
}
