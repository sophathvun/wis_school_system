<?php

use App\Support\G9NationalExamPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_g9_exam_certificate_number_setting',function(Blueprint $table){
            $table->id();$table->unsignedBigInteger('academic_year_id')->unique();$table->unsignedInteger('starting_sequence')->default(1);
            $table->json('config');$table->unsignedInteger('version')->default(1);$table->unsignedBigInteger('updated_by');$table->timestamps();
        });
        Schema::create('tb_g9_exam_certificate_number',function(Blueprint $table){
            $table->id();$table->unsignedBigInteger('candidate_id')->unique();$table->unsignedBigInteger('academic_year_id');
            $table->unsignedInteger('sequence');$table->string('number',30)->unique();$table->unsignedBigInteger('generated_by');$table->timestamps();
            $table->unique(['academic_year_id','sequence']);
        });
        Schema::create('tb_g9_exam_certificate_number_event',function(Blueprint $table){
            $table->id();$table->unsignedBigInteger('academic_year_id');$table->unsignedBigInteger('user_id');
            $table->string('action',30);$table->json('details');$table->timestamps();
        });
        G9NationalExamPermissions::register();
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_g9_exam_certificate_number_event');Schema::dropIfExists('tb_g9_exam_certificate_number');Schema::dropIfExists('tb_g9_exam_certificate_number_setting');
    }
};
