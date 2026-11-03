<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_daily_registers',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('academic_year_id');$t->unsignedBigInteger('campus_id');$t->unsignedBigInteger('class_id');$t->date('attendance_date');$t->unsignedInteger('version')->default(0);$t->unsignedBigInteger('updated_by');$t->timestamps();
            $t->unique(['academic_year_id','campus_id','class_id','attendance_date'],'daily_class_register_unique');
        });
        Schema::create('attendance_daily_marks',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('register_id');$t->unsignedBigInteger('academic_year_id');$t->unsignedBigInteger('student_id');$t->unsignedBigInteger('enrollment_id');$t->date('attendance_date');$t->string('status',2);$t->unsignedSmallInteger('missed_sessions')->default(0);$t->string('remarks',500)->nullable();$t->timestamps();
            $t->unique(['academic_year_id','student_id','attendance_date'],'daily_student_date_unique');
        });
        Schema::create('attendance_daily_events',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('register_id');$t->unsignedBigInteger('user_id');$t->json('details');$t->timestamps();
        });
    }
    public function down(): void
    {
        foreach(['attendance_daily_events','attendance_daily_marks','attendance_daily_registers'] as $table)Schema::dropIfExists($table);
    }
};
