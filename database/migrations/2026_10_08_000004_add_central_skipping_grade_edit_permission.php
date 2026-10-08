<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $code='student-skipping-grade.central-update';
        DB::table('access_permissions')->updateOrInsert(['code'=>$code],[
            'module'=>'student-skipping-grade','action'=>'central-update',
            'name'=>'Central Office: Edit Draft and Submitted Requests (All Campuses)',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $id=DB::table('access_permissions')->where('code',$code)->value('id');
        $super=DB::table('access_roles')->where('code','super-admin')->value('id');
        if ($super) DB::table('access_role_permissions')->insertOrIgnore([
            'role_id'=>$super,'permission_id'=>$id,'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function down(): void
    {
        $id=DB::table('access_permissions')->where('code','student-skipping-grade.central-update')->value('id');
        if (!$id) return;
        foreach (['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table) DB::table($table)->where('permission_id',$id)->delete();
        DB::table('access_permissions')->where('id',$id)->delete();
    }
};
