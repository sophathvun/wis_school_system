<?php

namespace App\Support;

use App\Models\{Permission, SchoolInfo, User};

class AttendancePermissions
{
    public const PARENTS = [
        'subject'=>'view', 'daily'=>'view', 'reports'=>'view', 'settings'=>'view',
        'take'=>'subject', 'subject-delete'=>'subject', 'reopen'=>'subject', 'history-export'=>'subject',
        'create'=>'daily', 'edit'=>'daily', 'delete'=>'daily', 'leave-create'=>'daily', 'leave-edit'=>'daily', 'leave-cancel'=>'daily', 'leave-attachment'=>'daily',
        'detail'=>'reports', 'statistics'=>'reports', 'print'=>'reports', 'excel'=>'reports', 'pdf'=>'reports',
        'weekdays'=>'settings', 'holidays'=>'settings', 'schedule-info'=>'settings', 'calendar-manage'=>'settings',
    ];

    public static function catalog(): array
    {
        return [
            'view'=>'Attendance: Main menu', 'subject'=>'Submenu: Subject Attendance', 'take'=>'Add / edit assigned subject attendance',
            'subject-delete'=>'Clear existing subject attendance marks',
            'reopen'=>'Reopen submitted subject attendance', 'history-export'=>'Export subject attendance history',
            'daily'=>'Submenu: Admin-Daily Attendance', 'create'=>'Add daily attendance / apply P to blank records',
            'edit'=>'Edit existing daily attendance and past dates', 'delete'=>'Delete daily attendance (audit history retained)',
            'leave-create'=>'Record approved student leave', 'leave-edit'=>'Shorten approved student leave',
            'leave-cancel'=>'Cancel remaining approved leave', 'leave-attachment'=>'View approved leave attachments',
            'reports'=>'Submenu: Attendance Reports (all campuses)', 'detail'=>'Tab: Student Absent Record',
            'statistics'=>'Tab: Attendance Statistics', 'print'=>'Print attendance reports', 'excel'=>'Export attendance reports to Excel', 'pdf'=>'Export attendance reports to PDF',
            'settings'=>'Submenu: Attendance Settings', 'weekdays'=>'Tab: School Week/Day', 'holidays'=>'Tab: Public Holiday',
            'schedule-info'=>'Tab: Schedule-based Subject Attendance', 'calendar-manage'=>'Save school weekdays / holidays in permitted campuses',
        ];
    }

    public static function sections(): array
    {
        return ['subject'=>['Subject Attendance',['take','subject-delete','reopen','history-export']],
            'daily'=>['Admin-Daily Attendance',['create','edit','delete','leave-create','leave-edit','leave-cancel','leave-attachment']],
            'reports'=>['Attendance Reports',['detail','statistics','print','excel','pdf']],
            'settings'=>['Attendance Settings',['weekdays','holidays','schedule-info','calendar-manage']]];
    }

    private static function granted(User $user, string $action, int $campus): bool
    {
        $code = 'attendance.'.$action;
        // Explicit denials must also override inherited attendance grants.
        if ($user->permissionOverrides()->where('code', $code)->wherePivot('allowed', false)->exists()) return false;
        if ($user->hasPermission($code, $campus)) return true;
        // Compatibility for isolated tests / installations before the catalog migration.
        if (!Permission::where('code', $code)->exists()) {
            $legacy = $action === 'history-export' ? 'take' : (in_array($action, ['subject','reports','detail','statistics','print','excel','pdf'], true) ? 'view' : 'manage');
            return $user->hasPermission('attendance.'.$legacy, $campus);
        }
        return false;
    }

    public static function allows(User $user, string $action, ?int $campus = null): bool
    {
        $request = request();
        $key = $user->id.':'.$action.':'.($campus ?? 'any');
        $cache = $request->attributes->get('_attendance_permissions', []);
        if (array_key_exists($key, $cache)) return $cache[$key];
        $allowed = self::check($user, $action, $campus);
        $cache = $request->attributes->get('_attendance_permissions', []);
        $cache[$key] = $allowed;
        $request->attributes->set('_attendance_permissions', $cache);
        return $allowed;
    }

    private static function check(User $user, string $action, ?int $campus): bool
    {
        if ($user->isSuperAdmin()) return $campus === null || $user->canAccessCampus($campus);
        if ($campus === null) return $user->accessibleCampuses()->get()->contains(fn ($c) => self::allows($user, $action, $c->id));
        if (!$user->canAccessCampus($campus) || !self::granted($user, $action, $campus)) return false;
        return !isset(self::PARENTS[$action]) || self::allows($user, self::PARENTS[$action], $campus);
    }

    public static function writeCampuses(User $user, string $action = 'daily'): array
    {
        return $user->accessibleCampuses()->get()->filter(fn ($c) => self::allows($user, $action, $c->id))->modelKeys();
    }

    public static function reportCampuses(User $user, string $tab): array
    {
        return self::allows($user, $tab) ? SchoolInfo::where('status', true)->pluck('id')->all() : [];
    }

    public static function flags(User $user): array
    {
        return collect(array_keys(self::catalog()))->mapWithKeys(fn ($action) => [$action => self::allows($user, $action)])->all();
    }
}
