<?php

namespace App\Services;

use App\Models\EnrollmentWorkflowAction;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\StudentEnrollment;
use App\Models\StudentEnrollmentHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentWorkflowService
{
    private const PROMOTION_ACTIONS = ['promotion', 'class_promotion', 'selected_promotion', 're_promotion'];

    public function promoteClass(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $sourceEnrollments = StudentEnrollment::where('campus_id', $data['from_campus_id'])
                ->where('academic_year_id', $data['from_academic_year_id'])
                ->where('grade_id', $data['from_grade_id'])
                ->where('class_id', $data['from_class_id'])
                ->get();

            $alreadyPromoted = EnrollmentWorkflowAction::whereIn('student_id', $sourceEnrollments->pluck('student_id'))
                ->where('from_campus_id', $data['from_campus_id'])
                ->where('from_academic_year_id', $data['from_academic_year_id'])
                ->where('from_grade_id', $data['from_grade_id'])
                ->where('from_class_id', $data['from_class_id'])
                ->where('to_academic_year_id', $data['to_academic_year_id'])
                ->whereIn('action_type', self::PROMOTION_ACTIONS)
                ->where('status', '!=', 'cancelled')
                ->exists();
            if ($alreadyPromoted) {
                throw ValidationException::withMessages([
                    'from_class_id' => 'Students in this class have already been promoted to the selected academic year.',
                ]);
            }

            $enrollments = $sourceEnrollments->where('enrollment_status', 'active')->values();

            if ($enrollments->isEmpty()) {
                throw ValidationException::withMessages(['from_class_id' => 'No active students were found in the selected class.']);
            }

            $alreadyPromoted = StudentEnrollment::whereIn('student_id', $enrollments->pluck('student_id'))
                ->where('academic_year_id', $data['to_academic_year_id'])
                ->exists();
            if ($alreadyPromoted) {
                throw ValidationException::withMessages([
                    'from_class_id' => 'Students in this class have already been promoted to the selected academic year.',
                ]);
            }

            foreach ($enrollments as $enrollment) {
                $this->promote($enrollment, [
                    'to_campus_id' => $data['to_campus_id'] ?? $data['from_campus_id'],
                    'to_academic_year_id' => $data['to_academic_year_id'],
                    'to_grade_id' => $data['to_grade_id'],
                    'to_class_id' => $data['to_class_id'],
                    'to_session_id' => $data['to_session_id'] ?? null,
                    'effective_on' => $data['effective_on'],
                    'reason' => $data['reason'] ?? 'Class promotion',
                    'notes' => $data['notes'] ?? null,
                    'action_type' => 'class_promotion',
                ]);
            }

            return $enrollments->count();
        });
    }

    public function promoteSelected(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $sourceEnrollments = StudentEnrollment::whereIn('id', $data['enrollment_ids'])
                ->where('campus_id', $data['from_campus_id'])
                ->where('academic_year_id', $data['from_academic_year_id'])
                ->where('grade_id', $data['from_grade_id'])
                ->where('class_id', $data['from_class_id'])
                ->get();

            $alreadyPromoted = EnrollmentWorkflowAction::whereIn('student_id', $sourceEnrollments->pluck('student_id'))
                ->where('from_campus_id', $data['from_campus_id'])
                ->where('from_academic_year_id', $data['from_academic_year_id'])
                ->where('from_grade_id', $data['from_grade_id'])
                ->where('from_class_id', $data['from_class_id'])
                ->where('to_academic_year_id', $data['to_academic_year_id'])
                ->whereIn('action_type', self::PROMOTION_ACTIONS)
                ->where('status', '!=', 'cancelled')
                ->exists();
            if ($alreadyPromoted) {
                throw ValidationException::withMessages([
                    'enrollment_ids' => 'One or more selected students have already been promoted to the selected academic year.',
                ]);
            }

            $enrollments = $sourceEnrollments->where('enrollment_status', 'active')->values();

            if ($enrollments->count() !== count(array_unique($data['enrollment_ids']))) {
                throw ValidationException::withMessages(['enrollment_ids' => 'One or more selected students are not active in the selected class.']);
            }

            $alreadyPromoted = StudentEnrollment::whereIn('student_id', $enrollments->pluck('student_id'))
                ->where('academic_year_id', $data['to_academic_year_id'])
                ->exists();
            if ($alreadyPromoted) {
                throw ValidationException::withMessages([
                    'enrollment_ids' => 'One or more selected students have already been promoted to the selected academic year.',
                ]);
            }

            foreach ($enrollments as $enrollment) {
                $this->promote($enrollment, [
                    'to_campus_id' => $data['to_campus_id'] ?? $data['from_campus_id'],
                    'to_academic_year_id' => $data['to_academic_year_id'],
                    'to_grade_id' => $data['to_grade_id'],
                    'to_class_id' => $data['to_class_id'],
                    'to_session_id' => $data['to_session_id'] ?? null,
                    'effective_on' => $data['effective_on'],
                    'reason' => $data['reason'] ?? 'Selected student promotion',
                    'notes' => $data['notes'] ?? null,
                    'action_type' => 'selected_promotion',
                ]);
            }

            return $enrollments->count();
        });
    }

    public function transferSelected(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $enrollments = StudentEnrollment::whereIn('id', $data['enrollment_ids'])
                ->where('campus_id', $data['from_campus_id'])
                ->where('academic_year_id', $data['from_academic_year_id'])
                ->where('grade_id', $data['from_grade_id'])
                ->where('class_id', $data['from_class_id'])
                ->where('status', 1)
                ->where('enrollment_status', 'active')
                ->get();

            if ($enrollments->count() !== count(array_unique($data['enrollment_ids']))) {
                throw ValidationException::withMessages(['enrollment_ids' => 'One or more selected students are not active in the selected class.']);
            }

            foreach ($enrollments as $enrollment) {
                $this->transfer($enrollment, [
                    'to_campus_id' => $data['to_campus_id'],
                    'to_grade_id' => $data['to_grade_id'] ?? null,
                    'to_class_id' => $data['to_class_id'] ?? null,
                    'to_session_id' => $data['to_session_id'] ?? null,
                    'effective_on' => $data['effective_on'],
                    'reason' => $data['reason'] ?? 'Selected student transfer',
                    'notes' => $data['notes'] ?? null,
                    'action_type' => 'selected_transfer',
                ]);
            }

            return $enrollments->count();
        });
    }

    public function transferClass(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $enrollments = StudentEnrollment::where('campus_id', $data['from_campus_id'])
                ->where('academic_year_id', $data['from_academic_year_id'])
                ->where('grade_id', $data['from_grade_id'])
                ->where('class_id', $data['from_class_id'])
                ->where('enrollment_status', 'active')
                ->get();

            if ($enrollments->isEmpty()) {
                throw ValidationException::withMessages(['from_class_id' => 'No active students were found in the selected class.']);
            }

            foreach ($enrollments as $enrollment) {
                $this->transfer($enrollment, [
                    'to_campus_id' => $data['to_campus_id'],
                    'to_grade_id' => $data['to_grade_id'] ?? null,
                    'to_class_id' => $data['to_class_id'] ?? null,
                    'to_session_id' => $data['to_session_id'] ?? null,
                    'effective_on' => $data['effective_on'],
                    'reason' => $data['reason'] ?? 'Class transfer',
                    'notes' => $data['notes'] ?? null,
                    'action_type' => 'class_transfer',
                ]);
            }

            return $enrollments->count();
        });
    }

    public function promote(StudentEnrollment $source, array $data): StudentEnrollment
    {
        return DB::transaction(function () use ($source, $data) {
            $cancelledPromotion = EnrollmentWorkflowAction::query()
                ->where('source_enrollment_id', $source->id)
                ->where('to_academic_year_id', $data['to_academic_year_id'])
                ->where('status', 'cancelled')
                ->whereIn('action_type', self::PROMOTION_ACTIONS)
                ->latest('id')
                ->first();
            if ($cancelledPromotion) {
                return $this->repromote($cancelledPromotion, [
                    'effective_on' => $data['effective_on'],
                    'reason' => $data['reason'] ?? 'Student returned and was promoted again',
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            $this->assertNextGradePromotion($source, (int) $data['to_grade_id']);

            $duplicate = StudentEnrollment::where('student_id', $source->student_id)
                ->where('academic_year_id', $data['to_academic_year_id'])
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['to_academic_year_id' => 'This student already has an enrollment in the target academic year.']);
            }

            $target = StudentEnrollment::create([
                'student_id' => $source->student_id,
                'campus_id' => $data['to_campus_id'] ?? $source->campus_id,
                'academic_year_id' => $data['to_academic_year_id'],
                'grade_id' => $data['to_grade_id'],
                'class_id' => $data['to_class_id'],
                'session_id' => $data['to_session_id'] ?? null,
                'group_id' => null,
                'status' => 1,
                'student_type' => 'old',
                'enrollment_status' => AcademicYear::findOrFail($data['to_academic_year_id'])->lifecycle_status === 'started' ? 'active' : 'pending',
                'enrolled_on' => $data['effective_on'],
            ]);

            $source->update(['status' => 1, 'enrollment_status' => 'completed', 'ended_on' => $data['effective_on'], 'exit_reason' => 'Promoted']);
            $this->record($source, $target, $data['action_type'] ?? 'promotion', $data);
            return $target;
        });
    }

    public function cancelPromotion(EnrollmentWorkflowAction $workflow, array $data): EnrollmentWorkflowAction
    {
        return DB::transaction(function () use ($workflow, $data) {
            $workflow = EnrollmentWorkflowAction::query()->lockForUpdate()->findOrFail($workflow->id);
            if (!in_array($workflow->action_type, self::PROMOTION_ACTIONS, true)) {
                throw ValidationException::withMessages(['workflow' => 'Only promotion records can be cancelled.']);
            }
            if ($workflow->status === 'cancelled') {
                throw ValidationException::withMessages(['workflow' => 'This promotion is already cancelled.']);
            }

            $target = StudentEnrollment::lockForUpdate()->find($workflow->target_enrollment_id);
            if (!$target) {
                throw ValidationException::withMessages(['workflow' => 'The promoted enrollment no longer exists.']);
            }

            $target->update([
                'enrollment_status' => 'promotion_cancelled',
                'ended_on' => $data['effective_on'],
                'exit_reason' => 'Promotion cancelled',
                'notes' => $data['notes'] ?? $target->notes,
            ]);
            $workflow->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancellation_reason' => $data['reason'] ?? 'Student will not study in the target academic year.',
                'notes' => $data['notes'] ?? $workflow->notes,
            ]);
            $this->recordEnrollmentHistory($target, 'promotion_cancelled', $data['effective_on'], $data['reason'] ?? 'Promotion cancelled', $data['notes'] ?? null);

            return $workflow->fresh();
        });
    }

    public function repromote(EnrollmentWorkflowAction $workflow, array $data): StudentEnrollment
    {
        return DB::transaction(function () use ($workflow, $data) {
            $workflow = EnrollmentWorkflowAction::query()->lockForUpdate()->findOrFail($workflow->id);
            if (!in_array($workflow->action_type, self::PROMOTION_ACTIONS, true) || $workflow->status !== 'cancelled') {
                throw ValidationException::withMessages(['workflow' => 'Only a cancelled promotion can be promoted again.']);
            }
            $target = StudentEnrollment::lockForUpdate()->find($workflow->target_enrollment_id);
            if (!$target) {
                throw ValidationException::withMessages(['workflow' => 'The original target enrollment no longer exists.']);
            }
            $activeDuplicate = StudentEnrollment::where('student_id', $workflow->student_id)
                ->where('academic_year_id', $workflow->to_academic_year_id)
                ->where('enrollment_status', 'active')
                ->where('id', '!=', $target->id)
                ->exists();
            if ($activeDuplicate) {
                throw ValidationException::withMessages(['workflow' => 'This student already has an active enrollment in the target academic year.']);
            }

            $targetYear = AcademicYear::findOrFail($workflow->to_academic_year_id);
            $target->update([
                'status' => 1,
                'enrollment_status' => $targetYear->lifecycle_status === 'started' ? 'active' : 'pending',
                'enrolled_on' => $data['effective_on'],
                'ended_on' => null,
                'exit_reason' => null,
                'notes' => $data['notes'] ?? $target->notes,
            ]);
            $this->record($target, $target, 're_promotion', [
                'effective_on' => $data['effective_on'],
                'reason' => $data['reason'] ?? 'Student returned and was promoted again',
                'notes' => $data['notes'] ?? null,
                'action_type' => 're_promotion',
                'parent_workflow_id' => $workflow->id,
            ], [
                'campus_id' => $workflow->from_campus_id,
                'academic_year_id' => $workflow->from_academic_year_id,
                'grade_id' => $workflow->from_grade_id,
                'class_id' => $workflow->from_class_id,
                'session_id' => $workflow->from_session_id,
            ]);

            return $target->fresh();
        });
    }

    private function assertNextGradePromotion(StudentEnrollment $source, int $targetGradeId): void
    {
        $grades = Grade::where('status', 1)
            ->orderByRaw('CAST(grade_order AS UNSIGNED)')
            ->get(['id', 'grade', 'grade_order'])
            ->values();

        $sourceIndex = $grades->search(fn ($grade) => (int) $grade->id === (int) $source->grade_id);
        if ($sourceIndex === false) {
            throw ValidationException::withMessages(['to_grade_id' => 'Unable to verify the current grade for this student.']);
        }

        $sourceGrade = $grades[$sourceIndex];
        $nextGrade = $grades->get($sourceIndex + 1);

        if (!$nextGrade) {
            throw ValidationException::withMessages([
                'to_grade_id' => "{$sourceGrade->grade} students should be processed from the Student Graduation page.",
            ]);
        }

        if ((int) $nextGrade->id !== $targetGradeId) {
            throw ValidationException::withMessages([
                'to_grade_id' => "Students from {$sourceGrade->grade} can only be promoted to {$nextGrade->grade}. Lower grades are not allowed.",
            ]);
        }
    }

    public function transfer(StudentEnrollment $source, array $data): StudentEnrollment
    {
        return DB::transaction(function () use ($source, $data) {
            $before = $source->only(['campus_id', 'academic_year_id', 'grade_id', 'class_id', 'session_id']);
            $source->update([
                'campus_id' => $data['to_campus_id'],
                'grade_id' => $data['to_grade_id'] ?? $source->grade_id,
                'class_id' => $data['to_class_id'] ?? $source->class_id,
                'session_id' => $data['to_session_id'] ?? null,
                'enrollment_status' => 'active',
            ]);
            $this->record($source, $source, $data['action_type'] ?? 'transfer', $data, $before);
            return $source->fresh();
        });
    }

    private function record(StudentEnrollment $source, StudentEnrollment $target, string $action, array $data, array $before = []): void
    {
        $to = $target->only(['campus_id', 'academic_year_id', 'grade_id', 'class_id', 'session_id']);
        EnrollmentWorkflowAction::create([
            'student_id' => $source->student_id,
            'source_enrollment_id' => $source->id,
            'target_enrollment_id' => $target->id,
            'parent_workflow_id' => $data['parent_workflow_id'] ?? null,
            'action_type' => $action,
            'status' => 'completed',
            'from_campus_id' => $before['campus_id'] ?? $source->campus_id,
            'to_campus_id' => $to['campus_id'],
            'from_academic_year_id' => $before['academic_year_id'] ?? $source->academic_year_id,
            'to_academic_year_id' => $to['academic_year_id'],
            'from_grade_id' => $before['grade_id'] ?? $source->grade_id,
            'to_grade_id' => $to['grade_id'],
            'from_class_id' => $before['class_id'] ?? $source->class_id,
            'to_class_id' => $to['class_id'],
            'from_session_id' => $before['session_id'] ?? $source->session_id,
            'to_session_id' => $to['session_id'],
            'effective_on' => $data['effective_on'],
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
            'changed_by' => auth()->id(),
        ]);

        $this->recordEnrollmentHistory($target, $action, $data['effective_on'], $data['reason'] ?? null, $data['notes'] ?? null);
    }

    private function recordEnrollmentHistory(StudentEnrollment $target, string $action, $effectiveOn, ?string $reason, ?string $notes): void
    {
        StudentEnrollmentHistory::create([
            'enrollment_id' => $target->id,
            'student_id' => $target->student_id,
            'action_type' => $action,
            'campus_id' => $target->campus_id,
            'academic_year_id' => $target->academic_year_id,
            'grade_id' => $target->grade_id,
            'class_id' => $target->class_id,
            'session_id' => $target->session_id,
            'enrollment_status' => $target->enrollment_status,
            'student_type' => $target->student_type,
            'effective_on' => $effectiveOn,
            'reason' => $reason,
            'notes' => $notes,
            'changed_by' => auth()->id(),
        ]);
    }
}
