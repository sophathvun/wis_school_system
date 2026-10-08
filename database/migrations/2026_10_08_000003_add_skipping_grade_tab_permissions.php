<?php

use App\Support\StudentSkippingGradePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (StudentSkippingGradePermissions::catalog() as $action=>$name) {
            DB::table('access_permissions')->updateOrInsert(['code'=>'student-skipping-grade.'.$action], [
                'module'=>'student-skipping-grade','action'=>$action,'name'=>$name,'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
        // Preserve existing access while making each tab and its editing actions independently selectable.
        $mapping=['view'=>['requests'], 'settings'=>['save-settings','request-template','edit-request-template','approval-template','edit-approval-template']];
        foreach (['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id','access_user_permission_overrides'=>'user_id'] as $table=>$owner) {
            foreach ($mapping as $old=>$actions) {
                $oldId=DB::table('access_permissions')->where('code','student-skipping-grade.'.$old)->value('id');
                foreach (DB::table($table)->where('permission_id',$oldId)->get() as $assignment) {
                    foreach ($actions as $action) {
                        $id=DB::table('access_permissions')->where('code','student-skipping-grade.'.$action)->value('id');
                        DB::table($table)->insertOrIgnore([$owner=>$assignment->$owner,'permission_id'=>$id,'created_at'=>now(),'updated_at'=>now()]
                            +($owner==='user_id'?['allowed'=>$assignment->allowed]:[]));
                    }
                }
            }
        }
        $super=DB::table('access_roles')->where('code','super-admin')->value('id');
        if ($super) foreach (DB::table('access_permissions')->where('module','student-skipping-grade')->pluck('id') as $id) {
            DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>$super,'permission_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
        }
    }

    public function down(): void
    {
        $codes=array_map(fn($action)=>'student-skipping-grade.'.$action,['requests','save-settings','request-template','edit-request-template','approval-template','edit-approval-template']);
        $ids=DB::table('access_permissions')->whereIn('code',$codes)->pluck('id');
        foreach (['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table) DB::table($table)->whereIn('permission_id',$ids)->delete();
        DB::table('access_permissions')->whereIn('id',$ids)->delete();
    }
};
