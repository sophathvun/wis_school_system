<?php

namespace App\Support;

use App\Models\SchoolInfo;
use App\Models\User;

class PlacementTestPermissions
{
    public const SECTIONS = [
        'dashboard' => ['Dashboard', 'ti-layout-dashboard', 'view'],
        'applicants' => ['Applicants', 'ti-user-plus', 'applicants'],
        'questions' => ['Question Bank', 'ti-library', 'questions'],
        'forms' => ['Test Forms', 'ti-forms', 'forms'],
        'sessions' => ['Test Sessions', 'ti-device-laptop', 'assign'],
        'marking' => ['Marking & Scan', 'ti-scan', 'score'],
        'results' => ['Results', 'ti-chart-bar', 'results'],
        'enrollment' => ['Enrollment Queue', 'ti-school', 'enroll'],
        'settings' => ['Settings', 'ti-settings', 'settings'],
    ];

    public static function catalog(): array
    {
        return [
            'view' => 'View Student Placement Test', 'applicants' => 'Manage Placement Test Applicants', 'questions' => 'Manage Placement Test Question Bank',
            'forms' => 'Manage Placement Test Forms', 'publish' => 'Approve and Publish Placement Test Forms', 'assign' => 'Assign Placement Tests',
            'score' => 'Review and Score Placement Tests', 'scan' => 'Upload and Verify Placement Test Answer Sheets', 'results' => 'View Placement Test Results',
            'enroll' => 'Send Passed Applicants to Student Enrollment', 'settings' => 'Manage Placement Test Settings',
        ];
    }

    public static function allows(?User $user, string $action, ?int $campusId = null): bool
    {
        if (! $user || ! isset(self::catalog()[$action])) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        $campus = $campusId ?? $user->active_campus_id;

        return $user->hasPermission('students.view', $campus) && $user->hasPermission('placement-tests.view', $campus)
            && ($action === 'view' || $user->hasPermission('placement-tests.'.$action, $campus));
    }

    public static function section(?User $user, ?string $requested): string
    {
        if ($requested && isset(self::SECTIONS[$requested]) && self::allows($user, self::SECTIONS[$requested][2])) {
            return $requested;
        }
        foreach (self::SECTIONS as $key => $section) {
            if (self::allows($user, $section[2])) {
                return $key;
            }
        }
        abort(403);
    }

    public static function campusIds(User $user, string $action = 'view')
    {
        if ($user->isSuperAdmin()) {
            return SchoolInfo::where('status', 1)->pluck('id');
        }
        $ids = $user->is_global ? SchoolInfo::where('status', 1)->pluck('id') : $user->campuses()->where('tb_school_info.status', 1)->pluck('tb_school_info.id');

        return $ids->filter(fn ($campusId) => self::allows($user, $action, (int) $campusId))->values();
    }
}
