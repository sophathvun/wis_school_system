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
        'print-reports'=>'requests',
        'create'=>'requests', 'update'=>'requests', 'central-update'=>'requests', 'submit'=>'requests', 'approve'=>'requests', 'reject'=>'requests', 'print'=>'requests',
        'save-settings'=>'settings', 'edit-request-template'=>'request-template', 'edit-approval-template'=>'approval-template',
        'save-campus-settings'=>'campus-settings',
        'archive-backup'=>'document-archive', 'archive-delete'=>'document-archive', 'archive-restore'=>'document-archive',
    ];

    public static function catalog(): array
    {
        return [
            'view'=>'Stu. Skipping Grade Submenu', 'requests'=>'Tab: Skipping Grade Requests (View All Campuses)',
            'print-reports'=>'Print A4 Skipping Reports (All Statuses and Campuses)',
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
            'document-archive'=>'Tab: Supporting Document Archive (All Campuses)',
            'archive-backup'=>'Create, Verify and Download Supporting Document Backups',
            'archive-delete'=>'Delete Verified, Externally Saved Documents from Older Academic Years',
            'archive-restore'=>'Restore Archived Supporting Documents',
        ];
    }

    public static function allows(?User $user, string $action, ?int $campus = null): bool
    {
        if (!$user || !array_key_exists($action,self::catalog())) return false;
        if ($user->isSuperAdmin()) return true;
        if ($campus !== null && in_array($action,self::CAMPUS_ACTIONS,true) && !self::assignedToCampus($user,$campus)) return false;
        $permissionCampus=self::permissionCampus($user);
        foreach (array_unique(['students.view', self::MODULE.'.view', self::MODULE.'.'.$action,
            self::MODULE.'.'.(self::PARENTS[$action]??$action)]) as $code) {
            if (!$user->hasPermission($code,$permissionCampus)) return false;
        }
        return true;
    }

    public static function permissionCampus(User $user): ?int
    {
        if ($user->active_campus_id) return (int)$user->active_campus_id;
        $campus=$user->campuses()->where('tb_school_info.status',1)
            ->orderByDesc('access_user_campuses.is_primary')->orderBy('tb_school_info.id')->value('tb_school_info.id');
        return $campus===null?null:(int)$campus;
    }

    public static function assignedToCampus(User $user, int $campus): bool
    {
        return $user->campuses()->whereKey($campus)->where('tb_school_info.status',1)->exists();
    }

    public static function assignedToCentral(User $user): bool
    {
        return self::centralCampuses($user)->isNotEmpty();
    }

    public static function centralCampuses(User $user)
    {
        return $user->campuses()->where('tb_school_info.status',1)
            ->whereRaw('UPPER(TRIM(tb_school_info.campus_name_en)) IN (?, ?, ?)',['CEN','CENTRAL','CENTRAL OFFICE'])->get();
    }

    public static function hasReviewerAccess(User $user, ?int $campus): bool
    {
        $campuses=$campus!==null?[$campus]:self::centralCampuses($user)->modelKeys();
        foreach ($campuses as $campusId) {
            if ($user->hasPermission('students.view',$campusId) && $user->hasPermission(self::MODULE.'.view',$campusId)) return true;
        }
        return false;
    }

    public static function canEdit(?User $user, StudentSkippingGrade $record): bool
    {
        if ($record->usesSequentialFlow() && $record->status !== 'draft') return false;
        return in_array($record->status,['draft','pending'],true) && (
            self::allows($user,'central-update')
            || ($record->status==='draft' && self::allows($user,'update',$record->campus_id))
        );
    }
}
