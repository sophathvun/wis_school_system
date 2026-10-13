<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Country;
use App\Models\Position;
use App\Models\Province;
use App\Models\SchoolInfo;
use App\Models\Staff;
use App\Support\StaffProfile;
use App\Support\StaffAddress;
use App\Services\StaffWorkInformation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StaffManagementController
{
    public function index(Request $request)
    {
        abort_unless(\App\Support\HrmPermissions::allows($request->user(),'staff'),403);

        $search = trim((string) $request->query('search'));
        $requestedPageSize=$request->query('per_page','20');
        $pageSize=is_scalar($requestedPageSize) && in_array((string)$requestedPageSize,['all','20','50','75','100'],true)
            ? (string)$requestedPageSize : '20';
        $staffQuery = Staff::with(['department', 'position', 'campuses', 'primaryCampus', 'user'])
            ->when($request->filled('employment_status'), fn ($query) => $query->where('employment_status', $request->query('employment_status')))
            ->when($request->integer('campus_id'), fn ($query) => $query->whereHas('campuses', fn ($campus) => $campus->where('tb_school_info.id', $request->integer('campus_id'))))
            ->when($request->filled('staff_category'), fn ($query) => $query->where('staff_category', $request->query('staff_category')))
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('staff_code', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('name_kh', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('work_email', 'like', "%{$search}%")
                    ->orWhere('secondary_phone', 'like', "%{$search}%")
                    ->orWhereHas('department', fn ($department) => $department->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('position', fn ($position) => $position->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('campuses', fn ($campus) => $campus->where('campus_name_en', 'like', "%{$search}%"));
            }))
            ->orderBy('name_en');

        $viewStaff = $request->integer('view')
            ? Staff::with(['campuses', 'user', 'department', 'position', 'primaryCampus', 'experiences', 'educations','workHistories','supervisor','birthCountry','addressCountry'])->findOrFail($request->integer('view'))
            : null;
        $editStaff = $request->integer('edit')
            ? Staff::with(['campuses','user','experiences','educations','workHistories','supervisor'])->findOrFail($request->integer('edit'))
            : null;
        $totalStaff=(clone $staffQuery)->count();
        $perPage=$pageSize==='all'?max(1,$totalStaff):(int)$pageSize;
        $birthplaceEnglish=old('place_of_birth_en',$editStaff?->place_of_birth_en);
        $birthProvinces=Province::where(fn($query)=>$query->where('status',1)
            ->when(is_string($birthplaceEnglish) && $birthplaceEnglish!=='',fn($query)=>$query->orWhere('province_name_en',$birthplaceEnglish))
            ->when($editStaff?->birth_province_id,fn($query)=>$query->orWhere('id',$editStaff->birth_province_id)))
            ->orderBy('province_name_en')->get(['id','country_id','province_name_en','province_name_kh']);
        $birthCountries=Country::where(fn($query)=>$query->where('status',1)
            ->when($editStaff?->birth_country_id,fn($query)=>$query->orWhere('id',$editStaff->birth_country_id))
            ->orWhereIn('id',$birthProvinces->where('province_name_en',$birthplaceEnglish)->pluck('country_id')))
            ->orderBy('country_name_en')->get(['id','country_name_en','country_name_kh','country_code','flag_path']);
        $nationalityEnglish=old('nationality',$editStaff?->nationality);
        $nationalityCountries=Country::where(fn($query)=>$query->where('status',1)
            ->when($editStaff?->nationality_country_id,fn($query)=>$query->orWhere('id',$editStaff->nationality_country_id))
            ->when(is_string($nationalityEnglish) && $nationalityEnglish!=='',fn($query)=>$query->orWhere('nationality_name_en',$nationalityEnglish)->orWhere('country_name_en',$nationalityEnglish)))
            ->orderBy('nationality_name_en')->orderBy('country_name_en')->get(['id','country_name_en','country_name_kh','nationality_name_en','nationality_name_kh','country_code','flag_path']);

        $selectedPositionId=(int)old('position_id',$editStaff?->position_id);
        $selectedDepartmentId=(int)old('department_id',$editStaff?->department_id);
        $positions=Position::with('department')->where(fn($query)=>$query->where('status',1)
            ->when($selectedPositionId,fn($query)=>$query->orWhere('id',$selectedPositionId)))->orderBy('name')->get();
        $positionDepartments=$positions->pluck('department_id')->filter()->all();
        $departments=\App\Support\DepartmentHierarchy::options(false)->filter(fn($department)=>$department->status
            || $department->id===$selectedDepartmentId || in_array($department->id,$positionDepartments,true));

        return view('staff-management', [
            'staffMetrics' => [
                'total' => $totalStaff,
                'active' => (clone $staffQuery)->where('employment_status','active')->where('status',true)->count(),
                'teachers' => (clone $staffQuery)->whereIn('staff_category',['teacher','staff_teacher'])->count(),
                'contracts' => (clone $staffQuery)->where('employment_status','active')->where('status',true)
                    ->whereBetween('contract_ends_on',[now()->toDateString(),now()->addDays(30)->toDateString()])->count(),
            ],
            'supervisors' => Staff::where(fn($query)=>$query->where(fn($query)=>$query->where('employment_status','active')->where('status',true))
                ->when($editStaff?->reports_to_staff_id,fn($query)=>$query->orWhere('id',$editStaff->reports_to_staff_id)))
                ->when($request->integer('edit'),fn($query)=>$query->whereKeyNot($request->integer('edit')))
                ->orderBy('name_en')->get(['id','name_en','staff_code']),
            'staff' => $staffQuery->paginate($perPage,['*'],'page',$pageSize==='all'?1:null)->withQueryString(),
            'pageSize' => $pageSize,
            'editStaff' => $editStaff,
            'viewStaff' => $viewStaff,
            'createStaff' => $request->boolean('create'),
            'departments' => $departments,
            'positions' => $positions,
            'campuses' => SchoolInfo::where('status', 1)->orderBy('campus_name_en')->get(),
            'addressLocations' => StaffAddress::formData($editStaff),
            'birthProvinces' => $birthProvinces,
            'birthCountries' => $birthCountries,
            'nationalityCountries' => $nationalityCountries,
            'defaultBirthCountryId' => $birthCountries->first(fn($country)=>in_array(strtolower($country->country_code??''),['kh','khm'],true)
                || strtolower($country->country_name_en)==='cambodia')?->id,
            'canManageStaff' => $request->user()?->hasPermission('staff.create', $request->user()?->active_campus_id)
                || $request->user()?->hasPermission('staff.edit', $request->user()?->active_campus_id)
                || $request->user()?->isSuperAdmin(),
            'canDeleteStaff' => $request->user()?->hasPermission('staff.delete', $request->user()?->active_campus_id) || $request->user()?->isSuperAdmin(),
            'canCreateStaff' => $request->user()?->hasPermission('staff.create', $request->user()?->active_campus_id) || $request->user()?->isSuperAdmin(),
            'canStatusStaff' => \App\Support\HrmPermissions::allows($request->user(),'staff','status'),
            'canEditStaff' => $request->user()?->hasPermission('staff.edit', $request->user()?->active_campus_id) || $request->user()?->isSuperAdmin(),
        ]);
    }

    public function addressOptions(Request $request)
    {
        abort_unless(\App\Support\HrmPermissions::allows($request->user(),'staff'),403);
        $data = $request->validate([
            'level' => ['required', Rule::in(array_keys(StaffAddress::LEVELS))],
            'parent_id' => ['nullable','integer','min:1'],
            'selected_id' => ['nullable','integer','min:1'],
        ]);
        return response()->json(StaffAddress::options($data['level'], $data['parent_id'] ?? null, $data['selected_id'] ?? null));
    }
    public function save(Request $request)
    {
        $staffId = $request->integer('staff_id');
        abort_unless(\App\Support\HrmPermissions::allows($request->user(),'staff',$staffId?'edit':'create'),403);
        $permission = $staffId ? 'staff.edit' : 'staff.create';
        abort_unless($request->user()?->hasPermission($permission, $request->user()?->active_campus_id) || $request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'staff_id' => ['nullable', 'exists:hrm_staff,id'],
            'staff_code' => ['required', 'string', 'max:50', Rule::unique('hrm_staff', 'staff_code')->ignore($staffId)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_kh' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'in:Male,Female,Other,male,female,other'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'nationality_kh' => ['nullable','string','max:255'],
            'nationality_country_id' => ['nullable','integer','exists:tb_country,id'],
            'place_of_birth_en' => ['nullable','string','max:255'],
            'place_of_birth_kh' => ['nullable','string','max:255'],
            'date_of_birth' => ['nullable', 'date','before_or_equal:today'],
            'birth_province_id' => ['nullable','integer','exists:tb_province,id'],
            'birth_country_id' => ['nullable','integer','exists:tb_country,id'],
            'marital_status' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'secondary_phone' => ['nullable','string','max:50'],
            'work_email' => ['nullable','email','max:255'],
            'identity_document_type' => ['nullable',Rule::in(array_keys(StaffProfile::IDENTITY_DOCUMENTS))],
            'identity_document_name' => ['nullable','string','max:255'],
            'identity_document_issued_on' => ['nullable','date'],
            'identity_document_number' => ['nullable','string','max:100'],
            'identity_document_expires_on' => ['nullable','date',...($request->filled('identity_document_issued_on')?['after_or_equal:identity_document_issued_on']:[])],
            'emergency_contact_address' => ['nullable','string','max:5000'],
            'contract_type' => ['nullable',Rule::in(array_keys(StaffProfile::CONTRACTS))],
            'contract_number' => ['nullable','string','max:100'],
            'contract_started_on' => ['nullable','date'],
            'contract_ends_on' => ['nullable','date',...($request->filled('contract_started_on')?['after_or_equal:contract_started_on']:[])],
            'probation_ends_on' => ['nullable','date',...($request->filled('joined_on')?['after_or_equal:joined_on']:[])],
            'reports_to_staff_id' => ['nullable','integer',Rule::exists('hrm_staff','id')->whereNull('deleted_at'),Rule::notIn($staffId?[$staffId]:[])],
            ...collect(StaffAddress::LEVELS)->mapWithKeys(fn($config,$level)=>['address_'.$level.'_id'=>['nullable','integer','exists:tb_'.$level.',id']])->all(),
            'current_address' => ['nullable', 'string'],
            'current_address_kh' => ['nullable','string','max:5000'],
            ...collect(['house_number_en','street_en','village_en','village_kh','commune_en','commune_kh','district_en','district_kh','province_en','province_kh'])
                ->mapWithKeys(fn($field)=>[$field=>['nullable','string','max:255']])->all(),
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:80'],
            'position_id' => ['nullable', 'exists:access_positions,id'],
            'department_id' => ['nullable', 'exists:access_departments,id'],
            'staff_category' => ['required', 'in:staff,teacher,staff_teacher'],
            'employment_type' => ['required', 'in:PT,SFT,FT'],
            'employment_status' => ['required', Rule::in(array_keys(StaffProfile::STATUS))],
            'joined_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date',...($request->filled('joined_on')?['after_or_equal:joined_on']:[]),'required_if:employment_status,resigned,terminated,retired'],
            'employment_end_reason' => ['nullable','string','max:5000'],
            'work_change_type' => ['nullable',Rule::in(array_keys(StaffProfile::CHANGES))],
            'work_effective_on' => ['nullable','date'],
            'work_change_reason' => ['nullable','string','max:5000'],
            ...collect(['session_1_in','session_1_out','session_2_in','session_2_out'])->mapWithKeys(fn($field)=>[$field=>['nullable','date_format:H:i']])->all(),
            'work_schedule' => ['sometimes','array:mon,tue,wed,thu,fri,sat,sun'],
            'work_schedule.*' => ['array:period,session_1_in,session_1_out,session_2_in,session_2_out'],
            'work_schedule.*.period' => ['required',Rule::in(array_keys(StaffProfile::PERIODS))],
            ...collect(['session_1_in','session_1_out','session_2_in','session_2_out'])->mapWithKeys(fn($field)=>['work_schedule.*.'.$field=>['nullable','date_format:H:i']])->all(),
            'primary_campus_id' => ['nullable', 'exists:tb_school_info,id'],
            'campuses' => ['array'],
            'campuses.*' => ['integer', 'exists:tb_school_info,id'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:0,1'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'experiences_present' => ['nullable','boolean'],
            'educations_present' => ['nullable','boolean'],
            'experiences' => ['array','max:50'],
            'experiences.*.company_name' => ['nullable', 'required_with:experiences.*.position,experiences.*.started_on,experiences.*.ended_on,experiences.*.responsibilities', 'string', 'max:255'],
            'experiences.*.position' => ['nullable', 'string', 'max:255'],
            'experiences.*.employment_type' => ['nullable', 'string', 'max:80'],
            'experiences.*.started_on' => ['nullable', 'date'],
            'experiences.*.ended_on' => ['nullable', 'date'],
            'experiences.*.is_current' => ['nullable', 'boolean'],
            'experiences.*.responsibilities' => ['nullable', 'string'],
            'educations' => ['array','max:50'],
            'educations.*.institution_name' => ['nullable', 'required_with:educations.*.degree,educations.*.field_of_study,educations.*.country,educations.*.graduated_year,educations.*.notes', 'string', 'max:255'],
            'educations.*.degree' => ['nullable', 'string', 'max:255'],
            'educations.*.field_of_study' => ['nullable', 'string', 'max:255'],
            'educations.*.started_on' => ['nullable', 'date'],
            'educations.*.ended_on' => ['nullable', 'date'],
            'educations.*.country' => ['nullable','string','max:255'],
            'educations.*.graduated_year' => ['nullable','integer','min:1900','max:'.now()->year],
            'educations.*.grade_or_result' => ['nullable', 'string', 'max:255'],
            'educations.*.notes' => ['nullable', 'string'],
        ]);

        if (array_key_exists('identity_document_type',$data)) {
            if ($data['identity_document_type'] !== 'other') $data['identity_document_name'] = null;
            if (!$data['identity_document_type']) foreach (['identity_document_name','identity_document_number','identity_document_issued_on','identity_document_expires_on'] as $field) $data[$field] = null;
        }
        $data = StaffAddress::normalize($data);
        $data['name_en'] = mb_strtoupper(trim($data['name_en']), 'UTF-8');
        if (isset($data['emergency_contact_name'])) $data['emergency_contact_name'] = mb_strtoupper(trim($data['emergency_contact_name']), 'UTF-8');
        $data['gender'] = $this->normalizeGender($data['gender'] ?? null);
        if (!empty($data['nationality_country_id'])) {
            $nationalityCountry=Country::findOrFail($data['nationality_country_id']);
            $data['nationality']=$nationalityCountry->nationality_name_en ?: $nationalityCountry->country_name_en;
            $data['nationality_kh']=$nationalityCountry->nationality_name_kh ?: $nationalityCountry->country_name_kh;
        }
        if (!empty($data['birth_province_id'])) {
            $birthProvince=Province::findOrFail($data['birth_province_id']);
            if (!empty($data['birth_country_id']) && (int)$data['birth_country_id']!==(int)$birthProvince->country_id) {
                throw \Illuminate\Validation\ValidationException::withMessages(['birth_province_id'=>'Select a Province / City belonging to the selected Country of Birth.']);
            }
            $data['birth_country_id']=$birthProvince->country_id;
            $data['place_of_birth_en']=$birthProvince->province_name_en;
            $data['place_of_birth_kh']=$birthProvince->province_name_kh;
        }
        $data['status'] = (bool) $data['status'];
        foreach (['educations','experiences'] as $section) foreach ($data[$section]??[] as $index=>$row) {
            if (!empty($row['started_on']) && !empty($row['ended_on']) && empty($row['is_current'])
                && \Carbon\Carbon::parse($row['ended_on'])->lt(\Carbon\Carbon::parse($row['started_on']))) {
                throw \Illuminate\Validation\ValidationException::withMessages([$section.'.'.$index.'.ended_on'=>'To must be on or after From.']);
            }
        }
        foreach (['house_number','street'] as $field) if (array_key_exists($field.'_en',$data)) $data[$field.'_kh']=StaffProfile::digits($data[$field.'_en']);

        $newPhoto=null; $oldPhoto=null;
        try { DB::transaction(function () use ($request, $data, $staffId, &$newPhoto, &$oldPhoto) {
            $staff = $staffId ? Staff::whereKey($staffId)->lockForUpdate()->firstOrFail() : new Staff();
            if ((int)$data['status']!==($staff->exists?(int)$staff->status:1)) abort_unless(\App\Support\HrmPermissions::allows($request->user(),'staff','status'),403);
            if ($staff->exists && array_key_exists('birth_country_id',$data) && $staff->birth_country_id
                && (int)$staff->birth_country_id!==(int)($data['birth_country_id']??0) && empty($data['birth_province_id'])) {
                $data['birth_province_id']=null;
                $data['place_of_birth_en']=null;
                $data['place_of_birth_kh']=null;
            }
            $before=$staff->exists?app(StaffWorkInformation::class)->snapshot($staff):null;
            if (array_intersect(['work_schedule','session_1_in','session_1_out','session_2_in','session_2_out'],array_keys($data))) {
                $scheduleData=array_replace($staff->only(['work_schedule','session_1_in','session_1_out','session_2_in','session_2_out']),$data);
                $schedule=app(StaffWorkInformation::class)->schedule($scheduleData);
                if (array_key_exists('work_schedule',$data)) $data['work_schedule']=$schedule;
            }
            $staff->fill(collect($data)->except(['staff_id', 'campuses', 'photo', 'experiences', 'educations','experiences_present','educations_present','work_change_type','work_effective_on','work_change_reason'])->toArray());
            $staff->updated_by = $request->user()->id;
            if (! $staff->exists) {
                $staff->created_by = $request->user()->id;
            }
            if ($request->hasFile('photo')) {
                $oldPhoto=$staff->photo_path;
                $newPhoto=$request->file('photo')->store('staff','public');
                $staff->photo_path=$newPhoto;
            }
            $staff->save();

            $campusIds = collect($data['campuses'] ?? [])->unique()->values();
            $primaryCampus=$data['primary_campus_id']??null;
            if ($primaryCampus && ! $campusIds->contains((int) $primaryCampus)) {
                $campusIds->prepend((int) $primaryCampus);
            }

            if (array_key_exists('campuses',$data) || array_key_exists('primary_campus_id',$data)) $staff->campuses()->sync($campusIds->mapWithKeys(fn ($id, $index) => [
                $id => ['is_primary' => (int) $id === (int) $primaryCampus || (! $primaryCampus && $index === 0), 'status' => true, 'assigned_at' => now()],
            ])->all());

            if (isset($data['experiences_present']) || array_key_exists('experiences',$data)) { $staff->experiences()->delete();
            foreach ($this->cleanExperiences($data['experiences'] ?? []) as $experience) {
                $staff->experiences()->create($experience);
            } }

            if (isset($data['educations_present']) || array_key_exists('educations',$data)) { $staff->educations()->delete();
            foreach ($this->cleanEducations($data['educations'] ?? []) as $education) {
                $staff->educations()->create($education);
            } }
            app(StaffWorkInformation::class)->record($staff,$before,$request->user(),$data);
        }); } catch (\Throwable $e) { if ($newPhoto) Storage::disk('public')->delete($newPhoto); throw $e; }
        if ($oldPhoto) Storage::disk('public')->delete($oldPhoto);

        return redirect()->route('staff-management.index')->with('success', $staffId ? 'Staff profile updated successfully.' : 'Staff profile created successfully.');
    }

    public function delete(Request $request, Staff $staff)
    {
        abort_unless(\App\Support\HrmPermissions::allows($request->user(),'staff','delete'),403);

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
                'country' => $row['country'] ?? null,
                'graduated_year' => $row['graduated_year'] ?? null,
            ])
            ->values()
            ->all();
    }
}
