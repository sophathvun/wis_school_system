<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolInfo;
use App\Models\StudentEnrollment;
use App\Models\StudentGraduation;
use App\Models\StudentEnrollmentHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GraduationController
{
    private const GRADUATABLE_ENROLLMENT_STATUSES = ['active', 'completed'];

    public function index() { return view('student-graduation'); }

    public function options()
    {
        $campusId = request()->integer('campus_id') ?: null;
        $academicYearId = request()->integer('academic_year_id') ?: null;
        $listMode = request()->boolean('list');
        $campusIds = $listMode
            ? ($academicYearId
                ? StudentGraduation::query()
                    ->whereIn('status', ['pending_result', 'completed', 'cancelled'])
                    ->where('academic_year_id', $academicYearId)
                    ->select('campus_id')->distinct()->pluck('campus_id')
                : collect())
            : StudentEnrollment::query()
                ->whereIn('enrollment_status', self::GRADUATABLE_ENROLLMENT_STATUSES)
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('tb_student_graduation')
                        ->whereColumn('tb_student_graduation.enrollment_id', 'tb_student_enrollment.id')
                        ->whereIn('tb_student_graduation.status', ['pending_result', 'completed']);
                })
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->whereHas('grade', fn ($q) => $q->where('grade', 'Grade 12'))
                ->select('campus_id')->distinct()->pluck('campus_id');

        $classIds = $listMode
            ? ($academicYearId && $campusId
                ? StudentGraduation::query()
                    ->whereIn('status', ['pending_result', 'completed', 'cancelled'])
                    ->whereNotNull('class_id')
                    ->where('campus_id', $campusId)
                    ->where('academic_year_id', $academicYearId)
                    ->select('class_id')->distinct()->pluck('class_id')
                : collect())
            : StudentEnrollment::query()
                ->whereIn('enrollment_status', self::GRADUATABLE_ENROLLMENT_STATUSES)
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('tb_student_graduation')
                        ->whereColumn('tb_student_graduation.enrollment_id', 'tb_student_enrollment.id')
                        ->whereIn('tb_student_graduation.status', ['pending_result', 'completed']);
                })
                ->whereNotNull('class_id')
                ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->whereHas('grade', fn ($q) => $q->where('grade', 'Grade 12'))
                ->select('class_id')->distinct()->pluck('class_id');

        $academicYears = $listMode
            ? AcademicYear::query()
                ->where('period_type', 'regular')
                ->whereIn('id', StudentGraduation::query()->whereIn('status', ['pending_result', 'completed', 'cancelled'])->select('academic_year_id')->distinct())
                ->orderByDesc('academic_year')
                ->get(['id', 'academic_year'])
            : AcademicYear::query()
                ->where('period_type', 'regular')
                ->whereIn('id', StudentEnrollment::query()
                    ->whereIn('enrollment_status', self::GRADUATABLE_ENROLLMENT_STATUSES)
                    ->whereHas('grade', fn ($q) => $q->where('grade', 'Grade 12'))
                    ->whereNotExists(function ($query) {
                        $query->selectRaw('1')
                            ->from('tb_student_graduation')
                            ->whereColumn('tb_student_graduation.enrollment_id', 'tb_student_enrollment.id')
                            ->whereIn('tb_student_graduation.status', ['pending_result', 'completed']);
                    })
                    ->select('academic_year_id')
                    ->distinct())
                ->orderByDesc('academic_year')
                ->get(['id', 'academic_year']);

        $enrollments = $listMode
            ? collect()
            : StudentEnrollment::query()
                ->select('tb_student_enrollment.*')
                ->join('tb_school_info', 'tb_school_info.id', '=', 'tb_student_enrollment.campus_id')
                ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
                ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
                ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
                ->with(['student:id,student_no,student_id,full_name_en,full_name_kh', 'academicYear:id,academic_year', 'campus:id,campus_name_en', 'schoolClass:id,class_name', 'session:id,session_short_name'])
                ->whereIn('tb_student_enrollment.enrollment_status', self::GRADUATABLE_ENROLLMENT_STATUSES)
                ->when($academicYearId, fn ($q) => $q->where('tb_student_enrollment.academic_year_id', $academicYearId))
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('tb_student_graduation')
                        ->whereColumn('tb_student_graduation.enrollment_id', 'tb_student_enrollment.id')
                        ->whereIn('tb_student_graduation.status', ['pending_result', 'completed']);
                })
                ->where('tb_grade.grade', 'Grade 12')
                ->orderBy('tb_school_info.campus_name_en')
                ->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')
                ->orderBy('tb_class.class_name')
                ->orderByRaw("COALESCE(NULLIF(tb_student.full_name_en, ''), tb_student.full_name_kh, tb_student.student_id)")
                ->get();

        $classes = $listMode && (!$academicYearId || !$campusId)
            ? collect()
            : ($listMode
                ? DB::table('tb_student_graduation')
                    ->join('tb_class', 'tb_class.id', '=', 'tb_student_graduation.class_id')
                    ->leftJoin('tb_grade', 'tb_grade.id', '=', 'tb_student_graduation.grade_id')
                    ->whereIn('tb_student_graduation.status', ['pending_result', 'completed', 'cancelled'])
                    ->whereNotNull('tb_student_graduation.class_id')
                    ->where('tb_student_graduation.academic_year_id', $academicYearId)
                    ->where('tb_student_graduation.campus_id', $campusId)
                : DB::table('tb_student_enrollment')
                    ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
                    ->leftJoin('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
                    ->whereIn('tb_student_enrollment.enrollment_status', self::GRADUATABLE_ENROLLMENT_STATUSES)
                    ->whereNotExists(function ($query) {
                        $query->selectRaw('1')
                            ->from('tb_student_graduation')
                            ->whereColumn('tb_student_graduation.enrollment_id', 'tb_student_enrollment.id')
                            ->whereIn('tb_student_graduation.status', ['pending_result', 'completed']);
                    })
                    ->whereNotNull('tb_student_enrollment.class_id')
                    ->when($academicYearId, fn ($q) => $q->where('tb_student_enrollment.academic_year_id', $academicYearId))
                    ->when($campusId, fn ($q) => $q->where('tb_student_enrollment.campus_id', $campusId))
                    ->where('tb_grade.grade', 'Grade 12'))
                ->select([
                    'tb_class.id',
                    'tb_class.class_name',
                    'tb_class.class_order',
                    'tb_grade.id as grade_id',
                    'tb_grade.grade',
                    'tb_grade.grade_order',
                ])
                ->distinct()
                ->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')
                ->orderByRaw('CAST(tb_class.class_order AS UNSIGNED)')
                ->orderBy('tb_class.class_name')
                ->get()
                ->map(function ($item) {
                    $grade = trim((string) $item->grade);
                    $class = trim((string) $item->class_name);
                    $gradeShort = trim(preg_replace('/^grade\s*/i', '', $grade));
                    $classShort = trim(preg_replace('/^grade\s*/i', '', $class));
                    $displayName = $class;

                    if ($grade && $class) {
                        $displayName = preg_match('/^grade\s*/i', $class)
                            ? $class
                            : ($grade . (stripos($classShort, $gradeShort) === 0 ? substr($classShort, strlen($gradeShort)) : $class));
                    }

                    return [
                        'id' => $item->id,
                        'class_name' => $class,
                        'class_order' => $item->class_order,
                        'display_name' => $displayName,
                        'grade' => [
                            'id' => $item->grade_id,
                            'grade' => $grade,
                            'grade_order' => $item->grade_order,
                        ],
                    ];
                });

        return response()->json([
            'academicYears' => $academicYears,
            'campuses' => SchoolInfo::where('status', 1)->whereIn('id', $campusIds)->orderBy('campus_name_en')->get(['id', 'campus_name_en']),
            'classes' => $classes,
            'grade12' => Grade::where('status', 1)->where('grade', 'Grade 12')->first(['id', 'grade']),
            'enrollments' => $enrollments,
        ]);
    }

    public function fetch(Request $request)
    {
        $sortBy = (string) $request->query('sortBy', 'graduation_date');
        $sortDir = $request->query('sortDir') === 'asc' ? 'asc' : 'desc';
        $query = StudentGraduation::query()
            ->select('tb_student_graduation.*')
            ->leftJoin('tb_student', 'tb_student.id', '=', 'tb_student_graduation.student_id')
            ->leftJoin('tb_academic_year', 'tb_academic_year.id', '=', 'tb_student_graduation.academic_year_id')
            ->leftJoin('tb_school_info', 'tb_school_info.id', '=', 'tb_student_graduation.campus_id')
            ->leftJoin('tb_class', 'tb_class.id', '=', 'tb_student_graduation.class_id')
            ->leftJoin('tb_session', 'tb_session.id', '=', 'tb_student_graduation.session_id')
            ->leftJoin('users', 'users.id', '=', 'tb_student_graduation.changed_by')
            ->with([
                'student:id,student_no,student_id,photo_path,full_name_en,full_name_kh',
                'academicYear:id,academic_year,lifecycle_status',
                'campus:id,campus_name_en',
                'grade:id,grade',
                'schoolClass:id,class_name',
                'session:id,session_short_name',
                'changedBy:id,name',
                'enrollment:id,academic_track_id',
                'enrollment.academicTrack:id,name_en,name_kh,code',
            ])
            ->whereIn('tb_student_graduation.status', ['pending_result', 'completed', 'cancelled'])
            ->when($request->academic_year_id, fn ($q, $value) => $q->where('tb_student_graduation.academic_year_id', $value))
            ->when($request->campus_id, fn ($q, $value) => $q->where('tb_student_graduation.campus_id', $value))
            ->when($request->class_id, fn ($q, $value) => $q->where('tb_student_graduation.class_id', $value))
            ->when($request->search, fn ($q, $value) => $q->whereHas('student', fn ($student) => $student->where('student_no', 'like', "%{$value}%")->orWhere('student_id', 'like', "%{$value}%")->orWhere('full_name_en', 'like', "%{$value}%")->orWhere('full_name_kh', 'like', "%{$value}%")));
        $summaryQuery = (clone $query)->withoutEagerLoads();
        $summaryQuery->getQuery()->columns = null;
        $summary = $summaryQuery
            ->selectRaw('COUNT(DISTINCT tb_student_graduation.student_id) as total')
            ->selectRaw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student.gender, '')) IN ('male', 'm') OR COALESCE(tb_student.gender_kh, '') LIKE '%ប្រុស%' THEN tb_student_graduation.student_id END) as male")
            ->selectRaw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student.gender, '')) IN ('female', 'f') OR COALESCE(tb_student.gender_kh, '') LIKE '%ស្រី%' THEN tb_student_graduation.student_id END) as female")
            ->first();
        match ($sortBy) {
            'student_id' => $query->orderBy('tb_student.student_id', $sortDir),
            'student_name' => $query->orderByRaw("COALESCE(NULLIF(tb_student.full_name_en, ''), tb_student.full_name_kh, tb_student.student_id) {$sortDir}"),
            'academic_year' => $query->orderBy('tb_academic_year.academic_year', $sortDir),
            'campus' => $query->orderBy('tb_school_info.campus_name_en', $sortDir),
            'class' => $query->orderBy('tb_class.class_name', $sortDir),
            'group' => $query->orderBy('tb_session.session_short_name', $sortDir),
            'certificate' => $query->orderBy('tb_student_graduation.certificate_number', $sortDir),
            'alumni' => $query->orderBy('tb_student_graduation.is_alumni', $sortDir),
            'graduated_by' => $query->orderBy('users.name', $sortDir),
            default => $query->orderBy('tb_student_graduation.graduation_date', $sortDir)->orderBy('tb_student_graduation.id', $sortDir),
        };
        $records = $query->paginate($request->integer('perPage', 10));
        return response()->json($records->toArray() + [
            'summary' => [
                'total' => (int) ($summary->total ?? 0),
                'male' => (int) ($summary->male ?? 0),
                'female' => (int) ($summary->female ?? 0),
            ],
        ]);
    }

    public function graduate(Request $request)
    {
        $data = $request->validate([
            'enrollment_id' => ['nullable', 'exists:tb_student_enrollment,id'],
            'enrollment_ids' => ['nullable', 'array', 'min:1', 'required_without:enrollment_id'],
            'enrollment_ids.*' => ['integer', 'exists:tb_student_enrollment,id'],
            'graduation_date' => ['required', 'date'],
            'is_alumni' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $count = DB::transaction(function () use ($data, $request) {
            $ids = collect($data['enrollment_ids'] ?? [$data['enrollment_id'] ?? null])
                ->filter()
                ->unique()
                ->values();

            if ($ids->isEmpty()) {
                throw ValidationException::withMessages(['enrollment_ids' => 'Please select at least one Grade 12 student.']);
            }

            AcademicYear::whereIn('id', StudentEnrollment::whereIn('id',$ids)->select('academic_year_id'))->orderBy('id')->lockForUpdate()->get();
            $alreadyGraduatedCount = StudentGraduation::query()
                ->whereIn('enrollment_id', $ids)
                ->whereIn('status', ['pending_result', 'completed'])
                ->count();

            if ($alreadyGraduatedCount > 0) {
                throw ValidationException::withMessages([
                    'enrollment_ids' => $alreadyGraduatedCount === 1
                        ? 'This student is already graduated. Please cancel the graduation first if you need to graduate again.'
                        : "{$alreadyGraduatedCount} selected students are already graduated. Please cancel their graduations first if you need to graduate again.",
                ]);
            }

            $enrollments = StudentEnrollment::query()
                ->select('tb_student_enrollment.*')
                ->join('tb_school_info', 'tb_school_info.id', '=', 'tb_student_enrollment.campus_id')
                ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
                ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
                ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
                ->whereIn('tb_student_enrollment.id', $ids)
                ->where('tb_student_enrollment.status',1)->where('tb_student.status',1)
                ->where('tb_grade.grade', 'Grade 12')
                ->whereIn('tb_student_enrollment.enrollment_status', self::GRADUATABLE_ENROLLMENT_STATUSES)
                ->orderBy('tb_school_info.campus_name_en')
                ->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')
                ->orderBy('tb_class.class_name')
                ->orderByRaw("COALESCE(NULLIF(tb_student.full_name_en, ''), tb_student.full_name_kh, tb_student.student_id)")
                ->get();

            if ($enrollments->count() !== $ids->count()) {
                throw ValidationException::withMessages(['enrollment_ids' => 'Only Grade 12 students with active or completed enrollment can be graduated.']);
            }

            $created = 0;
            $wis = app(\App\Services\G12WisGraduation::class);

            foreach ($enrollments as $enrollment) {
                $this->authorizeAction($request,'execute',$enrollment->campus_id);
                $certificateNumber = $wis->numberForGraduation($enrollment->student_id, $enrollment->academic_year_id);

                $graduation = StudentGraduation::updateOrCreate(['enrollment_id' => $enrollment->id], [
                    'student_id' => $enrollment->student_id,
                    'academic_year_id' => $enrollment->academic_year_id,
                    'campus_id' => $enrollment->campus_id,
                    'grade_id' => $enrollment->grade_id,
                    'class_id' => $enrollment->class_id,
                    'session_id' => $enrollment->session_id,
                    'graduation_date' => $data['graduation_date'],
                    'certificate_number' => $certificateNumber,
                    'is_alumni' => $data['is_alumni'],
                    'status' => 'pending_result',
                    'cancellation_type' => null,
                    'cancellation_reason' => null,
                    'cancelled_at' => null,
                    'cancelled_by' => null,
                    'notes' => $data['notes'] ?? null,
                    'changed_by' => auth()->id(),
                ]);
                $wis->event('graduation_pending', $enrollment->student_id, $enrollment->academic_year_id, $wis->certificate($enrollment->student_id, $enrollment->academic_year_id), $graduation,
                    ['previous_enrollment'=>$enrollment->only(['enrollment_status','ended_on','exit_reason'])]);
                StudentEnrollmentHistory::create([
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'action_type' => 'graduation_pending',
                    'campus_id' => $enrollment->campus_id,
                    'academic_year_id' => $enrollment->academic_year_id,
                    'grade_id' => $enrollment->grade_id,
                    'class_id' => $enrollment->class_id,
                    'session_id' => $enrollment->session_id,
                    'enrollment_status' => $enrollment->enrollment_status,
                    'student_type' => $enrollment->student_type,
                    'effective_on' => $data['graduation_date'],
                    'reason' => 'Graduation pending WIS exam result',
                    'notes' => $data['notes'] ?? null,
                    'changed_by' => auth()->id(),
                ]);
                $created++;
            }

            return $created;
        });
        return response()->json(['status' => 'success', 'message' => "{$count} students graduated successfully.", 'count' => $count]);
    }

    public function graduateBatch(Request $request)
    {
        $data = $request->validate([
            'scope' => ['required', 'in:class,campus,all_campuses'],
            'academic_year_id' => ['required', 'exists:tb_academic_year,id'],
            'campus_id' => ['nullable', 'exists:tb_school_info,id'],
            'class_id' => ['nullable', 'exists:tb_class,id'],
            'graduation_date' => ['required', 'date'],
            'is_alumni' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        if ($data['scope'] === 'class' && empty($data['class_id'])) {
            throw ValidationException::withMessages(['class_id' => 'Please select a class.']);
        }
        if (in_array($data['scope'], ['class', 'campus'], true) && empty($data['campus_id'])) {
            throw ValidationException::withMessages(['campus_id' => 'Please select a campus.']);
        }

        $count = DB::transaction(function () use ($data, $request) {
            AcademicYear::whereKey($data['academic_year_id'])->lockForUpdate()->firstOrFail();
            $query = StudentEnrollment::query()
                ->select('tb_student_enrollment.*')
                ->join('tb_school_info', 'tb_school_info.id', '=', 'tb_student_enrollment.campus_id')
                ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
                ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
                ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
                ->where('tb_student_enrollment.academic_year_id', $data['academic_year_id'])
                ->where('tb_student_enrollment.status',1)->where('tb_student.status',1)
                ->whereIn('tb_student_enrollment.enrollment_status', self::GRADUATABLE_ENROLLMENT_STATUSES)
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('tb_student_graduation')
                        ->whereColumn('tb_student_graduation.enrollment_id', 'tb_student_enrollment.id')
                        ->whereIn('tb_student_graduation.status', ['pending_result', 'completed']);
                })
                ->where('tb_grade.grade', 'Grade 12')
                ->orderBy('tb_school_info.campus_name_en')
                ->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')
                ->orderBy('tb_class.class_name')
                ->orderByRaw("COALESCE(NULLIF(tb_student.full_name_en, ''), tb_student.full_name_kh, tb_student.student_id)");
            if (in_array($data['scope'], ['class', 'campus'], true)) $query->where('tb_student_enrollment.campus_id', $data['campus_id']);
            if ($data['scope'] === 'class') $query->where('tb_student_enrollment.class_id', $data['class_id']);
            if (!$request->user()?->isSuperAdmin()) {
                $allowed=$request->user()?->accessibleCampuses()->pluck('tb_school_info.id')->filter(fn($campus)=>$request->user()->hasPermission('students.graduation.execute',$campus)||$request->user()->hasPermission('students.graduation.manage',$campus)) ?? collect();
                $query->whereIn('tb_student_enrollment.campus_id',$allowed);
            }
            $enrollments = $query->get();
            if ($enrollments->isEmpty()) throw ValidationException::withMessages(['scope' => 'No eligible Grade 12 students were found for this selection. Students already graduated are excluded.']);

            $created = 0;
            $wis = app(\App\Services\G12WisGraduation::class);

            foreach ($enrollments as $enrollment) {
                $this->authorizeAction($request,'execute',$enrollment->campus_id);
                $certificateNumber = $wis->numberForGraduation($enrollment->student_id, $enrollment->academic_year_id);
                $graduation = StudentGraduation::updateOrCreate(['enrollment_id' => $enrollment->id], [
                    'student_id' => $enrollment->student_id,
                    'academic_year_id' => $enrollment->academic_year_id, 'campus_id' => $enrollment->campus_id,
                    'grade_id' => $enrollment->grade_id, 'class_id' => $enrollment->class_id, 'session_id' => $enrollment->session_id,
                    'graduation_date' => $data['graduation_date'], 'certificate_number' => $certificateNumber,
                    'is_alumni' => $data['is_alumni'], 'status' => 'pending_result',
                    'cancellation_type' => null, 'cancellation_reason' => null, 'cancelled_at' => null, 'cancelled_by' => null,
                    'notes' => $data['notes'] ?? null, 'changed_by' => auth()->id(),
                ]);
                $wis->event('graduation_pending', $enrollment->student_id, $enrollment->academic_year_id, $wis->certificate($enrollment->student_id, $enrollment->academic_year_id), $graduation,
                    ['previous_enrollment'=>$enrollment->only(['enrollment_status','ended_on','exit_reason'])]);
                StudentEnrollmentHistory::create([
                    'enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id, 'action_type' => 'graduation_pending',
                    'campus_id' => $enrollment->campus_id, 'academic_year_id' => $enrollment->academic_year_id,
                    'grade_id' => $enrollment->grade_id, 'class_id' => $enrollment->class_id, 'session_id' => $enrollment->session_id,
                    'enrollment_status' => $enrollment->enrollment_status, 'student_type' => $enrollment->student_type,
                    'effective_on' => $data['graduation_date'], 'reason' => 'Graduation pending WIS exam result', 'notes' => $data['notes'] ?? null,
                    'changed_by' => auth()->id(),
                ]);
                $created++;
            }
            return $created;
        });
        return response()->json(['status' => 'success', 'message' => "{$count} students graduated successfully.", 'count' => $count]);
    }

    public function cancel(Request $request, StudentGraduation $graduation)
    {
        $this->authorizeAction($request,'cancel',$graduation->campus_id);
        if (!in_array($graduation->status ?? 'completed', ['pending_result','completed'], true)) {
            return response()->json(['message' => 'This graduation has already been cancelled.'], 422);
        }

        $data = $request->validate([
            'cancellation_type' => ['required', 'in:failed,cancelled,other'],
            'cancellation_reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($graduation, $data) {
            AcademicYear::whereKey($graduation->academic_year_id)->lockForUpdate()->firstOrFail();
            $graduation = StudentGraduation::whereKey($graduation->id)->lockForUpdate()->firstOrFail();
            if (!in_array($graduation->status, ['pending_result','completed'], true)) throw ValidationException::withMessages(['status'=>'This graduation is already cancelled.']);
            $graduation->load(['enrollment', 'academicYear']);
            $restoredStatus = $this->restoredEnrollmentStatus($graduation->academicYear);
            $reasonType = match ($data['cancellation_type']) {
                'failed' => 'Failed',
                'cancelled' => 'Cancelled',
                default => 'Other',
            };

            $graduation->update([
                'status' => 'cancelled',
                'cancellation_type' => $data['cancellation_type'],
                'cancellation_reason' => $data['cancellation_reason'],
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
            ]);
            app(\App\Services\G12WisGraduation::class)->void($graduation, $data['cancellation_reason']);

            if ($graduation->enrollment) {
                $pendingEvent=DB::table('tb_g12_wis_certificate_event')->where('graduation_id',$graduation->id)->where('action','graduation_pending')->latest('id')->value('details');
                $previous=$pendingEvent ? (json_decode($pendingEvent,true)['previous_enrollment']??null) : null;
                $restore=$previous ?: [
                    'enrollment_status' => $restoredStatus,
                    'ended_on' => null,
                    'exit_reason' => null,
                ];
                $graduation->enrollment->update($restore);
                $restoredStatus=$restore['enrollment_status'];

                StudentEnrollmentHistory::create([
                    'enrollment_id' => $graduation->enrollment->id,
                    'student_id' => $graduation->student_id,
                    'action_type' => 'graduation_cancelled',
                    'campus_id' => $graduation->campus_id,
                    'academic_year_id' => $graduation->academic_year_id,
                    'grade_id' => $graduation->grade_id,
                    'class_id' => $graduation->class_id,
                    'session_id' => $graduation->session_id,
                    'enrollment_status' => $restoredStatus,
                    'student_type' => $graduation->enrollment->student_type,
                    'effective_on' => now()->toDateString(),
                    'reason' => "Graduation cancelled: {$reasonType}",
                    'notes' => $data['cancellation_reason'],
                    'changed_by' => auth()->id(),
                ]);
            }
        });

        return response()->json(['status' => 'success', 'message' => 'Graduation cancelled. The linked WIS certificate is voided and its number is retained.']);
    }

    public function confirmPass(Request $request, StudentGraduation $graduation)
    {
        $this->authorizeAction($request,'execute',$graduation->campus_id);
        app(\App\Services\G12WisGraduation::class)->confirmPass($graduation);
        return response()->json(['message'=>'Exam pass confirmed. Graduation is completed and the WIS certificate is valid.']);
    }

    public function confirmPassPreview(Request $request)
    {
        abort_unless($request->user(),401);
        $data=$request->validate(['academic_year_id'=>'required|integer|exists:tb_academic_year,id','campus_id'=>'nullable|integer','class_id'=>'nullable|integer','search'=>'nullable|string|max:150']);
        if (!empty($data['campus_id'])) $this->authorizeAction($request,'execute',(int)$data['campus_id']);
        $query=StudentGraduation::where('status','pending_result')->where('academic_year_id',$data['academic_year_id'])
            ->when($data['campus_id']??null,fn($q,$v)=>$q->where('campus_id',$v))
            ->when($data['class_id']??null,fn($q,$v)=>$q->where('class_id',$v))
            ->when($data['search']??null,fn($q,$v)=>$q->whereHas('student',fn($s)=>$s->where('student_no','like',"%{$v}%")->orWhere('student_id','like',"%{$v}%")->orWhere('full_name_en','like',"%{$v}%")->orWhere('full_name_kh','like',"%{$v}%")));
        if (!$request->user()?->isSuperAdmin()) $query->whereIn('campus_id',$request->user()->accessibleCampuses()->pluck('tb_school_info.id'));
        $records=$query->orderBy('id')->get(['id','campus_id']);
        $allowed=[];
        foreach($records as $record){
            try { $this->authorizeAction($request,'execute',$record->campus_id);$allowed[]=$record->id; }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { if($e->getStatusCode()!==403) throw $e; }
        }
        return response()->json(['ids'=>$allowed,'count'=>count($allowed)]);
    }

    public function confirmPassBatch(Request $request)
    {
        $data=$request->validate(['ids'=>'required|array|min:1|max:25','ids.*'=>'required|integer|distinct|exists:tb_student_graduation,id']);
        $records=StudentGraduation::whereIn('id',$data['ids'])->orderBy('academic_year_id')->orderBy('id')->get();
        foreach($records as $record) $this->authorizeAction($request,'execute',$record->campus_id);
        $counts=DB::transaction(function()use($records){
            $confirmed=0;$skipped=0;
            foreach($records as $record){
                $record->refresh();
                if($record->status!=='pending_result'){ $skipped++;continue; }
                app(\App\Services\G12WisGraduation::class)->confirmPass($record);$confirmed++;
            }
            return ['confirmed'=>$confirmed,'skipped'=>$skipped];
        });
        return response()->json($counts);
    }

    private function authorizeAction(Request $request, string $action, ?int $campus): void
    {
        $user=$request->user();
        if ($user?->isSuperAdmin()) return;
        abort_unless($user && $campus && $user->accessibleCampuses()->where('tb_school_info.id',$campus)->exists()
            && ($user->hasPermission('students.graduation.'.$action,$campus)||$user->hasPermission('students.graduation.manage',$campus)),403);
    }

    private function restoredEnrollmentStatus(?AcademicYear $academicYear): string
    {
        return match ($academicYear?->lifecycle_status) {
            'started' => 'active',
            'pending', 'draft' => 'pending',
            default => 'completed',
        };
    }

}
