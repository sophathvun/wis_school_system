@extends('layouts.app')
@section('title', 'Positions')
@section('page-header')
<div class="container-fluid"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">Administrator</div><h2 class="page-title">Positions</h2></div><div class="col-auto"><button id="btnNewPosition" class="btn btn-primary"><i class="ti ti-plus icon"></i> New Position</button></div></div></div>
@endsection
@section('content')
<style>
#positionModal .modal-dialog{max-width:420px}
#positionModal .position-field{position:relative;margin-bottom:1rem}
#positionModal .position-field>.form-label{position:absolute;z-index:2;top:1rem;left:1rem;margin:0;padding:0 .35rem;background:transparent;color:var(--tblr-secondary);font-size:.95rem;font-weight:400;line-height:1.1;pointer-events:none;transition:top .16s ease,font-size .16s ease,color .16s ease,background-color .16s ease}
#positionModal .position-field.has-value>.form-label,#positionModal .position-field:focus-within>.form-label{top:.42rem;padding:0 .45rem;background:var(--tblr-bg-surface,#fff);color:#5b4bd1;font-size:.72rem;font-weight:700}
#positionModal .position-field>.form-control,#positionModal .position-field>.form-select{height:52px;min-height:52px;padding:1.25rem 1rem .35rem;border:1.5px solid #dfe3ea;border-radius:14px;background:var(--tblr-bg-forms,#fff);color:var(--tblr-body-color);box-shadow:0 2px 7px rgba(31,41,55,.04)}
#positionModal .position-combobox{position:relative}
#positionModal .position-combobox-toggle{width:100%;height:52px;padding:1.25rem 2.75rem .35rem 1rem;border:1.5px solid #dfe3ea;border-radius:14px;background:var(--tblr-bg-forms,#fff);color:var(--tblr-body-color);text-align:left;font:inherit;position:relative}
#positionModal .position-combobox-toggle:focus{outline:0;border-color:#6b5bd6;box-shadow:0 0 0 3px rgba(107,91,214,.14)}
#positionModal .position-combobox-toggle>i{position:absolute;right:1rem;top:50%;transform:translateY(-50%);color:var(--tblr-secondary)}
#positionModal .position-combobox-menu{position:absolute;z-index:20;left:0;right:0;top:calc(100% + .35rem);padding:.65rem;background:var(--tblr-bg-surface,#fff);border:1px solid var(--tblr-border-color);border-radius:14px;box-shadow:0 12px 28px rgba(31,41,55,.16)}
#positionModal .position-combobox-menu .form-control{height:44px;border-radius:10px}
#positionModal .position-combobox-results{max-height:220px;overflow-y:auto;margin-top:.5rem}
#positionModal .position-combobox-option{display:block;width:100%;border:0;background:transparent;text-align:left;padding:.65rem .75rem;border-radius:9px;color:var(--tblr-body-color)}
#positionModal .position-combobox-option:hover{background:rgba(127,127,127,.12)}
#positionModal .position-status-row{display:flex;justify-content:flex-end;margin:1rem 0;min-height:30px}
[data-bs-theme="dark"] #positionModal .position-field.has-value>.form-label,[data-bs-theme="dark"] #positionModal .position-field:focus-within>.form-label{background:#1e293b;color:#a99cff}
[data-bs-theme="dark"] #positionModal .position-field>.form-control,[data-bs-theme="dark"] #positionModal .position-field>.form-select,[data-bs-theme="dark"] #positionModal .position-combobox-toggle{background:#1e293b!important;color:#f8fafc!important;border-color:#475569!important}
[data-bs-theme="dark"] #positionModal .position-combobox-menu{background:#1e293b!important;color:#f8fafc;border-color:#475569}
[data-bs-theme="dark"] #positionModal .position-combobox-menu .form-control{background:#0f172a!important;color:#f8fafc!important;border-color:#64748b!important}
[data-bs-theme="dark"] #positionModal .position-combobox-option{color:#f8fafc}
[data-bs-theme="dark"] #positionModal .position-combobox-option:hover{background:#334155}
</style>
<div class="card"><div class="card-header"><h3 class="card-title">Position Lists</h3></div><form method="GET"><div class="card-body border-bottom py-3"><div class="row justify-content-between"><div class="col-auto text-secondary">Show <select class="form-select form-select-sm d-inline-block w-auto mx-2" name="per_page" onchange="this.form.submit()"><option value="10" @selected(request('per_page',10)==10)>10 / page</option><option value="25" @selected(request('per_page')==25)>25 / page</option><option value="50" @selected(request('per_page')==50)>50 / page</option></select> entries</div><div class="col-auto input-icon"><span class="input-icon-addon"><i class="ti ti-search"></i></span><input class="form-control form-control-sm" name="search" value="{{ request('search') }}" placeholder="Search positions"></div></div></div></form><div class="table-responsive table-vcenter text-nowrap"><table class="table card-table"><thead><tr><th>No.</th><th>Position</th><th>Department</th><th>Code</th><th>Status</th><th class="text-center">Actions</th></tr></thead><tbody>@forelse($positions as $position)<tr><td>{{ $positions->firstItem()+$loop->index }}</td><td>{{ $position->name }}</td><td>{{ $position->department?->name ?? '—' }}</td><td>{{ $position->code }}</td><td><span class="badge bg-{{ $position->status?'success':'secondary' }}">{{ $position->status?'Active':'Inactive' }}</span></td><td class="text-center"><a class="btn btn-sm btn-outline-primary" href="{{ route('positions.index',['edit'=>$position->id]) }}"><i class="ti ti-edit"></i></a><form class="d-inline" method="POST" action="{{ route('positions.delete',$position) }}" onsubmit="return confirm('Delete this position?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="ti ti-trash"></i></button></form></td></tr>@empty<tr><td colspan="6" class="text-center text-secondary py-4">No positions found.</td></tr>@endforelse</tbody></table></div><div class="card-footer">@include('partials.admin-pagination',['paginator'=>$positions])</div></div>
<div class="modal modal-blur fade" id="positionModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('positions.save') }}">@csrf<div class="modal-header"><h5 class="modal-title">{{ $editPosition?'Edit Position':'Create Position' }}</h5><a class="btn-close" href="{{ route('positions.index') }}"></a></div><div class="modal-body"><input type="hidden" name="position_id" value="{{ $editPosition?->id }}"><div class="position-field"><label class="form-label">Position Name</label><input class="form-control" name="name" value="{{ old('name',$editPosition?->name) }}" required></div><div class="position-field"><label class="form-label">Code</label><input class="form-control" name="code" value="{{ old('code',$editPosition?->code) }}" required></div><div class="position-field"><label class="form-label">Department</label><select class="form-select" name="department_id" required><option value=""> </option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id',$editPosition?->department_id)==$department->id)>{{ $department->name }}</option>@endforeach</select></div><div class="position-status-row"><select class="d-none" name="status"><option value="1" @selected((string)old('status',$editPosition?->status ?? 1)==='1')>Active</option><option value="0" @selected((string)old('status',$editPosition?->status)==='0')>Inactive</option></select><button type="button" class="status-toggle {{ old('status',$editPosition?->status ?? 1) ? 'is-active' : '' }}" id="positionStatusToggle" aria-pressed="{{ old('status',$editPosition?->status ?? 1) ? 'true' : 'false' }}"><span class="status-toggle-label">{{ old('status',$editPosition?->status ?? 1) ? 'ON' : 'OFF' }}</span><span class="status-toggle-knob"></span></button></div></div><div class="modal-footer"><a class="btn me-auto" href="{{ route('positions.index') }}">Cancel</a><button class="btn btn-primary">{{ $editPosition?'Update':'Create' }}</button></div></form></div></div></div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('positionModal');
    const name = modal?.querySelector('input[name="name"]');
    const code = modal?.querySelector('input[name="code"]');
    const department = modal?.querySelector('select[name="department_id"]');
    const fields = [name, code, department].map(control => control?.closest('.position-field')).filter(Boolean);
    fields.forEach(field => { const control = field.querySelector('input,select'); const sync = () => field.classList.toggle('has-value', Boolean(control.value)); control.addEventListener('input', sync); control.addEventListener('change', sync); sync(); });
    if (department) {
        const field = department.closest('.position-field');
        const combo = document.createElement('div');
        combo.className = 'position-combobox';
        combo.innerHTML = '<button type="button" class="position-combobox-toggle"><span></span><i class="ti ti-chevron-down"></i></button><div class="position-combobox-menu d-none"><input type="search" class="form-control" placeholder="Search Department"><div class="position-combobox-results"></div></div>';
        department.classList.add('d-none'); department.after(combo);
        const button = combo.querySelector('button'), menu = combo.querySelector('.position-combobox-menu'), search = combo.querySelector('input'), selected = combo.querySelector('span'), results = combo.querySelector('.position-combobox-results');
        const sync = () => { selected.textContent = department.value ? department.selectedOptions[0].textContent : ''; field.classList.toggle('has-value', Boolean(department.value)); };
        const render = () => { const term = search.value.toLowerCase().trim(); const options = [...department.options].filter(option => option.value && (!term || option.textContent.toLowerCase().includes(term))); results.innerHTML = options.length ? options.map(option => `<button type="button" class="position-combobox-option" data-value="${option.value}">${option.textContent}</button>`).join('') : '<div class="text-secondary px-2 py-2">No departments found</div>'; };
        button.addEventListener('click', () => { menu.classList.toggle('d-none'); if (!menu.classList.contains('d-none')) { search.value=''; render(); search.focus(); } }); search.addEventListener('input', render); results.addEventListener('click', event => { const option=event.target.closest('[data-value]'); if(!option)return; department.value=option.dataset.value; department.dispatchEvent(new Event('change',{bubbles:true})); menu.classList.add('d-none'); sync(); }); document.addEventListener('click', event => { if(!combo.contains(event.target)) menu.classList.add('d-none'); }); sync(); render();
    }
    const status = modal?.querySelector('select[name="status"]'); const toggle = modal?.querySelector('#positionStatusToggle');
    toggle?.addEventListener('click', () => { status.value = status.value === '1' ? '0' : '1'; const active=status.value==='1'; toggle.classList.toggle('is-active',active); toggle.querySelector('.status-toggle-label').textContent=active?'ON':'OFF'; toggle.setAttribute('aria-pressed',active?'true':'false'); });
    document.getElementById('btnNewPosition')?.addEventListener('click', () => new bootstrap.Modal(modal).show());
    @if($editPosition) new bootstrap.Modal(modal).show(); @endif
});
</script>
@endsection
