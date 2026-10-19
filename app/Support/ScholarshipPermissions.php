<?php

namespace App\Support;

use App\Models\User;
use App\Models\SchoolInfo;

class ScholarshipPermissions
{
    public const TABS = [
        'assign-rank'=>'tab-assign-rank', 'seat-plan'=>'tab-seat-plan', 'scoring'=>'tab-scoring',
        'result'=>'tab-result', 'exam-committee'=>'tab-exam-committee', 'settings'=>'tab-settings', 'data-transfer'=>'tab-data-transfer',
    ];
    public const PARENTS = [
        'assign-rank'=>'tab-assign-rank', 'seat-plan'=>'tab-seat-plan', 'scoring'=>'tab-scoring',
        'review-scores'=>'tab-scoring', 'awards'=>'tab-result', 'publish-results'=>'tab-result',
        'committee'=>'tab-exam-committee', 'settings'=>'tab-settings', 'import-history'=>'tab-data-transfer',
    ];

    public static function catalog(): array
    {
        return [
            'tab-assign-rank'=>'Tab: Assign Student Rank', 'tab-seat-plan'=>'Tab: Seat Plan',
            'tab-scoring'=>'Tab: Scoring', 'tab-result'=>'Tab: Result',
            'tab-exam-committee'=>'Tab: Exam Committee', 'tab-settings'=>'Tab: Scholarship Settings',
            'tab-data-transfer'=>'Tab: Import / Export', 'import-history'=>'Import historical scholarship records',
            'view'=>'View WIS Scholarship', 'assign-rank'=>'Assign and remove scholarship candidate class ranks',
            'settings'=>'Manage scholarship exam settings', 'seat-plan'=>'Generate and confirm scholarship seat plans',
            'scoring'=>'Enter scholarship exam scores', 'review-scores'=>'Review scholarship exam scores',
            'committee'=>'Manage scholarship exam committee', 'publish-results'=>'Publish and revise scholarship results',
            'export'=>'Print and export scholarship reports', 'awards'=>'Select and edit scholarship award winners',
        ];
    }

    private static function scope(User $user): array
    {
        $assigned=$user->campuses->filter(fn($campus)=>(bool)$campus->status);
        $central=$assigned->filter(fn($campus)=>in_array(strtoupper(trim($campus->campus_name_en)),['CEN','CENTRAL','CENTRAL OFFICE'],true));
        $all=(bool)$user->is_global || $central->isNotEmpty() || $user->roles->contains(fn($role)=>(bool)$role->is_global && (bool)$role->status);
        return [$assigned,$central,$all];
    }

    public static function campuses(User $user)
    {
        $query=SchoolInfo::where('status',1);
        if($user->isSuperAdmin())return $query;
        [$assigned,,$all]=self::scope($user);
        return $all?$query:$query->whereIn('id',$assigned->modelKeys());
    }

    public static function allows(User $user, string $action = 'view', ?int $campusId = null): bool
    {
        if(!array_key_exists($action,self::catalog()))return false;
        if ($user->isSuperAdmin()) return true;
        [$assigned,$central,$all]=self::scope($user);
        if($campusId && !self::campuses($user)->whereKey($campusId)->exists())return false;
        $campusIds = $campusId ? [$campusId] : ($all?self::campuses($user)->pluck('id')->all():$assigned->modelKeys());
        $campusIds=array_unique(array_merge($campusIds,$central->modelKeys()));
        foreach ($campusIds as $id) {
            if ($user->hasPermission('students.view', $id)
                && $user->hasPermission('western-scholarship.view', $id)
                && (!isset(self::PARENTS[$action]) || $user->hasPermission('western-scholarship.'.self::PARENTS[$action],$id))
                && ($action === 'view' || $user->hasPermission('western-scholarship.'.$action, $id))) return true;
        }
        return false;
    }
}
