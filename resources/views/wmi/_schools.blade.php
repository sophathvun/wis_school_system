<section class="card wmi-years-card" data-wmi-schools data-save-url="{{ route('wmi-management.schools.store',[],false) }}">
    <div class="card-header gap-3 flex-wrap">
        <span class="wmi-content-icon"><i class="ti ti-school" aria-hidden="true"></i></span>
        <div><div class="page-pretitle">Settings</div><h2 class="card-title mb-0">Assign Schools</h2></div>
        <div class="d-flex flex-nowrap align-items-center gap-2 ms-auto" style="max-width:100%;min-width:0">
            <form method="GET" action="{{ route('wmi-management.section','settings') }}" class="wmi-center-filter mb-0" style="flex:1 1 240px;min-width:0">
                <input type="hidden" name="tab" value="schools"><input type="hidden" name="per_page" value="{{ request('per_page')??'15' }}">
                <label class="visually-hidden" for="wmiSchoolProvinceFilter">School Location</label>
                <select class="form-select" name="province_id" id="wmiSchoolProvinceFilter"><option value="">All Locations</option>
                    @foreach($assignedSchoolProvinces as $province)<option value="{{ $province->id }}" @selected((string)request('province_id')===(string)$province->id)>{{ $province->province_name_en }}@if($province->province_name_kh) — {{ $province->province_name_kh }}@endif</option>@endforeach
                </select>
            </form>
            <button type="button" class="btn btn-primary flex-shrink-0 text-nowrap" @disabled(!\App\Support\WmiPermissions::current('settings.schools.create')) data-wmi-school-new><i class="ti ti-plus me-2" aria-hidden="true"></i>Add New</button>
        </div>
    </div>
    @if($schoolProvinces->isEmpty())<div class="card-body border-bottom"><div class="alert alert-info mb-0">School Location is optional. Add provinces in Location Settings when needed.</div></div>@endif
    <div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>No.</th><th>School Name (English)</th><th>School Name (Khmer)</th><th>School Type</th><th>School Location</th><th>Remark</th><th class="text-end">Actions</th></tr></thead><tbody>
        @forelse($schools as $school)
            @php($linked=in_array($school->id,$linkedSchoolIds,true))
            @php($schoolPayload=$school->only(['id','name_en','name_kh','school_type','province_id','remark']))
            <tr>
                <td><span class="wmi-year-number">{{ $schools->firstItem()+$loop->index }}</span></td>
                <td class="wmi-center-text fw-semibold">{{ $school->name_en }}@if($linked)<span class="badge bg-secondary-lt ms-2">Linked to data</span>@endif</td>
                <td class="wmi-center-text">{{ $school->name_kh?:'?' }}</td>
                <td><span class="badge {{ $school->school_type==='Private'?'bg-blue-lt':'bg-green-lt' }}">{{ $school->school_type }}</span></td>
                <td class="wmi-center-text">{{ $school->province?->province_name_en??'?' }}@if($school->province?->province_name_kh)<div class="text-secondary">{{ $school->province->province_name_kh }}</div>@endif</td>
                <td class="wmi-center-text">{{ $school->remark?:'—' }}</td>
                <td><div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" @disabled(!\App\Support\WmiPermissions::current('settings.schools.update')) data-wmi-school-edit data-school='@json($schoolPayload)' aria-label="Edit {{ $school->name_en }}"><i class="ti ti-edit me-1" aria-hidden="true"></i>Edit</button>
                    <span @if($linked) title="Schools linked to data cannot be deleted." @endif><button type="button" class="btn btn-sm btn-outline-danger" data-wmi-school-delete data-id="{{ $school->id }}" data-name="{{ $school->name_en }}" @disabled($linked || !\App\Support\WmiPermissions::current('settings.schools.delete')) aria-label="Delete {{ $school->name_en }}"><i class="ti ti-trash me-1" aria-hidden="true"></i>Delete</button></span>
                </div></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center py-5"><span class="wmi-empty-icon"><i class="ti ti-school" aria-hidden="true"></i></span><h3>No schools found</h3><p class="text-secondary mb-0">Add a participating school to get started.</p></td></tr>
        @endforelse
    </tbody></table></div>
    <div class="card-footer">@include('wmi._center-pagination',['records'=>$schools])</div>
</section>
