<?php

namespace App\Support;

use App\Models\ScholarshipExam;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ScholarshipSubjectOrder
{
    private function arrange(Collection $subjects,array $ids): Collection
    {
        $positions=array_flip(array_map('intval',$ids));
        return $subjects->sortBy(fn($subject)=>$positions[$subject->id]??PHP_INT_MAX)->values();
    }

    public function state(ScholarshipExam $exam): array
    {
        $subjects=$exam->subjects;$saved=$exam->subject_orders??[];$bands=[];$grades=[];
        foreach(['primary'=>[1,6],'secondary'=>[7,12]] as $key=>[$from,$to]){
            $eligible=$subjects->filter(fn($subject)=>$subject->grade_from<=$to&&$subject->grade_to>=$from);
            $bands[$key]=$this->arrange($eligible,$saved['bands'][$key]??[])->pluck('id')->map(fn($id)=>(int)$id)->all();
        }
        foreach($saved['grades']??[] as $grade=>$ids){
            if((int)$grade<1||(int)$grade>12)continue;
            $eligible=$subjects->filter(fn($subject)=>$subject->grade_from<=$grade&&$subject->grade_to>=$grade);
            $grades[$grade]=$this->arrange($eligible,$ids)->pluck('id')->map(fn($id)=>(int)$id)->all();
        }
        return ['bands'=>$bands,'grades'=>$grades];
    }

    public function forGrade(ScholarshipExam $exam,int $grade): Collection
    {
        $state=$this->state($exam);
        $subjects=$exam->subjects->filter(fn($subject)=>$subject->grade_from<=$grade&&$subject->grade_to>=$grade);
        return $this->arrange($subjects,$state['grades'][$grade]??$state['bands'][$grade<=6?'primary':'secondary']);
    }

    public function validate(ScholarshipExam $exam,array $bands,array $grades): array
    {
        $state=$this->state($exam);$result=['bands'=>[],'grades'=>[]];
        $permutation=function($ids,array $expected,string $field): array {
            if(!is_array($ids)||!array_is_list($ids)||count(array_unique($ids,SORT_REGULAR))!==count($ids))throw ValidationException::withMessages([$field=>'Include each applicable subject exactly once.']);
            foreach($ids as $id)if(!(is_int($id)||(is_string($id)&&ctype_digit($id)))||(int)$id<1)throw ValidationException::withMessages([$field=>'Choose valid subjects for this exam.']);
            $ids=array_map('intval',$ids);$actual=$ids;$wanted=$expected;sort($actual);sort($wanted);
            if($actual!==$wanted)throw ValidationException::withMessages([$field=>'Include each applicable subject exactly once; subjects from other grades or exams are not allowed.']);
            return $ids;
        };
        foreach(['primary','secondary'] as $band)$result['bands'][$band]=$permutation($bands[$band]??null,$state['bands'][$band],'bands.'.$band);
        foreach($grades as $grade=>$ids){
            if(!preg_match('/^(?:[1-9]|1[0-2])$/',(string)$grade))throw ValidationException::withMessages(['grades'=>'Choose a grade from 1 to 12.']);
            $eligible=$exam->subjects->filter(fn($subject)=>$subject->grade_from<=$grade&&$subject->grade_to>=$grade)->pluck('id')->map(fn($id)=>(int)$id)->all();
            $result['grades'][$grade]=$permutation($ids,$eligible,'grades.'.$grade);
        }
        return $result;
    }
}
