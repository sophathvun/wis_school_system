<?php

namespace App\Services;

use App\Models\EnrollmentWorkflowAction;
use App\Models\StudentEnrollment;
use App\Models\StudentEnrollmentHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentWorkflowService
{
    public function promoteClass(array $data): int
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
                $this->promote($enrollment, [
                    'to_campus_id' => $data['to_campus_id'] ?? $data['from_campus_id'],
                    'to_academic_year_id' => $data['to_academic_year_id'],
                    'to_grade_id' => $data['to_grade_id'],
                    'to_class_id' => $data['to_class_id'],
                    'to_session_id' => $data['to_session_id'] ?? null,
                    'effective_on' => $data['effective_on'],
                    'reason' => $data['reason'] ?? 'Class promotion',
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            return $enrollments->count();
        });
    }

    public function promoteSelected(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $enrollments = StudentEnrollment::whereIn('id', $data['enrollment_ids'])
                ->where('campus_id', $data['from_campus_id'])
                ->where('academic_year_id', $data['from_academic_year_id'])
                ->where('grade_id', $data['from_grade_id'])
                ->where('class_id', $data['from_class_id'])
                ->where('enrollment_status', 'active')
                ->get();

            if ($enrollments->count() !== count(array_unique($data['enrollment_ids']))) {
                throw ValidationException::withMessages(['enrollment_ids' => 'One or more selected students are not active in the selected class.']);
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
                ]);
            }

            return $enrollments->count();
        });
    }

    public function promote(StudentEnrollment $source, array $data): StudentEnrollment
    {
        return DB::transaction(function () use ($source, $data) {
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
                'enrollment_status' => 'active',
                'enrolled_on' => $data['effective_on'],
            ]);

            $source->update(['status' => 1, 'enrollment_status' => 'completed', 'ended_on' => $data['effective_on'], 'exit_reason' => 'Promoted']);
            $this->record($source, $target, 'promotion', $data);
            return $target;
        });
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
                'enrollment_status' => 'transferred',
            ]);
            $this->record($source, $source, 'transfer', $data, $before);
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
            'action_type' => $action,
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

        StudentEnrollmentHistory::create([
            'enrollment_id' => $target->id,
            'student_id' => $source->student_id,
            'action_type' => $action,
            'campus_id' => $to['campus_id'],
            'academic_year_id' => $to['academic_year_id'],
            'grade_id' => $to['grade_id'],
            'class_id' => $to['class_id'],
            'session_id' => $to['session_id'],
            'enrollment_status' => $target->enrollment_status,
            'student_type' => $target->student_type,
            'effective_on' => $data['effective_on'],
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
            'changed_by' => auth()->id(),
        ]);
    }
}
