<?php

namespace App\Support;

use App\Models\User;
use App\Http\Controllers\WmiManagementController;
use Illuminate\Support\Facades\DB;

class WmiPermissions
{
    public const ROOT='wmi-management';

    public static function functions(): array
    {
        return [
            'preliminary-round.pre-registration'=>['create'=>'Register Students (all campuses)','update'=>'Edit Student Registrations','delete'=>'Delete Student Registrations'],
            'preliminary-round.exam-card'=>['print'=>'Print Exam Cards','excel'=>'Export Exam Cards to Excel','pdf'=>'Download Exam Cards as PDF'],
            'preliminary-round.scoring'=>['update'=>'Enter / Update Scores'],
            'preliminary-round.result'=>['print'=>'Print Results','excel'=>'Export Results to Excel','pdf'=>'Download Results as PDF'],
            'preliminary-round.certificates'=>['issue'=>'Issue Certificates','print'=>'Print Certificates','pdf'=>'Download Certificates as PDF'],
            'settings.academic-year'=>['create'=>'Create WMI Years','update'=>'Edit WMI Years','delete'=>'Delete WMI Years'],
            'settings.exam-centers'=>['create'=>'Assign Exam Centers','update'=>'Edit Exam Centers','delete'=>'Delete Exam Centers'],
            'settings.schools'=>['create'=>'Add Schools','update'=>'Edit Schools','delete'=>'Delete Schools'],
            'settings.room-seats'=>['assign'=>'Assign Rooms and Seats'],
            'settings.date-time'=>['update'=>'Manage Exam and Issue Dates / Times'],
            'settings.awarding-ceremony'=>['update'=>'Manage Awarding Ceremony'],
            'import-export'=>['export-candidates'=>'Export Candidate Data','import-ids'=>'Import Committee WMI IDs'],
        ];
    }

    public static function catalog(): array
    {
        $catalog=[self::ROOT.'.view'=>'View WMI Management'];
        foreach(WmiManagementController::SECTIONS as $key=>$section){
            $catalog[self::ROOT.'.'.$key]='View '.$section['label'];
            foreach($section['tabs'] as $tab=>$item)$catalog[self::ROOT.'.'.$key.'.'.$tab]='View '.$item[0].' Tab';
        }
        foreach(self::functions() as $parent=>$actions)foreach($actions as $action=>$label)$catalog[self::ROOT.'.'.$parent.'.'.$action]=$label;
        return $catalog;
    }

    public static function parents(): array
    {
        $parents=[];
        foreach(WmiManagementController::SECTIONS as $key=>$section){
            $parents[self::ROOT.'.'.$key]=self::ROOT.'.view';
            foreach($section['tabs'] as $tab=>$item)$parents[self::ROOT.'.'.$key.'.'.$tab]=self::ROOT.'.'.$key;
        }
        foreach(self::functions() as $parent=>$actions)foreach($actions as $action=>$label)$parents[self::ROOT.'.'.$parent.'.'.$action]=self::ROOT.'.'.$parent;
        return $parents;
    }

    public static function registerMissing(): void
    {
        $rows=[];foreach(self::catalog() as $code=>$name)$rows[]=['code'=>$code,'module'=>self::ROOT,'action'=>substr($code,strlen(self::ROOT)+1),'name'=>$name,'created_at'=>now(),'updated_at'=>now()];
        DB::table('access_permissions')->insertOrIgnore($rows);
    }

    /** A WMI grant at any assigned role campus covers WMI records in every campus. */
    public function check(?User $user,string $code): bool
    {
        if(!$user||!isset(self::catalog()[$code]))return false;
        $user->loadMissing(['roles.permissions','permissionOverrides','department.permissions']);
        $activeRoles=$user->roles->filter(fn($role)=>(bool)$role->status);
        if($activeRoles->contains('code','super-admin'))return true;
        $grants=$activeRoles->flatMap(fn($role)=>$role->permissions->pluck('code'))
            ->merge($user->permissionOverrides->filter(fn($permission)=>(bool)$permission->pivot->allowed)->pluck('code'))
            ->merge($user->department?->permissions->pluck('code')??[])->flip();
        $parents=self::parents();
        do {if(!$grants->has($code))return false;$code=$parents[$code]??null;} while($code);
        return true;
    }
    public static function allows(?User $user,string $code): bool { return app(self::class)->check($user,$code); }
    public static function current(string $suffix): bool { return self::allows(auth()->user(),self::ROOT.'.'.$suffix); }
}
