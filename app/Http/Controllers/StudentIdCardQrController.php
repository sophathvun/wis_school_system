<?php

namespace App\Http\Controllers;

use App\Models\BrandingSetting;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\FamilyMember;
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
    private function gradeClassLabel(?Grade $grade, ?SchoolClass $schoolClass): string
    {
        $gradeCode = rtrim(trim((string) ($grade?->grade_short_name ?: $grade?->grade)), '- ');
        if (preg_match('/^(?:Grade\s*|G)?(\d+)$/i', $gradeCode, $matches)) {
            $gradeCode = $matches[1];
        } elseif (strcasecmp($gradeCode, 'Nursery') === 0) {
            $gradeCode = 'N';
        }

        $className = trim((string) ($schoolClass?->class_name ?? ''));
        if ($gradeCode === '' || $className === '') {
            return $gradeCode . $className;
        }

        // Some class records already include the grade prefix.
        if (preg_match('/^' . preg_quote($gradeCode, '/') . '(?:[-\s]|[A-Z])/i', $className)) {
            return $className;
        }

        $separator = preg_match('/^(?:N|K[123])$/i', $gradeCode) ? '-' : '';

        return $gradeCode . $separator . ltrim($className, '- ');
    }

    private function publicQrUrl(string $qr): string
    {
        $baseUrl = rtrim((string) config('services.public_qr_base_url', config('app.url')), '/');
        if ($baseUrl !== '') {
            return $baseUrl . route('student-id-card.public', ['qr' => $qr], false);
        }

        return route('student-id-card.public', ['qr' => $qr]);
    }

    public function publicCard(string $qr)
    {
        abort_unless(Schema::hasColumn((new Student())->getTable(), 'id_card_qr_code'), 404);

        $student = Student::query()
            ->select(['id', 'student_id', 'student_no', 'id_card_qr_code', 'full_name_en', 'full_name_kh', 'photo_path', 'home_phone'])
            ->with([
                'familyMembers:id,full_name_en,relationship_type,phone',
            ])
            ->where('id_card_qr_code', $qr)
            ->firstOrFail();

        $latestEnrollment = $student->enrollments()
            ->with([
                'academicYear:id,academic_year,period_type',
                'campus:id,campus_name_en,campus_name_kh',
                'grade:id,grade,grade_short_name,grade_order',
                'schoolClass:id,class_name,class_order',
            ])
            ->latest('id')
            ->first();

        $activeAcademicYearIds = AcademicYear::query()
            ->where('lifecycle_status', 'started')
            ->pluck('id');

        $hasActiveAcademicYearEnrollment = $activeAcademicYearIds->isNotEmpty()
            && $student->enrollments()
                ->whereIn('academic_year_id', $activeAcademicYearIds)
                ->where('status', 1)
                ->where('enrollment_status', 'active')
                ->exists();

        $grade = $latestEnrollment?->grade;
        $schoolClass = $latestEnrollment?->schoolClass;
        $gradeClassLabel = $this->gradeClassLabel($grade, $schoolClass);
        $enrollmentStatus = strtolower((string) ($latestEnrollment?->enrollment_status ?? ''));
        $isWithdrawn = $enrollmentStatus === 'withdrawn';
        $isInactive = ! $isWithdrawn && ! $hasActiveAcademicYearEnrollment;
        $withdrawnDate = $latestEnrollment?->ended_on;
        if ($isWithdrawn && ! $withdrawnDate && $latestEnrollment) {
            $withdrawnDate = $latestEnrollment->history()
                ->where('action_type', 'withdrawal')
                ->latest('effective_on')
                ->value('effective_on');
        }

        return view('student-id-card-public', [
            'branding' => BrandingSetting::current(),
            'student' => $student,
            'latestEnrollment' => $latestEnrollment,
            'isWithdrawn' => $isWithdrawn,
            'isInactive' => $isInactive,
            'withdrawnDateText' => $withdrawnDate ? \Illuminate\Support\Carbon::parse($withdrawnDate)->format('d-M-Y') : '—',
            'academicYearText' => $latestEnrollment?->academicYear?->academic_year ?: '—',
            'campusText' => $latestEnrollment?->campus?->campus_name_en ?: $latestEnrollment?->campus?->campus_name_kh ?: '—',
            'gradeText' => $gradeClassLabel ?: '—',
            'motherPhone' => $student->familyMembers->firstWhere('pivot.relationship_type', FamilyMember::RELATIONSHIP_MOTHER)?->phone
                ?: $student->familyMembers->firstWhere('relationship_type', FamilyMember::RELATIONSHIP_MOTHER)?->phone
                ?: '—',
            'fatherPhone' => $student->familyMembers->firstWhere('pivot.relationship_type', FamilyMember::RELATIONSHIP_FATHER)?->phone
                ?: $student->familyMembers->firstWhere('relationship_type', FamilyMember::RELATIONSHIP_FATHER)?->phone
                ?: '—',
        ]);
    }

    public function publicQr(string $qr)
    {
        abort_unless(Schema::hasColumn((new Student())->getTable(), 'id_card_qr_code'), 404);

        Student::query()
            ->where('id_card_qr_code', $qr)
            ->firstOrFail(['id']);

        $renderer = new ImageRenderer(new RendererStyle(360, 1), new SvgImageBackEnd());

        return response((new Writer($renderer))->writeString($this->publicQrUrl($qr)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
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
                $label = $this->gradeClassLabel($grade, $schoolClass);

                return (object) [
                    'grade_id' => (int) $row->grade_id,
                    'class_id' => (int) $row->class_id,
                    'label' => $label,
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
                ->regular()
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
