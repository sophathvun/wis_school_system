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
        if (!$user->hasPermission('reports.view', $user->active_campus_id)) return [];

        return array_filter(self::labels(), fn ($label, $type) => $user->hasPermission(
            self::REPORTS[$type]['module'].'.view', $user->active_campus_id
        ), ARRAY_FILTER_USE_BOTH);
    }

    public static function canView(?User $user, string $type): bool
    {
        if (!$user || !isset(self::REPORTS[$type])) return false;
        return $user->isSuperAdmin() || (
            $user->hasPermission('reports.view', $user->active_campus_id)
            && $user->hasPermission(self::REPORTS[$type]['module'].'.view', $user->active_campus_id)
        );
    }
}
