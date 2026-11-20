<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class GepReportPermissions
{
    public const SECTIONS = [
        'student-list' => ['label' => 'Student List', 'icon' => 'ti-list-details'],
        'student-contact-list' => ['label' => 'Student Contact List', 'icon' => 'ti-address-book'],
        'withdrawn-students' => ['label' => 'Withdrawn Students', 'icon' => 'ti-user-minus'],
        'graduated-certificates' => ['label' => 'Graduated Certificates', 'icon' => 'ti-certificate'],
        'student-statistics-detail' => ['label' => 'Stu. Statistics (Detail)', 'icon' => 'ti-chart-bar'],
        'student-statistics' => ['label' => 'Stu. Statistics (Summary)', 'icon' => 'ti-chart-pie'],
        'student-photos' => ['label' => 'Stu. Photos', 'icon' => 'ti-photo'],
        'customize-student-list' => ['label' => 'Customize Stu. List', 'icon' => 'ti-table-options'],
    ];

    public static function catalog(): array
    {
        $rows = [['code' => 'gep-reports.view', 'module' => 'gep-reports', 'action' => 'view', 'name' => 'View GEP Reports']];
        foreach (self::SECTIONS as $key => $item) {
            $rows[] = ['code' => 'gep-reports.'.$key.'.view', 'module' => 'gep-reports.'.$key, 'action' => 'view', 'name' => 'View GEP '.$item['label']];
        }
        return $rows;
    }

    public static function registerMissing(): void
    {
        foreach (self::catalog() as $row) DB::table('access_permissions')->insertOrIgnore([...$row, 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function allows(?User $user, string $section): bool
    {
        if (!$user || !isset(self::SECTIONS[$section]) || !isset(ProgramWorkspace::available($user)['gep'])) return false;
        if ($user->isSuperAdmin()) return true;
        foreach (array_unique([null, ...$user->accessibleCampuses()->pluck('tb_school_info.id')->all()]) as $campus) {
            if ($user->hasPermission('program-workspaces.view', $campus) && $user->hasPermission('program-workspaces.gep.view', $campus)
                && $user->hasPermission('gep-reports.view', $campus) && $user->hasPermission('gep-reports.'.$section.'.view', $campus)) return true;
        }
        return false;
    }
}
