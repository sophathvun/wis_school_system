<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_gep_student', function(Blueprint $t){
            $t->unsignedBigInteger('student_id')->nullable()->change();
            $t->string('student_identifier',30)->nullable()->unique();
            $t->json('profile')->nullable();
        });
        // Preserve existing GEP registrations as independent snapshots. WIS rows are read only.
        DB::table('tb_gep_student')->orderBy('id')->chunkById(100,function($members){
            foreach($members as $member){
                $student=DB::table('tb_student')->where('id',$member->student_id)->first();
                $profile=$student?array_intersect_key((array)$student,array_flip(\App\Support\GepStudentProfile::FIELDS)):[];
                $profile=\App\Support\GepStudentProfile::normalize($profile);
                DB::table('tb_gep_student')->where('id',$member->id)->update(['profile'=>json_encode($profile,JSON_THROW_ON_ERROR),'student_identifier'=>'GEP-'.str_pad((string)$member->id,6,'0',STR_PAD_LEFT)]);
            }
        });
    }

    public function down(): void
    {
        // Keep the nullable WIS reference so independent GEP students are never deleted on rollback.
        Schema::table('tb_gep_student',fn(Blueprint $t)=>$t->dropUnique(['student_identifier']));
        Schema::table('tb_gep_student',fn(Blueprint $t)=>$t->dropColumn(['profile','student_identifier']));
    }
};
