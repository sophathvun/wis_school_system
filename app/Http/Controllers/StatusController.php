<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Program;
use App\Models\SchoolClass;
use App\Models\SchoolGroup;
use App\Models\SchoolInfo;
use App\Models\Session;
use App\Models\StudentEnrollment;
use App\Models\Country;
use App\Models\Province;
use App\Models\District;
use App\Models\Commune;
use App\Models\Village;
use App\Models\User;
use App\Models\Department;
use App\Models\Role;
use App\Models\Occupation;
use App\Models\Nationality;
use App\Models\Family;
use App\Models\WithdrawalReason;
use App\Models\StudentDocumentType;
use Illuminate\Http\Request;

class StatusController
{
    public function toggle(Request $request)
    {
        $data = $request->validate([
            'entity' => ['required', 'string'],
            'id' => ['required', 'integer'],
            'status' => ['required', 'boolean'],
        ]);

        $models = [
            'academic-year' => AcademicYear::class,
            'education-level' => EducationLevel::class,
            'grade' => Grade::class,
            'program' => Program::class,
            'class' => SchoolClass::class,
            'group' => SchoolGroup::class,
            'school-profile' => SchoolInfo::class,
            'session' => Session::class,
            'student-enrollment' => StudentEnrollment::class,
            'country' => Country::class,
            'province' => Province::class,
            'district' => District::class,
            'commune' => Commune::class,
            'village' => Village::class,
            'user' => User::class,
            'department' => Department::class,
            'role' => Role::class,
            'occupation' => Occupation::class,
            'nationality' => Nationality::class,
            'family' => Family::class,
            'withdrawal-reason' => WithdrawalReason::class,
            'student-document-type' => StudentDocumentType::class,
        ];

        abort_unless(isset($models[$data['entity']]), 404, 'Status target not found.');
        $permissionModules = [
            'academic-year' => 'academic-years', 'education-level' => 'education-levels', 'grade' => 'grades', 'program' => 'programs',
            'class' => 'classes', 'session' => 'sessions', 'school-profile' => 'school-info', 'student-enrollment' => 'students.enrollment',
            'family' => 'families', 'user' => 'users', 'department' => 'departments', 'role' => 'roles', 'occupation' => 'occupations',
            'withdrawal-reason' => 'withdrawal-reasons', 'student-document-type' => 'student-document-types',
            'country' => 'locations', 'province' => 'locations', 'district' => 'locations', 'commune' => 'locations', 'village' => 'locations', 'nationality' => 'locations',
        ];
        $user = $request->user();
        abort_unless($user?->isSuperAdmin() || ($permissionModules[$data['entity']] ?? null) && $user->hasPermission($permissionModules[$data['entity']].'.status'), 403);
        $record = $models[$data['entity']]::findOrFail($data['id']);
        $record->status = $data['status'];
        $record->save();

        return response()->json([
            'status' => 'success',
            'message' => $data['status'] ? 'Item activated successfully.' : 'Item deactivated successfully.',
            'active' => (bool) $record->status,
        ]);
    }
}
