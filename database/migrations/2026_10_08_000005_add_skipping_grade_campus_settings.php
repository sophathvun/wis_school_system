<?php

use App\Support\StudentSkippingGradePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_skipping_grade_campus_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->unique()->constrained('tb_school_info')->restrictOnDelete();
            $table->json('committee_names');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        $super=DB::table('access_roles')->where('code','super-admin')->value('id');
        foreach (['campus-settings','save-campus-settings'] as $action) {
            $code='student-skipping-grade.'.$action;
            DB::table('access_permissions')->updateOrInsert(['code'=>$code],[
                'module'=>'student-skipping-grade','action'=>$action,'name'=>StudentSkippingGradePermissions::catalog()[$action],
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            if ($super) DB::table('access_role_permissions')->insertOrIgnore([
                'role_id'=>$super,'permission_id'=>DB::table('access_permissions')->where('code',$code)->value('id'),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
    }

    public function down(): void
    {
        $ids=DB::table('access_permissions')->whereIn('code',['student-skipping-grade.campus-settings','student-skipping-grade.save-campus-settings'])->pluck('id');
        foreach (['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table) DB::table($table)->whereIn('permission_id',$ids)->delete();
        DB::table('access_permissions')->whereIn('id',$ids)->delete();
        Schema::dropIfExists('tb_skipping_grade_campus_settings');
    }
};
