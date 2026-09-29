<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Position;
use App\Models\SchoolInfo;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StaffManagementController
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->hasPermission('staff.view', $request->user()?->active_campus_id) || $request->user()?->isSuperAdmin(), 403);

        $search = trim((string) $request->query('search'));
        $perPage = min(max($request->integer('per_page', 10), 10), 100);
        $staffQuery = Staff::with(['department', 'position', 'campuses', 'primaryCampus', 'user'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('staff_code', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('name_kh', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('department', fn ($department) => $department->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('position', fn ($position) => $position->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('campuses', fn ($campus) => $campus->where('campus_name_en', 'like', "%{$search}%"));
            }))
            ->orderBy('name_en');

        $viewStaff = $request->integer('view')
            ? Staff::with(['campuses', 'user', 'department', 'position', 'primaryCampus', 'experiences', 'educations'])->find($request->integer('view'))
            : null;

        return view('staff-management', [
            'staff' => $staffQuery->paginate($perPage)->withQueryString(),
            'editStaff' => $request->integer('edit') ? Staff::with(['campuses', 'user', 'experiences', 'educations'])->find($request->integer('edit')) : null,
            'viewStaff' => $viewStaff,
            'createStaff' => $request->boolean('create'),
            'departments' => Department::where('status', 1)->orderBy('name')->get(),
            'positions' => Position::with('department')->where('status', 1)->orderBy('name')->get(),
            'campuses' => SchoolInfo::where('status', 1)->orderBy('campus_name_en')->get(),
            'canManageStaff' => $request->user()?->hasPermission('staff.create', $request->user()?->active_campus_id)
                || $request->user()?->hasPermission('staff.edit', $request->user()?->active_campus_id)
                || $request->user()?->isSuperAdmin(),
            'canDeleteStaff' => $request->user()?->hasPermission('staff.delete', $request->user()?->active_campus_id) || $request->user()?->isSuperAdmin(),
        ]);
    }

    public function save(Request $request)
    {
        $staffId = $request->integer('staff_id');
        $permission = $staffId ? 'staff.edit' : 'staff.create';
        abort_unless($request->user()?->hasPermission($permission, $request->user()?->active_campus_id) || $request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'staff_id' => ['nullable', 'exists:hrm_staff,id'],
            'staff_code' => ['required', 'string', 'max:50', Rule::unique('hrm_staff', 'staff_code')->ignore($staffId)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_kh' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'in:Male,Female,Other,male,female,other'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'date_of_birth' => ['nullable', 'date'],
            'marital_status' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'current_address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:80'],
            'position_id' => ['nullable', 'exists:access_positions,id'],
            'department_id' => ['nullable', 'exists:access_departments,id'],
            'staff_category' => ['required', 'in:staff,teacher,staff_teacher'],
            'employment_type' => ['required', 'in:PT,SFT,FT'],
            'employment_status' => ['required', 'in:active,resigned,suspended,on_leave'],
            'joined_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date'],
            'primary_campus_id' => ['nullable', 'exists:tb_school_info,id'],
            'campuses' => ['array'],
            'campuses.*' => ['integer', 'exists:tb_school_info,id'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:0,1'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'experiences' => ['array'],
            'experiences.*.company_name' => ['nullable', 'string', 'max:255'],
            'experiences.*.position' => ['nullable', 'string', 'max:255'],
            'experiences.*.employment_type' => ['nullable', 'string', 'max:80'],
            'experiences.*.started_on' => ['nullable', 'date'],
            'experiences.*.ended_on' => ['nullable', 'date'],
            'experiences.*.is_current' => ['nullable', 'boolean'],
            'experiences.*.responsibilities' => ['nullable', 'string'],
            'educations' => ['array'],
            'educations.*.institution_name' => ['nullable', 'string', 'max:255'],
            'educations.*.degree' => ['nullable', 'string', 'max:255'],
            'educations.*.field_of_study' => ['nullable', 'string', 'max:255'],
            'educations.*.started_on' => ['nullable', 'date'],
            'educations.*.ended_on' => ['nullable', 'date'],
            'educations.*.grade_or_result' => ['nullable', 'string', 'max:255'],
            'educations.*.notes' => ['nullable', 'string'],
        ]);

        $data['gender'] = $this->normalizeGender($data['gender'] ?? null);
        $data['status'] = (bool) $data['status'];

        DB::transaction(function () use ($request, $data, $staffId) {
            $staff = $staffId ? Staff::findOrFail($staffId) : new Staff();
            $staff->fill(collect($data)->except(['staff_id', 'campuses', 'photo', 'experiences', 'educations'])->toArray());
            $staff->updated_by = $request->user()->id;
            if (! $staff->exists) {
                $staff->created_by = $request->user()->id;
            }
            if ($request->hasFile('photo')) {
                if ($staff->photo_path) {
                    Storage::disk('public')->delete($staff->photo_path);
                }
                $staff->photo_path = $request->file('photo')->store('staff', 'public');
            }
            $staff->save();

            $campusIds = collect($data['campuses'] ?? [])->unique()->values();
            if ($data['primary_campus_id'] && ! $campusIds->contains((int) $data['primary_campus_id'])) {
                $campusIds->prepend((int) $data['primary_campus_id']);
            }

            $staff->campuses()->sync($campusIds->mapWithKeys(fn ($id, $index) => [
                $id => ['is_primary' => (int) $id === (int) $data['primary_campus_id'] || (! $data['primary_campus_id'] && $index === 0), 'status' => true, 'assigned_at' => now()],
            ])->all());

            $staff->experiences()->delete();
            foreach ($this->cleanExperiences($data['experiences'] ?? []) as $experience) {
                $staff->experiences()->create($experience);
            }

            $staff->educations()->delete();
            foreach ($this->cleanEducations($data['educations'] ?? []) as $education) {
                $staff->educations()->create($education);
            }
        });

        return redirect()->route('staff-management.index')->with('success', $staffId ? 'Staff profile updated successfully.' : 'Staff profile created successfully.');
    }

    public function delete(Request $request, Staff $staff)
    {
        abort_unless($request->user()?->hasPermission('staff.delete', $request->user()?->active_campus_id) || $request->user()?->isSuperAdmin(), 403);

        if ($staff->user()->exists()) {
            return back()->withErrors(['staff' => 'This staff profile is linked to a user account. Unlink the user before deleting.']);
        }

        $staff->delete();

        return redirect()->route('staff-management.index')->with('success', 'Staff profile deleted successfully.');
    }

    private function normalizeGender(?string $value): ?string
    {
        return match (strtolower(trim((string) $value))) {
            'male' => 'Male',
            'female' => 'Female',
            'other' => 'Other',
            default => null,
        };
    }

    private function cleanExperiences(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($row) => trim((string) ($row['company_name'] ?? '')) !== '')
            ->map(fn ($row) => [
                'company_name' => trim((string) $row['company_name']),
                'position' => $row['position'] ?? null,
                'employment_type' => $row['employment_type'] ?? null,
                'started_on' => $row['started_on'] ?? null,
                'ended_on' => !empty($row['is_current']) ? null : ($row['ended_on'] ?? null),
                'is_current' => !empty($row['is_current']),
                'responsibilities' => $row['responsibilities'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function cleanEducations(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($row) => trim((string) ($row['institution_name'] ?? '')) !== '')
            ->map(fn ($row) => [
                'institution_name' => trim((string) $row['institution_name']),
                'degree' => $row['degree'] ?? null,
                'field_of_study' => $row['field_of_study'] ?? null,
                'started_on' => $row['started_on'] ?? null,
                'ended_on' => $row['ended_on'] ?? null,
                'grade_or_result' => $row['grade_or_result'] ?? null,
                'notes' => $row['notes'] ?? null,
            ])
            ->values()
            ->all();
    }
}
