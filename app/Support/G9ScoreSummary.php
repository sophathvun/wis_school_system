<?php

namespace App\Support;

class G9ScoreSummary
{
    public static function calculate(array $data): array
    {
        $language=match(mb_strtolower(trim((string)($data['exam_language']??'')))){
            'english','en','អង់គ្លេស'=>'english','khmer','kh','km','ខ្មែរ'=>'khmer',default=>null,
        };
        $eoy=$data['eoy_average']??null;
        $eoy=$eoy!==null&&$eoy!==''&&is_numeric($eoy)?(int)round((float)$eoy):null;
        $values=['eoy_average'=>$eoy];
        foreach(G9ExamFields::GROUPS['National Examination Scores'] as $field=>$label){
            if($language==='english'&&$field==='score_english')continue;
            $values[$field]=$data[$field]??null;
        }
        $entered=array_filter($values,fn($v)=>$v!==null&&$v!=='');
        $valid=collect($entered)->every(fn($v)=>is_numeric($v)&&is_finite((float)$v)&&(float)$v>=0);
        $units=0;
        foreach($entered as $field=>$value){
            if(!is_numeric($value))continue;
            $score=(int)round((float)$value*1000);
            $units+=$language==='khmer'&&$field==='score_english'?max(0,$score-25000):$score;
        }
        $total=$language&&$valid&&$entered?$units/1000:null;
        $complete=$language&&$valid&&count($entered)===count($values);
        $english=$data['score_english']??null;
        return ['language'=>$language,'eoy'=>$eoy,'total'=>$total,'complete'=>(bool)$complete,
            'english_contribution'=>$language==='english'?0:($language==='khmer'&&$english!==null&&$english!==''&&is_numeric($english)?max(0,(int)round((float)$english*1000)-25000)/1000:null),
            'result'=>$complete?($total<260?'ធ្លាក់':'ជាប់'):null,
            'grade'=>$complete?($total<260?'ធ្លាក់':($total<338?'មធ្យម':($total<416?'ល្អបង្គួរ':'ល្អ'))):null];
    }

    public static function display(mixed $score): string
    {
        return $score===null||$score===''||!is_numeric($score)?'—':rtrim(rtrim(number_format((float)$score,3,'.',''),'0'),'.');
    }
}
