<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentDocumentType;
use App\Models\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudentDocumentController
{
    public function index()
    {
        return view('student-documents');
    }

    public function options(Request $request)
    {
        if ($request->boolean('meta')) {
            return response()->json([
                'filterOptions' => $this->filterOptions($request),
                'types' => $this->documentTypes(),
            ]);
        }

        $term = trim((string) $request->query('search'));
        $termDigits = preg_replace('/\D+/', '', $term);
        if (str_starts_with($termDigits, '855')) {
            $termDigits = substr($termDigits, 3);
        }
        if (str_starts_with($termDigits, '0')) {
            $termDigits = substr($termDigits, 1);
        }
        $yearId = $request->query('academic_year_id');
        $campusId = $request->query('campus_id');
        $gradeLabel = trim((string) $request->query('grade'));
        $hasEnrollmentFilters = filled($yearId) || filled($campusId) || $gradeLabel !== '';

        $query = Student::with([
                'familyMembers:id,full_name_en,full_name_kh,relationship_type,phone',
                'enrollments' => fn ($query) => $query
                    ->with([
                        'academicYear:id,academic_year',
                        'campus:id,campus_name_en',
                        'grade:id,grade',
                        'schoolClass:id,class_name',
                        'session:id,session_short_name',
                    ])
                    ->latest('id'),
            ])
            ->where(fn ($query) => $query->whereNull('student_id')->orWhere('student_id', '!=', '#Deleted'))
            ->where(fn ($query) => $query->whereNull('full_name_en')->orWhere('full_name_en', '!=', '#Deleted'))
            ->when($term, fn ($query) => $query->where(fn ($student) => $student
                ->where('student_id', 'like', "%{$term}%")
                ->orWhere('student_no', 'like', "%{$term}%")
                ->orWhere('full_name_en', 'like', "%{$term}%")
                ->orWhere('full_name_kh', 'like', "%{$term}%")
                ->orWhere('home_phone', 'like', "%{$term}%")
                ->when($termDigits !== '', fn ($phoneQuery) => $phoneQuery
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(home_phone, ''), ' ', ''), '+', ''), '-', ''), '(', ''), ')', '') LIKE ?", ["%{$termDigits}%"])
                    ->orWhereHas('familyMembers', fn ($member) => $member->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), ' ', ''), '+', ''), '-', ''), '(', ''), ')', '') LIKE ?", ["%{$termDigits}%"])))
                ->orWhereHas('familyMembers', fn ($member) => $member->where('phone', 'like', "%{$term}%"))
            ));

        if ($hasEnrollmentFilters) {
            $studentIds = $this->enrollmentFilterQuery($request)->distinct()->pluck('tb_student_enrollment.student_id');
            $query->whereIn('id', $studentIds);
        }

        $students = $query
            ->orderByRaw("LOWER(COALESCE(NULLIF(full_name_en, ''), full_name_kh, student_id, student_no))")
            ->limit($term || $hasEnrollmentFilters ? 1000 : 500)
            ->get(['id', 'student_id', 'student_no', 'photo_path', 'full_name_en', 'full_name_kh', 'gender', 'gender_kh', 'date_of_birth', 'home_phone']);

        return response()->json([
            'students' => $students,
            'types' => $this->documentTypes(),
        ]);
    }

    public function fetch(Student $student)
    {
        return response()->json($student->documents()->with(['type', 'uploadedBy:id,name'])->latest('id')->get());
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:tb_student,id'],
            'document_type_id' => ['required', 'exists:tb_student_document_type,id'],
            'title' => ['nullable', 'string', 'max:180'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:20480'],
        ]);

        $file = $request->file('file');
        $path = $file->store('student-documents', 'public');
        $doc = StudentDocument::create($data + [
            'document_type' => StudentDocumentType::find($data['document_type_id'])?->name_en,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
            'status' => 1,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Student document uploaded successfully.',
            'data' => $doc->load('type'),
        ]);
    }

    public function download(StudentDocument $document)
    {
        abort_unless($document->file_path && Storage::disk('public')->exists($document->file_path), 404);

        return Storage::disk('public')->download($document->file_path, $document->original_filename);
    }

    public function view(StudentDocument $document)
    {
        abort_unless($document->file_path && Storage::disk('public')->exists($document->file_path), 404);

        return Storage::disk('public')->response($document->file_path, $document->original_filename, [], 'inline');
    }

    public function delete(StudentDocument $document)
    {
        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return response()->json(['status' => 'success', 'message' => 'Student document deleted successfully.']);
    }

    private function documentTypes()
    {
        return StudentDocumentType::where('status', 1)->orderBy('sort_order')->get(['id', 'name_en', 'name_kh']);
    }

    private function filterOptions(Request $request): array
    {
        $years = (clone $this->baseEnrollmentQuery())
            ->join('tb_academic_year', 'tb_academic_year.id', '=', 'tb_student_enrollment.academic_year_id')
            ->whereNotNull('tb_student_enrollment.academic_year_id')
            ->select('tb_academic_year.id as value', 'tb_academic_year.academic_year as label')
            ->distinct()
            ->orderByDesc('tb_academic_year.academic_year')
            ->get();

        $campuses = (clone $this->baseEnrollmentQuery())
            ->when($request->filled('academic_year_id'), fn ($query) => $query->where('tb_student_enrollment.academic_year_id', $request->query('academic_year_id')))
            ->join('tb_school_info', 'tb_school_info.id', '=', 'tb_student_enrollment.campus_id')
            ->whereNotNull('tb_student_enrollment.campus_id')
            ->select('tb_school_info.id as value', 'tb_school_info.campus_name_en as label')
            ->distinct()
            ->orderBy('tb_school_info.campus_name_en')
            ->get();

        $grades = (clone $this->baseEnrollmentQuery())
            ->when($request->filled('academic_year_id'), fn ($query) => $query->where('tb_student_enrollment.academic_year_id', $request->query('academic_year_id')))
            ->when($request->filled('campus_id'), fn ($query) => $query->where('tb_student_enrollment.campus_id', $request->query('campus_id')))
            ->leftJoin('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
            ->leftJoin('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
            ->whereNotNull('tb_student_enrollment.grade_id')
            ->selectRaw("CONCAT(COALESCE(tb_grade.grade, ''), COALESCE(tb_class.class_name, '')) as label")
            ->distinct()
            ->orderByRaw("LENGTH(label), label")
            ->get()
            ->map(fn ($row) => ['value' => $row->label, 'label' => $row->label])
            ->filter(fn ($row) => filled($row['label']))
            ->values();

        return [
            'academicYears' => $years,
            'campuses' => $campuses,
            'grades' => $grades,
        ];
    }

    private function baseEnrollmentQuery()
    {
        return StudentEnrollment::query()
            ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
            ->where(fn ($query) => $query->whereNull('tb_student.student_id')->orWhere('tb_student.student_id', '!=', '#Deleted'))
            ->where(fn ($query) => $query->whereNull('tb_student.full_name_en')->orWhere('tb_student.full_name_en', '!=', '#Deleted'));
    }

    private function enrollmentFilterQuery(Request $request)
    {
        return (clone $this->baseEnrollmentQuery())
            ->leftJoin('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
            ->leftJoin('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
            ->when($request->filled('academic_year_id'), fn ($query) => $query->where('tb_student_enrollment.academic_year_id', $request->query('academic_year_id')))
            ->when($request->filled('campus_id'), fn ($query) => $query->where('tb_student_enrollment.campus_id', $request->query('campus_id')))
            ->when(trim((string) $request->query('grade')) !== '', fn ($query) => $query->whereRaw("CONCAT(COALESCE(tb_grade.grade, ''), COALESCE(tb_class.class_name, '')) = ?", [trim((string) $request->query('grade'))]));
    }
}
