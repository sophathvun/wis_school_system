<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\HomeroomAssignment;
use App\Models\HomeroomRecord;
use App\Models\SchoolClass;
use App\Models\SchoolGroup;
use App\Models\SchoolInfo;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HomeroomController
{
    public function index(Request $request)
    {
        $user = $request->user();
        $campusId = $user?->active_campus_id;
        $canViewAll = $this->canViewAll($user, $campusId);
        $canManageAssignments = $this->canManageAssignments($user, $campusId);
        $canCreateRecords = $user?->hasPermission('homeroom.create', $campusId) || $canManageAssignments;

        $academicYears = AcademicYear::query()
            ->regular()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $selectedAcademicYearId = (int) ($request->integer('academic_year_id') ?: optional($academicYears->firstWhere('lifecycle_status', 'started'))->id ?: optional($academicYears->first())->id);
        $selectedCampusId = $request->integer('campus_id') ?: null;
        $selectedStaffId = $request->integer('staff_id') ?: null;
        $selectedAssignmentId = $request->integer('assignment_id') ?: null;

        $campuses = SchoolInfo::query()->where('status', 1)->orderBy('campus_name_en')->get();
        $grades = Grade::query()->where('status', 1)->orderBy('grade_order')->orderBy('grade')->get();
        $classes = SchoolClass::query()
            ->with('grade')
            ->where('status', 1)
            ->when($selectedAcademicYearId, fn ($query) => $query->where('academic_year_id', $selectedAcademicYearId))
            ->orderBy('class_order')
            ->orderBy('class_name')
            ->get();
        $groups = SchoolGroup::query()->where('status', 1)->orderBy('group_order')->orderBy('group_name')->get();
        $teachers = Staff::query()
            ->with(['user', 'campuses'])
            ->where('status', 1)
            ->where('employment_status', 'active')
            ->whereIn('staff_category', ['teacher', 'staff_teacher'])
            ->orderBy('name_en')
            ->get();

        $assignmentsQuery = HomeroomAssignment::query()
            ->with(['staff.user', 'teacher', 'academicYear', 'campus', 'grade', 'schoolClass'])
            ->withCount('records')
            ->when($selectedAcademicYearId, fn ($query) => $query->where('academic_year_id', $selectedAcademicYearId))
            ->when($selectedCampusId, fn ($query) => $query->where('campus_id', $selectedCampusId))
            ->when($selectedStaffId && ($canViewAll || $canManageAssignments), fn ($query) => $query->where('staff_id', $selectedStaffId));

        if (! $canViewAll && ! $canManageAssignments) {
            $assignmentsQuery->where(function ($query) use ($user) {
                $query->where('teacher_id', $user->id);
                if ($user?->staff_profile_id) {
                    $query->orWhere('staff_id', $user->staff_profile_id);
                }
            });
        }

        $assignments = $assignmentsQuery
            ->orderByDesc('status')
            ->orderBy('campus_id')
            ->orderBy('class_id')
            ->get();

        if (! $selectedAssignmentId || ! $assignments->contains('id', $selectedAssignmentId)) {
            $selectedAssignmentId = optional($assignments->firstWhere('status', true))->id ?: optional($assignments->first())->id;
        }

        $selectedAssignment = $selectedAssignmentId ? $assignments->firstWhere('id', $selectedAssignmentId) : null;
        if (! $selectedAssignment && $selectedAssignmentId) {
            $selectedAssignment = HomeroomAssignment::with(['staff.user', 'teacher', 'academicYear', 'campus', 'grade', 'schoolClass'])->find($selectedAssignmentId);
        }

        $assignmentIds = $assignments->pluck('id')->all();
        $studentEnrollments = collect();
        $recordStudentOptions = collect();
        $studentHistoryCounts = collect();
        if ($selectedAssignment) {
            $selectedClassStudentsQuery = StudentEnrollment::query()
                ->with(['student', 'campus', 'academicYear', 'grade', 'schoolClass', 'schoolGroup'])
                ->where('academic_year_id', $selectedAssignment->academic_year_id)
                ->where('campus_id', $selectedAssignment->campus_id)
                ->where('class_id', $selectedAssignment->class_id)
                ->where('status', true)
                ->orderBy('group_id')
                ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
                ->orderBy('tb_student.full_name_en')
                ->select('tb_student_enrollment.*');

            $recordStudentOptions = (clone $selectedClassStudentsQuery)->get();
            $studentHistoryCounts = HomeroomRecord::query()
                ->where('academic_year_id', $selectedAssignment->academic_year_id)
                ->where('campus_id', $selectedAssignment->campus_id)
                ->where('class_id', $selectedAssignment->class_id)
                ->select('student_id', DB::raw('count(*) as total_records'))
                ->groupBy('student_id')
                ->pluck('total_records', 'student_id');

            $studentEnrollments = $selectedClassStudentsQuery
                ->paginate(25, ['*'], 'students_page')
                ->withQueryString();
        }

        $recordsQuery = HomeroomRecord::query()
            ->with(['student', 'campus', 'academicYear', 'grade', 'schoolClass', 'schoolGroup', 'assignment.staff', 'assignment.teacher', 'creator'])
            ->when($selectedAcademicYearId, fn ($query) => $query->where('academic_year_id', $selectedAcademicYearId))
            ->when($selectedCampusId, fn ($query) => $query->where('campus_id', $selectedCampusId))
            ->when($selectedAssignmentId, fn ($query) => $query->where('assignment_id', $selectedAssignmentId));

        if (! $canViewAll && ! $canManageAssignments) {
            $recordsQuery->whereIn('assignment_id', $assignmentIds ?: [0]);
        }

        $records = $recordsQuery
            ->latest('recorded_on')
            ->latest('id')
            ->paginate(15, ['*'], 'records_page')
            ->withQueryString();

        return view('homeroom-activities', compact(
            'academicYears', 'campuses', 'grades', 'classes', 'groups', 'teachers', 'assignments', 'selectedAcademicYearId',
            'selectedCampusId', 'selectedStaffId', 'selectedAssignmentId', 'selectedAssignment', 'studentEnrollments',
            'records', 'canViewAll', 'canManageAssignments', 'canCreateRecords', 'recordStudentOptions', 'studentHistoryCounts'
        ));
    }

    public function storeAssignment(Request $request): RedirectResponse
    {
        $this->authorizeHomeroom($request, 'homeroom.manage-assignments');

        $data = $request->validate([
            'staff_id' => ['required', 'exists:hrm_staff,id'],
            'academic_year_id' => ['required', 'exists:tb_academic_year,id'],
            'campus_id' => ['required', 'exists:tb_school_info,id'],
            'grade_id' => ['required', 'exists:tb_grade,id'],
            'class_id' => ['required', 'exists:tb_class,id'],
            'is_primary' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]);

        $staff = Staff::with('user')->findOrFail($data['staff_id']);
        if (! $staff->user) {
            throw ValidationException::withMessages([
                'staff_id' => 'Please link this staff profile to a user account before assigning homeroom classes.',
            ]);
        }

        $status = $request->boolean('status', true);
        $existing = HomeroomAssignment::withTrashed()->where([
            'staff_id' => $data['staff_id'],
            'academic_year_id' => $data['academic_year_id'],
            'campus_id' => $data['campus_id'],
            'class_id' => $data['class_id'],
        ])->first();

        if (! $existing) {
            $existing = HomeroomAssignment::withTrashed()->where([
                'teacher_id' => $staff->user->id,
                'academic_year_id' => $data['academic_year_id'],
                'campus_id' => $data['campus_id'],
                'class_id' => $data['class_id'],
            ])->first();
        }

        if ($status) {
            $activeCount = HomeroomAssignment::query()
                ->where(function ($query) use ($data, $staff) {
                    $query->where('staff_id', $data['staff_id'])
                        ->orWhere('teacher_id', $staff->user->id);
                })
                ->where('academic_year_id', $data['academic_year_id'])
                ->where('status', true)
                ->when($existing, fn ($query) => $query->whereKeyNot($existing->id))
                ->count();

            if ($activeCount >= 2) {
                throw ValidationException::withMessages([
                    'staff_id' => 'One homeroom teacher can control only 2 active classes per academic year. The two classes can be in different campuses.',
                ]);
            }
        }

        $payload = [
            'staff_id' => $staff->id,
            'teacher_id' => $staff->user->id,
            'grade_id' => $data['grade_id'],
            'is_primary' => $request->boolean('is_primary', true),
            'status' => $status,
            'updated_by' => $request->user()->id,
        ];

        if ($existing) {
            $existing->restore();
            $existing->update($payload);
            $assignment = $existing;
        } else {
            $assignment = HomeroomAssignment::create($payload + [
                'staff_id' => $staff->id,
                'teacher_id' => $staff->user->id,
                'academic_year_id' => $data['academic_year_id'],
                'campus_id' => $data['campus_id'],
                'class_id' => $data['class_id'],
                'created_by' => $request->user()->id,
            ]);
        }

        return redirect()
            ->route('homeroom-activities.index', ['academic_year_id' => $assignment->academic_year_id, 'campus_id' => $assignment->campus_id, 'assignment_id' => $assignment->id])
            ->with('success', 'Homeroom assignment saved.');
    }

    public function updateAssignment(Request $request, HomeroomAssignment $assignment): RedirectResponse
    {
        $this->authorizeHomeroom($request, 'homeroom.manage-assignments');

        $status = $request->boolean('status');
        if ($status && ! $assignment->status) {
            $activeCount = HomeroomAssignment::query()
                ->where(function ($query) use ($assignment) {
                    $query->where('teacher_id', $assignment->teacher_id);
                    if ($assignment->staff_id) {
                        $query->orWhere('staff_id', $assignment->staff_id);
                    }
                })
                ->where('academic_year_id', $assignment->academic_year_id)
                ->where('status', true)
                ->whereKeyNot($assignment->id)
                ->count();

            if ($activeCount >= 2) {
                throw ValidationException::withMessages([
                    'status' => 'This teacher already has 2 active homeroom classes for this academic year.',
                ]);
            }
        }

        $assignment->update([
            'status' => $status,
            'is_primary' => $request->boolean('is_primary', $assignment->is_primary),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Homeroom assignment updated.');
    }

    public function destroyAssignment(Request $request, HomeroomAssignment $assignment): RedirectResponse
    {
        $this->authorizeHomeroom($request, 'homeroom.manage-assignments');
        $assignment->update(['updated_by' => $request->user()->id]);
        $assignment->delete();

        return redirect()->route('homeroom-activities.index', ['academic_year_id' => $assignment->academic_year_id])->with('success', 'Homeroom assignment removed.');
    }

    public function storeRecord(Request $request): RedirectResponse
    {
        $user = $request->user();
        $campusId = $user?->active_campus_id;
        abort_unless($user?->hasPermission('homeroom.create', $campusId) || $this->canManageAssignments($user, $campusId), 403);

        $data = $request->validate([
            'assignment_id' => ['required', 'exists:homeroom_assignments,id'],
            'student_id' => ['required', 'exists:tb_student,id'],
            'record_type' => ['required', 'string', 'max:80'],
            'problem_type' => ['nullable', 'string', 'max:120'],
            'recorded_on' => ['required', 'date'],
            'meeting_with' => ['required', 'string', 'max:40'],
            'status' => ['required', 'string', 'max:30'],
            'problem_description' => ['required', 'string'],
            'action_taken' => ['nullable', 'string'],
            'parent_feedback' => ['nullable', 'string'],
            'follow_up_on' => ['nullable', 'date'],
            'resolution_note' => ['nullable', 'string'],
        ]);

        $assignment = HomeroomAssignment::findOrFail($data['assignment_id']);
        $this->ensureAssignmentAccess($request, $assignment);

        $enrollment = StudentEnrollment::query()
            ->where('student_id', $data['student_id'])
            ->where('academic_year_id', $assignment->academic_year_id)
            ->where('campus_id', $assignment->campus_id)
            ->where('class_id', $assignment->class_id)
            ->where('status', true)
            ->firstOrFail();

        $record = HomeroomRecord::create([
            'assignment_id' => $assignment->id,
            'student_id' => $data['student_id'],
            'enrollment_id' => $enrollment->id,
            'academic_year_id' => $assignment->academic_year_id,
            'campus_id' => $assignment->campus_id,
            'grade_id' => $enrollment->grade_id ?: $assignment->grade_id,
            'class_id' => $assignment->class_id,
            'group_id' => $enrollment->group_id,
            'record_type' => $data['record_type'],
            'problem_type' => $data['problem_type'] ?? null,
            'recorded_on' => $data['recorded_on'],
            'meeting_with' => $data['meeting_with'],
            'status' => $data['status'],
            'problem_description' => $data['problem_description'],
            'action_taken' => $data['action_taken'] ?? null,
            'parent_feedback' => $data['parent_feedback'] ?? null,
            'follow_up_on' => $data['follow_up_on'] ?? null,
            'resolution_note' => $data['resolution_note'] ?? null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('homeroom-activities.index', ['academic_year_id' => $record->academic_year_id, 'campus_id' => $record->campus_id, 'assignment_id' => $record->assignment_id])
            ->with('success', 'Homeroom record saved.');
    }

    public function updateRecord(Request $request, HomeroomRecord $record): RedirectResponse
    {
        $this->ensureRecordAccess($request, $record);

        $data = $request->validate([
            'status' => ['required', 'string', 'max:30'],
            'action_taken' => ['nullable', 'string'],
            'parent_feedback' => ['nullable', 'string'],
            'follow_up_on' => ['nullable', 'date'],
            'resolution_note' => ['nullable', 'string'],
        ]);

        $record->update($data + ['updated_by' => $request->user()->id]);

        return back()->with('success', 'Homeroom record updated.');
    }

    private function authorizeHomeroom(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission, $request->user()?->active_campus_id), 403);
    }

    private function canViewAll(?User $user, ?int $campusId): bool
    {
        return (bool) ($user && ($user->hasPermission('homeroom.view-all', $campusId) || $user->isSuperAdmin()));
    }

    private function canManageAssignments(?User $user, ?int $campusId): bool
    {
        return (bool) ($user && ($user->hasPermission('homeroom.manage-assignments', $campusId) || $user->isSuperAdmin()));
    }

    private function ensureAssignmentAccess(Request $request, HomeroomAssignment $assignment): void
    {
        $user = $request->user();
        if ($this->canViewAll($user, $user?->active_campus_id) || $this->canManageAssignments($user, $user?->active_campus_id)) {
            return;
        }

        abort_unless(
            (int) $assignment->teacher_id === (int) $user?->id
            || ($user?->staff_profile_id && (int) $assignment->staff_id === (int) $user->staff_profile_id),
            403
        );
    }

    private function ensureRecordAccess(Request $request, HomeroomRecord $record): void
    {
        $user = $request->user();
        $campusId = $user?->active_campus_id;

        if ($this->canViewAll($user, $campusId) || $this->canManageAssignments($user, $campusId)) {
            return;
        }

        if ($user?->hasPermission('homeroom.edit-own', $campusId) && (int) $record->created_by === (int) $user->id) {
            return;
        }

        abort(403);
    }
}
