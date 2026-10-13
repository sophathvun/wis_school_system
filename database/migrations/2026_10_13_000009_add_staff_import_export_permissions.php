<?php

use App\Support\HrmPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach(HrmPermissions::catalog()['hrm-staff-data'] as $action=>$name) {
            DB::table('access_permissions')->updateOrInsert(['code'=>'hrm-staff-data.'.$action],['module'=>'hrm-staff-data','action'=>$action,'name'=>$name,'created_at'=>now(),'updated_at'=>now()]);
        }
        $role=DB::table('access_roles')->where('code','super-admin')->value('id');
        if ($role) foreach(DB::table('access_permissions')->where('module','hrm-staff-data')->pluck('id') as $id) DB::table('access_role_permissions')->updateOrInsert(['role_id'=>$role,'permission_id'=>$id],['created_at'=>now(),'updated_at'=>now()]);
    }
    public function down(): void
    {
        $ids=DB::table('access_permissions')->where('module','hrm-staff-data')->pluck('id');
        foreach(['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table) DB::table($table)->whereIn('permission_id',$ids)->delete();
        DB::table('access_permissions')->whereIn('id',$ids)->delete();
    }
};
