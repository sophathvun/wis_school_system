<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_g9_exam_candidate',function(Blueprint $table){
            $table->id();$table->unsignedBigInteger('academic_year_id');$table->unsignedBigInteger('campus_id');
            $table->string('student_code',80);$table->string('name_kh')->nullable();$table->string('name_en');
            $table->json('data');$table->string('photo_path')->nullable();$table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();$table->unsignedBigInteger('updated_by')->nullable();$table->timestamps();
            $table->unique(['academic_year_id','student_code']);$table->index(['campus_id','academic_year_id']);
        });
        Schema::create('tb_g9_exam_event',function(Blueprint $table){
            $table->id();$table->unsignedBigInteger('candidate_id');$table->unsignedBigInteger('user_id');
            $table->string('action',30);$table->json('details');$table->timestamps();
        });
        foreach(['view'=>'View G9 national exam candidates','create'=>'Add G9 exam candidates','edit'=>'Edit G9 exam candidates'] as $action=>$name)
            DB::table('access_permissions')->updateOrInsert(['code'=>'g9-national-exams.'.$action],['module'=>'g9-national-exams','action'=>$action,'name'=>$name,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function down(): void
    {
        Schema::dropIfExists('tb_g9_exam_event');Schema::dropIfExists('tb_g9_exam_candidate');
    }
};
