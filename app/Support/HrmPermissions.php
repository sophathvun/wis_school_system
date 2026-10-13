<?php

namespace App\Support;

use App\Models\User;

class HrmPermissions
{
    public static function catalog(): array
    {
        return [
            'hrm' => ['view' => 'View HRM Menu'],
            'staff' => [
                'view' => 'View Staff Directory, Profiles and Work History',
                'create' => 'Create Staff (Personal, Work, Education and Previous Employment)',
                'edit' => 'Edit Staff, Photos, Contracts, Schedules and Promotions / Transfers',
                'status' => 'Activate / Deactivate Staff Profiles',
                'delete' => 'Delete Staff Profiles',
            ],
            'hrm-settings' => ['view' => 'View HRM Settings'],
            'hrm-staff-data' => ['view'=>'View Staff Import / Export Tab','template'=>'Download Staff CSV Template','export'=>'Export Staff Information to Excel','import'=>'Import New Staff from CSV'],
            'departments' => [
                'view' => 'View Department List', 'create' => 'Create Departments / Subdepartments',
                'update' => 'Edit Departments / Parent Department', 'status' => 'Activate / Deactivate Departments',
                'delete' => 'Delete Departments', 'print' => 'Print Department List', 'export' => 'Export Department List to Excel',
                'manage' => 'Manage Departments (Create, Edit and Status)', 'permissions' => 'Assign Department Permissions',
            ],
            'positions' => [
                'view' => 'View Position List', 'create' => 'Create Positions', 'update' => 'Edit Positions / Department',
                'status' => 'Activate / Deactivate Positions', 'delete' => 'Delete Positions',
                'print' => 'Print Position List', 'export' => 'Export Position List to Excel',
                'manage' => 'Manage Positions (Create, Edit and Status)',
            ],
            'hrm-staff-attendance' => ['view' => 'View Staff Attendance'],
            'hrm-payroll' => ['view' => 'View Payroll'],
            'hrm-leave-requests' => ['view' => 'View Leave Requests'],
            'hrm-performance-reviews' => ['view' => 'View Performance Reviews'],
            'hrm-staff-reports' => ['view' => 'View Staff Reports'],
        ];
    }

    public static function allows(?User $user, string $module, string $action = 'view'): bool
    {
        if (!$user || !isset(self::catalog()[$module][$action])) return false;
        if ($user->isSuperAdmin()) return true;
        $has = fn(string $code) => $user->hasPermission($code, $user->active_campus_id);
        if (!$has('hrm.view')) return false;
        if ($module === 'hrm') return true;
        if (!$has($module.'.view')) return false;
        if (in_array($module, ['departments', 'positions','hrm-staff-data'], true) && !$has('hrm-settings.view')) return false;
        return $has($module.'.'.$action)
            || (in_array($module, ['departments', 'positions'], true)
                && in_array($action, ['create', 'update', 'status'], true) && $has($module.'.manage'));
    }

    public static function settingFlags(?User $user, string $module): array
    {
        return collect(['create', 'update', 'status', 'delete', 'print', 'export'])
            ->mapWithKeys(fn($action) => ['can'.ucfirst($action).'Setting' => self::allows($user,$module,$action)])->all();
    }
}
