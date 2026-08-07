@include('partials.full-access-toggle', ['fullAccess' => $fullAccess ?? false, 'fullAccessLocked' => $fullAccessLocked ?? false])
@php($permissionUser = $listedUser ?? $selectedUser ?? null)
@if($permissionUser)
  <div class="mb-3">
    <label class="form-label permission-campus-label">Campus Assignment</label>
    @if($permissionUser->is_global)
      <div class="form-control bg-light">All Campuses (Global Administrator)</div>
    @else
      <select class="form-select permission-campus-select" name="campuses[]" multiple size="4">
        @foreach($campuses ?? collect() as $campus)
          <option value="{{ $campus->id }}" @selected($permissionUser->campuses->contains($campus->id))>{{ $campus->campus_name_en }}</option>
        @endforeach
      </select>
      <div class="form-text">Hold Ctrl (Windows) or Command (Mac) to select more than one campus.</div>
    @endif
  </div>
@endif
<div class="row g-3">
@foreach($permissionHierarchy as $groupKey => $group)
  @php($main = $group['permission'])
  @if($main)
    @php($mainId = $permissionPrefix.'-main-'.$groupKey)
    @php($mainOn = $assignedPermissions->contains('id', $main->id))
    <div class="col-md-6">
      <div class="border rounded p-3" data-permission-group>
        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
          <span class="fw-bold fs-5">{{ strtoupper($group['label']) }}</span>
          <button type="button" class="status-toggle {{ $mainOn ? 'is-active' : '' }}" data-permission-toggle data-permission-level="main" data-permission-id="{{ $mainId }}" data-status="{{ $mainOn ? 1 : 0 }}" aria-pressed="{{ $mainOn ? 'true' : 'false' }}">
            <span class="status-toggle-label">{{ $mainOn ? 'ON' : 'OFF' }}</span><span class="status-toggle-knob"></span>
          </button>
        </div>
        @foreach($group['modules'] as $moduleKey => $module)
          @php($submenuId = $permissionPrefix.'-submenu-'.str_replace(['.', '-'], '_', $moduleKey))
          @php($submenuOn = $assignedPermissions->contains('id', $module['permission']->id))
          <div class="permission-submenu" data-parent-permission="{{ $mainId }}">
            <div class="d-flex justify-content-between align-items-center py-2">
              <span>{{ $module['label'] }}</span>
              <button type="button" class="status-toggle {{ $submenuOn ? 'is-active' : '' }}" data-permission-toggle data-permission-level="submenu" data-permission-id="{{ $submenuId }}" data-parent-permission="{{ $mainId }}" data-status="{{ $submenuOn ? 1 : 0 }}" aria-pressed="{{ $submenuOn ? 'true' : 'false' }}">
                <span class="status-toggle-label">{{ $submenuOn ? 'ON' : 'OFF' }}</span><span class="status-toggle-knob"></span>
              </button>
            </div>
            @foreach($module['actions'] as $action)
              @php($actionOn = $assignedPermissions->contains('id', $action->id))
              <div class="d-flex justify-content-between align-items-center border-top py-2 ps-3 permission-action" data-action-parent="{{ $submenuId }}">
                <span class="small text-secondary">{{ $action->name }} <small>({{ $action->code }})</small></span>
                <button type="button" class="status-toggle {{ $actionOn ? 'is-active' : '' }}" data-permission-toggle data-permission-level="action" data-parent-permission="{{ $submenuId }}" data-status="{{ $actionOn ? 1 : 0 }}" aria-pressed="{{ $actionOn ? 'true' : 'false' }}">
                  <input type="checkbox" class="d-none" name="permissions[]" value="{{ $action->id }}" @checked($actionOn)>
                  <span class="status-toggle-label">{{ $actionOn ? 'ON' : 'OFF' }}</span><span class="status-toggle-knob"></span>
                </button>
              </div>
            @endforeach
            <input type="checkbox" class="d-none" name="permissions[]" value="{{ $module['permission']->id }}" @checked($submenuOn) data-permission-input="{{ $submenuId }}">
          </div>
        @endforeach
        @foreach($group['actions'] as $action)
          @php($actionOn = $assignedPermissions->contains('id', $action->id))
          <div class="d-flex justify-content-between align-items-center border-top py-2 permission-action" data-action-parent="{{ $mainId }}">
            <span class="small text-secondary">{{ $action->name }} <small>({{ $action->code }})</small></span>
            <button type="button" class="status-toggle {{ $actionOn ? 'is-active' : '' }}" data-permission-toggle data-permission-level="action" data-parent-permission="{{ $mainId }}" data-status="{{ $actionOn ? 1 : 0 }}" aria-pressed="{{ $actionOn ? 'true' : 'false' }}">
              <input type="checkbox" class="d-none" name="permissions[]" value="{{ $action->id }}" @checked($actionOn)>
              <span class="status-toggle-label">{{ $actionOn ? 'ON' : 'OFF' }}</span><span class="status-toggle-knob"></span>
            </button>
          </div>
        @endforeach
        <input type="checkbox" class="d-none" name="permissions[]" value="{{ $main->id }}" @checked($mainOn) data-permission-input="{{ $mainId }}">
      </div>
    </div>
  @endif
