<?php

namespace App\Support;

use App\Models\{SchoolInfo, User};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GepEnrollmentPermissions
{
    public const ACTIONS = ['create' => 'Add GEP student enrollments', 'edit' => 'Edit GEP enrollments', 'delete' => 'Delete GEP enrollments', 'history' => 'View GEP activity history'];

    public static function registerMissing(): void
    {
        foreach (self::ACTIONS as $action => $label) DB::table('access_permissions')->insertOrIgnore([
            'code' => 'gep-students.enrollment.'.$action, 'module' => 'gep-students.enrollment', 'action' => $action,
            'name' => $label, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public static function allows(?User $user, string $action = 'view', ?int $campus = null): bool
    {
        if (!$user || !in_array($action, ['view', ...array_keys(self::ACTIONS)], true)) return false;
        if ($user->isSuperAdmin()) return isset(ProgramWorkspace::available($user)['gep']);
        $campuses = $campus === null ? $user->accessibleCampuses()->pluck('tb_school_info.id')->all() : [$campus];
        foreach ($campuses as $id) {
            if ($user->canAccessCampus((int) $id) && $user->hasPermission('program-workspaces.view', $id)
                && $user->hasPermission('program-workspaces.gep.view', $id) && $user->hasPermission('gep-students.view', $id)
                && $user->hasPermission('gep-students.enrollment.view', $id)
                && ($action === 'view' || $user->hasPermission('gep-students.enrollment.'.$action, $id))) return true;
        }
        return false;
    }

    public static function campuses(?User $user, string $action = 'view'): Collection
    {
        if (!$user) return collect();
        return SchoolInfo::where('status', 1)->orderBy('campus_name_en')->get(['id', 'campus_name_en'])
            ->filter(fn ($campus) => self::allows($user, $action, (int) $campus->id))->values();
    }
}
