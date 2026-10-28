<?php
namespace App\Support;
use App\Models\{User,SchoolInfo,Permission};
use Illuminate\Support\Facades\Schema;
class G12NationalExamPermissions {
    public const MODULE='g12-national-exams';
    public static function catalog(): array {return [
        'view'=>'G12 National Exams: Main menu','records'=>'Submenu: Student Exam Records','create'=>'Add student exam records','edit'=>'Edit student exam records',
        'assign-centers'=>'Submenu: Assign Exam Center','assign-centers-manage'=>'Assign exam center, room and seat','scoring'=>'Submenu: Scoring',
        'centers'=>'Submenu: Exam Center Settings','centers-manage'=>'Add and edit exam centers',
        'certificates'=>'Submenu: Certificate Tracking','documents-view'=>'View and download student documents','documents-upload'=>'Upload certificates and supporting documents',
        'collection'=>'Record certificate availability and collection','collection-correct'=>'Correct certificate collection (reason required)',
        'imports'=>'Submenu: Excel Imports and History','import'=>'Preview and confirm MoEYS Excel imports',
        'photos'=>'Submenu: Bulk Student Photos','photos-upload'=>'Preview and upload student photos / ZIP','photos-replace'=>'Replace existing national exam student photos',
        'reports'=>'Submenu: Reports','export'=>'Export Excel and print national exam reports',
    ];}
    public const PARENTS=['create'=>'records','edit'=>'records','assign-centers-manage'=>'assign-centers','centers-manage'=>'centers','documents-view'=>'certificates','documents-upload'=>'certificates','collection'=>'certificates','collection-correct'=>'certificates','import'=>'imports','photos-upload'=>'photos','photos-replace'=>'photos','export'=>'reports'];
    public static function submenus(): array {return ['records'=>['Student Exam Records',['create','edit']],'assign-centers'=>['Assign Exam Center',['assign-centers-manage']],'scoring'=>['Scoring',[]],'centers'=>['Exam Center Settings',['centers-manage']],'certificates'=>['Certificate Tracking',['documents-view','documents-upload','collection','collection-correct']],'imports'=>['Excel Imports / History',['import']],'photos'=>['Bulk Student Photos',['photos-upload','photos-replace']],'reports'=>['Reports',['export']]];}
    public const PATHS=['records'=>'student-records','assign-centers'=>'assign-exam-center','scoring'=>'scoring','centers'=>'exam-centers','certificates'=>'certificate-tracking','imports'=>'excel-imports','photos'=>'student-photos','reports'=>'reports'];
    public const ICONS=['records'=>'ti-users','assign-centers'=>'ti-map-pin','scoring'=>'ti-calculator','centers'=>'ti-building-community','certificates'=>'ti-certificate','imports'=>'ti-file-import','photos'=>'ti-photo-up','reports'=>'ti-report-analytics'];
    public static function registerMissing(): void {
        if(!Schema::hasTable('access_permissions'))return;
        foreach(self::catalog() as $action=>$name)Permission::firstOrCreate(['code'=>self::MODULE.'.'.$action],['module'=>self::MODULE,'action'=>$action,'name'=>$name]);
    }
    public static function allows(?User $user,string $action,?int $campus=null): bool {
        if(!$user||!isset(self::catalog()[$action]))return false;
        if($campus!==null&&!$user->canAccessCampus($campus))return false;
        if($user->isSuperAdmin())return true;
        $ids=$campus!==null?[$campus]:$user->accessibleCampuses()->pluck('tb_school_info.id')->all();
        if(!$ids)$ids=[null];
        foreach($ids as $id){$ok=true;foreach(array_unique([self::MODULE.'.view',self::MODULE.'.'.(self::PARENTS[$action]??$action),self::MODULE.'.'.$action]) as $code)if(!$user->hasPermission($code,$id)){$ok=false;break;}if($ok)return true;}
        return false;
    }
    public static function campusIds(User $user,string $action='records'): array {return $user->accessibleCampuses()->get()->filter(fn($c)=>self::allows($user,$action,(int)$c->id))->modelKeys();}
}
