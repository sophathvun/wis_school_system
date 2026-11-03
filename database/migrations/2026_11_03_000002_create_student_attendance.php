<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_assignments',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('campus_id');$t->unsignedBigInteger('academic_year_id');$t->unsignedBigInteger('class_id');$t->unsignedBigInteger('teacher_id');
            $t->string('subject',100);$t->unsignedTinyInteger('weekday');$t->time('starts_at');$t->time('ends_at');$t->boolean('active')->default(true);$t->timestamps();
            $t->index(['campus_id','academic_year_id','weekday']);
        });
        Schema::create('attendance_sessions',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('assignment_id');$t->date('attendance_date');$t->string('status',15)->default('draft');$t->unsignedInteger('version')->default(0);
            $t->unsignedBigInteger('updated_by')->nullable();$t->timestamp('submitted_at')->nullable();$t->timestamps();$t->unique(['assignment_id','attendance_date']);
        });
        Schema::create('attendance_marks',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('session_id');$t->unsignedBigInteger('enrollment_id');$t->unsignedBigInteger('student_id');$t->string('status',15);$t->unsignedSmallInteger('late_minutes')->default(0);$t->string('note',500)->nullable();$t->timestamps();$t->unique(['session_id','student_id']);
        });
        Schema::create('attendance_events',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('session_id');$t->unsignedBigInteger('user_id');$t->string('action',30);$t->json('details');$t->timestamps();
        });
        DB::table('access_permissions')->updateOrInsert(['code'=>'attendance.take'],['module'=>'attendance','action'=>'take','name'=>'Take attendance for assigned subject sessions','created_at'=>now(),'updated_at'=>now()]);
    }
    public function down(): void
    {
        foreach(['attendance_events','attendance_marks','attendance_sessions','attendance_assignments'] as $table)Schema::dropIfExists($table);
        DB::table('access_permissions')->where('code','attendance.take')->delete();
    }
};
