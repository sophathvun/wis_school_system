<?php
namespace App\Support;

use App\Models\ScholarshipExam;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ScholarshipSubjectSchedule
{
    public function normalize(array $value,string $field): ?array
    {
        $data=[];foreach(['exam_date','starts_at','ends_at'] as $key)$data[$key]=trim((string)($value[$key]??''))?:null;
        // A date can be prepared before times are known. Missing times remain unscheduled.
        if(!$data['starts_at']&&!$data['ends_at']){
            if($data['exam_date'])$this->validateFields($data,['exam_date'=>'date_format:Y-m-d'],$field);return null;
        }
        $this->validateFields($data,['exam_date'=>'required|date_format:Y-m-d','starts_at'=>'required|date_format:H:i','ends_at'=>'required|date_format:H:i'],$field);
        if($data['ends_at']<=$data['starts_at'])throw ValidationException::withMessages([$field.'.ends_at'=>'End time must be after start time.']);return $data;
    }

    private function validateFields(array $data,array $rules,string $prefix): void
    {
        $validator=Validator::make($data,$rules);if(!$validator->fails())return;
        $errors=[];foreach($validator->errors()->messages() as $field=>$messages)$errors[$prefix.'.'.$field]=$messages;
        throw ValidationException::withMessages($errors);
    }

    public function defaults(ScholarshipExam $exam): array
    {
        return $exam->subjects->mapWithKeys(fn($s)=>[$s->id=>['exam_date'=>$s->exam_date?:$exam->exam_date->format('Y-m-d'),'starts_at'=>$s->starts_at?substr($s->starts_at,0,5):'','ends_at'=>$s->ends_at?substr($s->ends_at,0,5):'']])->all();
    }

    public function state(ScholarshipExam $exam): array
    {return ['defaults'=>$this->defaults($exam),'grades'=>$exam->subject_schedule_overrides??[]];}

    public function forSubject(ScholarshipExam $exam,int $grade,int $subjectId): ?array
    {
        $override=$exam->subject_schedule_overrides[$grade][$subjectId]??null;
        if($override)return $override;
        $subject=$exam->subjects->firstWhere('id',$subjectId);
        if($subject?->exam_date&&$subject->starts_at&&$subject->ends_at)return ['exam_date'=>$subject->exam_date,'starts_at'=>substr($subject->starts_at,0,5),'ends_at'=>substr($subject->ends_at,0,5)];
        // Compatibility for old callers and data not yet upgraded; new saves use subject timings.
        if($exam->subject_schedule_overrides===null && !$exam->subjects->contains(fn($s)=>(bool)$s->starts_at)){
            $index=app(ScholarshipSubjectOrder::class)->forGrade($exam,$grade)->search(fn($s)=>$s->id===$subjectId);
            $session=$index===false?null:$exam->examSessions()->where('number',$index+1)->first();
            if($session && $session->starts_at!=='00:00:00' && $session->starts_at!=='00:00')return ['exam_date'=>$session->exam_date->format('Y-m-d'),'starts_at'=>substr($session->starts_at,0,5),'ends_at'=>substr($session->ends_at,0,5)];
        }
        return null;
    }

    public function forSession(ScholarshipExam $exam,int $grade,int $number): ?array
    {
        $subject=app(ScholarshipSubjectOrder::class)->forGrade($exam,$grade)->get($number-1);
        return $subject?$this->forSubject($exam,$grade,$subject->id):null;
    }

    public function validate(ScholarshipExam $exam,array $payload): array
    {
        Validator::make($payload,['defaults'=>'required|array|max:100','grades'=>'present|array|max:12'])->validate();$result=['defaults'=>[],'grades'=>[]];
        $subjects=$exam->subjects->keyBy('id');
        foreach($payload['defaults'] as $id=>$value){
            if(!$subjects->has($id)||!is_array($value))throw ValidationException::withMessages(['schedules.defaults'=>'Choose subjects from this exam.']);
            $result['defaults'][$id]=$this->normalize($value,'schedules.defaults.'.$id);
        }
        if(count($result['defaults'])!==$subjects->count())throw ValidationException::withMessages(['schedules.defaults'=>'Include the schedule for every exam subject.']);
        foreach($payload['grades'] as $grade=>$rows){
            if(!preg_match('/^(?:[1-9]|1[0-2])$/',(string)$grade)||!is_array($rows))throw ValidationException::withMessages(['schedules.grades'=>'Choose grades from 1 to 12.']);
            foreach($rows as $id=>$value){
                $subject=$subjects->get($id);if(!$subject||$subject->grade_from>$grade||$subject->grade_to<$grade||!is_array($value))throw ValidationException::withMessages(['schedules.grades.'.$grade=>'Choose applicable subjects for this grade.']);
                $schedule=$this->normalize($value,'schedules.grades.'.$grade.'.'.$id);
                if(!$schedule)throw ValidationException::withMessages(['schedules.grades.'.$grade.'.'.$id=>'Enter a date, start time and end time, or reset this subject to its default.']);
                $result['grades'][$grade][$id]=$schedule;
            }
        }
        return $result;
    }

    public function save(ScholarshipExam $exam,array $payload): void
    {
        $schedules=$this->validate($exam,$payload);
        foreach($schedules['defaults'] as $id=>$value)$exam->subjects()->whereKey($id)->update($value??['exam_date'=>null,'starts_at'=>null,'ends_at'=>null]);
        $exam->forceFill(['subject_schedule_overrides'=>$schedules['grades']])->save();$exam->unsetRelation('subjects');
        $this->ensureSessions($exam);
        app(\App\Services\ScholarshipInvigilators::class)->validateSchedules($exam);
    }

    public function ensureSessions(ScholarshipExam $exam): void
    {
        $count=collect(range(1,12))->map(fn($grade)=>app(ScholarshipSubjectOrder::class)->forGrade($exam,$grade)->count())->max()??0;
        foreach(range(1,max(1,$count)) as $number)$exam->examSessions()->firstOrCreate(['number'=>$number],['exam_date'=>$exam->exam_date->format('Y-m-d'),'starts_at'=>'00:00','ends_at'=>'00:01']);
    }
}
