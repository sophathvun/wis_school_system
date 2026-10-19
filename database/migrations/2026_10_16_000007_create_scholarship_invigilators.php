<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_scholarship_invigilator',function(Blueprint $t){
            $t->id();$t->foreignId('exam_id')->constrained('tb_scholarship_exam')->cascadeOnDelete();
            $t->string('code',40);$t->string('source',20)->default('external');
            // Deliberately no HRM foreign key: these are independent yearly snapshots.
            $t->string('staff_code',80)->nullable();$t->string('name_en',160);$t->string('gender',20);
            $t->string('campus',120);$t->string('position',160);$t->string('phone',50);
            $t->string('email',160)->nullable();$t->string('organization',160)->nullable();$t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamps();
            $t->unique(['exam_id','code']);$t->unique(['exam_id','staff_code']);
        });
        Schema::create('tb_scholarship_exam_session',function(Blueprint $t){
            $t->id();$t->foreignId('exam_id')->constrained('tb_scholarship_exam')->cascadeOnDelete();
            $t->unsignedSmallInteger('number');$t->date('exam_date');$t->time('starts_at');$t->time('ends_at');$t->timestamps();
            $t->unique(['exam_id','number']);
        });
        Schema::create('tb_scholarship_invigilator_duty',function(Blueprint $t){
            $t->id();$t->foreignId('exam_id')->constrained('tb_scholarship_exam')->cascadeOnDelete();
            $t->foreignId('invigilator_id')->constrained('tb_scholarship_invigilator')->restrictOnDelete();
            $t->foreignId('session_id')->constrained('tb_scholarship_exam_session')->restrictOnDelete();
            $t->foreignId('exam_center_id')->constrained('tb_scholarship_exam_center')->restrictOnDelete();
            $t->unsignedInteger('room_number');$t->timestamps();
            $t->unique(['invigilator_id','session_id'],'scholarship_invigilator_session_unique');
            $t->index(['exam_id','exam_center_id','room_number'],'scholarship_invigilator_room_index');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tb_scholarship_invigilator_duty');
        Schema::dropIfExists('tb_scholarship_exam_session');Schema::dropIfExists('tb_scholarship_invigilator');
    }
};
