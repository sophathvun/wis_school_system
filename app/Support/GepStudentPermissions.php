<?php
namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class GepStudentPermissions
{
    public const SECTIONS=[
        'enrollment'=>['label'=>'GEP Stu. Enrollment','icon'=>'ti-user-plus'],
        'promotion'=>['label'=>'GEP Stu. Promotion','icon'=>'ti-trending-up'],
        'graduation'=>['label'=>'GEP Stu. Graduation','icon'=>'ti-certificate'],
        'transfer'=>['label'=>'GEP Stu. Transfer','icon'=>'ti-arrows-left-right'],
        'withdrawal'=>['label'=>'GEP Stu. Withdrawal','icon'=>'ti-user-minus'],
        'reentry'=>['label'=>'GEP Stu. Re-Entry','icon'=>'ti-user-check'],
        'id-card'=>['label'=>'GEP Stu. ID Card (QR)','icon'=>'ti-qrcode'],
        'import-export'=>['label'=>'Import/Export Data','icon'=>'ti-file-import'],
        'help-guide'=>['label'=>'Help Guide','icon'=>'ti-lifebuoy'],
    ];
    public static function catalog():array
    {
        $rows=[['code'=>'gep-students.view','module'=>'gep-students','action'=>'view','name'=>'View GEP Students']];
        foreach(self::SECTIONS as $key=>$item)$rows[]=['code'=>'gep-students.'.$key.'.view','module'=>'gep-students.'.$key,'action'=>'view','name'=>'View '.$item['label']];
        $rows[]=['code'=>'gep-students.promotion.promote','module'=>'gep-students.promotion','action'=>'promote','name'=>'Promote GEP students within permitted campuses'];
        return $rows;
    }
    public static function registerMissing():void
    {
        foreach(self::catalog() as $row)DB::table('access_permissions')->insertOrIgnore([...$row,'created_at'=>now(),'updated_at'=>now()]);
    }
    public static function allows(?User $user,string $section):bool
    {
        if(!$user || !isset(self::SECTIONS[$section]) || !isset(ProgramWorkspace::available($user)['gep']))return false;
        if($user->isSuperAdmin())return true;
        foreach(array_unique([null,...$user->accessibleCampuses()->pluck('tb_school_info.id')->all()]) as $campus)
            if($user->hasPermission('program-workspaces.view',$campus) && $user->hasPermission('program-workspaces.gep.view',$campus)
                && $user->hasPermission('gep-students.view',$campus) && $user->hasPermission('gep-students.'.$section.'.view',$campus))return true;
        return false;
    }
}
