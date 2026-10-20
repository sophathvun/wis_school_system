<?php

namespace App\Support;

use App\Models\StudentEnrollment;
use App\Models\User;

/** Request-local permissions: reading/creating is global; existing records have campus ownership. */
class StudentEnrollmentPermissions
{
    private array $grants = [];
    private ?array $assignedCampuses = null;
    private ?bool $central = null;
    private ?bool $superAdmin = null;
    private array $studentCampuses = [];

    public function __construct(private readonly ?User $user) {}

    public static function current(): self
    {
        $request = request();
        if (!$request->attributes->has(self::class)) {
            $request->attributes->set(self::class, new self($request->user()));
        }
        return $request->attributes->get(self::class);
    }

    public static function catalog(): array
    {
        return [
            'view' => 'View Student Enrollment List and Statistics (All Campuses)',
            'filter' => 'Search and Filter Students (All Campuses)',
            'create' => 'Add New Enrollment, Student, Family and Photo (All Campuses)',
            'update' => 'Edit Student Information, Family and Photo (Own Campus; Central: All Campuses)',
            'delete' => 'Delete Enrollment Records (Own Campus; Central: All Campuses)',
            'profile' => 'View Student Profile, Family and Siblings (All Campuses)',
            'history' => 'View Enrollment History and Academic Years (All Campuses)',
            'print' => 'Print Student Profile Report',
            'export' => 'Save Student Profile Report as PDF',
        ];
    }

    public function allows(string $action, ?int $permissionCampus = null): bool
    {
        if (!$this->user || !isset(self::catalog()[$action])) return false;
        if ($this->isSuperAdmin()) return true;
        $campus = $permissionCampus ?? $this->user->active_campus_id;
        $key = $action.':'.$campus;
        return $this->grants[$key] ??= $this->user->hasPermission('students.view', $campus)
            && $this->user->hasPermission('students.enrollment.view', $campus)
            && $this->user->hasPermission('students.enrollment.'.$action, $campus);
    }

    public function isCentral(): bool
    {
        if (!$this->user) return false;
        return $this->central ??= $this->isSuperAdmin() || $this->user->is_global
            || $this->user->roles()->where('access_roles.status', 1)->where('is_global', true)->exists()
            || StudentSkippingGradePermissions::assignedToCentral($this->user);
    }

    private function isSuperAdmin(): bool
    {
        return $this->superAdmin ??= $this->user?->isSuperAdmin() ?? false;
    }

    private function ownsCampus(?int $campus): bool
    {
        if (!$this->user || !$campus) return false;
        return in_array($campus, $this->assignedCampusIds(), true);
    }

    private function assignedCampusIds(): array
    {
        return $this->assignedCampuses ??= $this->user?->campuses()->where('tb_school_info.status', 1)
            ->pluck('tb_school_info.id')->map(fn ($id) => (int) $id)->all() ?? [];
    }

    public function canEditAnyCampus(): bool
    {
        if ($this->allows('update')) return true;
        if (!$this->allows('view') || $this->isCentral()) return false;
        foreach ($this->assignedCampusIds() as $campus) if ($this->allows('update', $campus)) return true;
        return false;
    }

    public function canUseForm(): bool
    {
        return $this->allows('create') || $this->canEditAnyCampus();
    }

    public function canEditStudent(int $studentId): bool
    {
        if (!$this->canEditAnyCampus()) return false;
        if (!$this->canAccessStudentCampus($studentId)) return false;
        return $this->allows('update', $this->isCentral() ? null : $this->studentCampuses[$studentId]);
    }

    /** Load ownership in one query for the displayed page, rather than per row. */
    public function loadStudents(array $studentIds): void
    {
        if ($this->isCentral()) return;
        $missing = array_values(array_diff($studentIds, array_keys($this->studentCampuses)));
        if (!$missing) return;
        foreach ($missing as $id) $this->studentCampuses[$id] = null;
        $records = StudentEnrollment::query()->join('tb_academic_year as ownership_year', 'ownership_year.id', '=', 'tb_student_enrollment.academic_year_id')
            ->whereIn('tb_student_enrollment.student_id', $missing)
            ->orderByRaw("CASE WHEN ownership_year.period_type = 'regular' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN ownership_year.lifecycle_status = 'started' THEN 0 ELSE 1 END")
            ->orderByDesc('ownership_year.academic_year')
            ->orderByRaw("CASE WHEN tb_student_enrollment.enrollment_status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('tb_student_enrollment.id')
            ->get(['tb_student_enrollment.student_id', 'tb_student_enrollment.campus_id'])->unique('student_id');
        foreach ($records as $record) $this->studentCampuses[$record->student_id] = (int) $record->campus_id;
    }

    public function canAccessStudentCampus(int $studentId): bool
    {
        if ($this->isCentral()) return true;
        $this->loadStudents([$studentId]);
        return $this->ownsCampus($this->studentCampuses[$studentId] ?? null);
    }

    public function canUploadDocuments(int $studentId): bool
    {
        if ($this->canAccessStudentCampus($studentId)) return true;
        // The enrollment form uploads its attachments immediately after Save.
        // Keep that authorized creation flow available for other campuses.
        $created = request()->hasSession() ? request()->session()->get('enrollment_document_creation.'.$studentId) : null;
        return $this->allows('create') && is_array($created)
            && ($created['user_id'] ?? null) === $this->user?->id
            && ($created['expires_at'] ?? 0) > now()->timestamp;
    }

    public function canMutate(StudentEnrollment $record, string $action): bool
    {
        if ($this->isCentral()) return $this->allows($action);
        return $this->ownsCampus((int) $record->campus_id)
            && $this->canAccessStudentCampus((int) $record->student_id)
            && $this->allows($action, (int) $record->campus_id);
    }

    public function flags(): array
    {
        return collect(array_keys(self::catalog()))->mapWithKeys(fn ($action) => [$action =>
            $action === 'update' ? $this->canEditAnyCampus() : $this->allows($action)])->all();
    }
}
