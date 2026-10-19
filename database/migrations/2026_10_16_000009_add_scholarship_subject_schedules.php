<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_scholarship_subject',function(Blueprint $t){$t->date('exam_date')->nullable();$t->time('starts_at')->nullable();$t->time('ends_at')->nullable();});
        Schema::table('tb_scholarship_exam',fn(Blueprint $t)=>$t->json('subject_schedule_overrides')->nullable());
        // Preserve the old session timetable for each grade's actual subject order.
        foreach(\App\Models\ScholarshipExam::cursor() as $exam){
            $sessions=$exam->examSessions()->get()->keyBy('number');if($sessions->isEmpty())continue;$overrides=[];
            foreach(range(1,12) as $grade)foreach(app(\App\Support\ScholarshipSubjectOrder::class)->forGrade($exam,$grade) as $index=>$subject){
                $session=$sessions->get($index+1);if(!$session)continue;
                $overrides[$grade][$subject->id]=['exam_date'=>$session->exam_date->format('Y-m-d'),'starts_at'=>substr($session->starts_at,0,5),'ends_at'=>substr($session->ends_at,0,5)];
            }
            foreach($exam->subjects as $subject){
                $timings=[];foreach($overrides as $grade=>$rows)if(isset($rows[$subject->id]))$timings[]=$rows[$subject->id];
                if(!$timings)continue;$default=$timings[0];$subject->update($default);
                foreach($overrides as $grade=>&$rows)if(($rows[$subject->id]??null)===$default)unset($rows[$subject->id]);unset($rows);
            }
            $exam->forceFill(['subject_schedule_overrides'=>array_filter($overrides)])->save();
        }
    }
    public function down(): void
    {
        Schema::table('tb_scholarship_subject',fn(Blueprint $t)=>$t->dropColumn(['exam_date','starts_at','ends_at']));
        Schema::table('tb_scholarship_exam',fn(Blueprint $t)=>$t->dropColumn('subject_schedule_overrides'));
    }
};
