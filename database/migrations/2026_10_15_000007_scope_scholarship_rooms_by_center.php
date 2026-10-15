<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_scholarship_candidate',function(Blueprint $table){
            // Zero scopes historical plans without a center; unlike a nullable index, it retains uniqueness.
            $table->unsignedBigInteger('seat_scope')->default(0);
        });
        DB::table('tb_scholarship_candidate')->update(['seat_scope'=>DB::raw('COALESCE(exam_center_id, 0)')]);
        Schema::table('tb_scholarship_candidate',function(Blueprint $table){
            $table->unique(['exam_id','seat_scope','room_number','room_seat'],'scholarship_candidate_center_seat_unique');
            $table->dropUnique('scholarship_candidate_seat_unique');
        });
        Schema::table('tb_scholarship_committee',function(Blueprint $table){
            $table->foreignId('exam_center_id')->nullable()->constrained('tb_scholarship_exam_center')->cascadeOnDelete();
        });
        // Preserve the original center of existing room duties before room numbers can repeat.
        DB::table('tb_scholarship_committee')->whereNotNull('room_number')->orderBy('id')->chunkById(200,function($members){
            foreach($members as $member){
                $centers=DB::table('tb_scholarship_candidate')->where('exam_id',$member->exam_id)->where('room_number',$member->room_number)->distinct()->pluck('exam_center_id');
                if($centers->count()===1 && $centers->first())DB::table('tb_scholarship_committee')->where('id',$member->id)->update(['exam_center_id'=>$centers->first()]);
            }
        });
    }

    public function down(): void
    {
        // Restoring this index deliberately fails if center-local numbers cannot fit the old constraint.
        Schema::table('tb_scholarship_candidate',function(Blueprint $table){$table->unique(['exam_id','room_number','room_seat'],'scholarship_candidate_seat_unique');});
        Schema::table('tb_scholarship_candidate',function(Blueprint $table){$table->dropUnique('scholarship_candidate_center_seat_unique');$table->dropColumn('seat_scope');});
        Schema::table('tb_scholarship_committee',function(Blueprint $table){$table->dropConstrainedForeignId('exam_center_id');});
    }
};
