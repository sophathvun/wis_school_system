<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up():void
    {
        Schema::table('tb_gep_academic_year',fn(Blueprint $t)=>$t->boolean('status')->default(true));
        DB::table('tb_gep_academic_year')->where('lifecycle_status','finished')->update(['status'=>false]);
        DB::table('tb_gep_academic_year')->update(['started_slot'=>null]);
        \App\Support\GepSettingsPermissions::registerMissing();
        $status=DB::table('access_permissions')->where('code','gep-settings.academic-years.status')->value('id');
        $old=DB::table('access_permissions')->where('code','gep-settings.academic-years.start')->value('id');
        if($old)foreach(['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table){
            foreach(DB::table($table)->where('permission_id',$old)->get() as $grant){
                $data=(array)$grant;unset($data['id']);$data['permission_id']=$status;DB::table($table)->insertOrIgnore($data);
            }
            DB::table($table)->where('permission_id',$old)->delete();
        }
        if($old)DB::table('access_permissions')->where('id',$old)->delete();
        foreach(DB::table('access_roles')->whereIn('code',['super-admin','central-office-admin'])->pluck('id') as $role)DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>$role,'permission_id'=>$status,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function down():void
    {
        Schema::table('tb_gep_academic_year',fn(Blueprint $t)=>$t->dropColumn('status'));
    }
};
