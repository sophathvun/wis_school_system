<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_calendars',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('academic_year_id');$t->unsignedBigInteger('campus_id');
            $t->json('weekdays');$t->json('holidays');$t->unsignedInteger('version')->default(1);$t->unsignedBigInteger('updated_by');$t->timestamps();
            $t->unique(['academic_year_id','campus_id']);
        });
        Schema::create('attendance_leaves',function(Blueprint $t){
            $t->id();foreach(['academic_year_id','campus_id','enrollment_id','student_id','created_by'] as $column)$t->unsignedBigInteger($column);
            $t->unsignedBigInteger('class_id');$t->unsignedBigInteger('grade_id')->nullable();
            $t->date('starts_on');$t->date('ends_on');$t->string('reason',500);$t->string('approved_by',150);
            $t->string('mode',15);$t->json('periods');$t->string('attachment_path')->nullable();$t->string('attachment_name')->nullable();
            $t->string('status',15)->default('approved');$t->unsignedInteger('version')->default(1);$t->timestamps();
            $t->index(['academic_year_id','campus_id','student_id']);
        });
        Schema::create('attendance_leave_days',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('leave_id');$t->unsignedBigInteger('student_id');$t->unsignedBigInteger('academic_year_id');$t->date('attendance_date');
            $t->string('status',15)->default('scheduled');$t->timestamps();$t->index(['attendance_date','status']);$t->unique(['leave_id','attendance_date']);
        });
        Schema::create('attendance_leave_events',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('leave_id')->index();$t->unsignedBigInteger('user_id');$t->string('action',30);$t->json('details');$t->timestamps();
        });
        Schema::table('attendance_daily_marks',fn(Blueprint $t)=>$t->unsignedBigInteger('leave_id')->nullable());
    }
    public function down(): void
    {
        Schema::table('attendance_daily_marks',fn(Blueprint $t)=>$t->dropColumn('leave_id'));
        foreach(['attendance_leave_events','attendance_leave_days','attendance_leaves','attendance_calendars'] as $table)Schema::dropIfExists($table);
    }
};
