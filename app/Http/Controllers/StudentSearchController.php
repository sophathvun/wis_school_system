<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolGroup;
use App\Models\SchoolInfo;
use App\Models\StudentEnrollment;
use Illuminate\Http\Request;

class StudentSearchController
{
    public function index()
    {
        return view('student-search');
    }

    public function options(Request $request)
    {
        $user = $request->user();
        $campuses = $user->isSuperAdmin()
            ? SchoolInfo::query()
            : $user->accessibleCampuses();

        $classIds = StudentEnrollment::query()
            ->when($request->academic_year_id, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when($request->campus_id, fn ($query, $id) => $query->where('campus_id', $id))
            ->distinct()->pluck('class_id');

        $campusIds = StudentEnrollment::query()
            ->when($request->academic_year_id, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->distinct()->pluck('campus_id');

        $classes = SchoolClass::query()
            ->where('status', 1)
            ->whereIn('id', $classIds)
            ->when($request->academic_year_id, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->orderBy('class_order')
            ->orderBy('class_name')
            ->get(['id', 'class_name', 'academic_year_id']);

        $groups = SchoolGroup::query()
            ->where('status', 1)
            ->whereIn('class_id', $classIds)
            ->when($request->class_id, fn ($query, $id) => $query->where('class_id', $id))
            ->orderBy('group_order')
            ->orderBy('group_name')
            ->get(['id', 'group_name', 'class_id']);

        return response()->json([
            'academicYears' => AcademicYear::orderByRaw("CASE lifecycle_status WHEN 'started' THEN 1 WHEN 'pending' THEN 2 WHEN 'draft' THEN 3 WHEN 'finished' THEN 4 ELSE 5 END")
                ->orderByDesc('academic_year')
                ->get(['id', 'academic_year', 'lifecycle_status']),
            'campuses' => $campuses->where('status', 1)->whereIn('id', $campusIds)->orderBy('campus_name_en')->get(['id', 'campus_name_en', 'campus_name_kh']),
            'classes' => $classes,
            'groups' => $groups,
        ]);
    }

    public function fetch(Request $request)
    {
        $user = $request->user();
        $query = StudentEnrollment::query()
            ->with(['student', 'academicYear', 'campus', 'schoolClass', 'schoolGroup'])
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->whereIn('campus_id', $user->accessibleCampuses()->pluck('tb_school_info.id')))
            ->when($request->academic_year_id, fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when($request->campus_id, fn ($q, $id) => $q->where('campus_id', $id))
            ->when($request->class_id, fn ($q, $id) => $q->where('class_id', $id))
            ->when($request->group_id, fn ($q, $id) => $q->where('group_id', $id))
            ->when($request->search, function ($q, $term) {
                $term = trim($term);
                $q->whereHas('student', fn ($student) => $student
                    ->where('student_no', 'like', "%{$term}%")
                    ->orWhere('student_id', 'like', "%{$term}%")
                    ->orWhere('full_name_en', 'like', "%{$term}%")
                    ->orWhere('full_name_kh', 'like', "%{$term}%"));
            })
            ->latest('id');

        return response()->json($query->paginate($request->integer('perPage', 10)));
    }
}
