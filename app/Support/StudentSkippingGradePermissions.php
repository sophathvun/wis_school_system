<?php

namespace App\Support;

use App\Models\User;
use App\Models\StudentSkippingGrade;

class StudentSkippingGradePermissions
{
    public const MODULE = 'student-skipping-grade';
    public const CENTRAL_ACTIONS = ['central-update', 'approve', 'reject', 'settings', 'save-settings', 'request-template', 'edit-request-template', 'approval-template', 'edit-approval-template'];
    public const CAMPUS_ACTIONS = ['create', 'update', 'submit', 'campus-settings', 'save-campus-settings'];
    public const PARENTS = [
        'create'=>'requests', 'update'=>'requests', 'central-update'=>'requests', 'submit'=>'requests', 'approve'=>'requests', 'reject'=>'requests', 'print'=>'requests',
        'save-settings'=>'settings', 'edit-request-template'=>'request-template', 'edit-approval-template'=>'approval-template',
        'save-campus-settings'=>'campus-settings',
    ];

    public static function catalog(): array
    {
        return [
            'view'=>'Stu. Skipping Grade Submenu', 'requests'=>'Tab: Skipping Grade Requests (View All Campuses)',
            'create'=>'Create Grade Skipping Requests (Assigned Campuses Only)',
            'update'=>'Edit Draft Grade Skipping Requests', 'submit'=>'Submit Parent-signed Requests',
            'central-update'=>'Central Office: Edit Draft and Submitted Requests (All Campuses)',
            'approve'=>'Central Office: Approve and Move Student Grade', 'reject'=>'Central Office: Reject Requests',
            'print'=>'Print Grade Skipping Request and Approval Forms (All Campuses)',
            'campus-settings'=>'Tab: Campus Settings (Assigned Campuses Only)',
            'save-campus-settings'=>'Save Campus Committee Names (Assigned Campuses Only)',
            'settings'=>'Tab: Approval Settings', 'save-settings'=>'Save Approval Settings, Committee, Signature and Stamp',
            'request-template'=>'Tab: Customize Request Form', 'edit-request-template'=>'Edit / Save / Discard / Restore Request Template',
            'approval-template'=>'Tab: Customize Approval Form', 'edit-approval-template'=>'Edit / Save / Discard / Restore Approval Template',
        ];
    }

    public static function allows(?User $user, string $action, ?int $campus = null): bool
    {
        if (!$user || !array_key_exists($action,self::catalog())) return false;
        if ($user->isSuperAdmin()) return true;
        if ($campus !== null && in_array($action,self::CAMPUS_ACTIONS,true) && !$user->canAccessCampus($campus)) return false;
        foreach (array_unique(['students.view', self::MODULE.'.view', self::MODULE.'.'.$action,
            self::MODULE.'.'.(self::PARENTS[$action]??$action)]) as $code) {
            if (!$user->hasPermission($code,$user->active_campus_id)) return false;
        }
        return true;
    }

    public static function canEdit(?User $user, StudentSkippingGrade $record): bool
    {
        return in_array($record->status,['draft','pending'],true) && (
            self::allows($user,'central-update')
            || ($record->status==='draft' && self::allows($user,'update',$record->campus_id))
        );
    }
}
