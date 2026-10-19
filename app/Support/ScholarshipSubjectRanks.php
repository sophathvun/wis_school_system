<?php

namespace App\Support;

use Illuminate\Support\Collection;

class ScholarshipSubjectRanks
{
    /** Rank each subject across the full grade before display filters are applied. */
    public function forResults(Collection $results): array
    {
        $ranks=[];
        foreach($results->groupBy(fn($row)=>$row['candidate']->grade_number) as $gradeRows){
            $scores=$gradeRows->reject(fn($row)=>$row['candidate']->absent)->flatMap(fn($row)=>
                $row['candidate']->scores->whereNotNull('score')->map(fn($score)=>[
                    'candidate'=>$row['candidate']->id,'subject'=>$score->subject_id,'score'=>(float)$score->score,
                ])
            );
            foreach($scores->groupBy('subject') as $subject=>$subjectScores){
                $position=0;$rank=0;$previous=null;
                foreach($subjectScores->sortByDesc('score',SORT_NUMERIC) as $score){
                    $position++;
                    if($previous===null||$previous!==$score['score'])$rank=$position;
                    $ranks[$score['candidate']][$subject]=$rank;$previous=$score['score'];
                }
            }
        }
        return $ranks;
    }
}
