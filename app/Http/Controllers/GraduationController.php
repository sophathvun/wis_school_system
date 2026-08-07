<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolInfo;
use App\Models\Session;
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
            ? StudentGraduation::query()
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->select('campus_id')->distinct()->pluck('campus_id')
            : StudentEnrollment::query()
                ->where('enrollment_status', 'active')
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->whereHas('grade', fn ($q) => $q->where('grade', 'Grade 12'))
                ->select('campus_id')->distinct()->pluck('campus_id');

        $classIds = $listMode
            ? StudentGraduation::query()
                ->whereNotNull('class_id')
                ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->select('class_id')->distinct()->pluck('class_id')
            : StudentEnrollment::query()
                ->where('enrollment_status', 'active')
                ->whereNotNull('class_id')
                ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->whereHas('grade', fn ($q) => $q->where('grade', 'Grade 12'))
                ->select('class_id')->distinct()->pluck('class_id');

        return response()->json([
            'academicYears' => AcademicYear::where('status', 1)->orderByDesc('id')->get(['id', 'academic_year']),
            'campuses' => SchoolInfo::where('status', 1)->whereIn('id', $campusIds)->orderBy('campus_name_en')->get(['id', 'campus_name_en']),
            'classes' => SchoolClass::where('status', 1)->whereIn('id', $classIds)->orderBy('class_name')->get(['id', 'class_name']),
            'grade12' => Grade::where('status', 1)->where('grade', 'Grade 12')->first(['id', 'grade']),
            'enrollments' => StudentEnrollment::with(['student:id,student_no,student_id,first_name_en,last_name_en', 'academicYear:id,academic_year', 'campus:id,campus_name_en', 'schoolClass:id,class_name', 'session:id,session_short_name'])
                ->where('enrollment_status', 'active')->whereHas('grade', fn ($q) => $q->where('grade', 'Grade 12'))->latest('id')->get(),
        ]);
    }

    public function fetch(Request $request)
    {
        $query = StudentGraduation::with(['student:id,student_no,student_id,first_name_en,last_name_en', 'academicYear:id,academic_year', 'campus:id,campus_name_en', 'grade:id,grade', 'schoolClass:id,class_name', 'session:id,session_short_name'])
            ->when($request->academic_year_id, fn ($q, $value) => $q->where('academic_year_id', $value))
            ->when($request->campus_id, fn ($q, $value) => $q->where('campus_id', $value))
            ->when($request->class_id, fn ($q, $value) => $q->where('class_id', $value))
            ->when($request->search, fn ($q, $value) => $q->whereHas('student', fn ($student) => $student->where('student_no', 'like', "%{$value}%")->orWhere('student_id', 'like', "%{$value}%")->orWhere('first_name_en', 'like', "%{$value}%")->orWhere('last_name_en', 'like', "%{$value}%")))
            ->latest('id');
        return response()->json($query->paginate($request->integer('perPage', 10)));
    }

    public function graduate(Request $request)
    {
        $data = $request->validate([
            'enrollment_id' => ['required', 'exists:tb_student_enrollment,id'],
            'graduation_date' => ['required', 'date'],
            'certificate_number' => ['nullable', 'string', 'max:80'],
            'is_alumni' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $graduation = DB::transaction(function () use ($data) {
            $enrollment = StudentEnrollment::with('grade')->findOrFail($data['enrollment_id']);
            if (($enrollment->grade?->grade ?? '') !== 'Grade 12') {
                throw ValidationException::withMessages(['enrollment_id' => 'Only Grade 12 students can be graduated.']);
            }
            if (StudentGraduation::where('enrollment_id', $enrollment->id)->exists()) {
                throw ValidationException::withMessages(['enrollment_id' => 'This student has already been graduated.']);
            }
            $enrollment->update(['enrollment_status' => 'graduated', 'ended_on' => $data['graduation_date'], 'exit_reason' => 'Graduated']);
            $graduation = StudentGraduation::create([
                'student_id' => $enrollment->student_id,
                'enrollment_id' => $enrollment->id,
                'academic_year_id' => $enrollment->academic_year_id,
                'campus_id' => $enrollment->campus_id,
                'grade_id' => $enrollment->grade_id,
                'class_id' => $enrollment->class_id,
                'session_id' => $enrollment->session_id,
                'graduation_date' => $data['graduation_date'],
                'certificate_number' => $data['certificate_number'] ?? null,
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
            return $graduation;
        });
        return response()->json(['status' => 'success', 'message' => 'Student graduated successfully.', 'data' => $graduation]);
    }

    public function graduateBatch(Request $request)
    {
        $data = $request->validate([
            'scope' => ['required', 'in:class,campus'],
            'academic_year_id' => ['required', 'exists:tb_academic_year,id'],
            'campus_id' => ['required', 'exists:tb_school_info,id'],
            'class_id' => ['nullable', 'exists:tb_class,id'],
            'graduation_date' => ['required', 'date'],
            'certificate_number' => ['nullable', 'string', 'max:80'],
            'is_alumni' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        if ($data['scope'] === 'class' && empty($data['class_id'])) {
            throw ValidationException::withMessages(['class_id' => 'Please select a class.']);
        }

        $count = DB::transaction(function () use ($data) {
            $query = StudentEnrollment::where('academic_year_id', $data['academic_year_id'])
                ->where('campus_id', $data['campus_id'])
                ->where('enrollment_status', 'active')
                ->whereHas('grade', fn ($q) => $q->where('grade', 'Grade 12'));
            if ($data['scope'] === 'class') $query->where('class_id', $data['class_id']);
            $enrollments = $query->get();
            if ($enrollments->isEmpty()) throw ValidationException::withMessages(['scope' => 'No active Grade 12 students were found for this selection.']);

            foreach ($enrollments as $enrollment) {
                if (StudentGraduation::where('enrollment_id', $enrollment->id)->exists()) continue;
                $enrollment->update(['enrollment_status' => 'graduated', 'ended_on' => $data['graduation_date'], 'exit_reason' => 'Graduated']);
                StudentGraduation::create([
                    'student_id' => $enrollment->student_id, 'enrollment_id' => $enrollment->id,
                    'academic_year_id' => $enrollment->academic_year_id, 'campus_id' => $enrollment->campus_id,
                    'grade_id' => $enrollment->grade_id, 'class_id' => $enrollment->class_id, 'session_id' => $enrollment->session_id,
                    'graduation_date' => $data['graduation_date'], 'certificate_number' => $data['certificate_number'] ?? null,
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
            }
            return $enrollments->count();
        });
        return response()->json(['status' => 'success', 'message' => "{$count} students graduated successfully.", 'count' => $count]);
    }
}