@endforeach
</div>
<style>
.permission-campus-picker{position:relative}
.permission-campus-picker-toggle{display:flex;width:100%;align-items:center;justify-content:space-between;min-height:52px;padding:.65rem 1rem;border:1px solid var(--tblr-border-color);border-radius:var(--tblr-border-radius);background:var(--tblr-bg-forms);color:var(--tblr-body-color);text-align:left}
.permission-campus-picker-menu{position:absolute;z-index:30;left:0;right:0;top:calc(100% + .35rem);padding:.65rem;background:var(--tblr-bg-surface);border:1px solid var(--tblr-border-color);border-radius:var(--tblr-border-radius);box-shadow:0 12px 28px rgba(31,41,55,.16)}
.permission-campus-picker-results{max-height:220px;overflow-y:auto;margin-top:.5rem}
.permission-campus-picker-option{display:block;padding:.45rem .5rem;border-radius:.35rem}
.permission-campus-picker-option:hover{background:rgba(127,127,127,.12)}
.permission-campus-select{display:none!important}
.permission-campus-label{display:block;background:transparent!important;color:var(--tblr-body-color)!important}
[data-bs-theme="dark"] .permission-campus-picker-toggle span{color:#f8fafc}
[data-bs-theme="dark"] .permission-campus-picker-toggle,[data-bs-theme="dark"] .permission-campus-picker-menu{background:#1e293b;color:#f8fafc;border-color:#475569}
[data-bs-theme="dark"] .permission-campus-picker + .form-text,[data-bs-theme="dark"] .permission-campus-picker ~ .form-text{color:#94a3b8}
[data-bs-theme="dark"] .form-label{color:var(--tblr-body-color)}
[data-bs-theme="dark"] .permission-campus-label{background:transparent!important;color:#f8fafc!important}
[data-bs-theme="dark"] .permission-full-access-bar{background:#1e293b!important;color:#f8fafc;border-color:#475569!important}
</style>
<script>
const initPermissionCampusPickers = () => {
    document.querySelectorAll('.permission-campus-select').forEach(select => {
        if (select.dataset.searchableReady) return;
        select.dataset.searchableReady = '1';
        select.classList.add('d-none');
        const picker = document.createElement('div');
        picker.className = 'permission-campus-picker';
        picker.innerHTML = '<button type="button" class="permission-campus-picker-toggle"><span></span><i class="ti ti-chevron-down"></i></button><div class="permission-campus-picker-menu d-none"><input type="search" class="form-control" placeholder="Search campuses"><div class="permission-campus-picker-results"></div></div>';
        select.after(picker);
        const button = picker.querySelector('button'), menu = picker.querySelector('.permission-campus-picker-menu'), search = picker.querySelector('input'), summary = picker.querySelector('button span'), results = picker.querySelector('.permission-campus-picker-results');
        const sync = () => { const chosen = [...select.selectedOptions].map(option => option.textContent.trim()); summary.textContent = chosen.join(', '); };
        const render = () => { const term = search.value.toLowerCase().trim(); const options = [...select.options].filter(option => !term || option.textContent.toLowerCase().includes(term)); results.innerHTML = options.length ? options.map(option => `<label class="permission-campus-picker-option"><input class="form-check-input me-2" type="checkbox" value="${option.value}" ${option.selected ? 'checked' : ''}>${option.textContent}</label>`).join('') : '<div class="text-secondary px-2 py-2">No campuses found</div>'; };
        button.addEventListener('click', () => { menu.classList.toggle('d-none'); if (!menu.classList.contains('d-none')) { search.value=''; render(); search.focus(); } });
        search.addEventListener('input', render);
        results.addEventListener('change', event => { if (!event.target.matches('input[type="checkbox"]')) return; const option = [...select.options].find(item => item.value === event.target.value); if (option) option.selected = event.target.checked; select.dispatchEvent(new Event('change',{bubbles:true})); sync(); });
        document.addEventListener('click', event => { if (!picker.contains(event.target)) menu.classList.add('d-none'); });
        select.addEventListener('change', () => { sync(); render(); });
        sync();
    });
};
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initPermissionCampusPickers);
else initPermissionCampusPickers();
</script>
