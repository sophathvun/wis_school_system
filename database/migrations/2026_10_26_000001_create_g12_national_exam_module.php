<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_g12_exam_center',function(Blueprint $t){
            $t->id();$t->string('source_id',80)->nullable();$t->string('name');$t->string('name_key')->unique();$t->boolean('active')->default(true);$t->timestamps();
        });
        Schema::create('tb_g12_exam_record',function(Blueprint $t){
            $t->id();$t->string('academic_year',9);$t->string('student_code',80);$t->unsignedBigInteger('student_id')->nullable()->index();
            $t->unsignedBigInteger('campus_id')->index();$t->unsignedBigInteger('center_id')->nullable()->index();
            $t->string('name_en');$t->string('name_kh')->nullable();$t->string('grade',80)->nullable();$t->string('overall_grade',40)->nullable();
            $t->string('result_status',40)->default('pending');$t->string('photo_filename')->nullable();$t->string('photo_path',700)->nullable();
            $t->string('document_folder',220);$t->json('data');$t->unsignedInteger('version')->default(1);$t->timestamps();
            $t->unique(['academic_year','student_code'],'g12_exam_student_year_unique');
        });
        Schema::create('tb_g12_exam_certificate',function(Blueprint $t){
            $t->id();$t->foreignId('record_id')->constrained('tb_g12_exam_record')->restrictOnDelete();$t->string('type',10);
            $t->string('status',30)->default('awaiting');$t->string('number',100)->nullable();$t->date('available_date')->nullable();
            $t->date('collected_date')->nullable();$t->string('recipient')->nullable();$t->unsignedBigInteger('recorded_by')->nullable();
            $t->boolean('managed')->default(false);$t->string('imported_status')->nullable();$t->unsignedInteger('version')->default(1);$t->timestamps();
            $t->unique(['record_id','type']);
        });
        Schema::create('tb_g12_exam_file',function(Blueprint $t){
            $t->id();$t->foreignId('record_id')->constrained('tb_g12_exam_record')->restrictOnDelete();$t->string('kind',40);
            $t->string('path',700)->unique();$t->string('original_name');$t->string('mime',100);$t->unsignedBigInteger('size');
            $t->string('sha256',64);$t->unsignedBigInteger('uploaded_by');$t->timestamps();
        });
        Schema::create('tb_g12_exam_import',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('user_id')->index();$t->string('kind',20);$t->string('filename');$t->string('source_path',700)->nullable();
            $t->string('status',20)->default('preview');$t->json('payload');$t->json('summary')->nullable();$t->timestamp('expires_at');$t->timestamp('confirmed_at')->nullable();$t->timestamps();
        });
        Schema::create('tb_g12_exam_event',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('record_id')->nullable()->index();$t->unsignedBigInteger('user_id');$t->string('action',80);$t->json('details');$t->timestamps();
        });
        \App\Support\G12NationalExamPermissions::registerMissing();
    }
    public function down(): void
    {foreach(['event','import','file','certificate','record','center'] as $name)Schema::dropIfExists('tb_g12_exam_'.$name);}
};
