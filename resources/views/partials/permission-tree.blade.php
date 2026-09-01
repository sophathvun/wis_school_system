@include('partials.full-access-toggle', [
    'fullAccess' => $fullAccess ?? false,
    'fullAccessLocked' => $fullAccessLocked ?? false,
])
@php($mainMenuIcons = ['administrator' => 'ti-shield-lock', 'communication' => 'ti-messages', 'settings' => 'ti-settings', 'students' => 'ti-user', 'dashboard' => 'ti-dashboard'])
@php($submenuIcons = ['users' => 'ti-users', 'departments' => 'ti-building-community', 'positions' => 'ti-briefcase', 'roles' => 'ti-shield', 'dashboard-templates' => 'ti-layout-dashboard', 'notifications' => 'ti-bell', 'chat' => 'ti-message-circle', 'academic-years' => 'ti-calendar', 'grades' => 'ti-school', 'classes' => 'ti-door', 'sessions' => 'ti-clock', 'education-levels' => 'ti-school', 'programs' => 'ti-books', 'school-info' => 'ti-building-community', 'locations' => 'ti-map-pin', 'occupations' => 'ti-briefcase', 'academic-tracks' => 'ti-route', 'withdrawal-reasons' => 'ti-user-minus', 'student-document-types' => 'ti-file-description', 'branding' => 'ti-palette', 'database-backups' => 'ti-database', 'students.search' => 'ti-search', 'students.enrollment' => 'ti-user-plus', 'families' => 'ti-users-group', 'students.promotion' => 'ti-arrows-transfer-up', 'students.transfer' => 'ti-arrows-left-right', 'students.graduation' => 'ti-certificate', 'student-reentry' => 'ti-user-check', 'student-documents' => 'ti-files', 'student-data-transfer' => 'ti-file-import'])
<div class="row g-3 align-items-start mb-3">
@php($permissionUser = $listedUser ?? ($selectedUser ?? null))
@if (($showCampusAssignment ?? false) && $permissionUser)
    <div class="col-md-6">
        @if ($permissionUser->is_global)
            <div class="form-control bg-light">All Campuses (Global Administrator)</div>
        @else
            <div class="permission-campus-picker {{ $permissionUser->campuses->isNotEmpty() ? 'has-value' : '' }}" data-campus-picker>
                <button type="button" class="permission-campus-picker-toggle" data-campus-picker-toggle
                    aria-expanded="false">
                    <span class="permission-campus-picker-field-label">Campus Assignment</span>
                    <span data-campus-picker-label>{{ $permissionUser->campuses->pluck('campus_name_en')->join(', ') }}</span>
                    <i class="ti ti-chevron-down"></i>
                </button>
                <div class="permission-campus-picker-menu d-none" data-campus-picker-menu>
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input type="search" class="form-control" placeholder="Search campuses" data-campus-picker-search>
                    </div>
                    <div class="permission-campus-picker-results" data-campus-picker-results>
                        @foreach ($campuses ?? collect() as $campus)
                            <label class="permission-campus-picker-option" data-campus-name="{{ strtolower($campus->campus_name_en) }}">
                                <input type="checkbox" class="form-check-input me-2" name="campuses[]" value="{{ $campus->id }}"
                                    @checked($permissionUser->campuses->contains($campus->id))>
                                <span>{{ $campus->campus_name_en }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
@endif
<div class="permission-module-search {{ ($showCampusAssignment ?? false) ? 'col-md-6' : 'col-md-12' }}">
    <div class="input-icon"><span class="input-icon-addon"><i class="ti ti-search"></i></span><input type="search"
            class="form-control" data-permission-module-search placeholder="Search main modules or actions..."></div>
</div>
</div>
<div class="row g-3">
    @foreach ($permissionHierarchy as $groupKey => $group)
        @php($main = $group['permission'])
        @if ($main)
            @php($mainId = $permissionPrefix . '-main-' . $groupKey)
            @php($mainOn = $assignedPermissions->contains('id', $main->id))
            <div class="col-md-6">
                <div class="border rounded p-3" data-permission-group
                    data-permission-group-label="{{ strtolower($group['label']) }}">
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                        <span class="fw-bold fs-5 permission-main-menu"><i
                                class="ti {{ $mainMenuIcons[$groupKey] ?? 'ti-menu-2' }} me-2"></i>{{ strtoupper($group['label']) }}</span>
                        <button type="button" class="status-toggle {{ $mainOn ? 'is-active' : '' }}"
                            data-permission-toggle data-permission-level="main" data-permission-id="{{ $mainId }}"
                            data-status="{{ $mainOn ? 1 : 0 }}" aria-pressed="{{ $mainOn ? 'true' : 'false' }}">
                            <span class="status-toggle-label">{{ $mainOn ? 'ON' : 'OFF' }}</span><span
                                class="status-toggle-knob"></span>
                        </button>
                    </div>
                    @foreach ($group['modules'] as $moduleKey => $module)
                        @php($submenuId = $permissionPrefix . '-submenu-' . str_replace(['.', '-'], '_', $moduleKey))
                        @php($submenuOn = $assignedPermissions->contains('id', $module['permission']->id))
                        <div class="permission-submenu" data-permission-module
                            data-permission-module-label="{{ strtolower($module['label']) }}"
                            data-parent-permission="{{ $mainId }}">
                            <div class="d-flex justify-content-between align-items-center py-2 permission-module-header"
                                data-permission-module-toggle>
                                <span class="permission-submenu-label"><i
                                        class="ti {{ $submenuIcons[$moduleKey] ?? 'ti-point' }} me-2"></i>{{ strtoupper($module['label']) }}</span>
                                @if ($module['actions']->isNotEmpty())
                                    <i class="ti ti-chevron-up permission-module-chevron" aria-hidden="true"></i>
                                @endif
                                <button type="button" class="status-toggle {{ $submenuOn ? 'is-active' : '' }}"
                                    data-permission-toggle data-permission-level="submenu"
                                    data-permission-id="{{ $submenuId }}"
                                    data-parent-permission="{{ $mainId }}"
                                    data-status="{{ $submenuOn ? 1 : 0 }}"
                                    aria-pressed="{{ $submenuOn ? 'true' : 'false' }}">
                                    <span class="status-toggle-label">{{ $submenuOn ? 'ON' : 'OFF' }}</span><span
                                        class="status-toggle-knob"></span>
                                </button>
                            </div>
                            @foreach ($module['actions'] as $action)
                                @php($actionOn = $assignedPermissions->contains('id', $action->id))
                                <div class="d-flex justify-content-between align-items-center border-top py-2 ps-3 permission-action"
                                    data-action-parent="{{ $submenuId }}">
                                    <span class="small text-secondary">{{ $action->name }}
                                        <small>({{ $action->code }})</small></span>
                                    <button type="button" class="status-toggle {{ $actionOn ? 'is-active' : '' }}"
                                        data-permission-toggle data-permission-level="action"
                                        data-parent-permission="{{ $submenuId }}"
                                        data-status="{{ $actionOn ? 1 : 0 }}"
                                        aria-pressed="{{ $actionOn ? 'true' : 'false' }}">
                                        <input type="checkbox" class="d-none" name="permissions[]"
                                            value="{{ $action->id }}" @checked($actionOn)>
                                        <span class="status-toggle-label">{{ $actionOn ? 'ON' : 'OFF' }}</span><span
                                            class="status-toggle-knob"></span>
                                    </button>
                                </div>
                            @endforeach
                            <input type="checkbox" class="d-none" name="permissions[]"
                                value="{{ $module['permission']->id }}" @checked($submenuOn)
                                data-permission-input="{{ $submenuId }}">
                        </div>
                    @endforeach
                    @foreach ($group['actions'] as $action)
                        @php($actionOn = $assignedPermissions->contains('id', $action->id))
                        <div class="d-flex justify-content-between align-items-center border-top py-2 permission-action"
                            data-action-parent="{{ $mainId }}">
                            <span class="small text-secondary">{{ $action->name }}
                                <small>({{ $action->code }})</small></span>
                            <button type="button" class="status-toggle {{ $actionOn ? 'is-active' : '' }}"
                                data-permission-toggle data-permission-level="action"
                                data-parent-permission="{{ $mainId }}" data-status="{{ $actionOn ? 1 : 0 }}"
                                aria-pressed="{{ $actionOn ? 'true' : 'false' }}">
                                <input type="checkbox" class="d-none" name="permissions[]" value="{{ $action->id }}"
                                    @checked($actionOn)>
                                <span class="status-toggle-label">{{ $actionOn ? 'ON' : 'OFF' }}</span><span
                                    class="status-toggle-knob"></span>
                            </button>
                        </div>
                    @endforeach
                    <input type="checkbox" class="d-none" name="permissions[]" value="{{ $main->id }}"
                        @checked($mainOn) data-permission-input="{{ $mainId }}">
                </div>
            </div>
        @endif
    @endforeach
</div>

