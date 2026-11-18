<section class="card wmi-years-card" data-wmi-years data-save-url="{{ route('wmi-management.years.store',[],false) }}">
    <div class="card-header gap-3 flex-wrap">
        <span class="wmi-content-icon"><i class="ti ti-calendar" aria-hidden="true"></i></span>
        <div><div class="page-pretitle">Settings</div><h2 class="card-title mb-0">WMI Year</h2></div>
        <button type="button" class="btn btn-primary ms-auto" data-wmi-year-new><i class="ti ti-plus me-2" aria-hidden="true"></i>Add New</button>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter mb-0">
            <thead><tr><th class="wmi-year-number-column">No.</th><th>WMI Year</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($years as $year)
                @php($linked=in_array($year->id,$linkedYearIds,true))
                <tr>
                    <td><span class="wmi-year-number">{{ $years->firstItem()+$loop->index }}</span></td>
                    <td><strong>WMI {{ $year->year }}</strong>@if($linked)<span class="badge bg-secondary-lt ms-2">Linked to data</span>@endif</td>
                    <td><div class="d-flex flex-wrap justify-content-end gap-2">
                        <button class="btn btn-sm btn-outline-primary" type="button" data-wmi-year-edit data-id="{{ $year->id }}" data-year="{{ $year->year }}" aria-label="Edit WMI {{ $year->year }}"><i class="ti ti-edit me-1" aria-hidden="true"></i>Edit</button>
                        <span @if($linked) title="This WMI year is linked to data and cannot be deleted." @endif><button class="btn btn-sm btn-outline-danger" type="button" data-wmi-year-delete data-id="{{ $year->id }}" data-year="{{ $year->year }}" @disabled($linked) aria-label="Delete WMI year {{ $year->year }}"><i class="ti ti-trash me-1" aria-hidden="true"></i>Delete</button></span>
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center py-5"><span class="wmi-empty-icon"><i class="ti ti-calendar-plus" aria-hidden="true"></i></span><h3>No WMI years yet</h3><p class="text-secondary mb-0">Select Add New to create your first WMI year.</p></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex flex-wrap align-items-center gap-3"><span class="text-secondary">{{ number_format($years->total()) }} WMI {{ $years->total()===1 ? 'year' : 'years' }}</span><div class="ms-auto">{{ $years->links() }}</div></div>
</section>
