<?php

namespace App\Support;

use App\Models\ScholarshipAnswerScan;

class ScholarshipMarkedAnswers
{
    public function __construct(private ScholarshipAnswerSheetLayout $layout){}

    public function data(ScholarshipAnswerScan $scan): array
    {
        $snapshot=$scan->sheet->snapshot;$key=$scan->key;
        $responses=$scan->responses??$scan->detected;
        $absent=$scan->subjectAbsent();
        $points=$key->question_points??array_fill(0,$snapshot['question_count'],(float)$snapshot['points']);
        $rows=[];$totals=['correct'=>0,'incorrect'=>0,'unanswered'=>0,'multiple'=>0,'unclear'=>0];$total=0;
        foreach($this->layout->questions($snapshot['question_count'],$snapshot['choices'],$snapshot['layout_version']??1) as $geometry){
            $index=$geometry['number']-1;$answer=isset($responses[$index])?(string)$responses[$index]:null;
            $correct=(string)$key->answers[$index];$detected=isset($scan->detected[$index])?(string)$scan->detected[$index]:null;
            $flag=$scan->flags[$geometry['number']]??null;
            $status=$answer!==null?($answer===$correct?'correct':'incorrect'):(in_array($flag,['multiple','unclear'],true)?$flag:'unanswered');
            if($absent)$status='absent';
            $earned=$status==='correct'?(float)$points[$index]:0;$total+=$earned;$totals[$status]=($totals[$status]??0)+1;
            $borders=[];
            foreach($geometry['dots'] as $dot){
                if($absent)continue;
                if($dot['label']===$correct)$borders[]=$dot+['state'=>'correct','offset'=>2];
                if($answer!==null&&$dot['label']===$answer&&$answer!==$correct)$borders[]=$dot+['state'=>'incorrect','offset'=>2];
                if($answer===null&&in_array($status,['multiple','unclear'],true)&&($scan->confidence[$index][$dot['label']]??0)>.10)
                    $borders[]=$dot+['state'=>'warning','offset'=>$dot['label']===$correct?5:2];
            }
            $rows[]=['number'=>$geometry['number'],'answer'=>$answer,'correct'=>$correct,'status'=>$status,'points'=>(float)$points[$index],
                'earned'=>$earned,'detected'=>$detected,'flag'=>$flag,'corrected'=>$scan->responses!==null&&$answer!==$detected,'borders'=>$borders];
        }
        return ['key_revision'=>$key->revision,'rows'=>$rows,'totals'=>$totals,'subject_absent'=>$absent,'absence'=>$scan->absence,'total'=>round($total,2),'maximum'=>round(array_sum($points),2),
            'width'=>$this->layout::WIDTH,'height'=>$this->layout::HEIGHT];
    }
}
