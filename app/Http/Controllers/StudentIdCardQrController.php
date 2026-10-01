<?php

namespace App\Http\Controllers;

use App\Models\BrandingSetting;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolInfo;
use App\Models\Student;
use App\Models\StudentEnrollment;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class StudentIdCardQrController
{
    public function publicCard(string $qr)
    {
        abort_unless(Schema::hasColumn((new Student())->getTable(), 'id_card_qr_code'), 404);

        $student = Student::query()
            ->select(['id', 'student_id', 'student_no', 'id_card_qr_code', 'full_name_en', 'full_name_kh', 'photo_path', 'home_phone'])
            ->where('id_card_qr_code', $qr)
            ->firstOrFail();

        return view('student-id-card-public', [
            'branding' => BrandingSetting::current(),
            'student' => $student,
        ]);
    }

    public function index(Request $request)
    {
        [$gradeId, $classId] = array_pad(explode(':', (string) $request->input('grade_class'), 2), 2, null);
        $qrStudent = null;
        $hasStoredQrCode = Schema::hasColumn((new Student())->getTable(), 'id_card_qr_code');

        $filters = [
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
            'campus_id' => $request->integer('campus_id') ?: null,
            'grade_id' => (int) $gradeId ?: $request->integer('grade_id') ?: null,
            'class_id' => (int) $classId ?: $request->integer('class_id') ?: null,
            'student_id' => $request->integer('student_id') ?: null,
            'print_scope' => $request->input('print_scope') === 'class' ? 'class' : 'student',
        ];

        if ($hasStoredQrCode && $request->filled('qr')) {
            $qrStudent = Student::query()
                ->where('id_card_qr_code', $request->string('qr')->toString())
                ->first();

            if ($qrStudent) {
                $latestEnrollment = $qrStudent->enrollments()
                    ->latest('id')
                    ->first();

                $filters['student_id'] = $qrStudent->id;
                $filters['print_scope'] = 'student';
                $filters['academic_year_id'] ??= $latestEnrollment?->academic_year_id;
                $filters['campus_id'] ??= $latestEnrollment?->campus_id;
                $filters['grade_id'] ??= $latestEnrollment?->grade_id;
                $filters['class_id'] ??= $latestEnrollment?->class_id;
            }
        }

        $canShowStudents = filled($filters['academic_year_id'])
            && filled($filters['campus_id'])
            && filled($filters['grade_id'])
            && filled($filters['class_id']);

        $studentsQuery = Student::query()
            ->select(array_values(array_filter(['id', 'student_id', 'student_no', $hasStoredQrCode ? 'id_card_qr_code' : null, 'full_name_en', 'full_name_kh', 'photo_path', 'home_phone'])))
            ->whereHas('enrollments', function ($query) use ($filters) {
                $query
                    ->when($filters['academic_year_id'], fn ($query, $id) => $query->where('academic_year_id', $id))
                    ->when($filters['campus_id'], fn ($query, $id) => $query->where('campus_id', $id))
                    ->when($filters['grade_id'], fn ($query, $id) => $query->where('grade_id', $id))
                    ->when($filters['class_id'], fn ($query, $id) => $query->where('class_id', $id));
            })
            ->orderByRaw("COALESCE(NULLIF(full_name_en, ''), full_name_kh, student_id, student_no) ASC")
            ->limit(1000);

        $students = $canShowStudents ? (clone $studentsQuery)->get() : collect();

        $selectedStudent = null;
        if ($canShowStudents && $filters['student_id']) {
            $selectedStudent = (clone $studentsQuery)->find($filters['student_id']);
        }

        $selectedStudent ??= $students->first();
        if ($selectedStudent) {
            $selectedStudent->ensureIdCardQrCode();
        }

        $classEnrollmentIds = StudentEnrollment::query()
            ->whereNotNull('class_id')
            ->whereNotNull('grade_id')
            ->when($filters['academic_year_id'], fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when($filters['campus_id'], fn ($query, $id) => $query->where('campus_id', $id))
            ->select('grade_id', 'class_id')
            ->distinct()
            ->get();

        $gradeIds = $classEnrollmentIds->pluck('grade_id')->filter()->unique()->values();
        $classIds = $classEnrollmentIds->pluck('class_id')->filter()->unique()->values();
        $grades = Grade::query()
            ->whereIn('id', $gradeIds)
            ->get(['id', 'grade', 'grade_short_name', 'grade_order'])
            ->keyBy('id');
        $schoolClasses = SchoolClass::query()
            ->whereIn('id', $classIds)
            ->get(['id', 'class_name', 'class_order'])
            ->keyBy('id');

        $classes = $classEnrollmentIds
            ->map(function ($row) use ($grades, $schoolClasses) {
                $grade = $grades->get($row->grade_id);
                $schoolClass = $schoolClasses->get($row->class_id);
                $gradeText = trim((string) ($grade?->grade_short_name ?: $grade?->grade));
                $gradeNumber = preg_match('/\d+/', $gradeText, $matches) ? $matches[0] : $gradeText;
                $section = trim((string) ($schoolClass?->class_name ?? ''));
                $label = $gradeNumber . $section;

                return (object) [
                    'grade_id' => (int) $row->grade_id,
                    'class_id' => (int) $row->class_id,
                    'label' => $label !== '' ? $label : $section,
                    'grade_order' => (int) ($grade?->grade_order ?? 999),
                    'class_order' => (int) ($schoolClass?->class_order ?? 999),
                ];
            })
            ->sortBy([
                ['grade_order', 'asc'],
                ['class_order', 'asc'],
                ['label', 'asc'],
            ])
            ->values();

        return view('student-id-card-qr', [
            'branding' => BrandingSetting::current(),
            'academicYears' => AcademicYear::query()
                ->whereIn('id', StudentEnrollment::query()->whereNotNull('academic_year_id')->select('academic_year_id')->distinct())
                ->orderByDesc('academic_year')
                ->get(['id', 'academic_year', 'period_type']),
            'campuses' => SchoolInfo::query()
                ->whereIn('id', StudentEnrollment::query()->whereNotNull('campus_id')->select('campus_id')->distinct())
                ->orderBy('campus_name_en')
                ->get(['id', 'campus_name_en', 'campus_name_kh']),
            'classes' => $classes,
            'filters' => $filters,
            'canShowStudents' => $canShowStudents,
            'students' => $students,
            'selectedStudent' => $selectedStudent,
        ]);
    }

    public function qr(Student $student)
    {
        $hasStoredQrCode = Schema::hasColumn($student->getTable(), 'id_card_qr_code');
        $value = $hasStoredQrCode
            ? route('student-id-card.public', ['qr' => $student->ensureIdCardQrCode()])
            : route('student-id-card-qr.index', ['student_id' => $student->id]);
        $renderer = new ImageRenderer(new RendererStyle(360, 1), new SvgImageBackEnd());

        return response((new Writer($renderer))->writeString($value), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
