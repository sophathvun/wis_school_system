<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        \App\Support\GepStudentPermissions::registerMissing();
        $id=DB::table('access_permissions')->where('code','gep-students.promotion.promote')->value('id');
        // Campus staff receive this action only when an administrator explicitly grants it.
        foreach(DB::table('access_roles')->whereIn('code',['super-admin','central-office-admin'])->pluck('id') as $role)
            DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>$role,'permission_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
    }

    public function down(): void
    {
        $ids=DB::table('access_permissions')->where('code','gep-students.promotion.promote')->pluck('id');
        foreach(['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table)DB::table($table)->whereIn('permission_id',$ids)->delete();
        DB::table('access_permissions')->whereIn('id',$ids)->delete();
    }
};
