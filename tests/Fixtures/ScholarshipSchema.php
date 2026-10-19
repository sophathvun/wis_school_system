<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function createScholarshipSchema(): void
{
    Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->boolean('status')->default(true);$t->boolean('is_global')->default(false);$t->timestamps();});
    Schema::create('tb_academic_year',function(Blueprint $t){$t->id();$t->string('academic_year');$t->string('period_type')->default('regular');$t->string('lifecycle_status')->default('started');$t->softDeletes();});
    Schema::create('tb_school_info',function(Blueprint $t){$t->id();$t->string('campus_name_en');$t->string('campus_name_kh')->nullable();$t->boolean('status')->default(true);$t->softDeletes();});
    Schema::create('tb_grade',function(Blueprint $t){$t->id();$t->string('grade');$t->string('grade_short_name')->nullable();$t->integer('grade_order');$t->softDeletes();});
    Schema::create('tb_class',function(Blueprint $t){$t->id();$t->unsignedBigInteger('academic_year_id');$t->unsignedBigInteger('grade_id');$t->string('class_name');$t->integer('class_order')->default(1);$t->softDeletes();});
    Schema::create('tb_group',function(Blueprint $t){$t->id();$t->string('group_name');$t->softDeletes();});
    Schema::create('tb_session',function(Blueprint $t){$t->id();$t->string('session_name');$t->string('session_short_name')->nullable();$t->softDeletes();});
    Schema::create('tb_student',function(Blueprint $t){$t->id();foreach(['student_id','student_no','full_name_en','full_name_kh','gender','gender_kh','photo_path']as$c)$t->string($c)->nullable();});
    Schema::create('tb_student_enrollment',function(Blueprint $t){$t->id();foreach(['student_id','academic_year_id','campus_id','grade_id','class_id']as$c)$t->unsignedBigInteger($c);$t->unsignedBigInteger('group_id')->nullable();$t->unsignedBigInteger('session_id')->nullable();$t->timestamps();});
    Schema::create('access_roles',function(Blueprint $t){$t->id();$t->string('code');$t->boolean('is_global')->default(false);$t->boolean('status')->default(true);});
    Schema::create('access_user_roles',function(Blueprint $t){$t->unsignedBigInteger('user_id');$t->unsignedBigInteger('role_id');$t->unsignedBigInteger('campus_id')->nullable();$t->timestamps();});
    Schema::create('access_user_campuses',function(Blueprint $t){$t->unsignedBigInteger('user_id');$t->unsignedBigInteger('campus_id');$t->boolean('is_primary')->default(false);$t->timestamp('assigned_at')->nullable();$t->timestamps();});
    Schema::create('access_permissions',function(Blueprint $t){$t->id();foreach(['code','module','action','name']as$c)$t->string($c);$t->timestamps();});
    Schema::create('access_role_permissions',function(Blueprint $t){$t->unsignedBigInteger('role_id');$t->unsignedBigInteger('permission_id');$t->timestamps();});
    Schema::create('access_department_permissions',function(Blueprint $t){$t->unsignedBigInteger('department_id');$t->unsignedBigInteger('permission_id');$t->timestamps();});
    Schema::create('access_user_permission_overrides',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');$t->unsignedBigInteger('permission_id');$t->boolean('allowed');$t->timestamps();});
    (require database_path('migrations/2026_10_14_000004_create_scholarship_student_ranks.php'))->up();
    (require database_path('migrations/2026_10_14_000006_create_scholarship_exam_workflow.php'))->up();
    (require database_path('migrations/2026_10_14_000007_create_scholarship_awards.php'))->up();
    (require database_path('migrations/2026_10_14_000008_prepare_scholarship_answer_sheets.php'))->up();
    (require database_path('migrations/2026_10_15_000001_add_scholarship_eoy_average.php'))->up();
    (require database_path('migrations/2026_10_15_000003_create_scholarship_print_settings.php'))->up();
    (require database_path('migrations/2026_10_15_000004_add_scholarship_report_verifier.php'))->up();
    Schema::create('access_positions',function(Blueprint $t){$t->id();$t->string('name');$t->boolean('status')->default(true);$t->timestamps();});
    Schema::create('hrm_staff',function(Blueprint $t){$t->id();$t->string('staff_code');$t->string('name_en');$t->string('name_kh')->nullable();$t->string('gender')->nullable();$t->string('phone')->nullable();$t->string('email')->nullable();$t->unsignedBigInteger('position_id')->nullable();$t->unsignedBigInteger('primary_campus_id')->nullable();$t->boolean('status')->default(true);$t->string('employment_status')->default('active');$t->timestamps();$t->softDeletes();});
    Schema::create('hrm_staff_campuses',function(Blueprint $t){$t->unsignedBigInteger('staff_id');$t->unsignedBigInteger('campus_id');$t->boolean('status')->default(true);});
    (require database_path('migrations/2026_10_15_000005_link_scholarship_print_signers_to_staff.php'))->up();
    (require database_path('migrations/2026_10_15_000006_add_scholarship_exam_centers.php'))->up();
    (require database_path('migrations/2026_10_15_000007_scope_scholarship_rooms_by_center.php'))->up();
    (require database_path('migrations/2026_10_16_000001_add_scholarship_examination_type.php'))->up();
    (require database_path('migrations/2026_10_16_000002_add_scholarship_historical_import.php'))->up();
    (require database_path('migrations/2026_10_16_000003_create_scholarship_seat_print_settings.php'))->up();
    (require database_path('migrations/2026_10_16_000004_add_scholarship_signing_location.php'))->up();
    (require database_path('migrations/2026_10_16_000005_move_scholarship_location_to_exam_centers.php'))->up();
    (require database_path('migrations/2026_10_16_000006_add_scholarship_subject_orders.php'))->up();
    (require database_path('migrations/2026_10_16_000007_create_scholarship_invigilators.php'))->up();
    (require database_path('migrations/2026_10_16_000008_create_scholarship_session_links.php'))->up();
    (require database_path('migrations/2026_10_16_000009_add_scholarship_subject_schedules.php'))->up();
    foreach(['Registrar','Vice Principal','School Principal','Registrar Director','Academic Director','Vice President'] as $i=>$name)DB::table('access_positions')->insert(['id'=>$i+1,'name'=>$name]);
    foreach(['BCH','BKK','CENTRAL'] as $campus=>$label)foreach(['PREPARER','VERIFIER','APPROVER'] as $role=>$suffix){
        $id=$campus*3+$role+1;
        DB::table('hrm_staff')->insert(['id'=>$id,'staff_code'=>'HRM-00'.$id,'name_en'=>$label.' '.$suffix,'position_id'=>($campus===2?4:1)+$role,'primary_campus_id'=>$campus+1]);
    }
    DB::table('users')->insert([['id'=>1,'name'=>'Rank Officer'],['id'=>2,'name'=>'Exam Staff']]);
    DB::table('access_user_campuses')->insert([['user_id'=>1,'campus_id'=>1],['user_id'=>2,'campus_id'=>1]]);
    DB::table('tb_academic_year')->insert([['id'=>1,'academic_year'=>'2025-2026'],['id'=>2,'academic_year'=>'2026-2027']]);
    DB::table('tb_school_info')->insert([['id'=>1,'campus_name_en'=>'BCH'],['id'=>2,'campus_name_en'=>'BKK']]);
    DB::table('tb_grade')->insert([['id'=>1,'grade'=>'Grade 3','grade_order'=>3],['id'=>2,'grade'=>'Grade 4','grade_order'=>4]]);
    DB::table('tb_class')->insert([['id'=>1,'academic_year_id'=>1,'grade_id'=>1,'class_name'=>'A'],['id'=>2,'academic_year_id'=>2,'grade_id'=>2,'class_name'=>'B']]);
    DB::table('tb_student')->insert([['id'=>1,'student_id'=>'2026001','full_name_en'=>'CHEAN CHAMNUNY','full_name_kh'=>'ចាន់'],['id'=>2,'student_id'=>'2026002','full_name_en'=>'SECOND STUDENT','full_name_kh'=>'សិស្ស']]);
    DB::table('tb_student_enrollment')->insert([
        ['id'=>1,'student_id'=>1,'academic_year_id'=>1,'campus_id'=>1,'grade_id'=>1,'class_id'=>1],
        ['id'=>2,'student_id'=>1,'academic_year_id'=>2,'campus_id'=>1,'grade_id'=>2,'class_id'=>2],
        ['id'=>3,'student_id'=>2,'academic_year_id'=>1,'campus_id'=>2,'grade_id'=>1,'class_id'=>1],
    ]);
}
