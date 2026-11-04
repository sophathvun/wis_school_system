<?php

use App\Support\AttendancePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (AttendancePermissions::catalog() as $action => $name) DB::table('access_permissions')->updateOrInsert(
            ['code'=>'attendance.'.$action], ['module'=>'attendance','action'=>$action,'name'=>$name,'created_at'=>now(),'updated_at'=>now()]);
        $ids=DB::table('access_permissions')->where('module','attendance')->pluck('id','code');
        $read=['subject','daily','reports','detail','statistics','print','excel','pdf'];
        $manage=array_diff(array_keys(AttendancePermissions::catalog()), ['view','take']);
        foreach (['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id','access_user_permission_overrides'=>'user_id'] as $table=>$owner) {
            if (!Schema::hasTable($table)) continue;
            foreach (['view'=>$read,'take'=>['subject','history-export'],'manage'=>$manage] as $legacy=>$actions) {
                if (!isset($ids['attendance.'.$legacy])) continue;
                foreach (DB::table($table)->where('permission_id',$ids['attendance.'.$legacy])->get() as $grant) {
                    foreach ($actions as $action) {
                        $target=[$owner=>$grant->$owner,'permission_id'=>$ids['attendance.'.$action]];
                        if (DB::table($table)->where($target)->exists()) continue;
                        $values=$target;
                        if ($table==='access_user_permission_overrides') $values+=['allowed'=>$grant->allowed,'created_at'=>now(),'updated_at'=>now()];
                        DB::table($table)->insert($values);
                    }
                }
            }
        }
    }
    public function down(): void
    {
        // Preserve assigned grants and historical attendance on rollback.
    }
};
