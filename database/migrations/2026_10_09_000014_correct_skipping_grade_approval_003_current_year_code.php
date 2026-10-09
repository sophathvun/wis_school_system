<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceNumber('SG2728-003', 'SG2627-003');
    }

    public function down(): void
    {
        $this->replaceNumber('SG2627-003', 'SG2728-003');
    }

    private function replaceNumber(string $from, string $to): void
    {
        DB::transaction(function () use ($from, $to) {
            $record=DB::table('tb_student_skipping_grade')->where('id',3)
                ->where('status','approved')->where('reference_number',$from)->lockForUpdate()->first();
            if (!$record) return;
            $source=DB::table('tb_academic_year')->where('id',$record->academic_year_id)->first();
            $target=DB::table('tb_academic_year')->where('id',$record->target_academic_year_id)->first();
            if ($source?->academic_year!=='2025-2026' || $target?->academic_year!=='2026-2027'
                || trim((string)$source->academic_year_code)!=='2627'
                || trim((string)$target->academic_year_code)!=='2728') return;
            if (DB::table('tb_student_skipping_grade')->where('reference_number',$to)->exists()) {
                throw new RuntimeException('Cannot correct approval 003: '.$to.' is already in use.');
            }
            DB::table('tb_student_skipping_grade')->where('id',$record->id)->update([
                'reference_number'=>$to,'updated_at'=>now(),
            ]);
            DB::table('tb_student_enrollment_history')->where('student_id',$record->student_id)
                ->whereIn('enrollment_id',[$record->enrollment_id,$record->target_enrollment_id])
                ->whereIn('action_type',['grade_skipping_from','grade_skipping'])
                ->where('reason','Approved grade skipping: '.$from)
                ->update(['reason'=>'Approved grade skipping: '.$to,'updated_at'=>now()]);
        });
    }
};
