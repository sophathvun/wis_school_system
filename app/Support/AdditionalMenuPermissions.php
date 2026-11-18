<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdditionalMenuPermissions
{
    public const MODULES = [
        'financial-management' => 'Financial Management',
        'financial-management.school-fee' => 'School Fee',
        'financial-management.invoices' => 'Invoices',
        'financial-management.payments' => 'Payments',
        'financial-management.expenses' => 'Expenses',
        'student-discipline' => 'Student Discipline',
        'reports.hrm' => 'HRM Reports',
        'reports.financial' => 'Financial Reports',
    ];

    public static function catalog(): array
    {
        $rows = [];
        foreach (self::MODULES as $module => $label) {
            $rows[] = ['code' => $module.'.view', 'module' => $module, 'action' => 'view', 'name' => 'View '.$label];
        }
        return $rows;
    }

    public static function registerMissing(): void
    {
        foreach (self::catalog() as $row) {
            DB::table('access_permissions')->insertOrIgnore([...$row, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public static function allows(?User $user, string $code): bool
    {
        if (!$user || !in_array($code, array_column(self::catalog(), 'code'), true)) return false;
        if ($user->isSuperAdmin()) return true;
        $parent = str_starts_with($code, 'financial-management.') && $code !== 'financial-management.view'
            ? 'financial-management.view' : (str_starts_with($code, 'reports.') ? 'reports.view' : null);
        $campuses = $user->active_campus_id !== null ? [(int) $user->active_campus_id]
            : [null, ...$user->accessibleCampuses()->pluck('tb_school_info.id')->all()];
        foreach ($campuses as $campus) {
            if ($user->hasPermission($code, $campus) && (!$parent || $user->hasPermission($parent, $campus))) return true;
        }
        return false;
    }
}
