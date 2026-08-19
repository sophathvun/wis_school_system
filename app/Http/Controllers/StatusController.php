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
use App\Models\Position;
use App\Models\Role;
use App\Models\Occupation;
use App\Models\Nationality;
use App\Models\Family;
use App\Models\WithdrawalReason;
use App\Models\StudentDocumentType;
use App\Models\AcademicTrack;
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
            'position' => Position::class,
            'role' => Role::class,
            'occupation' => Occupation::class,
            'nationality' => Nationality::class,
            'family' => Family::class,
            'withdrawal-reason' => WithdrawalReason::class,
            'student-document-type' => StudentDocumentType::class,
            'academic-track' => AcademicTrack::class,
        ];

        abort_unless(isset($models[$data['entity']]), 404, 'Status target not found.');
        $permissionModules = [
            'academic-year' => 'academic-years', 'education-level' => 'education-levels', 'grade' => 'grades', 'program' => 'programs',
            'class' => 'classes', 'session' => 'sessions', 'school-profile' => 'school-info', 'student-enrollment' => 'students.enrollment',
            'family' => 'families', 'user' => 'users', 'department' => 'departments', 'position' => 'positions', 'role' => 'roles', 'occupation' => 'occupations',
            'withdrawal-reason' => 'withdrawal-reasons', 'student-document-type' => 'student-document-types',
            'academic-track' => 'academic-tracks',
            'country' => 'locations', 'province' => 'locations', 'district' => 'locations', 'commune' => 'locations', 'village' => 'locations', 'nationality' => 'locations',
        ];
        $user = $request->user();
        $permissionModule = $permissionModules[$data['entity']] ?? null;
        $canToggleStatus = $user?->isSuperAdmin()
            || ($permissionModule && $user->hasPermission($permissionModule.'.status'))
            || ($data['entity'] === 'position' && $user?->hasPermission('positions.manage'));
        abort_unless($canToggleStatus, 403);
        if ($data['entity'] === 'user' && (int) $data['id'] === (int) $user->id && !$data['status']) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot deactivate your own account while you are logged in.',
                'active' => true,
            ], 422);
        }
        $record = $models[$data['entity']]::findOrFail($data['id']);
        $record->status = $data['status'];
        if ($data['entity'] === 'academic-year') {
            $record->lifecycle_status = $data['status']
                ? ($record->lifecycle_status === 'archived' ? 'pending' : ($record->lifecycle_status ?: 'pending'))
                : 'finished';
        }
        $record->save();

        return response()->json([
            'status' => 'success',
            'message' => $data['status'] ? 'Item activated successfully.' : 'Item deactivated successfully.',
            'active' => (bool) $record->status,
        ]);
    }
}
