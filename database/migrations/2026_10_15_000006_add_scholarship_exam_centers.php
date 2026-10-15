<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_scholarship_exam_center',function(Blueprint $table){
            $table->id();$table->foreignId('exam_id')->constrained('tb_scholarship_exam')->cascadeOnDelete();
            $table->string('name',160);$table->unsignedSmallInteger('position')->default(0);$table->timestamps();
        });
        Schema::create('tb_scholarship_center_campus',function(Blueprint $table){
            $table->foreignId('exam_id')->constrained('tb_scholarship_exam')->cascadeOnDelete();
            $table->foreignId('exam_center_id')->constrained('tb_scholarship_exam_center')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('tb_school_info')->restrictOnDelete();
            $table->primary(['exam_id','campus_id'],'scholarship_center_campus_primary');
        });
        Schema::table('tb_scholarship_candidate',function(Blueprint $table){
            $table->foreignId('exam_center_id')->nullable()->constrained('tb_scholarship_exam_center')->nullOnDelete();
            $table->string('exam_center_name',160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tb_scholarship_candidate',function(Blueprint $table){$table->dropConstrainedForeignId('exam_center_id');$table->dropColumn('exam_center_name');});
        Schema::dropIfExists('tb_scholarship_center_campus');Schema::dropIfExists('tb_scholarship_exam_center');
    }
};
