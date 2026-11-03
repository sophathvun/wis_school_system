<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teaching_schedules', function (Blueprint $table) {
            $table->id();
            foreach (['campus_id','academic_year_id','class_id','teacher_id','created_by'] as $column) $table->unsignedBigInteger($column);
            $table->unsignedBigInteger('grade_id')->nullable();
            $table->unsignedBigInteger('replaces_id')->nullable();
            $table->string('subject',100);
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('status',15)->default('draft');
            $table->unsignedInteger('version')->default(0);
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['academic_year_id','campus_id','weekday']);
        });
        Schema::table('attendance_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('schedule_id')->nullable()->unique();
            $table->unsignedBigInteger('grade_id')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
        });
        DB::table('access_permissions')->updateOrInsert(['code'=>'schedules.manage'],['module'=>'schedules','action'=>'manage','name'=>'Create and publish student and teacher schedules','created_at'=>now(),'updated_at'=>now()]);
    }

    public function down(): void
    {
        Schema::table('attendance_assignments', function (Blueprint $table) {
            $table->dropUnique(['schedule_id']);
            $table->dropColumn(['schedule_id','grade_id','effective_from','effective_until']);
        });
        Schema::dropIfExists('teaching_schedules');
    }
};
