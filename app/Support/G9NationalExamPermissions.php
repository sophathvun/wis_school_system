<?php

namespace App\Support;

use App\Models\{Permission,SchoolInfo,User};

class G9NationalExamPermissions
{
    public const MODULE='g9-national-exams';
    public const PATHS=['candidates'=>'candidates','eoy'=>'eoy-average','scoring'=>'scoring','results'=>'results','certificates'=>'certificates','imports'=>'imports-exports','photos'=>'bulk-photos','settings'=>'settings'];
    public const ICONS=['candidates'=>'ti-users','eoy'=>'ti-calculator','scoring'=>'ti-pencil','results'=>'ti-chart-bar','certificates'=>'ti-certificate','imports'=>'ti-file-import','photos'=>'ti-photo','settings'=>'ti-settings'];
    public const REPORTS=['candidates','cover','attendance-1','attendance-2','profile-cover','profile','seat-stickers','room-seat-plan'];

    public static function sections(): array
    {
        return [
            'candidates'=>['List of Candidate',['create','edit','print','excel','pdf',...array_map(fn($type)=>'report-'.$type,self::REPORTS)]],
            'eoy'=>['Assign EOY Average',['eoy-assign','eoy-print','eoy-pdf','eoy-report-cover','eoy-report-card']],
            'scoring'=>['Scoring',[]], 'results'=>['Exam Result',[]], 'certificates'=>['G9 Certificate Tracking',[]],
            'imports'=>['G9 Imports/Export',['import','export','import-history']],
            'photos'=>['G9 Bulk Stu. Photo',['photos-upload','photos-replace']],
            'settings'=>['G9 Setting',['settings-manage','eoy-settings-manage']],
        ];
    }

    public static function catalog(): array
    {
        return [
            'view'=>'G9 National Exams: Main menu',
            'candidates'=>'Submenu: List of Candidate (view all campuses)',
            'create'=>'Add G9 candidates in permitted campuses', 'edit'=>'Edit G9 candidates in permitted campuses',
            'print'=>'Print candidate reports for all campuses','excel'=>'Export candidate reports to Excel for all campuses','pdf'=>'Export candidate reports to PDF for all campuses',
            'report-candidates'=>'Report: List of candidates','report-cover'=>'Report: Attendance list cover',
            'report-attendance-1'=>'Report: Attendance list page 1','report-attendance-2'=>'Report: Attendance list page 2',
            'report-profile-cover'=>'Report: Student card cover','report-profile'=>'Report: Student personal card',
            'report-seat-stickers'=>'Report: Seat plan stickers','report-room-seat-plan'=>'Report: Seat plan by room',
            'eoy'=>'Submenu: Assign EOY Average','eoy-assign'=>'Edit / autosave semester scores and EOY total (own campuses; central users: permitted campuses)',
            'eoy-print'=>'Print annual score cards and covers','eoy-pdf'=>'Download annual score cards and covers as PDF',
            'eoy-report-cover'=>'Report: Annual score card cover','eoy-report-card'=>'Report: Annual score card',
            'scoring'=>'Submenu: Scoring','results'=>'Submenu: Exam Result','certificates'=>'Submenu: G9 Certificate Tracking',
            'imports'=>'Submenu: G9 Imports/Export','import'=>'Preview / confirm MoEYS and legacy imports',
            'export'=>'Download sample CSV / export all candidate data in permitted campuses','import-history'=>'View own import history',
            'photos'=>'Submenu: G9 Bulk Stu. Photo','photos-upload'=>'Preview / upload matched photos and ZIP files','photos-replace'=>'Replace existing student photos',
            'settings'=>'Submenu: G9 Setting','settings-manage'=>'Manage shared issue dates, exam center headings and signatures (central users only)',
            'eoy-settings-manage'=>'Set annual score compiler and campus director names in permitted campuses',
        ];
    }

    public static function parent(string $action): ?string
    {
        if($action==='view')return null;
        foreach(self::sections() as $section=>$group)if(in_array($action,$group[1],true))return $section;
        return 'view';
    }

    public static function register(): void
    {
        foreach(self::catalog() as $action=>$name)Permission::updateOrCreate(['code'=>self::MODULE.'.'.$action],['module'=>self::MODULE,'action'=>$action,'name'=>$name]);
    }

    public static function allows(?User $user,string $action,?int $campus=null): bool
    {
        if(!$user||!isset(self::catalog()[$action]))return false;
        $request=request();$key=$user->id.':'.$action.':'.($campus??'any');$cache=$request->attributes->get('_g9_permissions',[]);
        if(array_key_exists($key,$cache))return $cache[$key];
        $access=self::access($user);
        if($campus===null)$result=collect($access['campuses'])->contains(fn($id)=>self::allows($user,$action,$id));
        elseif(!in_array($campus,$access['campuses'],true))$result=false;
        elseif($access['super'])$result=true;
        else{
            $code=self::MODULE.'.'.$action;
            $granted=in_array($code,$access['user'],true)||in_array($code,$access['department'],true)
                ||$access['roles']->contains(fn($role)=>($role->is_global||(int)$role->pivot->campus_id===$campus)&&$role->permissions->contains('code',$code));
            $result=!in_array($code,$access['denied'],true)&&$granted&&(!self::parent($action)||self::allows($user,self::parent($action),$campus));
        }
        $cache=$request->attributes->get('_g9_permissions',[]);$cache[$key]=$result;$request->attributes->set('_g9_permissions',$cache);
        return $result;
    }

    public static function campusIds(User $user,string $action): array
    {
        return array_values(array_filter(self::access($user)['campuses'],fn($id)=>self::allows($user,$action,$id)));
    }

    public static function reportCampuses(User $user,string $action='candidates'): array
    {
        return self::allows($user,$action)?SchoolInfo::where('status',1)->pluck('id')->all():[];
    }

    private static function access(User $user): array
    {
        $request=request();$key='_g9_access_'.$user->id;
        if(!$request->attributes->has($key)){
            $roles=$user->roles()->where('access_roles.status',1)->with('permissions')->get();
            $super=$roles->contains('code','super-admin');$global=$super||$user->is_global||$roles->contains('is_global',true);
            $campuses=$global?SchoolInfo::where('status',1)->pluck('id'):$user->campuses()->where('tb_school_info.status',1)->pluck('tb_school_info.id');
            $overrides=$user->permissionOverrides()->get();
            $request->attributes->set($key,['super'=>$super,'roles'=>$roles,'campuses'=>$campuses->map(fn($id)=>(int)$id)->all(),
                'user'=>$overrides->filter(fn($p)=>(bool)$p->pivot->allowed)->pluck('code')->all(),
                'denied'=>$overrides->reject(fn($p)=>(bool)$p->pivot->allowed)->pluck('code')->all(),
                'department'=>$user->department_id?($user->department?->permissions->pluck('code')->all()??[]):[]]);
        }
        return $request->attributes->get($key);
    }
}
