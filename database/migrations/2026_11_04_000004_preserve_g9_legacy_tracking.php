<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_g9_exam_candidate',fn(Blueprint $table)=>$table->json('legacy_data')->nullable());
        Schema::create('tb_g9_exam_year_setting',function(Blueprint $table){
            $table->id();$table->unsignedBigInteger('academic_year_id')->unique();$table->json('data');
            $table->unsignedInteger('version')->default(1);$table->unsignedBigInteger('updated_by');$table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tb_g9_exam_year_setting');
        Schema::table('tb_g9_exam_candidate',fn(Blueprint $table)=>$table->dropColumn('legacy_data'));
    }
};
