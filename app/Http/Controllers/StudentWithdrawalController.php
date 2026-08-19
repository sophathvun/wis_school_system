<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\FamilyMember;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolInfo;
use App\Models\StudentEnrollment;
use App\Models\StudentEnrollmentHistory;
use App\Models\WithdrawalReason;
use App\Services\StudentWithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentWithdrawalController
{
    public const REASONS = [
        ['key' => 'transfer', 'en' => 'Transfer to another school', 'kh' => 'ផ្ទេរទៅសាលាផ្សេង'],
        ['key' => 'study_abroad', 'en' => 'Study abroad', 'kh' => 'ទៅសិក្សានៅបរទេស'],
        ['key' => 'change_residence', 'en' => 'Change of residence', 'kh' => 'ផ្លាស់ប្តូរទីលំនៅ'],
        ['key' => 'academic_difficulty', 'en' => 'Academic difficulty', 'kh' => 'ការលំបាកក្នុងការសិក្សា'],
        ['key' => 'behavior_difficulty', 'en' => 'Behavior difficulty', 'kh' => 'បញ្ហាអាកប្បកិរិយា'],
        ['key' => 'dislike_school', 'en' => 'Dislike of the school experience', 'kh' => 'មិនពេញចិត្តបទពិសោធន៍នៅសាលា'],
        ['key' => 'economic', 'en' => 'Economic reasons', 'kh' => 'ហេតុផលសេដ្ឋកិច្ច'],
        ['key' => 'employment', 'en' => 'Employment status', 'kh' => 'ស្ថានភាពការងារ'],
        ['key' => 'transportation', 'en' => 'Transportation difficulties', 'kh' => 'ការលំបាកក្នុងការធ្វើដំណើរ'],
        ['key' => 'lack_interest', 'en' => 'Lack of interest or motivation', 'kh' => 'ខ្វះចំណាប់អារម្មណ៍ ឬការលើកទឹកចិត្ត'],
        ['key' => 'physical', 'en' => 'Physical illness or disability', 'kh' => 'ជំងឺរាងកាយ ឬពិការភាព'],
        ['key' => 'staff_relationship', 'en' => 'Poor student/staff relationship', 'kh' => 'ទំនាក់ទំនងមិនល្អជាមួយបុគ្គលិក'],
        ['key' => 'student_relationship', 'en' => 'Poor relationship with fellow students', 'kh' => 'ទំនាក់ទំនងមិនល្អជាមួយសិស្សដទៃ'],
        ['key' => 'expelled', 'en' => 'Expelled', 'kh' => 'ត្រូវបានបណ្តេញចេញ'],
    ];
    public function __construct(private readonly StudentWithdrawalService $service) {}

    public function index() { return view('student-withdrawal', ['reasons' => WithdrawalReason::where('status', true)->orderBy('sort_order')->orderBy('name_en')->get(['reason_key as key', 'name_en as en', 'name_kh as kh'])]); }

    public function options(Request $request)
    {
        $yearId = $request->integer('academic_year_id') ?: null;
        $campusId = $request->integer('campus_id') ?: null;
        $campusQuery = SchoolInfo::where('status', 1)->orderBy('campus_name_en');
        if ($yearId) $campusQuery->whereIn('id', StudentEnrollment::where('academic_year_id', $yearId)->where('status', 1)->where('enrollment_status', 'active')->select('campus_id'));
        $gradeClassQuery = StudentEnrollment::query()
            ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
            ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
            ->where('tb_student_enrollment.status', 1)->where('tb_student_enrollment.enrollment_status', 'active')
            ->when($yearId, fn ($query) => $query->where('tb_student_enrollment.academic_year_id', $yearId))
            ->when($campusId, fn ($query) => $query->where('tb_student_enrollment.campus_id', $campusId));
        return response()->json([
            'academicYears' => AcademicYear::whereIn('lifecycle_status', ['started', 'pending'])
                ->orderByDesc('academic_year')
                ->orderByDesc('id')
                ->get(['id', 'academic_year', 'lifecycle_status']),
            'campuses' => $campusQuery->get(['id', 'campus_name_en']),
            'grades' => Grade::where('status', 1)->orderByRaw('CAST(grade_order AS UNSIGNED)')->get(['id', 'grade']),
            'reasons' => WithdrawalReason::where('status', true)->orderBy('sort_order')->orderBy('name_en')->get(['reason_key as key', 'name_en as en', 'name_kh as kh']),
            'classes' => SchoolClass::where('status', 1)->orderBy('class_name')->get(['id', 'class_name', 'grade_id']),
            'gradeClasses' => $gradeClassQuery->select('tb_student_enrollment.grade_id', 'tb_student_enrollment.class_id', 'tb_grade.grade', 'tb_grade.grade_order', 'tb_class.class_name', 'tb_class.class_order')->distinct()->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')->orderByRaw('CAST(tb_class.class_order AS UNSIGNED)')->orderBy('tb_class.class_name')->get(),
        ]);
    }

    public function students(Request $request)
    {
        $students = StudentEnrollment::with([
                'student:id,student_no,student_id,full_name_en,full_name_kh,family_number',
                'student.families.members' => fn ($query) => $query->whereIn('relationship_type', ['mother', 'father', 'guardian']),
            ])
            ->where('status', 1)->where('enrollment_status', 'active')
            ->when($request->filled('campus_id'), fn ($query) => $query->where('campus_id', $request->integer('campus_id')))
            ->when($request->filled('academic_year_id'), fn ($query) => $query->where('academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('grade_id'), fn ($query) => $query->where('grade_id', $request->integer('grade_id')))
            ->when($request->filled('class_id'), fn ($query) => $query->where('class_id', $request->integer('class_id')))
            ->when($request->filled('search'), function ($query) use ($request) { $term = trim((string) $request->input('search')); $query->whereHas('student', fn ($student) => $student->where('student_id', 'like', "%{$term}%")->orWhere('student_no', 'like', "%{$term}%")->orWhere('full_name_en', 'like', "%{$term}%")->orWhere('full_name_kh', 'like', "%{$term}%")); })
            ->orderByRaw('(select lower(coalesce(full_name_en, full_name_kh)) from tb_student where tb_student.id = tb_student_enrollment.student_id) asc')
            ->orderBy('student_id')->limit(500)->get();
        $students->each(function ($enrollment) {
            $members = $enrollment->student?->families?->flatMap->members ?? collect();
            if ($members->isEmpty() && $enrollment->student?->family_number) {
                $members = FamilyMember::whereHas('family', fn ($query) => $query->where('family_number', $enrollment->student->family_number))->get();
            }
            $enrollment->setAttribute('family_members', $members
                ->whereIn('relationship_type', ['mother', 'father', 'guardian'])
                ->unique(fn ($member) => $member->relationship_type)
                ->values()
                ->map(fn ($member) => [
                    'relationship_type' => $member->relationship_type,
                    'name' => $member->full_name_en,
                    'phone' => $member->phone,
                ]));
        });

        return response()->json(['enrollments' => $students]);
    }

    public function historyOptions(Request $request)
    {
        $yearId = $request->integer('academic_year_id') ?: null;
        $campusId = $request->integer('campus_id') ?: null;
        $gradeId = $request->integer('grade_id') ?: null;
        $classId = $request->integer('class_id') ?: null;
        $sessionId = $request->integer('session_id') ?: null;

        $filtered = function (bool $includeYear = true, bool $includeCampus = true, bool $includeGrade = true, bool $includeClass = true, bool $includeSession = true) use ($yearId, $campusId, $gradeId, $classId, $sessionId) {
            return StudentEnrollmentHistory::where('action_type', 'withdrawal')
                ->when($includeYear && $yearId, fn ($query) => $query->where('academic_year_id', $yearId))
                ->when($includeCampus && $campusId, fn ($query) => $query->where('campus_id', $campusId))
                ->when($includeGrade && $gradeId, fn ($query) => $query->where('grade_id', $gradeId))
                ->when($includeClass && $classId, fn ($query) => $query->where('class_id', $classId))
                ->when($includeSession && $sessionId, fn ($query) => $query->where('session_id', $sessionId));
        };

        $yearIds = (clone $filtered(false, false, false, false, false))->whereNotNull('academic_year_id')->distinct()->pluck('academic_year_id');
        $campusIds = (clone $filtered(true, false, false, false, false))->whereNotNull('campus_id')->distinct()->pluck('campus_id');
        $gradeIds = (clone $filtered(true, true, false, false, false))->whereNotNull('grade_id')->distinct()->pluck('grade_id');
        $classIds = (clone $filtered(true, true, false, false, false))->whereNotNull('class_id')->distinct()->pluck('class_id');
        $sessionIds = (clone $filtered(true, true, true, true, false))->whereNotNull('session_id')->distinct()->pluck('session_id');
        $studentIds = (clone $filtered(true, true, true, true, true))->whereNotNull('student_id')->distinct()->pluck('student_id');

        return response()->json([
            'academicYears' => AcademicYear::whereIn('id', $yearIds)->orderByDesc('academic_year')->orderByDesc('id')->get(['id', 'academic_year']),
            'campuses' => SchoolInfo::whereIn('id', $campusIds)->orderBy('campus_name_en')->get(['id', 'campus_name_en']),
            'gradeClasses' => StudentEnrollmentHistory::query()
                ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment_history.grade_id')
                ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment_history.class_id')
                ->where('tb_student_enrollment_history.action_type', 'withdrawal')
                ->when($yearId, fn ($query) => $query->where('tb_student_enrollment_history.academic_year_id', $yearId))
                ->when($campusId, fn ($query) => $query->where('tb_student_enrollment_history.campus_id', $campusId))
                ->select('tb_student_enrollment_history.grade_id', 'tb_student_enrollment_history.class_id', 'tb_grade.grade', 'tb_grade.grade_order', 'tb_class.class_name', 'tb_class.class_order')
                ->distinct()->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')->orderByRaw('CAST(tb_class.class_order AS UNSIGNED)')->get(),
            'groups' => \App\Models\Session::whereIn('id', $sessionIds)->orderBy('session_order')->orderBy('session_name')->get(['id', 'session_name', 'session_short_name']),
                    'students' => \App\Models\Student::whereIn('id', $studentIds)->orderByRaw('LOWER(coalesce(full_name_en, full_name_kh))')->get(['id', 'student_id', 'student_no', 'full_name_en', 'full_name_kh']),
        ]);
    }

    public function fetch(Request $request)
    {
        $history = StudentEnrollmentHistory::with(['student:id,student_no,student_id,photo_path,full_name_en,full_name_kh,home_phone', 'campus:id,campus_name_en', 'academicYear:id,academic_year', 'grade:id,grade', 'schoolClass:id,class_name', 'academicTrack:id,name_en,name_kh,code', 'session:id,session_name,session_short_name', 'changedBy:id,name'])
            ->where('action_type', 'withdrawal');
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $history->where(function ($query) use ($search) {
                $query->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($student) => $student->where('student_id', 'like', "%{$search}%")->orWhere('student_no', 'like', "%{$search}%")->orWhere('full_name_en', 'like', "%{$search}%")->orWhere('full_name_kh', 'like', "%{$search}%"))
                    ->orWhereHas('campus', fn ($campus) => $campus->where('campus_name_en', 'like', "%{$search}%"))
                ->orWhereHas('academicYear', fn ($year) => $year->where('academic_year', 'like', "%{$search}%"));
            });
        }
        $history->when($request->filled('academic_year_id'), fn ($query) => $query->where('academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('campus_id'), fn ($query) => $query->where('campus_id', $request->integer('campus_id')))
            ->when($request->filled('grade_id'), fn ($query) => $query->where('grade_id', $request->integer('grade_id')))
            ->when($request->filled('class_id'), fn ($query) => $query->where('class_id', $request->integer('class_id')))
            ->when($request->filled('session_id'), fn ($query) => $query->where('session_id', $request->integer('session_id')))
            ->when($request->filled('student_id'), fn ($query) => $query->where('student_id', $request->integer('student_id')));

        $sort = (string) $request->input('sortBy', 'effective_on');
        $direction = strtolower((string) $request->input('sortDir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sorts = [
            'academic_year' => ['academic_year_id', $direction],
            'campus' => ['campus_id', $direction],
            'grade' => ['grade_id', $direction],
            'class' => ['class_id', $direction],
            'group' => ['session_id', $direction],
            'date' => ['effective_on', $direction],
            'requested_by' => ['requested_by_name', $direction],
            'student_id' => ['student_id', $direction],
            'withdrawn_by' => ['changed_by', $direction],
        ];
        if ($sort === 'student_name') {
            $history->orderByRaw("(select lower(coalesce(nullif(full_name_en, ''), full_name_kh)) from tb_student where tb_student.id = tb_student_enrollment_history.student_id) {$direction}");
        } elseif (isset($sorts[$sort])) {
            $history->orderBy(...$sorts[$sort]);
        } else {
            $history->orderByDesc('id');
        }

        $result = $history->paginate($request->integer('perPage', 10));
        $result->getCollection()->transform(fn ($item) => $item
            ->setAttribute('form_url', route('student-withdrawals.form', $item))
            ->setAttribute('edit_url', route('student-withdrawals.show', $item))
            ->setAttribute('update_url', route('student-withdrawals.update', $item)));
        return response()->json($result);
    }

    public function enrollmentFamily(StudentEnrollment $enrollment)
    {
        $enrollment->load('student.families.members');
        $members = $enrollment->student?->families?->flatMap->members ?? collect();

        if ($members->isEmpty() && $enrollment->student?->family_number) {
            $members = FamilyMember::whereHas('family', fn ($query) => $query->where('family_number', $enrollment->student->family_number))->get();
        }

        return response()->json([
            'family_members' => $members
                ->whereIn('relationship_type', ['mother', 'father', 'guardian'])
                ->unique(fn ($member) => $member->relationship_type)
                ->values()
                ->map(fn ($member) => [
                    'relationship_type' => $member->relationship_type,
                    'name' => $member->full_name_en,
                    'phone' => $member->phone,
                ]),
        ]);
    }

    public function show(StudentEnrollmentHistory $history)
    {
        abort_unless($history->action_type === 'withdrawal', 404);
        $history->load([
            'student.families.members',
            'academicYear',
            'campus',
            'grade',
            'schoolClass',
            'session',
            'academicTrack',
        ]);
        $members = $history->student?->families?->flatMap->members ?? collect();
        $student = $history->student;
        $studentNameEn = $student?->full_name_en;
        $studentNameKh = $student?->full_name_kh;

        return response()->json([
            'data' => $history->only([
                'id', 'withdrawal_status', 'effective_on', 'reason', 'reasons', 'reason_kh', 'other_reason_en', 'other_reason_kh', 'rejection_reason', 'cancellation_reason',
                'new_school', 'new_school_address', 'dropout_type', 'requested_by_type',
                'requested_by_name', 'requested_by_phone', 'additional_comments', 'notes',
            ]),
            'student_summary' => [
                'student_id' => $student?->student_id ?: $student?->student_no,
                'photo_path' => $student?->photo_path,
                'name_en' => $studentNameEn,
                'name_kh' => $studentNameKh,
                'academic_year' => $history->academicYear?->academic_year,
                'campus' => $history->campus?->campus_name_en,
                'grade' => $history->grade?->grade,
                'class' => $history->schoolClass?->class_name,
                'group' => $history->session?->session_short_name ?: $history->session?->session_name,
                'track' => $history->academicTrack?->name_en ?: $history->academicTrack?->code,
            ],
            'family_members' => $members
                ->whereIn('relationship_type', ['mother', 'father', 'guardian'])
                ->unique(fn ($member) => $member->relationship_type)
                ->values()
                ->map(fn ($member) => [
                    'relationship_type' => $member->relationship_type,
                    'name' => $member->full_name_en,
                    'phone' => $member->phone,
                ]),
        ]);
    }

    public function update(Request $request, StudentEnrollmentHistory $history)
    {
        abort_unless($history->action_type === 'withdrawal', 404);

        $data = $request->validate([
            'withdrawal_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reasons' => ['nullable', 'array'],
            'reasons.*' => ['string'],
            'reason_kh' => ['nullable', 'string', 'max:5000'],
            'other_reason_en' => ['nullable', 'string', 'max:500'],
            'other_reason_kh' => ['nullable', 'string', 'max:500'],
            'new_school' => ['nullable', 'string', 'max:200'],
            'new_school_address' => ['nullable', 'string', 'max:500'],
            'dropout_type' => ['required', 'in:official_leave,dropped_out'],
            'requested_by_type' => ['required', 'in:mother,father,guardian'],
            'requested_by_name' => ['required', 'string', 'max:180'],
            'requested_by_phone' => ['required', 'string', 'max:50'],
            'additional_comments' => ['nullable', 'string', 'max:5000'],
        ]);

        $statusUpdate = $history->withdrawal_status === 'rejected' ? [
            'withdrawal_status' => 'pending',
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_reason' => null,
            'changed_by' => auth()->id(),
        ] : [];
        $history->update([
            'effective_on' => $data['withdrawal_date'],
            'reason' => $data['reason'],
            'reasons' => $data['reasons'],
            'reason_kh' => $data['reason_kh'] ?? null,
            'other_reason_en' => $data['other_reason_en'] ?? null,
            'other_reason_kh' => $data['other_reason_kh'] ?? null,
            'new_school' => $data['new_school'] ?? null,
            'new_school_address' => $data['new_school_address'] ?? null,
            'dropout_type' => $data['dropout_type'],
            'requested_by_type' => $data['requested_by_type'],
            'requested_by_name' => $data['requested_by_name'],
            'requested_by_phone' => $data['requested_by_phone'],
            'additional_comments' => $data['additional_comments'] ?? null,
            'notes' => $data['notes'] ?? null,
            'changed_by' => auth()->id(),
        ] + $statusUpdate);

        if ($history->withdrawal_status === 'approved' && $history->enrollment) {
            $history->enrollment->update([
                'ended_on' => $data['withdrawal_date'],
                'exit_reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
            ]);
        }

        return response()->json(['status' => 'success', 'message' => 'Withdrawal request updated successfully.', 'data' => $history->fresh()]);
    }

    public function markPrincipalApproved(Request $request, StudentEnrollmentHistory $history)
    {
        abort_unless($history->action_type === 'withdrawal', 404);
        abort_if($history->withdrawal_status === 'approved', 422, 'This withdrawal has already been approved.');
        $request->validate(['paper_signed' => ['required', 'accepted']]);

        $history->update([
            'withdrawal_status' => 'principal_approved',
            'paper_signed_at' => now(),
            'paper_signed_by' => auth()->id(),
            'principal_approved_at' => now(),
            'principal_approved_by' => auth()->id(),
            'changed_by' => auth()->id(),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Paper approval recorded. The withdrawal is ready for final system approval.']);
    }

    public function approve(StudentEnrollmentHistory $history)
    {
        abort_unless($history->action_type === 'withdrawal', 404);
        abort_if($history->withdrawal_status !== 'principal_approved', 422, 'The signed Principal approval must be recorded before final approval.');

        DB::transaction(function () use ($history) {
            $enrollment = $history->enrollment()->lockForUpdate()->firstOrFail();
            if ($enrollment->enrollment_status !== 'active' || !$enrollment->status) {
                abort(422, 'This student is no longer active.');
            }
            $enrollment->update([
                'enrollment_status' => 'withdrawn',
                'ended_on' => $history->effective_on,
                'exit_reason' => $history->reason,
                'notes' => $history->notes,
            ]);
            $history->update([
                'withdrawal_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => auth()->id(),
                'changed_by' => auth()->id(),
                'enrollment_status' => 'withdrawn',
            ]);
        });

        return response()->json(['status' => 'success', 'message' => 'Withdrawal approved. The student is now officially withdrawn.']);
    }

    public function reject(Request $request, StudentEnrollmentHistory $history)
    {
        abort_unless($history->action_type === 'withdrawal', 404);
        abort_if(!in_array($history->withdrawal_status, ['pending', 'principal_approved'], true), 422, 'Only pending withdrawal requests can be rejected.');
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:2000']]);
        $history->update([
            'withdrawal_status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => auth()->id(),
            'rejection_reason' => $data['rejection_reason'],
            'changed_by' => auth()->id(),
        ]);
        return response()->json(['status' => 'success', 'message' => 'Withdrawal request rejected. The student remains Active.']);
    }

    public function cancel(Request $request, StudentEnrollmentHistory $history)
    {
        abort_unless($history->action_type === 'withdrawal', 404);
        abort_if(!in_array($history->withdrawal_status, ['pending', 'principal_approved', 'rejected'], true), 422, 'This withdrawal request cannot be cancelled.');
        $data = $request->validate(['cancellation_reason' => ['required', 'string', 'max:2000']]);
        $history->update([
            'withdrawal_status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'cancellation_reason' => $data['cancellation_reason'],
            'changed_by' => auth()->id(),
        ]);
        return response()->json(['status' => 'success', 'message' => 'Withdrawal request cancelled. The student remains Active.']);
    }

    public function form(StudentEnrollmentHistory $history)
    {
        abort_unless($history->action_type === 'withdrawal', 404);
        $history->load(['student', 'campus', 'academicYear', 'grade', 'schoolClass', 'session', 'changedBy']);
        $familyMembers = $history->student?->family_number
            ? FamilyMember::whereHas('family', fn ($query) => $query->where('family_number', $history->student->family_number))->get()
            : collect();
        return view('student-withdrawal-form', ['history' => $history, 'familyMembers' => $familyMembers, 'reasons' => WithdrawalReason::orderBy('sort_order')->orderBy('name_en')->get(['reason_key as key', 'name_en as en', 'name_kh as kh'])]);
    }

    public function withdraw(Request $request)
    {
        $data = $request->validate([
            'enrollment_id' => ['required', 'exists:tb_student_enrollment,id'],
            'withdrawal_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reasons' => ['nullable', 'array'], 'reasons.*' => ['string'], 'reason_kh' => ['nullable', 'string', 'max:5000'],
            'other_reason_en' => ['nullable', 'string', 'max:500'], 'other_reason_kh' => ['nullable', 'string', 'max:500'], 'new_school' => ['nullable', 'string', 'max:200'], 'new_school_address' => ['nullable', 'string', 'max:500'], 'dropout_type' => ['required', 'in:official_leave,dropped_out'], 'requested_by_type' => ['required', 'in:mother,father,guardian'], 'requested_by_name' => ['required', 'string', 'max:180'], 'requested_by_phone' => ['required', 'string', 'max:50'], 'additional_comments' => ['nullable', 'string', 'max:5000'],
        ]);
        $enrollment = $this->service->withdraw(StudentEnrollment::findOrFail($data['enrollment_id']), $data);
        $history = StudentEnrollmentHistory::where('enrollment_id', $enrollment->id)->where('action_type', 'withdrawal')->latest('id')->first();
        return response()->json(['status' => 'success', 'message' => 'Withdrawal request created and is pending approval.', 'data' => $enrollment, 'form_url' => $history ? route('student-withdrawals.form', $history) : null]);
    }

    public function withdrawSelected(Request $request)
    {
        $data = $request->validate([
            'enrollment_ids' => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer', 'distinct', 'exists:tb_student_enrollment,id'],
            'from_campus_id' => ['required', 'exists:tb_school_info,id'],
            'from_academic_year_id' => ['required', 'exists:tb_academic_year,id'],
            'from_grade_id' => ['required', 'exists:tb_grade,id'],
            'from_class_id' => ['required', 'exists:tb_class,id'],
            'withdrawal_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reasons' => ['nullable', 'array'], 'reasons.*' => ['string'], 'reason_kh' => ['nullable', 'string', 'max:5000'], 'other_reason_en' => ['nullable', 'string', 'max:500'], 'other_reason_kh' => ['nullable', 'string', 'max:500'], 'new_school' => ['nullable', 'string', 'max:200'], 'new_school_address' => ['nullable', 'string', 'max:500'], 'dropout_type' => ['required', 'in:official_leave,dropped_out'], 'requested_by_type' => ['required', 'in:mother,father,guardian'], 'requested_by_name' => ['required', 'string', 'max:180'], 'requested_by_phone' => ['required', 'string', 'max:50'], 'additional_comments' => ['nullable', 'string', 'max:5000'],
        ]);
        $count = $this->service->withdrawMany($data['enrollment_ids'], $data, ['campus_id' => $data['from_campus_id'], 'academic_year_id' => $data['from_academic_year_id'], 'grade_id' => $data['from_grade_id'], 'class_id' => $data['from_class_id']]);
        return response()->json(['status' => 'success', 'message' => "{$count} withdrawal requests created and are pending approval.", 'count' => $count]);
    }

    public function withdrawClass(Request $request)
    {
        $data = $request->validate([
            'from_campus_id' => ['required', 'exists:tb_school_info,id'],
            'from_academic_year_id' => ['required', 'exists:tb_academic_year,id'],
            'from_grade_id' => ['required', 'exists:tb_grade,id'],
            'from_class_id' => ['required', 'exists:tb_class,id'],
            'withdrawal_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reasons' => ['nullable', 'array'], 'reasons.*' => ['string'], 'reason_kh' => ['nullable', 'string', 'max:5000'], 'other_reason_en' => ['nullable', 'string', 'max:500'], 'other_reason_kh' => ['nullable', 'string', 'max:500'], 'new_school' => ['nullable', 'string', 'max:200'], 'new_school_address' => ['nullable', 'string', 'max:500'], 'dropout_type' => ['required', 'in:official_leave,dropped_out'], 'requested_by_type' => ['required', 'in:mother,father,guardian'], 'requested_by_name' => ['required', 'string', 'max:180'], 'requested_by_phone' => ['required', 'string', 'max:50'], 'additional_comments' => ['nullable', 'string', 'max:5000'],
        ]);
        $ids = StudentEnrollment::where('campus_id', $data['from_campus_id'])->where('academic_year_id', $data['from_academic_year_id'])->where('grade_id', $data['from_grade_id'])->where('class_id', $data['from_class_id'])->where('status', 1)->where('enrollment_status', 'active')->pluck('id')->all();
        if (!$ids) return response()->json(['message' => 'No active students were found in the selected class.'], 422);
        $count = $this->service->withdrawMany($ids, $data);
        return response()->json(['status' => 'success', 'message' => "{$count} withdrawal requests created and are pending approval.", 'count' => $count]);
    }
}
