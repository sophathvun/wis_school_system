<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_gep_enrollment',fn(Blueprint $table)=>$table->string('student_type',3)->nullable());
        $seen=[];
        DB::table('tb_gep_enrollment')->orderBy('enrolled_on')->orderBy('id')->chunk(500,function($rows)use(&$seen){
            foreach($rows as $row){
                DB::table('tb_gep_enrollment')->where('id',$row->id)->update(['student_type'=>isset($seen[$row->gep_student_id])?'old':'new']);
                if(!$row->deleted_at)$seen[$row->gep_student_id]=true;
            }
        });
    }
    public function down(): void
    {
        Schema::table('tb_gep_enrollment',fn(Blueprint $table)=>$table->dropColumn('student_type'));
    }
};
