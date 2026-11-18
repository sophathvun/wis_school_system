<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return function () {
    if(!Schema::hasTable('users'))Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');});
    Schema::create('tb_academic_year',function(Blueprint $t){$t->id();$t->string('academic_year');$t->string('period_type')->default('regular');$t->timestamps();$t->softDeletes();});
    Schema::create('tb_school_info',function(Blueprint $t){$t->id();$t->string('campus_name_en');$t->boolean('status')->default(true);$t->timestamps();$t->softDeletes();});
    Schema::create('tb_grade',function(Blueprint $t){$t->id();$t->string('grade');$t->timestamps();$t->softDeletes();});
    Schema::create('tb_class',function(Blueprint $t){$t->id();$t->string('class_name');$t->timestamps();$t->softDeletes();});
    Schema::create('tb_student',function(Blueprint $t){$t->id();$t->string('student_id');$t->string('full_name_en');$t->string('full_name_kh')->nullable();$t->date('date_of_birth')->nullable();$t->string('gender');$t->string('home_phone')->nullable();$t->string('email')->nullable();$t->timestamps();});
    Schema::create('tb_student_enrollment',function(Blueprint $t){$t->id();foreach(['student_id','campus_id','academic_year_id','grade_id','class_id'] as $field)$t->unsignedBigInteger($field)->nullable();$t->timestamps();});
};
