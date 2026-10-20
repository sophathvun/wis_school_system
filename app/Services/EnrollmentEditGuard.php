<?php

namespace App\Services;

use App\Models\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnrollmentEditGuard
{
    public function preserve(Request $request, StudentEnrollment $enrollment): void
    {
        $stored = $enrollment->only(['campus_id', 'academic_year_id', 'grade_id', 'class_id', 'academic_track_id',
            'session_id', 'group_id', 'status', 'enrollment_status', 'student_type', 'exit_reason']);
        $stored['student_record_id'] = $enrollment->student_id;
        $stored['enrollment_notes'] = $enrollment->notes;
        $stored['enrolled_on'] = $enrollment->enrolled_on?->toDateString();
        $stored['ended_on'] = $enrollment->ended_on?->toDateString();

        foreach ($stored as $field => $value) {
            if (! $request->exists($field)) continue;
            $incoming = $request->input($field);
            if ($incoming !== null && ! is_scalar($incoming)) {
                throw ValidationException::withMessages(['enrollment_id' => 'Enrollment information is read-only here. Use the appropriate enrollment workflow to change it.']);
            }
            $numeric = str_ends_with($field, '_id') || $field === 'status';
            $normalize = static fn ($item) => $item === null || $item === '' ? null
                : ($numeric && is_numeric($item) ? (string) (int) $item : (is_bool($item) ? (string) (int) $item : (string) $item));
            if ($normalize($incoming) !== $normalize($value)) {
                throw ValidationException::withMessages([
                    'enrollment_id' => 'Enrollment information is read-only here. Use Transfer Student or the appropriate enrollment workflow to change it.',
                ]);
            }
        }

        // Disabled form controls are omitted from FormData. Validate and save
        // profile edits against the stored enrollment, never a browser fallback.
        $request->merge($stored);
    }
}
