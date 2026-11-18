<?php

namespace App\Support;

use App\Models\User;

class AcademicReportPermissions
{
    public const REPORTS = [
        'student-list' => ['label' => 'Student List', 'module' => 'reports.student-list'],
        'student-contact-list' => ['label' => 'Stu. Contact List', 'module' => 'reports.student-contact-list'],
        'score-list' => ['label' => 'Score List', 'module' => 'reports.score-list'],
        'attendance-list' => ['label' => 'Attendance List', 'module' => 'reports.attendance-list'],
        'student-statistics' => ['label' => 'Stu. Statistics (Summary)', 'module' => 'reports.student-statistics'],
        'student-statistics-detail' => ['label' => 'Stu. Statistics (Detail)', 'module' => 'reports.student-statistics-detail'],
        'withdrawn-students' => ['label' => 'Withdrawn Students', 'module' => 'reports.withdrawn-students'],
        'student-id-books-moeys' => ['label' => 'Stu. ID Book (MoEYS)', 'module' => 'reports.student-id-books-moeys'],
        'moeys-sikkhakarik-book' => ['label' => 'Stu. Transcript Book', 'module' => 'reports.moeys-sikkhakarik-book'],
        'k3-certificate-wis' => ['label' => 'K3 Certificate (WIS)', 'module' => 'reports.k3'],
        'g9-certificate-wis' => ['label' => 'G9 Certificate (WIS)', 'module' => 'reports.g9'],
        'g12-certificate-wis' => ['label' => 'G12 Certificate (WIS)', 'module' => 'reports.g12'],
        'moeys-id-number-book' => ['label' => 'Customize Stu. List', 'module' => 'reports.moeys-id-number-book'],
        'student-profile-label' => ['label' => 'Stu. Profile Label', 'module' => 'reports.student-profile-label'],
        'student-photo' => ['label' => 'Print Stu. Photo', 'module' => 'reports.student-photo'],
    ];

    public static function labels(): array
    {
        return array_map(fn ($report) => $report['label'], self::REPORTS);
    }

    public static function moduleLabels(): array
    {
        return array_column(self::REPORTS, 'label', 'module');
    }

    public static function catalog(): array
    {
        $permissions = [];
        foreach (self::REPORTS as $report) {
            $permissions[] = [
                'code' => $report['module'].'.view', 'module' => $report['module'],
                'action' => 'view', 'name' => 'View '.$report['label'],
            ];
        }
        return $permissions;
    }

    public static function visibleTypes(?User $user): array
    {
        if (!$user) return [];
        if ($user->isSuperAdmin()) return self::labels();
        $campuses=self::permissionCampuses($user);
        $visible=[];
        foreach($campuses as $campus){
            if(!$user->hasPermission('reports.view',$campus))continue;
            foreach(self::REPORTS as $type=>$report){
                if(!isset($visible[$type])&&$user->hasPermission($report['module'].'.view',$campus))$visible[$type]=$report['label'];
            }
        }
        // Preserve the configured report order when multiple assigned campuses contribute grants.
        return array_intersect_key(self::labels(),$visible);
    }

    public static function canView(?User $user, string $type): bool
    {
        if (!$user || !isset(self::REPORTS[$type])) return false;
        if($user->isSuperAdmin())return true;
        foreach(self::permissionCampuses($user) as $campus){
            if($user->hasPermission('reports.view',$campus)&&$user->hasPermission(self::REPORTS[$type]['module'].'.view',$campus))return true;
        }
        return false;
    }

    private static function permissionCampuses(User $user): array
    {
        // Reports routes do not require campus selection. A new campus user's
        // role still applies to their assigned campus before they select one.
        if($user->active_campus_id!==null)return [(int)$user->active_campus_id];
        // Null retains existing global-role and personal permission behavior.
        return [null,...$user->accessibleCampuses()->orderBy('tb_school_info.id')->pluck('tb_school_info.id')->map(fn($id)=>(int)$id)->all()];
    }
}
