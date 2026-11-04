<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        foreach(['settings'=>'View G9 academic-year date settings','settings-manage'=>'Edit G9 shared dates (central users only)'] as $action=>$name)
            DB::table('access_permissions')->updateOrInsert(['code'=>'g9-national-exams.'.$action],['module'=>'g9-national-exams','action'=>$action,'name'=>$name,'created_at'=>now(),'updated_at'=>now()]);
        Schema::create('tb_g9_exam_setting_event',function(Blueprint $table){
            $table->id();$table->unsignedBigInteger('academic_year_id');$table->unsignedBigInteger('user_id');$table->string('action');$table->json('details');$table->timestamps();
        });
    }
    public function down(): void {Schema::dropIfExists('tb_g9_exam_setting_event');}
};
