<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $subjectOrder = (int) DB::table('tb_placement_test_subject')->max('display_order');
        $topicOrder = (int) DB::table('tb_placement_test_topic')->max('display_order');

        DB::table('tb_placement_test_question')->orderBy('id')->get()->each(function ($question) use (&$subjectOrder, &$topicOrder) {
            if ($question->subject_id && $question->topic_id) {
                return;
            }
            $subjectName = trim((string) $question->subject);
            if ($subjectName === '') {
                return;
            }

            $subject = DB::table('tb_placement_test_subject')->where('name_en', $subjectName)->first();
            if (! $subject) {
                $base = strtoupper(Str::slug($subjectName, '-')) ?: 'SUBJECT-'.$question->id;
                $code = substr($base, 0, 40);
                $suffix = 1;
                while (DB::table('tb_placement_test_subject')->where('code', $code)->exists()) {
                    $code = substr($base, 0, 35).'-'.++$suffix;
                }
                $subjectId = DB::table('tb_placement_test_subject')->insertGetId(['code' => $code, 'name_en' => $subjectName, 'name_kh' => $subjectName, 'display_order' => ++$subjectOrder, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                $subject = DB::table('tb_placement_test_subject')->find($subjectId);
            }
            DB::table('tb_placement_test_subject_grade')->insertOrIgnore(['subject_id' => $subject->id, 'grade_id' => $question->grade_id]);

            $topicName = trim((string) $question->topic) ?: 'General';
            $topic = DB::table('tb_placement_test_topic')->where('subject_id', $subject->id)->where('name_en', $topicName)->first();
            if (! $topic) {
                $base = strtoupper(Str::slug($subject->code.'-'.$topicName, '-')) ?: 'TOPIC-'.$question->id;
                $code = substr($base, 0, 50);
                $suffix = 1;
                while (DB::table('tb_placement_test_topic')->where('code', $code)->exists()) {
                    $code = substr($base, 0, 45).'-'.++$suffix;
                }
                $topicId = DB::table('tb_placement_test_topic')->insertGetId(['code' => $code, 'subject_id' => $subject->id, 'name_en' => $topicName, 'name_kh' => $topicName, 'display_order' => ++$topicOrder, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                $topic = DB::table('tb_placement_test_topic')->find($topicId);
            }
            DB::table('tb_placement_test_topic_grade')->insertOrIgnore(['topic_id' => $topic->id, 'grade_id' => $question->grade_id]);
            DB::table('tb_placement_test_question')->where('id', $question->id)->update(['subject_id' => $subject->id, 'topic_id' => $topic->id]);
        });
    }

    public function down(): void
    {
        DB::table('tb_placement_test_question')->update(['subject_id' => null, 'topic_id' => null]);
    }
};
