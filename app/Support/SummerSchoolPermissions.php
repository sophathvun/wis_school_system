<?php

namespace App\Support;

use App\Models\StudentEnrollment;
use App\Models\User;

class SummerSchoolPermissions
{
    private array $grants = [];
    private ?array $campuses = null;
    private ?bool $superAdmin = null;
    private StudentEnrollmentPermissions $studentAccess;

    public function __construct(private readonly ?User $user)
    {
        $this->studentAccess = new StudentEnrollmentPermissions($user);
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
            'view' => 'View Summer School List (All Campuses)',
            'filter' => 'Search and Filter Summer Students (All Campuses)',
            'create' => 'Register Summer Students, Family, Photo and Documents (All Campuses)',
            'update' => 'Edit Summer Students, Family, Photo and Documents (Own Campus; Central: All Campuses)',
            'delete' => 'Delete Summer Enrollments (Own Campus; Central: All Campuses)',
            'convert' => 'Continue Summer Students at Western (Own Campus; Central: All Campuses)',
        ];
    }

    public function allows(string $action, ?int $campus = null): bool
    {
        if (!$this->user || !isset(self::catalog()[$action])) return false;
        if ($this->superAdmin ??= $this->user->isSuperAdmin()) return true;
        $campus ??= $this->user->active_campus_id;
        return $this->grants[$action.':'.$campus] ??= $this->user->hasPermission('students.view', $campus)
            && $this->user->hasPermission('summer-school.view', $campus)
            && $this->user->hasPermission('summer-school.'.$action, $campus);
    }

    private function campusIds(): array
    {
        return $this->campuses ??= $this->user?->campuses()->where('tb_school_info.status', 1)
            ->pluck('tb_school_info.id')->map(fn ($id) => (int) $id)->all() ?? [];
    }

    public function canUseForm(): bool
    {
        if ($this->allows('create') || $this->allows('update')) return true;
        if (!$this->allows('view') || $this->studentAccess->isCentral()) return false;
        foreach ($this->campusIds() as $campus) if ($this->allows('update', $campus)) return true;
        return false;
    }

    public function canMutate(StudentEnrollment $record, string $action): bool
    {
        if (!in_array($action, ['update', 'delete', 'convert'], true)) return false;
        $record->loadMissing(['academicYear' => fn ($q) => $q->withTrashed()]);
        if ($record->academicYear?->period_type !== 'summer') return false;
        if ($this->studentAccess->isCentral()) return $this->allows($action);
        if (!in_array((int) $record->campus_id, $this->campusIds(), true)
            || !$this->allows($action, (int) $record->campus_id)) return false;
        // External students share their profile with any later regular enrollment.
        // An old Summer record must not bypass ownership after a transfer.
        return $action !== 'update' || $record->enrollment_origin !== 'external'
            || $this->studentAccess->canAccessStudentCampus((int) $record->student_id);
    }

    public function loadStudents(array $ids): void
    {
        $this->studentAccess->loadStudents($ids);
    }

    public function flags(): array
    {
        return collect(array_keys(self::catalog()))->mapWithKeys(fn ($action) => [$action => $this->allows($action)])
            ->put('form', $this->canUseForm())->all();
    }
}
