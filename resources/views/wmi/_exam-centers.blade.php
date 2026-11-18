<section class="card wmi-years-card" data-wmi-centers data-save-url="{{ route('wmi-management.centers.store',[],false) }}">
    <div class="card-header gap-3 flex-wrap">
        <span class="wmi-content-icon"><i class="ti ti-building" aria-hidden="true"></i></span>
        <div><div class="page-pretitle">Settings</div><h2 class="card-title mb-0">Assign Exam Center</h2></div>
        <div class="d-flex flex-nowrap align-items-center gap-2 ms-auto" style="max-width:100%;min-width:0">
            @if($centerYears->isNotEmpty())
                <form method="GET" action="{{ route('wmi-management.section','settings') }}" class="wmi-center-filter mb-0" style="flex:1 1 240px;min-width:0">
                    <input type="hidden" name="tab" value="exam-centers">
                    <input type="hidden" name="per_page" value="{{ request('per_page')??'15' }}">
                    <label class="visually-hidden" for="wmiCenterYearFilter">WMI Year</label>
                    <select class="form-select" name="wmi_year_id" id="wmiCenterYearFilter" data-wmi-year-picker data-auto-filter>
                        <option value="">All WMI Years</option>
                        @foreach($centerYears as $year)<option value="{{ $year->id }}" @selected((string)request('wmi_year_id')===(string)$year->id)>WMI {{ $year->year }}</option>@endforeach
                    </select>
                </form>
            @endif
            <button type="button" class="btn btn-primary flex-shrink-0 text-nowrap" data-wmi-center-new @disabled($centerYears->isEmpty())><i class="ti ti-plus me-2" aria-hidden="true"></i>Add New</button>
        </div>
    </div>
    @if($centerYears->isEmpty())
        <div class="card-body border-bottom"><div class="alert alert-info mb-0">Create a <a href="{{ route('wmi-management.section',['section'=>'settings','tab'=>'academic-year']) }}">WMI Year</a> before assigning an exam center.</div></div>
    @endif
    <div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th class="wmi-year-number-column">No.</th><th>WMI Year</th><th>Exam Center</th><th>Location</th><th class="text-end">Actions</th></tr></thead><tbody>
        @forelse($examCenters as $center)
            @php($linked=in_array($center->id,$linkedCenterIds,true))
            @php($centerPayload=["id"=>$center->id,"wmi_year_id"=>$center->wmi_year_id,"name"=>$center->name,"location"=>$center->location,"linked"=>$linked])
            <tr><td><span class="wmi-year-number">{{ $examCenters->firstItem()+$loop->index }}</span></td><td class="text-nowrap fw-semibold">WMI {{ $center->wmiYear->year }}</td><td class="wmi-center-text"><strong>{{ $center->name }}</strong>@if($linked)<span class="badge bg-secondary-lt ms-2">Linked to data</span>@endif</td><td class="wmi-center-text">{{ $center->location }}</td><td><div class="d-flex justify-content-end gap-2"><button type="button" class="btn btn-sm btn-outline-primary" data-wmi-center-edit data-center='@json($centerPayload)' aria-label="Edit {{ $center->name }}"><i class="ti ti-edit me-1" aria-hidden="true"></i>Edit</button><span @if($linked) title="This exam center is linked to data and cannot be deleted." @endif><button class="btn btn-sm btn-outline-danger" type="button" data-wmi-center-delete data-id="{{ $center->id }}" data-name="{{ $center->name }}" @disabled($linked) aria-label="Delete {{ $center->name }}"><i class="ti ti-trash me-1" aria-hidden="true"></i>Delete</button></span></div></td></tr>
        @empty
            <tr><td colspan="5" class="text-center py-5"><span class="wmi-empty-icon"><i class="ti ti-building" aria-hidden="true"></i></span><h3>No exam centers found</h3><p class="text-secondary mb-0">Add an exam center and assign its WMI year.</p></td></tr>
        @endforelse
    </tbody></table></div>
    <div class="card-footer">@include('wmi._center-pagination',['records'=>$examCenters])</div>
</section>
