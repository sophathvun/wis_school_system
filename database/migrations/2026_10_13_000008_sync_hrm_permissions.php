<?php

use App\Support\HrmPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach (HrmPermissions::catalog() as $module=>$actions) {
            foreach ($actions as $action=>$name) {
                $code=$module.'.'.$action;
                $existing=DB::table('access_permissions')->where('code',$code)->first();
                if ($existing) DB::table('access_permissions')->where('id',$existing->id)->update(['name'=>$name,'module'=>$module,'action'=>$action,'updated_at'=>now()]);
                else DB::table('access_permissions')->insert(['code'=>$code,'name'=>$name,'module'=>$module,'action'=>$action,'created_at'=>now(),'updated_at'=>now()]);
            }
        }
        $ids=DB::table('access_permissions')->pluck('id','code');
        $legacy=[
            'staff.view'=>['hrm.view','hrm-staff-attendance.view','hrm-payroll.view','hrm-leave-requests.view','hrm-performance-reviews.view','hrm-staff-reports.view'],
            'attendance.view'=>['hrm.view','hrm-staff-attendance.view','hrm-payroll.view','hrm-leave-requests.view','hrm-performance-reviews.view','hrm-staff-reports.view'],
            'staff.edit'=>['staff.status'],
            'departments.view'=>['hrm.view','hrm-settings.view'], 'positions.view'=>['hrm.view','hrm-settings.view'],
            'departments.export'=>['departments.print'], 'positions.export'=>['positions.print'],
            'departments.manage'=>['departments.create','departments.update','departments.status'],
            'positions.manage'=>['positions.create','positions.update','positions.status'],
        ];
        foreach (['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id','access_user_permission_overrides'=>'user_id'] as $table=>$owner) {
            foreach ($legacy as $source=>$targets) {
                if (!$ids->has($source)) continue;
                $grants=DB::table($table)->where('permission_id',$ids[$source]);
                if ($owner==='user_id') $grants->where('allowed',true);
                foreach ($grants->get() as $grant) {
                    foreach ($targets as $target) {
                        $key=[$owner=>$grant->{$owner},'permission_id'=>$ids[$target]];
                        // Preserve every existing assignment, including explicit overrides.
                        if (DB::table($table)->where($key)->exists()) continue;
                        DB::table($table)->insert($key+['created_at'=>now(),'updated_at'=>now()]+($owner==='user_id'?['allowed'=>true]:[]));
                    }
                }
            }
        }
        $superAdmin=DB::table('access_roles')->where('code','super-admin')->value('id');
        if ($superAdmin) foreach ($ids as $code=>$id) {
            if (!isset(HrmPermissions::catalog()[strtok($code,'.')])) continue;
            DB::table('access_role_permissions')->updateOrInsert(['role_id'=>$superAdmin,'permission_id'=>$id],['created_at'=>now(),'updated_at'=>now()]);
        }
    }

    public function down(): void
    {
        $codes=['hrm.view','hrm-settings.view','staff.status','departments.print','positions.print',
            'hrm-staff-attendance.view','hrm-payroll.view','hrm-leave-requests.view','hrm-performance-reviews.view','hrm-staff-reports.view'];
        $ids=DB::table('access_permissions')->whereIn('code',$codes)->pluck('id');
        foreach (['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table) DB::table($table)->whereIn('permission_id',$ids)->delete();
        DB::table('access_permissions')->whereIn('id',$ids)->delete();
    }
};
