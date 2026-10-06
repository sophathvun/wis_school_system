<?php

namespace App\Services;

use App\Models\SchoolInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StudentPhotoReport
{
    public function payload(Request $request, array $filters, bool $preview = false): array
    {
        $campuses = $request->user()->isSuperAdmin()
            ? SchoolInfo::where('status', 1)->orderBy('campus_name_en')->get(['id', 'campus_name_en'])
            : $request->user()->accessibleCampuses()->where('tb_school_info.status', 1)->orderBy('campus_name_en')->get(['tb_school_info.id', 'campus_name_en']);
        $year = (int) ($filters['academic_year_id'] ?? 0);
        $hasDataFilter = $year > 0 && (!$preview || !empty($filters['campus_id']));
        $all = collect();
        if ($hasDataFilter) {
            $all = DB::table('tb_student_enrollment as e')
                ->join('tb_student as s', 's.id', '=', 'e.student_id')
                ->join('tb_grade as g', 'g.id', '=', 'e.grade_id')
                ->join('tb_class as c', 'c.id', '=', 'e.class_id')
                ->join('tb_school_info as campus', 'campus.id', '=', 'e.campus_id')
                ->join('tb_academic_year as ay', 'ay.id', '=', 'e.academic_year_id')
                ->where('e.academic_year_id', $year)->whereIn('e.campus_id', $campuses->pluck('id'))
                ->when($preview, fn ($query) => $query->where('e.campus_id', $filters['campus_id']))
                ->where('e.status', 1)->where('s.status', 1)->whereIn('e.enrollment_status', ['active', 'completed'])
                ->whereNull('ay.deleted_at')->whereNull('g.deleted_at')->whereNull('c.deleted_at')
                ->select('e.id', 'e.student_id', 'e.campus_id', 'e.grade_id', 'e.class_id',
                    's.student_id as student_code', 's.full_name_en', 's.full_name_kh', 's.photo_path',
                    'campus.campus_name_en', 'g.grade', 'g.grade_short_name', 'g.grade_order', 'c.class_name', 'c.class_order')
                ->orderByDesc('e.id')->get()->unique('student_id')
                ->sortBy(fn ($row) => [strtolower($row->campus_name_en), (int) $row->grade_order, (int) $row->class_order, $row->class_name, strtolower($row->full_name_en ?? ''), $row->student_id])->values();
        }
        $campusRows = $all->filter(fn ($row) => empty($filters['campus_id']) || $row->campus_id == $filters['campus_id'])->values();
        $classes = $campusRows->unique(fn ($row) => $row->grade_id.':'.$row->class_id)
            ->map(fn ($row) => ['value' => $row->grade_id.':'.$row->class_id, 'label' => $this->classLabel($row)])->values();
        $students = $campusRows->filter(fn ($row) => empty($filters['grade_class']) || $row->grade_id.':'.$row->class_id === $filters['grade_class'])->values();
        $options = $students->sortBy(fn ($row) => [strtolower($row->full_name_en ?? ''), $row->student_id])->values();
        if (!empty($filters['photo_student_id'])) $students = $students->where('student_id', (int) $filters['photo_student_id'])->values();
        foreach ($students as $student) {
            $path = str_replace('\\', '/', trim($student->photo_path ?? '', '/\\ '));
            $student->photo_url = $path !== '' && !in_array('..', explode('/', $path), true) && Storage::disk('public')->exists($path)
                ? asset('storage/'.$path) : null;
            $student->grade_label = $this->classLabel($student);
        }
        $size = $filters['photo_size'] ?? '4x6';
        $photos = $students->filter(fn ($student) => $student->photo_url)->values();
        $pages = collect();
        if (!$preview) {
            foreach ($photos->groupBy(fn ($row) => $row->campus_id.':'.$row->grade_id.':'.$row->class_id) as $group) {
                foreach ($group->chunk($size === '3x4' ? 30 : 16) as $chunk) $pages->push($chunk->values());
            }
        }
        $previewRows = $students;
        $pagination = null;
        if ($preview && $hasDataFilter) {
            $pageSize = (string) ($filters['preview_page_size'] ?? '25');
            if (!in_array($pageSize, ['all', '25', '50', '75', '100'], true)) $pageSize = '25';
            $total = $students->count();
            $lastPage = $pageSize === 'all' ? 1 : max(1, (int) ceil($total / (int) $pageSize));
            $page = $pageSize === 'all' ? 1 : min($lastPage, max(1, (int) ($filters['preview_page'] ?? 1)));
            $offset = $pageSize === 'all' ? 0 : ($page - 1) * (int) $pageSize;
            $previewRows = $pageSize === 'all' ? $students : $students->slice($offset, (int) $pageSize)->values();
            $filters['preview_page_size'] = $pageSize;
            $filters['preview_page'] = $page;
            $pagination = [
                'page' => $page, 'pageSize' => $pageSize, 'perPage' => $pageSize === 'all' ? $total : (int) $pageSize,
                'total' => $total, 'lastPage' => $lastPage, 'from' => $total ? $offset + 1 : 0,
                'to' => $offset + $previewRows->count(), 'sizes' => ['all', '25', '50', '75', '100'],
            ];
        }
        return [
            'filters' => $filters + ['photo_size' => $size], 'enrollments' => collect(), 'photoStudents' => $previewRows,
            'photoStudentOptions' => $options, 'photoGradeClassOptions' => $classes, 'photoCampuses' => $campuses,
            'photoPages' => $pages, 'photoMissingCount' => $students->count() - $photos->count(),
            'photoTotal' => $students->count(), 'hasDataFilter' => $hasDataFilter,
            'photoPagination' => $pagination,
            'isReportStub' => false, 'hasMorePreviewRows' => false, 'studentListPagination' => null,
        ];
    }

    private function classLabel(object $row): string
    {
        $grade = trim($row->grade_short_name ?: $row->grade);
        $class = trim($row->class_name);
        return $class !== '' && str_starts_with(strtolower($class), strtolower(rtrim($grade, '-')))
            ? $class : $grade.ltrim($class, '-');
    }
}
