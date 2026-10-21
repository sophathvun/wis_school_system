<?php

namespace App\Services;

use App\Models\Family;
use App\Models\FamilyHistory;

class FamilyHistoryRecorder
{
    public function snapshot(Family $family): array
    {
        return [
            'family' => $family->fresh()->only(['id', ...$family->getFillable()]),
            'members' => $family->members()->withTrashed()->orderBy('id')->get()->map(fn ($member) =>
                $member->only(['id', ...$member->getFillable(), 'deleted_at']))->all(),
            'students' => $family->students()->orderBy('tb_student.id')->get(['tb_student.id','tb_student.student_id','tb_student.full_name_en'])
                ->map(fn ($student) => $student->only(['id','student_id','full_name_en']))->all(),
        ];
    }

    public function record(Family $family, string $action, ?array $before, ?array $after): void
    {
        if ($before === $after) return;
        FamilyHistory::create([
            'family_id' => $family->id, 'action' => $action,
            'changed_by' => auth()->id(), 'changed_by_name' => auth()->user()?->name ?? 'System',
            'before_values' => $before, 'after_values' => $after,
        ]);
    }
}
