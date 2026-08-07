@extends('layouts.app')
@section('title','Send Notification')
@section('page-header')<div class="container-fluid"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">Administrator</div><h2 class="page-title">Send Notification</h2></div><div class="col-auto"><a class="btn btn-outline-primary" href="{{ route('notifications.manage') }}">Notification Management</a></div></div></div>@endsection
@section('content')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const userDepartments = @json($users->pluck('department_id', 'id'));
    document.querySelectorAll('select[multiple]').forEach(select => {
        const wrapper = document.createElement('div');
        wrapper.className = 'border rounded p-2';
        wrapper.dataset.selectName = select.name;
        const search = document.createElement('input');
        search.className = 'form-control form-control-sm mb-2';
        search.placeholder = 'Search...';
        const list = document.createElement('div');
        list.className = 'overflow-auto';
        list.style.maxHeight = '210px';
        [...select.options].forEach(option => {
            const label = document.createElement('label');
            label.className = 'form-check mb-1';
            label.dataset.text = option.text.toLowerCase();
            label.innerHTML = '<input class="form-check-input" type="checkbox" value="' + option.value + '"' + (option.selected ? ' checked' : '') + '><span class="form-check-label">' + option.text + '</span>';
            label.querySelector('input').addEventListener('change', event => { option.selected = event.target.checked; });
            list.appendChild(label);
        });
        search.addEventListener('input', () => {
            const query = search.value.toLowerCase();
            list.querySelectorAll('label').forEach(label => { label.classList.toggle('d-none', !label.dataset.text.includes(query)); });
        });
        select.classList.add('d-none');
        select.parentElement.insertBefore(wrapper, select);
        wrapper.append(search, list);
    });
    const departmentSelect = document.querySelector('select[name="department_ids[]"]');
    const userSelect = document.querySelector('select[name="recipient_ids[]"]');
    const departmentBox = departmentSelect?.previousElementSibling;
    const userBox = userSelect?.previousElementSibling;
    const syncDepartmentRecipients = () => {
        if (!departmentSelect || !userSelect || !departmentBox || !userBox) return;
        const selectedDepartments = [...departmentSelect.options].filter(option => option.selected).map(option => String(option.value));
        [...userSelect.options].forEach(option => {
            const checkbox = userBox.querySelector('input[value="' + option.value + '"]');
            const blocked = selectedDepartments.includes(String(userDepartments[option.value] || ''));
            if (blocked) {
                option.selected = false;
                if (checkbox) { checkbox.checked = false; checkbox.disabled = true; }
            } else if (checkbox) checkbox.disabled = false;
        });
    };
    [...(departmentBox?.querySelectorAll('input[type="checkbox"]') || [])].forEach(checkbox => checkbox.addEventListener('change', syncDepartmentRecipients));
    syncDepartmentRecipients();
});
</script>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card"><div class="card-header"><h3 class="card-title">Create Notification</h3></div><form method="POST" action="{{ route('notifications.send.save') }}">@csrf<div class="card-body"><div class="row g-3"><div class="col-md-4"><label class="form-label">Notification Type</label><select class="form-select" name="type"><option value="announcement">Announcement</option><option value="system">System</option><option value="enrollment">Enrollment</option><option value="approval">Approval</option></select></div><div class="col-md-8"><label class="form-label">Title</label><input class="form-control" name="title" value="{{ old('title') }}" required></div><div class="col-12"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="5" required>{{ old('message') }}</textarea></div><div class="col-12"><label class="form-label">Action URL <span class="text-secondary">(optional)</span></label><input class="form-control" type="url" name="action_url" value="{{ old('action_url') }}" placeholder="https://..."></div><div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="send_to_all" value="1" id="sendToAll"><span class="form-check-label">Send to all active users</span></label></div><div class="col-md-6" id="departmentBox"><label class="form-label">Departments</label><select class="form-select" name="department_ids[]" multiple size="7">@foreach($departments as $department)<option value="{{ $department->id }}" @selected(collect(old('department_ids'))->contains($department->id))>{{ $department->name }}</option>@endforeach</select><small class="text-secondary">Select one or multiple departments.</small></div><div class="col-md-6" id="recipientBox"><label class="form-label">Individual Staff</label><select class="form-select" name="recipient_ids[]" multiple size="7">@foreach($users as $user)<option value="{{ $user->id }}" @selected(collect(old('recipient_ids'))->contains($user->id))>{{ $user->name }} — {{ $user->username }} — {{ $user->email }}</option>@endforeach</select><small class="text-secondary">Select one or multiple staff.</small></div></div></div><div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-send me-1"></i> Send Notification</button></div></form></div>
<script>document.addEventListener('DOMContentLoaded',()=>{const all=document.getElementById('sendToAll'),boxes=[document.getElementById('departmentBox'),document.getElementById('recipientBox')];const sync=()=>boxes.forEach(box=>{box.classList.toggle('opacity-50',all.checked);box.querySelector('select').disabled=all.checked});all.addEventListener('change',sync);sync()});</script>
@endsection
