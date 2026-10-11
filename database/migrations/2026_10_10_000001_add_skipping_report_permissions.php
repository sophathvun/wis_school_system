<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['reports'=>'Tab: Skipping Reports (View All Campuses)','print-reports'=>'Print A4 Skipping Reports (All Campuses)'] as $action=>$name) {
            DB::table('access_permissions')->updateOrInsert(['code'=>'student-skipping-grade.'.$action], [
                'module'=>'student-skipping-grade','action'=>$action,'name'=>$name,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
        foreach (['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id','access_user_permission_overrides'=>'user_id'] as $table=>$owner) {
            foreach (['requests'=>'reports','print'=>'print-reports'] as $source=>$target) {
                $sourceId = DB::table('access_permissions')->where('code','student-skipping-grade.'.$source)->value('id');
                $targetId = DB::table('access_permissions')->where('code','student-skipping-grade.'.$target)->value('id');
                foreach (DB::table($table)->where('permission_id',$sourceId)->get() as $assignment) {
                    DB::table($table)->insertOrIgnore([
                        $owner=>$assignment->$owner,'permission_id'=>$targetId,'created_at'=>now(),'updated_at'=>now(),
                    ] + ($owner==='user_id' ? ['allowed'=>$assignment->allowed] : []));
                }
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('access_permissions')->whereIn('code',['student-skipping-grade.reports','student-skipping-grade.print-reports'])->pluck('id');
        foreach (['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table) DB::table($table)->whereIn('permission_id',$ids)->delete();
        DB::table('access_permissions')->whereIn('id',$ids)->delete();
    }
};
