<?php

namespace App\Services;

use App\Models\StudentEnrollment;
use App\Models\StudentEnrollmentHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentWithdrawalService
{
    public function withdraw(StudentEnrollment $enrollment, array $data): StudentEnrollment
    {
        return DB::transaction(function () use ($enrollment, $data) {
            if ($enrollment->enrollment_status !== 'active' || !$enrollment->status) {
                throw ValidationException::withMessages(['enrollment_id' => 'This student is no longer active and cannot be withdrawn.']);
            }

            if (StudentEnrollmentHistory::where('enrollment_id', $enrollment->id)->where('action_type', 'withdrawal')->whereIn('withdrawal_status', ['pending', 'principal_approved'])->exists()) {
                throw ValidationException::withMessages(['enrollment_id' => 'A withdrawal request is already waiting for approval for this student.']);
            }

            StudentEnrollmentHistory::create([
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'action_type' => 'withdrawal',
                'campus_id' => $enrollment->campus_id,
                'academic_year_id' => $enrollment->academic_year_id,
                'grade_id' => $enrollment->grade_id,
                'class_id' => $enrollment->class_id,
                'session_id' => $enrollment->session_id,
                'withdrawal_status' => 'pending',
                'enrollment_status' => 'active',
                'student_type' => $enrollment->student_type,
                'effective_on' => $data['withdrawal_date'],
                'reason' => $data['reason'],
                'reasons' => $data['reasons'] ?? [],
                'reason_kh' => $data['reason_kh'] ?? null,
                'other_reason_en' => $data['other_reason_en'] ?? null,
                'other_reason_kh' => $data['other_reason_kh'] ?? null,
                'new_school' => $data['new_school'] ?? null,
                'new_school_address' => $data['new_school_address'] ?? null,
                'dropout_type' => $data['dropout_type'] ?? null,
                'requested_by_type' => $data['requested_by_type'] ?? null,
                'requested_by_name' => $data['requested_by_name'] ?? null,
                'requested_by_phone' => $data['requested_by_phone'] ?? null,
                'additional_comments' => $data['additional_comments'] ?? null,
                'notes' => $data['notes'] ?? null,
                'changed_by' => auth()->id(),
            ]);

            return $enrollment->fresh();
        });
    }

    public function withdrawMany(array $enrollmentIds, array $data, array $source = []): int
    {
        return DB::transaction(function () use ($enrollmentIds, $data, $source) {
            $query = StudentEnrollment::whereIn('id', $enrollmentIds)
                ->where('status', 1)->where('enrollment_status', 'active');
            foreach (['campus_id', 'academic_year_id', 'grade_id', 'class_id'] as $key) {
                if (isset($source[$key])) $query->where($key, $source[$key]);
            }
            $enrollments = $query->get();
            if ($enrollments->count() !== count(array_unique($enrollmentIds))) {
                throw ValidationException::withMessages(['enrollment_ids' => 'One or more selected students are no longer active in the selected class.']);
            }
            foreach ($enrollments as $enrollment) $this->withdraw($enrollment, $data);
            return $enrollments->count();
        });
    }
}
