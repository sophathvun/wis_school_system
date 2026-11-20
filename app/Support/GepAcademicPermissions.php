<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class GepAcademicPermissions
{
    public const MENUS = [
        'attendance' => ['label' => 'GEP Stu. Attendance', 'icon' => 'ti-calendar-check'],
        'grading' => ['label' => 'Grading', 'icon' => 'ti-report'],
        'student-schedule' => ['label' => 'GEP Student Schedule', 'icon' => 'ti-calendar-time'],
    ];

    public static function catalog(): array
    {
        $rows = [];
        foreach (self::MENUS as $key => $menu) $rows[] = ['code' => 'gep-'.$key.'.view', 'module' => 'gep-'.$key, 'action' => 'view', 'name' => 'View '.$menu['label']];
        return $rows;
    }

    public static function registerMissing(): void
    {
        foreach (self::catalog() as $row) DB::table('access_permissions')->insertOrIgnore([...$row, 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function allows(?User $user, string $menu): bool
    {
        if (!$user || !isset(self::MENUS[$menu]) || !isset(ProgramWorkspace::available($user)['gep'])) return false;
        if ($user->isSuperAdmin()) return true;
        foreach (array_unique([null, ...$user->accessibleCampuses()->pluck('tb_school_info.id')->all()]) as $campus) {
            if ($user->hasPermission('program-workspaces.view', $campus) && $user->hasPermission('program-workspaces.gep.view', $campus)
                && $user->hasPermission('gep-'.$menu.'.view', $campus)) return true;
        }
        return false;
    }
}
