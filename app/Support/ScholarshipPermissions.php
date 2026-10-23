<?php

namespace App\Support;

use App\Models\User;
use App\Models\SchoolInfo;
use Illuminate\Support\Facades\DB;

class ScholarshipPermissions
{
    public const TABS = [
        'answer-sheets'=>'tab-answer-sheets',
        'assign-rank'=>'tab-assign-rank', 'seat-plan'=>'tab-seat-plan', 'scoring'=>'tab-scoring',
        'result'=>'tab-result', 'scholarship-result'=>'tab-scholarship-result', 'certificates'=>'tab-certificates', 'exam-committee'=>'tab-exam-committee', 'settings'=>'tab-settings', 'data-transfer'=>'tab-data-transfer',
    ];
    public const PARENTS = [
        'answer-paper'=>'tab-answer-sheets','answer-key'=>'tab-answer-sheets','answer-key-approve'=>'tab-answer-sheets',
        'answer-key-print'=>'tab-answer-sheets','answer-key-scan'=>'tab-answer-sheets',
        'answer-link'=>'tab-answer-sheets','answer-unlink'=>'tab-answer-sheets','answer-print'=>'tab-answer-sheets','answer-scan'=>'tab-answer-sheets','answer-verify'=>'tab-answer-sheets','answer-marked-view'=>'tab-answer-sheets','answer-replace'=>'tab-answer-sheets','answer-delete'=>'tab-answer-sheets','answer-clear-scans'=>'tab-answer-sheets',
        'certificate-settings'=>'tab-certificates', 'certificate-template'=>'tab-certificates', 'certificate-assign'=>'tab-certificates', 'certificate-print'=>'tab-certificates', 'certificate-cancel'=>'tab-certificates',
        'assign-rank'=>'tab-assign-rank', 'seat-plan'=>'tab-seat-plan', 'reset-for-reseating'=>'tab-seat-plan', 'scoring'=>'tab-scoring',
        'review-scores'=>'tab-scoring', 'awards'=>'tab-result', 'publish-results'=>'tab-result',
        'committee'=>'tab-exam-committee', 'settings'=>'tab-settings', 'import-history'=>'tab-data-transfer',
        'export-ranks'=>'tab-assign-rank', 'export-seats'=>'tab-seat-plan', 'export-scores'=>'tab-scoring',
        'export-results'=>'tab-result', 'export-result-scores'=>'tab-result', 'result-print-settings'=>'tab-result',
        'export-scholarship-result'=>'tab-scholarship-result', 'export-committee'=>'tab-exam-committee', 'export-data'=>'tab-data-transfer',
    ];

    public static function catalog(): array
    {
        return [
            'tab-answer-sheets'=>'Tab: Answer Sheets', 'answer-paper'=>'Set up shared grade / subject papers',
            'answer-key'=>'Edit shared draft answer keys', 'answer-key-approve'=>'Approve shared answer key revisions',
            'answer-key-print'=>'Print blank teacher answer-key sheets',
            'answer-key-scan'=>'Scan/upload teacher answer keys (requires Edit shared draft answer keys)',
            'answer-link'=>'Link grade students to answer-sheet papers',
            'answer-unlink'=>'Unlink students from unscanned answer-sheet papers',
            'answer-print'=>'Issue and print personalized answer sheets', 'answer-scan'=>'Upload / photograph answer sheets',
            'answer-verify'=>'Verify scans and save draft scores (Central only; score-entry duties required)',
            'answer-marked-view'=>'View marked student answer sheets, correct answers and scores (Central only)',
            'answer-replace'=>'Replace an existing score or recheck with a revised key (Central only)',
            'answer-delete'=>'Delete unlinked answer-sheet papers (Central only)',
            'answer-clear-scans'=>'Clear Test Scans and scan-created scores (Central only)',
            'tab-certificates'=>'Tab: Certificates', 'certificate-settings'=>'Set certificate dates, signers and frame / logo (Central only)',
            'certificate-template'=>'Customize certificate text, positions, fonts and colors (Central only)',
            'certificate-assign'=>'Preview and assign school-wide certificate numbers (Central only)',
            'certificate-print'=>'Preview, print and download certificates as PDF',
            'certificate-cancel'=>'Cancel issued certificates and retain their numbers (Central only)',
            'tab-assign-rank'=>'Tab: Assign Student Rank', 'tab-seat-plan'=>'Tab: Seat Plan',
            'tab-scoring'=>'Tab: Scoring', 'tab-result'=>'Tab: Result (Central / confidential scores)',
            'tab-scholarship-result'=>'Tab: Scholarship Result (published award winners only)',
            'tab-exam-committee'=>'Tab: Exam Committee', 'tab-settings'=>'Tab: Scholarship Settings',
            'tab-data-transfer'=>'Tab: Import / Export', 'import-history'=>'Import historical scholarship records',
            'view'=>'View WIS Scholarship', 'assign-rank'=>'Assign/remove candidate class ranks, EOY averages and rank-list signers',
            'settings'=>'Manage exam details, subjects, subject order, grade schedules and exam center locations', 'seat-plan'=>'Generate, confirm and reopen scholarship seat plans',
            'reset-for-reseating'=>'Reset Exam for Reseating — clear test work and reopen seats (Central only)',
            'scoring'=>'Unlock score entry, mark subject/all-exam absences and save score drafts', 'review-scores'=>'Mark scholarship exam scores as reviewed',
            'committee'=>'Manage committee members, invigilators, Excel imports, room duties and linked sessions', 'publish-results'=>'Publish, lock and reopen scholarship results',
            'export'=>'Print and export scholarship reports', 'awards'=>'Select and edit scholarship award winners',
            'export-ranks'=>'Print / Excel / PDF candidate rank lists',
            'export-seats'=>'Print / Excel / PDF candidate, attendance, cover, sticker and seat-plan reports',
            'export-scores'=>'Print / Excel / PDF score verification lists',
            'export-results'=>'Print / Excel / PDF Central result lists',
            'export-result-scores'=>'Print / Excel / CSV confidential result scores (Central only)',
            'result-print-settings'=>'Set result report signers and signing dates (Central only)',
            'export-scholarship-result'=>'Print / Excel / PDF published awarded students only (no scores)',
            'export-committee'=>'Download invigilators and room assignments',
            'export-data'=>'Export scholarship records and scores (Central only)',
        ];
    }

