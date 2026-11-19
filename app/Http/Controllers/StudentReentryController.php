<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolInfo;
use App\Models\Session;
use App\Models\StudentEnrollment;
use App\Models\StudentEnrollmentHistory;
use App\Models\Grade;
use App\Models\Student;
use App\Services\ReentryCandidates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentReentryController
{
    public function index() { return view('student-reentry'); }

    public function options(Request $request)
    {
        $candidates = app(ReentryCandidates::class);
        $target = $candidates->targetYear($request->integer('academic_year_id') ?: null);
        $historyQuery = $candidates->query($target)
            ->with(['student:id,student_no,student_id,full_name_en,full_name_kh,photo_path', 'academicYear:id,academic_year', 'campus:id,campus_name_en', 'grade:id,grade', 'schoolClass:id,class_name', 'session:id,session_short_name'])
            ->when($request->filled('search'), function ($query) use ($request) { $term = trim((string) $request->input('search')); $query->whereHas('student', fn ($student) => $student->where('student_id', 'like', "%{$term}%")->orWhere('student_no', 'like', "%{$term}%")->orWhere('full_name_en', 'like', "%{$term}%")->orWhere('full_name_kh', 'like', "%{$term}%")); })
            ->orderByDesc('academic_year_id')->orderByDesc('id');
        $withdrawals = $historyQuery->get(['id', 'student_id', 'academic_year_id', 'campus_id', 'grade_id', 'class_id', 'session_id', 'enrollment_status', 'ended_on']);
        $withdrawals->each(function ($enrollment) {
            $enrollment->setAttribute('source_enrollment_id', $enrollment->id);
            $enrollment->setAttribute('effective_on', $enrollment->ended_on?->format('Y-m-d'));
            $enrollment->setAttribute('return_type', $enrollment->enrollment_status === 'withdrawn' ? 'withdrawn' : 'study_gap');
        });

        return response()->json([
            'withdrawals' => $withdrawals,
            'target_academic_year_id' => $target?->id,
            'academicYears' => AcademicYear::where('period_type', 'regular')->whereIn('lifecycle_status', ['started', 'pending'])
                ->orderByDesc('academic_year')
                ->get(['id', 'academic_year', 'lifecycle_status']),
            'campuses' => SchoolInfo::where('status', 1)->orderBy('campus_name_en')->get(['id', 'campus_name_en']),
            'grades' => Grade::where('status', 1)->orderByRaw('CAST(grade_order AS UNSIGNED)')->orderBy('grade')->get(['id', 'grade', 'grade_order']),
            'classes' => SchoolClass::where('status', 1)->orderBy('grade_id')->orderBy('class_name')->get(['id', 'class_name', 'grade_id']),
            'groups' => Session::where('status', 1)->orderBy('session_order')->get(['id', 'session_short_name']),
        ]);
    }

    public function reenter(Request $request)
    {
        $data = $request->validate([
            'source_enrollment_id' => ['required_without:source_history_id', 'nullable', 'integer', 'exists:tb_student_enrollment,id'],
            'source_history_id' => ['nullable', 'integer', 'exists:tb_student_enrollment_history,id'],
            'academic_year_id' => ['required', Rule::exists('tb_academic_year', 'id')->where('period_type', 'regular')->whereIn('lifecycle_status', ['started', 'pending'])], 'campus_id' => ['required', 'exists:tb_school_info,id'], 'grade_id' => ['required', 'exists:tb_grade,id'], 'class_id' => ['required', 'exists:tb_class,id'], 'session_id' => ['required', 'exists:tb_session,id'], 'effective_on' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['effective_on'] = \Illuminate\Support\Carbon::parse($data['effective_on'])->toDateString();
        $enrollment = DB::transaction(function () use ($data) {
            $history = !empty($data['source_history_id']) ? StudentEnrollmentHistory::whereKey($data['source_history_id'])
                ->where('action_type', 'withdrawal')->where('withdrawal_status', 'approved')->where('enrollment_status', 'withdrawn')->firstOrFail() : null;
            $sourceId = $data['source_enrollment_id'] ?? $history?->enrollment_id;
            if ($history && (int) $sourceId !== (int) $history->enrollment_id) {
                throw ValidationException::withMessages(['source_enrollment_id' => 'The withdrawal does not match the selected enrollment.']);
            }
            $source = StudentEnrollment::findOrFail($sourceId);
            // Serialize returns for the same student, including requests from different tabs.
            Student::whereKey($source->student_id)->lockForUpdate()->firstOrFail();
            $academicYear = AcademicYear::lockForUpdate()->findOrFail($data['academic_year_id']);
            if (!in_array($academicYear->lifecycle_status, ['started', 'pending'], true)
                || !app(ReentryCandidates::class)->query($academicYear)->whereKey($sourceId)->exists()) {
                throw ValidationException::withMessages(['source_enrollment_id' => 'This student is no longer eligible for re-entry in the selected year. Check their latest regular enrollment.']);
            }
            if (StudentEnrollment::where('student_id', $source->student_id)->where('academic_year_id', $academicYear->id)
                ->where('id', '!=', $sourceId)->exists()) {
                throw ValidationException::withMessages(['academic_year_id' => 'This student already has an enrollment in the selected academic year.']);
            }
            if ($source->ended_on && $data['effective_on'] < $source->ended_on->format('Y-m-d')) {
                throw ValidationException::withMessages(['effective_on' => 'The return date cannot be before the previous enrollment ended.']);
            }
            $history ??= StudentEnrollmentHistory::where('enrollment_id', $sourceId)->where('action_type', 'withdrawal')
                ->where('withdrawal_status', 'approved')->where('enrollment_status', 'withdrawn')->latest('id')->first();
            $studentId = $source->student_id;
            $enrollmentStatus = $academicYear->lifecycle_status === 'started' ? 'active' : 'pending';
            $enrollment = StudentEnrollment::create(['student_id' => $studentId, 'academic_year_id' => $data['academic_year_id'], 'campus_id' => $data['campus_id'], 'grade_id' => $data['grade_id'], 'class_id' => $data['class_id'], 'session_id' => $data['session_id'], 'status' => 1, 'student_type' => 'old', 'enrollment_status' => $enrollmentStatus, 'enrolled_on' => $data['effective_on'], 'notes' => $data['notes'] ?? null]);
            StudentEnrollmentHistory::create(['enrollment_id' => $enrollment->id, 'source_enrollment_id' => $source->id, 'source_history_id' => $history?->id, 'student_id' => $studentId, 'action_type' => 're_entry', 'campus_id' => $data['campus_id'], 'academic_year_id' => $data['academic_year_id'], 'grade_id' => $data['grade_id'], 'class_id' => $data['class_id'], 'session_id' => $data['session_id'], 'enrollment_status' => $enrollmentStatus, 'student_type' => 'old', 'effective_on' => $data['effective_on'], 'reason' => $source->enrollment_status === 'withdrawn' ? 'Returned after withdrawal' : 'Returned after a study gap', 'notes' => $data['notes'] ?? null, 'changed_by' => auth()->id()]);
            return $enrollment;
        });
        return response()->json(['status' => 'success', 'message' => 'Student re-entered successfully.', 'data' => $enrollment]);
    }
}
