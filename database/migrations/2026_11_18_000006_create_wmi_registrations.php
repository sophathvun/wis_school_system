<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wmi_registrations',function(Blueprint $t){
            $t->id();$t->string('reference',45)->unique();
            $t->foreignId('wmi_year_id')->constrained('wmi_years')->restrictOnDelete();
            $t->foreignId('wmi_exam_center_id')->constrained('wmi_exam_centers')->restrictOnDelete();
            $t->foreignId('wmi_school_id')->nullable()->constrained('wmi_schools')->restrictOnDelete();
            $t->string('student_source',10);
            $t->foreignId('enrollment_id')->nullable()->constrained('tb_student_enrollment')->restrictOnDelete();
            $t->string('student_code')->nullable();$t->string('student_name');$t->string('student_name_kh')->nullable();
            $t->date('date_of_birth')->nullable();$t->string('gender',10);$t->string('current_grade');$t->string('grade_contest',3);
            $t->string('campus_study');$t->string('campus_registered');
            $t->foreignId('registrar_campus_id')->nullable()->constrained('tb_school_info')->restrictOnDelete();
            $t->string('phone_number',40)->nullable();$t->string('telegram_number',40)->nullable();$t->string('email_address')->nullable();
            $t->string('wmi_id_number',80)->nullable();$t->unsignedInteger('room_no')->nullable();$t->unsignedInteger('seat_no')->nullable();
            $t->text('remark')->nullable();$t->unsignedBigInteger('registered_by')->nullable();$t->timestamps();
            $t->unique(['wmi_year_id','enrollment_id']);$t->unique(['wmi_year_id','wmi_id_number']);
            $t->unique(['wmi_exam_center_id','room_no','seat_no']);
            $t->index(['wmi_year_id','student_name']);
        });
    }
    public function down(): void { Schema::dropIfExists('wmi_registrations'); }
};