    /** Register missing catalog rows without changing any role, department or user grants. */
    public static function registerMissing(): void
    {
        $catalog=self::catalog();
        $existing=DB::table('access_permissions')->whereIn('code',array_map(fn($action)=>'western-scholarship.'.$action,array_keys($catalog)))->pluck('code')->flip();
        $rows=[];$now=now();
        foreach($catalog as $action=>$name){
            $code='western-scholarship.'.$action;
            if(!$existing->has($code))$rows[]=['code'=>$code,'module'=>'western-scholarship','action'=>$action,'name'=>$name,'created_at'=>$now,'updated_at'=>$now];
        }
        if($rows)DB::table('access_permissions')->insertOrIgnore($rows);
    }

    /** Main menu > WIS Scholarship submenu > tab > the tab's functions. */
    public static function tabGroups(): array
    {
        return [
            'answer-sheets'=>['label'=>'Answer Sheets','actions'=>['answer-paper','answer-key','answer-key-approve','answer-key-print','answer-key-scan','answer-link','answer-unlink','answer-print','answer-scan','answer-verify','answer-marked-view','answer-replace','answer-delete','answer-clear-scans']],
            'assign-rank'=>['label'=>'Assign Student Rank','actions'=>['assign-rank','export-ranks']],
            'settings'=>['label'=>'Scholarship Settings','actions'=>['settings']],
            'seat-plan'=>['label'=>'Seat Plan','actions'=>['seat-plan','reset-for-reseating','export-seats']],
            'scoring'=>['label'=>'Scoring','actions'=>['scoring','review-scores','export-scores']],
            'result'=>['label'=>'Result — Central / Confidential','actions'=>['awards','publish-results','result-print-settings','export-results','export-result-scores']],
            'scholarship-result'=>['label'=>'Scholarship Result — Published Award Winners','actions'=>['export-scholarship-result']],
            'certificates'=>['label'=>'Certificates','actions'=>['certificate-settings','certificate-template','certificate-assign','certificate-print','certificate-cancel']],
            'exam-committee'=>['label'=>'Exam Committee','actions'=>['committee','export-committee']],
            'data-transfer'=>['label'=>'Import / Export','actions'=>['import-history','export-data']],
        ];
    }

    public static function isCentral(User $user): bool
    {
        if($user->isSuperAdmin())return true;
        return self::scope($user)[2];
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
        if(($action==='tab-result' || (self::PARENTS[$action]??null)==='tab-result'
            || in_array($action,['tab-data-transfer','import-history','export-data','certificate-settings','certificate-template','certificate-assign','certificate-cancel','answer-verify','answer-marked-view','answer-replace','answer-delete','answer-clear-scans','reset-for-reseating'],true)) && !self::isCentral($user))return false;
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
