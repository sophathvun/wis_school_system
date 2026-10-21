<?php

namespace App\Support;

use App\Models\Family;
use App\Models\Student;
use App\Models\User;

class FamilyPermissions
{
    private array $grants = [];
    private ?array $campuses = null;
    private ?bool $superAdmin = null;
    private StudentEnrollmentPermissions $enrollmentAccess;

    public function __construct(private readonly ?User $user)
    {
        $this->enrollmentAccess = new StudentEnrollmentPermissions($user);
    }

    public static function current(): self
    {
        $request = request();
        if (!$request->attributes->has(self::class)) $request->attributes->set(self::class, new self($request->user()));
        return $request->attributes->get(self::class);
    }

    public static function catalog(): array
    {
        return [
            'view' => 'View Families, Contacts and Children (All Campuses)',
            'search' => 'Search and Filter Families (All Campuses)',
            'create' => 'Create Family with Initial Parent / Guardian Details',
            'update' => 'Edit Family Information (Linked Current Campus Children; Central: All Families)',
            'members-create' => 'Add Mother, Father or Guardian to an Authorized Family',
            'members-update' => 'Edit Parent / Guardian Information in an Authorized Family',
            'members-delete' => 'Remove Parent / Guardian from an Authorized Family',
            'link' => 'Link Student to Family (Own Current Campus Students; Central: All Students)',
            'unlink' => 'Unlink Student from Family (Own Current Campus Students; Central: All Students)',
            'history' => 'View Family Change History (All Campuses)',
            'print' => 'Print Family List (All Campuses)',
            'export' => 'Export Family List to CSV (All Campuses)',
            'status' => 'Central Only: Activate / Deactivate Shared Families',
            'delete' => 'Central Only: Delete Families with No Linked Students',
        ];
    }

    public function isCentral(): bool { return $this->enrollmentAccess->isCentral(); }

    public function allows(string $action, ?int $campus = null): bool
    {
        if (!$this->user || !isset(self::catalog()[$action])) return false;
        if ($this->superAdmin ??= $this->user->isSuperAdmin()) return true;
        if (in_array($action, ['delete','status'], true) && !$this->isCentral()) return false;
        $campus ??= $this->user->active_campus_id;
        return $this->grants[$action.':'.$campus] ??= $this->user->hasPermission('students.view', $campus)
            && $this->user->hasPermission('families.view', $campus)
            && $this->user->hasPermission('families.'.$action, $campus);
    }

    public function campusIds(): array
    {
        return $this->campuses ??= $this->user?->campuses()->where('tb_school_info.status', 1)
            ->pluck('tb_school_info.id')->map(fn ($id) => (int) $id)->all() ?? [];
    }

    public function any(string $action): bool
    {
        if ($this->allows($action)) return true;
        if ($this->isCentral()) return false;
        foreach ($this->campusIds() as $campus) if ($this->allows($action, $campus)) return true;
        return false;
    }

    public function studentCampuses(Student $student): array
    {
        $student->loadMissing(['enrollments.academicYear', 'enrollments.campus']);
        return $student->enrollments->filter(fn ($record) => $record->enrollment_status === 'active'
            && (bool) $record->status && $record->academicYear?->lifecycle_status === 'started' && !$record->academicYear->trashed())
            ->pluck('campus_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public function studentAction(Student $student, string $action): bool
    {
        if (!in_array($action, ['link','unlink'], true)) return false;
        if ($this->isCentral()) return $this->allows($action);
        foreach (array_intersect($this->studentCampuses($student), $this->campusIds()) as $campus) {
            if ($this->allows($action, $campus)) return true;
        }
        return false;
    }

    public function familyAction(Family $family, string $action): bool
    {
        if ($this->isCentral()) return $this->allows($action);
        if (!in_array($action, ['update','members-create','members-update','members-delete'], true)) return false;
        $family->loadMissing('students.enrollments.academicYear');
        foreach ($family->students as $student) {
            foreach (array_intersect($this->studentCampuses($student), $this->campusIds()) as $campus) {
                if ($this->allows($action, $campus)) return true;
            }
        }
        return false;
    }

    public function describe(Family $family): array
    {
        $family->loadMissing(['students.enrollments.academicYear','students.enrollments.campus']);
        $names = [];
        foreach ($family->students as $student) foreach ($student->enrollments as $record) {
            if (in_array((int) $record->campus_id, $this->studentCampuses($student), true)) {
                $names[(int) $record->campus_id] = $record->campus?->campus_name_en ?: 'Campus '.$record->campus_id;
            }
        }
        return ['children' => $family->students->count(), 'campuses' => array_values(array_unique($names))];
    }

    public function flags(): array
    {
        return collect(array_keys(self::catalog()))->mapWithKeys(fn ($action) => [$action => $this->any($action)])->all();
    }
}
