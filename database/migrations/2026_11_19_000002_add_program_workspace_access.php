<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users',fn(Blueprint $t)=>$t->string('preferred_program',10)->nullable());
        \App\Support\ProgramWorkspace::registerMissing();
        $permissions=DB::table('access_permissions')->whereIn('code',array_column(\App\Support\ProgramWorkspace::catalog(),'code'))->pluck('id','code');
        $defaults=[$permissions['program-workspaces.view'],$permissions['program-workspaces.wis.view']];
        foreach(DB::table('access_roles')->get(['id','code']) as $role){
            foreach($defaults as $permission)DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>$role->id,'permission_id'=>$permission,'created_at'=>now(),'updated_at'=>now()]);
            if(in_array($role->code,['super-admin','central-office-admin'],true))DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>$role->id,'permission_id'=>$permissions['program-workspaces.gep.view'],'created_at'=>now(),'updated_at'=>now()]);
        }
        foreach(DB::table('access_departments')->pluck('id') as $department)foreach($defaults as $permission)
            DB::table('access_department_permissions')->insertOrIgnore(['department_id'=>$department,'permission_id'=>$permission,'created_at'=>now(),'updated_at'=>now()]);
        // Preserve existing users who receive their permissions without a role or department.
        foreach(DB::table('users')->whereNull('department_id')->whereNotExists(fn($q)=>$q->selectRaw('1')->from('access_user_roles')->whereColumn('access_user_roles.user_id','users.id'))->pluck('id') as $user)foreach($defaults as $permission)
            DB::table('access_user_permission_overrides')->insertOrIgnore(['user_id'=>$user,'permission_id'=>$permission,'allowed'=>true,'created_at'=>now(),'updated_at'=>now()]);
    }

    public function down(): void
    {
        DB::table('access_permissions')->whereIn('code',array_column(\App\Support\ProgramWorkspace::catalog(),'code'))->delete();
        Schema::table('users',fn(Blueprint $t)=>$t->dropColumn('preferred_program'));
    }
};
