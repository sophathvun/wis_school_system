<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up():void
    {
        \App\Support\GepStudentPermissions::registerMissing();
        $ids=DB::table('access_permissions')->where('module','like','gep-students%')->pluck('id');
        foreach(DB::table('access_roles')->whereIn('code',['super-admin','central-office-admin'])->pluck('id') as $role)foreach($ids as $permission)DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>$role,'permission_id'=>$permission,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function down():void
    {
        DB::table('access_permissions')->where('module','like','gep-students%')->delete();
    }
};
