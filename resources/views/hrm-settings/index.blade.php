@extends('layouts.app')
@section('title', 'HRM Settings')
@section('content')
@php
    $isStaffData = $activeTab === 'import-export';
    $isPosition = $activeTab === 'positions';
    $entityLabel = $settingTabs[$activeTab]['label'];
    if (!$isStaffData) {
    $records = $isPosition ? $positions : $departments;
    $editRecord = $isPosition ? $editPosition : $editDepartment;
    $idField = $isPosition ? 'position_id' : 'department_id';
    $formMarker = $isPosition ? 'hrm_position_form' : 'hrm_department_form';
    $modalId = $isPosition ? 'hrmPositionModal' : 'hrmDepartmentModal';
    if (old($formMarker) && !old($idField)) $editRecord = null;
    $status = (string) old('status', $editRecord ? (int) $editRecord->status : 1);
    $sortUrl = fn(string $field) => request()->fullUrlWithQuery([
        'tab' => $activeTab, 'sortBy' => $field,
        'sortDir' => $sortBy === $field && $sortDir === 'asc' ? 'desc' : 'asc', 'page' => 1,
    ]);
    } else { $editRecord=null;$formMarker='staff_csv_import'; }
@endphp
<div class="hrm-settings hrm-settings-tabs-collapsed" data-hrm-settings>
    <aside class="card hrm-settings-navigation">
        <div class="card-header hrm-settings-header hrm-settings-tabs-header">
            <h3 class="card-title"><i class="ti ti-settings me-2" aria-hidden="true"></i>HRM Settings</h3>
            <button type="button" class="btn btn-icon btn-outline-light" data-hrm-tabs-toggle aria-expanded="false" aria-controls="hrmSettingsTabs" aria-label="Expand HRM settings tabs"><i class="ti ti-layout-sidebar-left-expand" aria-hidden="true"></i></button>
        </div>
        <nav class="hrm-settings-tabs" id="hrmSettingsTabs" aria-label="HRM settings tabs">
            @foreach($settingTabs as $key => $tab)
                <a href="{{ route('hrm.settings.index', ['tab' => $key]) }}" @class(['hrm-settings-tab', 'active' => $activeTab === $key]) aria-label="{{ $tab['label'] }}" data-hrm-tab-label="{{ $tab['label'] }}" @if($activeTab === $key) aria-current="page" @endif>
                    <i class="ti {{ $tab['icon'] }}" aria-hidden="true"></i><span class="hrm-settings-tab-label">{{ $tab['label'] }}</span><i class="ti ti-chevron-right ms-auto" aria-hidden="true"></i>
                </a>
            @endforeach
        </nav>
    </aside>
    <main class="hrm-settings-content" aria-label="{{ $entityLabel }} settings">
        @if(session('success'))
            <div hidden data-hrm-settings-success="{{ session('success') }}"></div>
            <noscript><div class="alert alert-success" role="alert">{{ session('success') }}</div></noscript>
        @endif
        @if($errors->any() && !old($formMarker))
            <div class="alert alert-danger" role="alert"><strong>Please check the form.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @if($isStaffData)
            @include('hrm-settings._staff-data')
        @else
        <section class="card hrm-settings-list">
            <div class="card-header hrm-settings-header hrm-settings-list-header">
                <h3 class="card-title">{{ $entityLabel }} List <span class="badge bg-white-lt ms-2">{{ $records->total() }}</span></h3>
                @include('hrm-settings._search')
                <div class="d-flex gap-2 flex-wrap ms-auto">
                    @if($canPrintSetting)<a class="btn btn-sm btn-outline-light" href="{{ route($activeTab.'.print') }}" target="_blank" rel="noopener"><i class="ti ti-printer me-1" aria-hidden="true"></i>Print</a>@endif
                    @if($canExportSetting)<a class="btn btn-sm btn-outline-light" href="{{ route($activeTab.'.excel') }}"><i class="ti ti-file-spreadsheet me-1" aria-hidden="true"></i>Excel</a>@endif
                    @if($canCreateSetting)<button type="button" class="btn btn-primary" data-hrm-setting-new data-bs-toggle="modal" data-bs-target="#{{ $modalId }}"><i class="ti ti-plus me-1" aria-hidden="true"></i>New {{ $entityLabel }}</button>@endif
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>No.</th>
                        @foreach(['name' => $entityLabel] + ($isPosition ? ['department' => 'Department'] : []) + ['code' => 'Code', 'status' => 'Status'] as $field => $label)
                            <th><a class="hrm-settings-sort" href="{{ $sortUrl($field) }}">{{ $label }} <span aria-hidden="true">{{ $sortBy === $field ? ($sortDir === 'asc' ? '↑' : '↓') : '↕' }}</span></a></th>
                        @endforeach
                        @unless($isPosition)<th>Parent Department</th>@endunless
                        <th class="text-end">Actions</th>
                    </tr></thead>
                    <tbody>
                        @forelse($records as $record)
                            @php($linkedCount = $isPosition ? $record->users_count : $record->users_count + $record->roles_count + $record->permissions_count + $record->children_count + $record->positions_count + $record->staff_count)
                            <tr><td>{{ $records->firstItem() + $loop->index }}</td><td class="fw-medium">{{ $isPosition ? $record->name : $record->hierarchy_name }}</td>
                                @if($isPosition)<td>{{ $record->department?->hierarchy_name ?? '—' }}</td>@endif
                                <td>{{ $record->code }}</td><td><span class="badge {{ $record->status ? 'bg-success-lt' : 'bg-secondary-lt' }}">{{ $record->status ? 'Active' : 'Inactive' }}</span></td>
                                @unless($isPosition)<td>{{ $record->parent?->hierarchy_name ?? 'Main Department' }}</td>@endunless
                                <td class="text-end"><div class="hrm-settings-actions">
                                    @if($canUpdateSetting)<a class="btn btn-sm btn-outline-primary" data-action-normalized="true" href="{{ route('hrm.settings.index', ['tab' => $activeTab, 'edit' => $record->id]) }}" aria-label="Edit {{ $record->name }}"><i class="ti ti-edit me-1" aria-hidden="true"></i>Edit</a>@endif
                                    @if($canDeleteSetting)<form method="POST" action="{{ route('hrm.settings.'.$activeTab.'.delete', $record) }}" data-hrm-settings-delete="{{ $record->name }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-action-normalized="true" @disabled($linkedCount > 0) title="{{ $linkedCount > 0 ? 'Assigned records cannot be deleted.' : 'Delete '.$entityLabel }}" aria-label="Delete {{ $record->name }}"><i class="ti ti-trash me-1" aria-hidden="true"></i>Delete</button>
                                    </form>@endif
                                </div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-4">No {{ strtolower($entityLabel) }}s found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">@include('partials.admin-pagination', ['paginator' => $records])</div>
        </section>
        @endif
    </main>
</div>
    @if(!$isStaffData && ($canCreateSetting || $canUpdateSetting))
    <div class="modal modal-blur fade hrm-setting-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Title" aria-hidden="true" data-entity-label="{{ $entityLabel }}" data-id-field="{{ $idField }}" data-open-on-load="{{ $editRecord || old($formMarker) ? '1' : '0' }}">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content hrm-settings-modal-content">
            <div class="modal-header hrm-settings-header"><h3 class="modal-title" id="{{ $modalId }}Title">{{ $editRecord ? 'Edit '.$entityLabel : 'Create New '.$entityLabel }}</h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
            @include('hrm-settings._form')
        </div></div>
    </div>
    @endif
@endsection
@push('styles') @vite('resources/css/pages/hrm-settings.css') @endpush
@push('scripts') @vite('resources/js/hrmSettings.js') @endpush
