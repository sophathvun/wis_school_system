<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolInfo;
use App\Models\Session;
use App\Models\StudentEnrollment;
use App\Models\StudentEnrollmentHistory;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentReentryController
{
    public function index() { return view('student-reentry'); }

    public function options(Request $request)
    {
        $historyQuery = StudentEnrollmentHistory::with(['student:id,student_no,student_id,full_name_en,full_name_kh,photo_path', 'academicYear:id,academic_year', 'campus:id,campus_name_en', 'grade:id,grade', 'schoolClass:id,class_name', 'session:id,session_short_name'])
            ->where('action_type', 'withdrawal')
            ->where('withdrawal_status', 'approved')
            ->where('enrollment_status', 'withdrawn')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('tb_student_enrollment_history as reentry')->whereColumn('reentry.source_history_id', 'tb_student_enrollment_history.id')->where('reentry.action_type', 're_entry'))
            ->when($request->filled('search'), function ($query) use ($request) { $term = trim((string) $request->input('search')); $query->whereHas('student', fn ($student) => $student->where('student_id', 'like', "%{$term}%")->orWhere('student_no', 'like', "%{$term}%")->orWhere('full_name_en', 'like', "%{$term}%")->orWhere('full_name_kh', 'like', "%{$term}%")); })
            ->latest('id')->limit(500);
        $withdrawals = $historyQuery->get();
        $withdrawals->each(function ($history) {
            if ($history->effective_on) {
                $history->setAttribute('effective_on', $history->effective_on->format('d-M-Y'));
            }
        });

        return response()->json([
            'withdrawals' => $withdrawals,
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
            'source_history_id' => ['required', 'exists:tb_student_enrollment_history,id'], 'academic_year_id' => ['required', Rule::exists('tb_academic_year', 'id')->where('period_type', 'regular')->whereIn('lifecycle_status', ['started', 'pending'])], 'campus_id' => ['required', 'exists:tb_school_info,id'], 'grade_id' => ['required', 'exists:tb_grade,id'], 'class_id' => ['required', 'exists:tb_class,id'], 'session_id' => ['required', 'exists:tb_session,id'], 'effective_on' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $source = StudentEnrollmentHistory::where('id', $data['source_history_id'])->where('action_type', 'withdrawal')->where('withdrawal_status', 'approved')->where('enrollment_status', 'withdrawn')->firstOrFail();
        $studentId = $source->student_id;
        if (StudentEnrollment::where('student_id', $studentId)->where('academic_year_id', $data['academic_year_id'])->where('status', 1)->where('enrollment_status', 'active')->exists()) throw ValidationException::withMessages(['source_history_id' => 'This student already has an active enrollment in the selected academic year.']);
        if (StudentEnrollmentHistory::where('source_history_id', $source->id)->where('action_type', 're_entry')->exists()) throw ValidationException::withMessages(['source_history_id' => 'This withdrawal has already been re-entered.']);
        $enrollment = DB::transaction(function () use ($data, $source, $studentId) {
            $academicYear = AcademicYear::findOrFail($data['academic_year_id']);
            $enrollmentStatus = $academicYear->lifecycle_status === 'started' ? 'active' : 'pending';
            $enrollment = StudentEnrollment::create(['student_id' => $studentId, 'academic_year_id' => $data['academic_year_id'], 'campus_id' => $data['campus_id'], 'grade_id' => $data['grade_id'], 'class_id' => $data['class_id'], 'session_id' => $data['session_id'], 'status' => 1, 'student_type' => 'old', 'enrollment_status' => $enrollmentStatus, 'enrolled_on' => $data['effective_on'], 'notes' => $data['notes'] ?? null]);
            StudentEnrollmentHistory::create(['enrollment_id' => $enrollment->id, 'source_history_id' => $source->id, 'student_id' => $studentId, 'action_type' => 're_entry', 'campus_id' => $data['campus_id'], 'academic_year_id' => $data['academic_year_id'], 'grade_id' => $data['grade_id'], 'class_id' => $data['class_id'], 'session_id' => $data['session_id'], 'enrollment_status' => $enrollmentStatus, 'student_type' => 'old', 'effective_on' => $data['effective_on'], 'reason' => 'Returned after withdrawal', 'notes' => $data['notes'] ?? null, 'changed_by' => auth()->id()]);
            return $enrollment;
        });
        return response()->json(['status' => 'success', 'message' => 'Student re-entered successfully.', 'data' => $enrollment]);
    }
}
