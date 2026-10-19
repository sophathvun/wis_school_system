<?php

namespace App\Support;

use App\Models\ScholarshipExam;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Portable subject names/ranges keep archives independent of database subject IDs. */
class ScholarshipTransferSettings
{
    private function fail(string $message): never { throw ValidationException::withMessages(['file'=>$message]); }

    private function references(Collection $subjects,array $ids): array
    {
        return array_map(function($id)use($subjects){
            $subject=$subjects->firstWhere('id',$id);
            return $subjects->where('name',$subject->name)->count()===1?$subject->name:
                ['name'=>$subject->name,'grade_from'=>(int)$subject->grade_from,'grade_to'=>(int)$subject->grade_to];
        },$ids);
    }

    public function exportOrder(ScholarshipExam $exam,int $grade): string
    {
        $state=app(ScholarshipSubjectOrder::class)->state($exam);$band=$grade<=6?'primary':'secondary';
        return json_encode(['band'=>$this->references($exam->subjects,$state['bands'][$band]),
            'grade'=>isset($state['grades'][$grade])?$this->references($exam->subjects,$state['grades'][$grade]):null],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }

    public function parseOrder(ScholarshipExam $exam,int $grade,string $value): ?array
    {
        if($value==='')return null;
        if(mb_strlen($value)>40000)$this->fail('subject_order is too long.');
        try{$payload=json_decode($value,true,8,JSON_THROW_ON_ERROR);}catch(\JsonException){$this->fail('subject_order must be valid JSON, as shown in the sample.');}
        if(!is_array($payload)||array_diff(array_keys($payload),['band','grade'])||!array_key_exists('band',$payload)||!array_key_exists('grade',$payload))$this->fail('subject_order must contain band and grade. Set grade to null to follow the grade band.');
        $state=app(ScholarshipSubjectOrder::class)->state($exam);$band=$grade<=6?'primary':'secondary';
        $resolve=function($items,Collection $eligible): array {
            if(!is_array($items)||!array_is_list($items)||count($items)>100)$this->fail('subject_order must list each applicable subject exactly once.');
            $ids=[];
            foreach($items as $item){
                if(is_string($item))$matches=$eligible->where('name',$item);
                elseif(is_array($item)&&count($item)===3&&isset($item['name'],$item['grade_from'],$item['grade_to'])&&is_string($item['name'])&&is_int($item['grade_from'])&&is_int($item['grade_to']))
                    $matches=$eligible->where('name',$item['name'])->where('grade_from',$item['grade_from'])->where('grade_to',$item['grade_to']);
                else $this->fail('subject_order entries must be subject names or name/grade_from/grade_to objects from the exported sample.');
                if($matches->count()!==1)$this->fail('subject_order contains an unknown, ambiguous, or ineligible subject. Use subject names and grade ranges from Scholarship Settings.');
                $ids[]=(int)$matches->first()->id;
            }
            return $ids;
        };
        $bandSubjects=$exam->subjects->whereIn('id',$state['bands'][$band]);
        $bandIds=$resolve($payload['band'],$bandSubjects);$bands=$state['bands'];$bands[$band]=$bandIds;
        $gradeIds=$payload['grade']===null?null:$resolve($payload['grade'],$exam->subjects->filter(fn($s)=>$s->grade_from<=$grade&&$s->grade_to>=$grade));
        try{app(ScholarshipSubjectOrder::class)->validate($exam,$bands,$gradeIds===null?[]:[$grade=>$gradeIds]);}
        catch(ValidationException $error){$this->fail('subject_order: '.implode(' ',array_merge(...array_values($error->errors()))));}
        return ['band'=>$band,'band_ids'=>$bandIds,'grade_ids'=>$gradeIds];
    }

    public function plan(ScholarshipExam $exam,array $rows,bool $replace): array
    {
        $centers=[];$bands=[];$grades=[];$errors=[];$changes=[];
        foreach($rows as $row){
            $key=mb_strtolower($row['exam_center']);$location=$row['exam_center_location'];
            if($location!==''){
                if(isset($centers[$key])&&$centers[$key]['location']!==$location)$errors[]=['line'=>$row['line'],'student_id'=>$row['student_code'],'message'=>'Conflicting exam_center_location values for the same exam center.'];
                else $centers[$key]=['name'=>$row['exam_center'],'location'=>$location];
            }
            if($row['subject_order']!==null){
                $order=$row['subject_order'];$grade=$row['grade'];
                if(isset($bands[$order['band']])&&$bands[$order['band']]!==$order['band_ids'])$errors[]=['line'=>$row['line'],'student_id'=>$row['student_code'],'message'=>'Conflicting subject_order band defaults in the CSV.'];
                if(array_key_exists($grade,$grades)&&$grades[$grade]!==$order['grade_ids'])$errors[]=['line'=>$row['line'],'student_id'=>$row['student_code'],'message'=>'Conflicting subject_order overrides for Grade '.$grade.'.'];
                $bands[$order['band']]=$order['band_ids'];$grades[$grade]=$order['grade_ids'];
            }
        }
        foreach($centers as $key=>&$center){
            $existing=$exam->centers->first(fn($item)=>mb_strtolower(trim($item->name))===$key);
            $center['name']=$existing?->name??$center['name'];$center['id']=$existing?->id;$center['before']=$existing?->location;
            if($center['before']!==$center['location'])$changes[]=['setting'=>'Exam center location: '.$center['name'],'before'=>$center['before']??'Not configured','after'=>$center['location']];
        }unset($center);
        $current=app(ScholarshipSubjectOrder::class)->state($exam);$order=$current;$hasOrderChanges=false;
        $describe=function(?array $ids)use($exam): string {
            return $ids===null?'Follow Grade Band Order':implode(' → ',array_map(fn($id)=>$exam->subjects->firstWhere('id',$id)->name,$ids));
        };
        foreach($bands as $band=>$ids){
            if($current['bands'][$band]!==$ids){$hasOrderChanges=true;$changes[]=['setting'=>($band==='primary'?'Grades 1–6':'Grades 7–12').' default exam order','before'=>$describe($current['bands'][$band]),'after'=>$describe($ids)];}
            $order['bands'][$band]=$ids;
        }
        foreach($grades as $grade=>$ids){
            $before=$current['grades'][$grade]??null;
            if($before!==$ids){$hasOrderChanges=true;$changes[]=['setting'=>'Grade '.$grade.' exam order','before'=>$describe($before),'after'=>$describe($ids)];}
            if($ids===null)unset($order['grades'][$grade]);else $order['grades'][$grade]=$ids;
        }
        ksort($order['grades']);
        if($changes&&!$replace&&$exam->candidates()->exists())$errors[]=['line'=>0,'student_id'=>'','message'=>'Report settings would change for an existing exam center or grade. Enable Replace Matching Records and review the shared settings changes.'];
        return ['centers'=>$centers,'subject_orders'=>$hasOrderChanges?$order:null,'changes'=>$changes,'errors'=>$errors];
    }
}
