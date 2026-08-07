@extends('layouts.app')

@section('title', 'Search Students')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col"><h2 class="page-title">Search Students</h2><div class="text-secondary">Find students by academic year, campus, grade/class, or group.</div></div>
        </div>
    </div>
</div>
<div class="page-body">
    <div class="container-xl">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Student Search</h3></div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3"><label class="form-label" for="search-year">Academic Year</label><select id="search-year" class="form-select"><option value="">All Academic Years</option></select></div>
                    <div class="col-md-3"><label class="form-label" for="search-campus">Campus</label><select id="search-campus" class="form-select"><option value="">All Campuses</option></select></div>
                    <div class="col-md-3"><label class="form-label" for="search-class">Grade / Class</label><select id="search-class" class="form-select"><option value="">All Grades / Classes</option></select></div>
                    <div class="col-md-3"><label class="form-label" for="search-group">Group</label><select id="search-group" class="form-select"><option value="">All Groups</option></select></div>
                    <div class="col-md-5"><label class="form-label" for="search-text">Student</label><div class="input-icon"><span class="input-icon-addon"><i class="ti ti-search"></i></span><input id="search-text" type="search" class="form-control" placeholder="Search student ID or name"></div></div>
                    <div class="col-md-7 d-flex justify-content-end"><button type="button" id="search-reset" class="btn btn-outline-secondary"><i class="ti ti-refresh me-1"></i>Reset</button></div>
                </div>
            </div>
        </div>
        <div class="card mt-3">
            <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Student No.</th><th>Student ID</th><th>Name</th><th>Academic Year</th><th>Campus</th><th>Grade / Class</th><th>Group</th><th>Status</th></tr></thead><tbody id="search-results"><tr><td colspan="8" class="text-center text-secondary py-5">Loading students...</td></tr></tbody></table></div>
            <div class="card-footer d-flex align-items-center"><p id="search-summary" class="m-0 text-secondary"></p><ul id="search-pagination" class="pagination m-0 ms-auto"></ul></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const year = document.getElementById('search-year'), campus = document.getElementById('search-campus'), cls = document.getElementById('search-class'), group = document.getElementById('search-group'), text = document.getElementById('search-text'), results = document.getElementById('search-results'), summary = document.getElementById('search-summary'), pagination = document.getElementById('search-pagination');
    const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
    const optionText = item => item.campus_name_en ? item.campus_name_en : (item.class_name || item.group_name || item.academic_year);
    function fill(select, items, empty) { select.innerHTML = '<option value="">' + empty + '</option>' + items.map(item => '<option value="' + item.id + '">' + esc(optionText(item)) + '</option>').join(''); }
    const combos = {};
    function makeSearchable(select, placeholder) {
        select.classList.add('d-none');
        const wrapper = document.createElement('div'); wrapper.className = 'location-combobox';
        wrapper.innerHTML = '<button type="button" class="location-combobox-toggle"><span class="location-combobox-selected">' + placeholder + '</span><i class="ti ti-chevron-down"></i></button><div class="location-combobox-menu d-none"><input type="search" class="form-control location-combobox-search" placeholder="Search"><div class="location-combobox-results"></div></div>';
        select.after(wrapper);
        const combo = combos[select.id] = {select:select, menu:wrapper.querySelector('.location-combobox-menu'), input:wrapper.querySelector('.location-combobox-search'), results:wrapper.querySelector('.location-combobox-results'), label:wrapper.querySelector('.location-combobox-selected'), placeholder:placeholder};
        const render = () => { const term=combo.input.value.toLowerCase(); const opts=Array.from(select.options).slice(1).filter(o=>o.text.toLowerCase().includes(term)); combo.results.innerHTML=opts.length ? opts.map(o=>'<button type="button" class="location-combobox-option" data-value="'+o.value+'">'+esc(o.text)+'</button>').join('') : '<div class="text-secondary px-2 py-2">No results found</div>'; combo.results.querySelectorAll('[data-value]').forEach(btn=>btn.onclick=()=>{ select.value=btn.dataset.value; select.dispatchEvent(new Event('change')); combo.menu.classList.add('d-none'); }); };
        wrapper.querySelector('.location-combobox-toggle').onclick=()=>{ Object.values(combos).forEach(other=>{if(other!==combo) other.menu.classList.add('d-none');}); combo.menu.classList.toggle('d-none'); combo.input.value=''; render(); combo.input.focus(); };
        combo.input.oninput=render;
        select.addEventListener('change',()=>{ const selected=select.options[select.selectedIndex]; combo.label.textContent=selected?.value ? selected.text : combo.placeholder; });
    }
    makeSearchable(year, 'All Academic Years'); makeSearchable(campus, 'All Campuses'); makeSearchable(cls, 'All Grades / Classes'); makeSearchable(group, 'All Groups');
    async function loadOptions() {
        const params = new URLSearchParams(); if (year.value) params.set('academic_year_id', year.value); if (cls.value) params.set('class_id', cls.value);
        const data = await fetch('{{ route('searchStudent.options') }}?' + params).then(r => r.json());
        const selectedYear = year.value, selectedClass = cls.value, selectedGroup = group.value;
        fill(year, data.academicYears, 'All Academic Years'); fill(campus, data.campuses, 'All Campuses'); fill(cls, data.classes, 'All Grades / Classes');
        year.value = selectedYear; cls.value = selectedClass;
        fill(group, data.groups, 'All Groups'); group.value = selectedGroup;
    }
    function queryUrl(page) { const p = new URLSearchParams({ page: page || 1, perPage: 10 }); if (year.value) p.set('academic_year_id', year.value); if (campus.value) p.set('campus_id', campus.value); if (cls.value) p.set('class_id', cls.value); if (group.value) p.set('group_id', group.value); if (text.value.trim()) p.set('search', text.value.trim()); return '{{ route('searchStudent.fetch') }}?' + p; }
    function render(data) { results.innerHTML = data.data.length ? data.data.map(item => { const s=item.student||{}; return '<tr><td>' + esc(s.student_no) + '</td><td>' + esc(s.student_id) + '</td><td>' + esc([s.first_name_en,s.last_name_en].filter(Boolean).join(' ') || '-') + '</td><td>' + esc(item.academic_year?.academic_year || '-') + '</td><td>' + esc(item.campus?.campus_name_en || '-') + '</td><td>' + esc(item.school_class?.class_name || '-') + '</td><td>' + esc(item.school_group?.group_name || '-') + '</td><td><span class="badge bg-green-lt">' + esc(item.enrollment_status || 'Active') + '</span></td></tr>'; }).join('') : '<tr><td colspan="8" class="text-center text-secondary py-5">No students found.</td></tr>'; summary.textContent = data.total ? 'Showing ' + data.from + ' to ' + data.to + ' of ' + data.total + ' students' : ''; pagination.innerHTML = ''; for (let i=1;i<=data.last_page;i++) { const li=document.createElement('li'); li.className='page-item' + (i===data.current_page?' active':''); li.innerHTML='<button type="button" class="page-link">'+i+'</button>'; li.querySelector('button').onclick=()=>load(i); pagination.appendChild(li); } }
    async function load(page) { results.innerHTML='<tr><td colspan="8" class="text-center text-secondary py-5">Loading students...</td></tr>'; render(await fetch(queryUrl(page)).then(r=>r.json())); }
    [year,campus,cls,group].forEach(select => select.addEventListener('change', async function () { if (select === year || select === cls) await loadOptions(); load(1); }));
    let timer; text.addEventListener('input', function(){ clearTimeout(timer); timer=setTimeout(()=>load(1),300); });
    document.getElementById('search-reset').onclick=async()=>{ year.value=''; campus.value=''; cls.value=''; group.value=''; text.value=''; await loadOptions(); load(1); };
    loadOptions().then(()=>load(1));
});
</script>
@endpush
