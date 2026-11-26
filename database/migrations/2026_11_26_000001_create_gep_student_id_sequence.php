<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_gep_student_id_sequence',function(Blueprint $table){
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedInteger('last_number');
        });
        DB::table('tb_gep_student_id_sequence')->insert(['id'=>1,'last_number'=>190000]);
    }
    public function down(): void { Schema::dropIfExists('tb_gep_student_id_sequence'); }
};
