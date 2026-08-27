<?php

namespace App\Support;

use App\Models\Permission;
use Illuminate\Support\Collection;

class PermissionHierarchy
{
    public static function moduleLabels(): array
    {
        return [
            'users' => 'Users', 'departments' => 'Departments', 'positions' => 'Positions', 'roles' => 'Roles', 'notifications' => 'Notifications', 'chat' => 'Chat',
            'academic-years' => 'Academic Years', 'grades' => 'Grades', 'classes' => 'Classes', 'sessions' => 'Sessions',
            'education-levels' => 'Education Levels', 'programs' => 'Programs', 'school-info' => 'School Information',
            'locations' => 'Locations', 'occupations' => 'Occupations', 'nationalities' => 'Nationalities', 'academic-tracks' => 'Academic Tracks', 'withdrawal-reasons' => 'Withdrawal Reasons',
            'student-document-types' => 'Document Types', 'dashboard-templates' => 'Dashboard Templates', 'branding' => 'Branding', 'database-backups' => 'Database Backups',
            'groups' => 'School Groups', 'terms' => 'Terms', 'campuses' => 'Campuses', 'summer-school' => 'Summer School', 'student-withdrawals' => 'Student Withdrawals',
            'reports' => 'Reports',
            'students.search' => 'Search Students', 'students.enrollment' => 'Student Enrollment', 'families' => 'Family Management',
            'students.promotion' => 'Student Promotion / Transfer', 'students.graduation' => 'Student Graduation',
            'student-reentry' => 'Student Re-entry', 'student-documents' => 'Student Documents', 'student-data-transfer' => 'Import / Export Data',
        ];
    }

    public static function groups(): array
    {
        return [
            'administrator' => [
                'label' => 'Administrator', 'permission' => 'administrator.view',
                'modules' => ['users', 'departments', 'positions', 'roles', 'dashboard-templates', 'branding', 'database-backups', 'campuses'],
                'actions' => [],
            ],
            'communication' => [
                'label' => 'Communication', 'permission' => 'communication.view',
                'modules' => ['chat', 'notifications'],
                'actions' => [],
            ],
            'settings' => [
                'label' => 'Settings', 'permission' => 'settings.view',
                'modules' => ['academic-years', 'grades', 'classes', 'sessions', 'education-levels', 'programs', 'groups', 'terms', 'school-info', 'locations', 'occupations', 'nationalities', 'academic-tracks', 'withdrawal-reasons', 'student-document-types'],
                'actions' => ['settings.manage'],
            ],
            'students' => [
                'label' => 'Students', 'permission' => 'students.view',
                'modules' => ['students.search', 'students.enrollment', 'summer-school', 'families', 'students.promotion', 'students.graduation', 'student-withdrawals', 'student-reentry', 'student-documents', 'student-data-transfer'],
                'actions' => ['students.manage'],
            ],
            'dashboard' => [
                'label' => 'Dashboard', 'permission' => 'dashboard.view',
                'modules' => [],
                'actions' => ['dashboard.customize', 'dashboard.reset'],
            ],
            'reports' => [
                'label' => 'Reports', 'permission' => 'reports.view',
                'modules' => [],
                'actions' => ['reports.export'],
            ],
        ];
    }

    public static function tree(Collection $permissions): array
    {
        $byCode = $permissions->keyBy('code');
        $tree = [];

        foreach (self::groups() as $key => $group) {
            $modules = [];
            foreach ($group['modules'] as $module) {
                $viewCode = "{$module}.view";
                $items = $permissions->filter(fn (Permission $permission) => str_starts_with($permission->code, "{$module}.") && $permission->code !== $viewCode)->values();
                if ($byCode->has($viewCode)) {
                    $modules[$module] = ['label' => self::moduleLabels()[$module] ?? ucwords(str_replace(['.', '-', '_'], ' ', $module)), 'permission' => $byCode->get($viewCode), 'actions' => $items];
                }
            }
            $actions = collect($group['actions'])->map(fn ($code) => $byCode->get($code))->filter()->values();
            $tree[$key] = ['label' => $group['label'], 'permission' => $byCode->get($group['permission']), 'modules' => $modules, 'actions' => $actions];
        }

        return $tree;
    }

    public static function normalizeIds(array $ids, Collection $permissions): array
    {
        $byId = $permissions->keyBy('id');
        $selected = collect($ids)->map(fn ($id) => (int) $id)->filter(fn ($id) => $byId->has($id))->values();
        $codes = $selected->map(fn ($id) => $byId->get($id)->code)->flip();

        foreach (self::groups() as $group) {
            $mainOn = $codes->has($group['permission']);
            if (!$mainOn) {
                foreach ($group['actions'] as $action) $codes->forget($action);
            }
            foreach ($group['modules'] as $module) {
                $submenuCode = "{$module}.view";
                $submenuOn = $mainOn && $codes->has($submenuCode);
                if (!$mainOn) $codes->forget($group['permission']);
                if (!$submenuOn) {
                    $codes->forget($submenuCode);
                    foreach ($codes->keys() as $code) {
                        if (str_starts_with($code, "{$module}.")) $codes->forget($code);
                    }
                }
            }
        }

        return $codes->keys()->map(fn ($code) => $byId->firstWhere('code', $code)->id)->values()->all();
    }
}
