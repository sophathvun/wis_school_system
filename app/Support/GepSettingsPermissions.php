<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class GepSettingsPermissions
{
    public const SECTIONS = [
        'academic-years' => ['label'=>'GEP Academic Year','icon'=>'ti-calendar','description'=>'Define the academic years used by the General English Program.'],
        'terms' => ['label'=>'GEP Terms','icon'=>'ti-calendar-month','description'=>'Organize teaching terms within each GEP academic year.'],
        'assign-terms' => ['label'=>'Assign Terms','icon'=>'ti-calendar-plus','description'=>'Schedule GEP terms by year, dates and order, then start or finish each term.'],
        'levels' => ['label'=>'GEP Levels','icon'=>'ti-stairs-up','description'=>'Define the English learning levels offered by GEP.'],
        'class-types' => ['label'=>'GEP Class Types','icon'=>'ti-category','description'=>'Manage Full-Time and Part-Time class types.'],
        'classes' => ['label'=>'GEP Classes','icon'=>'ti-door','description'=>'Organize GEP classes by term, level and campus.'],
        'groups' => ['label'=>'GEP Group','icon'=>'ti-users-group','description'=>'Define the study groups used by GEP classes.'],
    ];

    public static function catalog(): array
    {
        $rows=[['code'=>'gep-settings.view','module'=>'gep-settings','action'=>'view','name'=>'View GEP Settings']];
        foreach(self::SECTIONS as $key=>$section)$rows[]=['code'=>'gep-settings.'.$key.'.view','module'=>'gep-settings.'.$key,'action'=>'view','name'=>'View '.$section['label']];
        foreach(['create'=>'Add GEP academic years','edit'=>'Edit GEP academic years','delete'=>'Delete unlinked GEP academic years','status'=>'Activate or deactivate GEP academic years'] as $action=>$name)$rows[]=['code'=>'gep-settings.academic-years.'.$action,'module'=>'gep-settings.academic-years','action'=>$action,'name'=>$name];
        foreach(['create'=>'Add GEP terms','edit'=>'Edit GEP terms','delete'=>'Delete unlinked GEP terms','status'=>'Turn GEP terms On or Off'] as $action=>$name)$rows[]=['code'=>'gep-settings.terms.'.$action,'module'=>'gep-settings.terms','action'=>$action,'name'=>$name];
        foreach(['create'=>'Add GEP levels','edit'=>'Edit GEP levels','delete'=>'Delete unlinked GEP levels','status'=>'Turn GEP levels On or Off'] as $action=>$name)$rows[]=['code'=>'gep-settings.levels.'.$action,'module'=>'gep-settings.levels','action'=>$action,'name'=>$name];
        foreach(['create'=>'Add GEP classes','edit'=>'Edit GEP classes','delete'=>'Delete unlinked GEP classes','status'=>'Turn GEP classes On or Off'] as $action=>$name)$rows[]=['code'=>'gep-settings.classes.'.$action,'module'=>'gep-settings.classes','action'=>$action,'name'=>$name];
        foreach(['create'=>'Add GEP groups','edit'=>'Edit GEP groups','delete'=>'Delete unlinked GEP groups','status'=>'Turn GEP groups On or Off'] as $action=>$name)$rows[]=['code'=>'gep-settings.groups.'.$action,'module'=>'gep-settings.groups','action'=>$action,'name'=>$name];
        foreach(['create'=>'Add GEP class types','edit'=>'Edit GEP class types','delete'=>'Delete unlinked GEP class types','status'=>'Turn GEP class types On or Off'] as $action=>$name)$rows[]=['code'=>'gep-settings.class-types.'.$action,'module'=>'gep-settings.class-types','action'=>$action,'name'=>$name];
        foreach(['create'=>'Assign GEP terms','edit'=>'Edit GEP term assignments','delete'=>'Delete unlinked Pending assignments','start'=>'Start the next GEP term','finish'=>'Finish the current GEP term'] as $action=>$name)$rows[]=['code'=>'gep-settings.assign-terms.'.$action,'module'=>'gep-settings.assign-terms','action'=>$action,'name'=>$name];
        return $rows;
    }

    public static function registerMissing(): void
    {
        foreach(self::catalog() as $row)DB::table('access_permissions')->insertOrIgnore([...$row,'created_at'=>now(),'updated_at'=>now()]);
    }

    public static function allows(?User $user, string $section,string $action='view'): bool
    {
        if(!in_array($action,match($section){'assign-terms'=>['view','create','edit','delete','start','finish'],'academic-years'=>['view','create','edit','delete','status'],'terms','levels','classes','groups','class-types'=>['view','create','edit','delete','status'],default=>['view']},true))return false;
        if(!$user || !isset(self::SECTIONS[$section]) || !isset(ProgramWorkspace::available($user)['gep']))return false;
        if($user->isSuperAdmin())return true;
        $campuses=array_unique([null,...$user->accessibleCampuses()->pluck('tb_school_info.id')->all()]);
        foreach($campuses as $campus)if($user->hasPermission('program-workspaces.view',$campus)
            && $user->hasPermission('program-workspaces.gep.view',$campus)
            && $user->hasPermission('gep-settings.view',$campus)
            && $user->hasPermission('gep-settings.'.$section.'.view',$campus)
            && $user->hasPermission('gep-settings.'.$section.'.'.$action,$campus))return true;
        return false;
    }
}
