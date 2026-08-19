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
    public function index() { return view('student-graduation'); }

    public function options()
    {
        $campusId = request()->integer('campus_id') ?: null;
        $academicYearId = request()->integer('academic_year_id') ?: null;
        $listMode = request()->boolean('list');
        $campusIds = $listMode
            ? ($academicYearId
                ? StudentGraduation::query()
                    ->where('academic_year_id', $academicYearId)
                    ->select('campus_id')->distinct()->pluck('campus_id')
                : collect())
            : StudentEnrollment::query()
                ->where('enrollment_status', 'active')
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->whereHas('grade', fn ($q) => $q->where('grade', 'Grade 12'))
                ->select('campus_id')->distinct()->pluck('campus_id');

        $classIds = $listMode
            ? ($academicYearId && $campusId
                ? StudentGraduation::query()
                    ->whereNotNull('class_id')
                    ->where('campus_id', $campusId)
                    ->where('academic_year_id', $academicYearId)
                    ->select('class_id')->distinct()->pluck('class_id')
                : collect())
            : StudentEnrollment::query()
                ->where('enrollment_status', 'active')
                ->whereNotNull('class_id')
                ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->whereHas('grade', fn ($q) => $q->where('grade', 'Grade 12'))
                ->select('class_id')->distinct()->pluck('class_id');

        $academicYears = $listMode
            ? AcademicYear::query()
                ->where('period_type', 'regular')
                ->whereIn('id', StudentGraduation::query()->select('academic_year_id')->distinct())
                ->orderByDesc('academic_year')
                ->get(['id', 'academic_year'])
            : AcademicYear::query()
                ->where('status', 1)
                ->where('period_type', 'regular')
                ->whereIn('lifecycle_status', ['started', 'pending'])
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
                ->where('tb_student_enrollment.enrollment_status', 'active')
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
                    ->whereNotNull('tb_student_graduation.class_id')
                    ->where('tb_student_graduation.academic_year_id', $academicYearId)
                    ->where('tb_student_graduation.campus_id', $campusId)
                : DB::table('tb_student_enrollment')
                    ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
                    ->leftJoin('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
                    ->where('tb_student_enrollment.enrollment_status', 'active')
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
                'academicYear:id,academic_year',
                'campus:id,campus_name_en',
                'grade:id,grade',
                'schoolClass:id,class_name',
                'session:id,session_short_name',
                'changedBy:id,name',
                'enrollment:id,academic_track_id',
                'enrollment.academicTrack:id,name_en,name_kh,code',
            ])
            ->when($request->academic_year_id, fn ($q, $value) => $q->where('tb_student_graduation.academic_year_id', $value))
            ->when($request->campus_id, fn ($q, $value) => $q->where('tb_student_graduation.campus_id', $value))
            ->when($request->class_id, fn ($q, $value) => $q->where('tb_student_graduation.class_id', $value))
            ->when($request->search, fn ($q, $value) => $q->whereHas('student', fn ($student) => $student->where('student_no', 'like', "%{$value}%")->orWhere('student_id', 'like', "%{$value}%")->orWhere('full_name_en', 'like', "%{$value}%")->orWhere('full_name_kh', 'like', "%{$value}%")));
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
        return response()->json($query->paginate($request->integer('perPage', 10)));
    }

    public function graduate(Request $request)
    {
        $data = $request->validate([
            'enrollment_id' => ['nullable', 'exists:tb_student_enrollment,id'],
            'enrollment_ids' => ['nullable', 'array', 'min:1', 'required_without:enrollment_id'],
            'enrollment_ids.*' => ['integer', 'exists:tb_student_enrollment,id'],
            'graduation_date' => ['required', 'date'],
            'certificate_number' => ['required', 'digits:4'],
            'is_alumni' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $count = DB::transaction(function () use ($data) {
            $ids = collect($data['enrollment_ids'] ?? [$data['enrollment_id'] ?? null])
                ->filter()
                ->unique()
                ->values();

            if ($ids->isEmpty()) {
                throw ValidationException::withMessages(['enrollment_ids' => 'Please select at least one Grade 12 student.']);
            }

            $enrollments = StudentEnrollment::query()
                ->select('tb_student_enrollment.*')
                ->join('tb_school_info', 'tb_school_info.id', '=', 'tb_student_enrollment.campus_id')
                ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
                ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
                ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
                ->whereIn('tb_student_enrollment.id', $ids)
                ->where('tb_grade.grade', 'Grade 12')
                ->where('tb_student_enrollment.enrollment_status', 'active')
                ->orderBy('tb_school_info.campus_name_en')
                ->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')
                ->orderBy('tb_class.class_name')
                ->orderByRaw("COALESCE(NULLIF(tb_student.full_name_en, ''), tb_student.full_name_kh, tb_student.student_id)")
                ->get();

            if ($enrollments->count() !== $ids->count()) {
                throw ValidationException::withMessages(['enrollment_ids' => 'Only active Grade 12 students can be graduated.']);
            }

            $created = 0;
            $sequences = [];

            foreach ($enrollments as $enrollment) {
                if (StudentGraduation::where('enrollment_id', $enrollment->id)->exists()) continue;

                $sequences[$enrollment->academic_year_id] ??= $this->nextCertificateSequence($enrollment->academic_year_id);
                $certificateNumber = $this->makeCertificateNumber($data['certificate_number'], $sequences[$enrollment->academic_year_id]++);

                $enrollment->update(['enrollment_status' => 'graduated', 'ended_on' => $data['graduation_date'], 'exit_reason' => 'Graduated']);
                StudentGraduation::create([
                    'student_id' => $enrollment->student_id,
                    'enrollment_id' => $enrollment->id,
                    'academic_year_id' => $enrollment->academic_year_id,
                    'campus_id' => $enrollment->campus_id,
                    'grade_id' => $enrollment->grade_id,
                    'class_id' => $enrollment->class_id,
                    'session_id' => $enrollment->session_id,
                    'graduation_date' => $data['graduation_date'],
                    'certificate_number' => $certificateNumber,
                    'is_alumni' => $data['is_alumni'],
                    'notes' => $data['notes'] ?? null,
                    'changed_by' => auth()->id(),
                ]);
                StudentEnrollmentHistory::create([
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'action_type' => 'graduated',
                    'campus_id' => $enrollment->campus_id,
                    'academic_year_id' => $enrollment->academic_year_id,
                    'grade_id' => $enrollment->grade_id,
                    'class_id' => $enrollment->class_id,
                    'session_id' => $enrollment->session_id,
                    'enrollment_status' => 'graduated',
                    'student_type' => $enrollment->student_type,
                    'effective_on' => $data['graduation_date'],
                    'reason' => 'Graduated',
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
            'certificate_number' => ['required', 'digits:4'],
            'is_alumni' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        if ($data['scope'] === 'class' && empty($data['class_id'])) {
            throw ValidationException::withMessages(['class_id' => 'Please select a class.']);
        }
        if (in_array($data['scope'], ['class', 'campus'], true) && empty($data['campus_id'])) {
            throw ValidationException::withMessages(['campus_id' => 'Please select a campus.']);
        }

        $count = DB::transaction(function () use ($data) {
            $query = StudentEnrollment::query()
                ->select('tb_student_enrollment.*')
                ->join('tb_school_info', 'tb_school_info.id', '=', 'tb_student_enrollment.campus_id')
                ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
                ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
                ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
                ->where('tb_student_enrollment.academic_year_id', $data['academic_year_id'])
                ->where('tb_student_enrollment.enrollment_status', 'active')
                ->where('tb_grade.grade', 'Grade 12')
                ->orderBy('tb_school_info.campus_name_en')
                ->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')
                ->orderBy('tb_class.class_name')
                ->orderByRaw("COALESCE(NULLIF(tb_student.full_name_en, ''), tb_student.full_name_kh, tb_student.student_id)");
            if (in_array($data['scope'], ['class', 'campus'], true)) $query->where('tb_student_enrollment.campus_id', $data['campus_id']);
            if ($data['scope'] === 'class') $query->where('tb_student_enrollment.class_id', $data['class_id']);
            $enrollments = $query->get();
            if ($enrollments->isEmpty()) throw ValidationException::withMessages(['scope' => 'No active Grade 12 students were found for this selection.']);

            $created = 0;
            $nextCertificateSequence = $this->nextCertificateSequence($data['academic_year_id']);

            foreach ($enrollments as $enrollment) {
                if (StudentGraduation::where('enrollment_id', $enrollment->id)->exists()) continue;
                $enrollment->update(['enrollment_status' => 'graduated', 'ended_on' => $data['graduation_date'], 'exit_reason' => 'Graduated']);
                StudentGraduation::create([
                    'student_id' => $enrollment->student_id, 'enrollment_id' => $enrollment->id,
                    'academic_year_id' => $enrollment->academic_year_id, 'campus_id' => $enrollment->campus_id,
                    'grade_id' => $enrollment->grade_id, 'class_id' => $enrollment->class_id, 'session_id' => $enrollment->session_id,
                    'graduation_date' => $data['graduation_date'], 'certificate_number' => $this->makeCertificateNumber($data['certificate_number'], $nextCertificateSequence++),
                    'is_alumni' => $data['is_alumni'], 'notes' => $data['notes'] ?? null, 'changed_by' => auth()->id(),
                ]);
                StudentEnrollmentHistory::create([
                    'enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id, 'action_type' => 'graduated',
                    'campus_id' => $enrollment->campus_id, 'academic_year_id' => $enrollment->academic_year_id,
                    'grade_id' => $enrollment->grade_id, 'class_id' => $enrollment->class_id, 'session_id' => $enrollment->session_id,
                    'enrollment_status' => 'graduated', 'student_type' => $enrollment->student_type,
                    'effective_on' => $data['graduation_date'], 'reason' => 'Graduated', 'notes' => $data['notes'] ?? null,
                    'changed_by' => auth()->id(),
                ]);
                $created++;
            }
            return $created;
        });
        return response()->json(['status' => 'success', 'message' => "{$count} students graduated successfully.", 'count' => $count]);
    }

    private function nextCertificateSequence(int $academicYearId): int
    {
        AcademicYear::query()->whereKey($academicYearId)->lockForUpdate()->first();

        $next = ((int) StudentGraduation::query()
            ->where('academic_year_id', $academicYearId)
            ->whereNotNull('certificate_number')
            ->lockForUpdate()
            ->max(DB::raw('CAST(RIGHT(certificate_number, 3) AS UNSIGNED)'))) + 1;

        if ($next > 999) {
            throw ValidationException::withMessages(['certificate_number' => 'The certificate running number for this academic year has reached 999.']);
        }

        return $next;
    }

    private function makeCertificateNumber(string $prefix, int $sequence): string
    {
        if ($sequence > 999) {
            throw ValidationException::withMessages(['certificate_number' => 'The certificate running number for this academic year has reached 999.']);
        }

        return $prefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }
}
