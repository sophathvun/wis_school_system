<?php

use App\Support\ScholarshipPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (ScholarshipPermissions::catalog() as $action=>$name) {
            DB::table('access_permissions')->updateOrInsert(['code'=>'western-scholarship.'.$action], [
                'module'=>'western-scholarship','action'=>$action,'name'=>$name,'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
        $ids=DB::table('access_permissions')->where('module','western-scholarship')->pluck('id','action');
        // Preserve access for existing rank viewers and the tabs needed by existing action grants.
        $parents=['view'=>'tab-assign-rank']+ScholarshipPermissions::PARENTS;
        foreach (['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id','access_user_permission_overrides'=>'user_id'] as $table=>$owner) {
            if(!Schema::hasTable($table))continue;
            foreach($parents as $action=>$tab) {
                $grants=DB::table($table)->where('permission_id',$ids[$action]);
                if($table==='access_user_permission_overrides')$grants->where('allowed',true);
                foreach($grants->get() as $grant) {
                    $key=[$owner=>$grant->$owner,'permission_id'=>$ids[$tab]];
                    if(DB::table($table)->where($key)->exists())continue;
                    DB::table($table)->insert($key+['created_at'=>now(),'updated_at'=>now()]+($table==='access_user_permission_overrides'?['allowed'=>true]:[]));
                }
            }
        }
        $super=DB::table('access_roles')->where('code','super-admin')->value('id');
        if($super)foreach(ScholarshipPermissions::TABS as $tab){
            $key=['role_id'=>$super,'permission_id'=>$ids[$tab]];
            if(!DB::table('access_role_permissions')->where($key)->exists())DB::table('access_role_permissions')->insert($key+['created_at'=>now(),'updated_at'=>now()]);
        }
    }

    public function down(): void
    {
        $ids=DB::table('access_permissions')->whereIn('code',array_map(fn($tab)=>'western-scholarship.'.$tab,array_values(ScholarshipPermissions::TABS)))->pluck('id');
        foreach(['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table)
            if(Schema::hasTable($table))DB::table($table)->whereIn('permission_id',$ids)->delete();
        DB::table('access_permissions')->whereIn('id',$ids)->delete();
    }
};
