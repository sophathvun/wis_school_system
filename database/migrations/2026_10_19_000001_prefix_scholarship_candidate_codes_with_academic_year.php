<?php

use App\Support\ScholarshipCandidateCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void { $this->prefixCodes(true); }
    public function down(): void { $this->prefixCodes(false); }

    private function prefixCodes(bool $addPrefix): void
    {
        DB::transaction(function()use($addPrefix){
            $exams=DB::table('tb_scholarship_exam as exam')
                ->join('tb_academic_year as year','year.id','=','exam.academic_year_id')
                ->whereNull('exam.historical_imported_at')->get(['exam.id','year.academic_year']);
            foreach($exams as $exam){
                $prefix=ScholarshipCandidateCode::yearPrefix($exam->academic_year);
                if($prefix===null)continue;
                DB::table('tb_scholarship_candidate')->where('exam_id',$exam->id)
                    ->whereNotNull('candidate_code')->where('grade_exam_number','>',0)
                    ->orderBy('id')->chunkById(500,function($candidates)use($prefix,$addPrefix){
                        foreach($candidates as $candidate){
                            $legacy=sprintf('G%02d-%03d',$candidate->grade_number,$candidate->grade_exam_number);
                            $prefixed=$prefix.'-'.$legacy;
                            $before=$addPrefix?$legacy:$prefixed;$after=$addPrefix?$prefixed:$legacy;
                            // Only transform this system's generated format; preserve imported/custom codes.
                            DB::table('tb_scholarship_candidate')->where('id',$candidate->id)
                                ->where('candidate_code',$before)->update(['candidate_code'=>$after]);
                        }
                    });
            }
        });
    }
};
