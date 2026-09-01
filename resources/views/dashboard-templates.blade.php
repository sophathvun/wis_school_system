@extends('layouts.app')

@section('title', 'Dashboard Templates')

@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Administrator</div>
                <h2 class="page-title">Dashboard Templates</h2>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" id="btnNewDashboardTemplate">
                    <i class="ti ti-plus icon"></i> New Template
                </button>
            </div>
        </div>
    </div>
@endsection

@section('content')
    @vite('resources/css/pages/dashboard-templates.css')

    @php
        $dashboardSortBy = $sortBy ?? request('sortBy', 'display_order');
        $dashboardSortDir = $sortDir ?? request('sortDir', 'asc');
        $sortUrl = function (string $field) use ($dashboardSortBy, $dashboardSortDir) {
            $nextDir = $dashboardSortBy === $field && $dashboardSortDir === 'asc' ? 'desc' : 'asc';

            return request()->fullUrlWithQuery([
                'sortBy' => $field,
                'sortDir' => $nextDir,
                'page' => 1,
            ]);
        };
        $sortIcon = function (string $field) use ($dashboardSortBy, $dashboardSortDir) {
            if ($dashboardSortBy !== $field) {
                return '↕';
            }

            return $dashboardSortDir === 'asc' ? '↑' : '↓';
        };
        $layoutLabels = [
            'premium_grid' => 'Premium Grid',
            'two_columns' => 'Two Columns',
            'three_columns' => 'Three Columns',
            'executive' => 'Executive',
        ];
        $widgetCategory = function ($widget) {
            if ($widget->type === 'filter') {
                return 'Filters';
            }

            if ($widget->type === 'chat' || str_contains($widget->code, 'chat')) {
                return 'Chats';
            }

            if (str_contains($widget->code, 'staff')) {
                return 'Staff';
            }

            if (str_contains($widget->code, 'student') || str_contains($widget->code, 'enrollment')) {
                return 'Students';
            }

            if (str_contains($widget->code, 'notification')) {
                return 'Communication';
            }

            return 'System';
        };
        $selectedWidgetIds = collect(old('widget_ids', $editTemplate?->widgets->pluck('id')->all() ?? []))
            ->map(fn ($id) => (int) $id)
            ->all();
        $widgetWidths = old('widget_widths', $editTemplate?->widgets->mapWithKeys(fn ($widget) => [$widget->id => $widget->pivot->width])->all() ?? []);
        $widgetSections = $editTemplate?->widgets->mapWithKeys(function ($widget) {
            $settings = json_decode($widget->pivot->settings ?: '{}', true);

            return [$widget->id => $settings['section_id'] ?? 'section-1'];
        })->all() ?? [];
        $widgetChartTypes = old('widget_chart_types', $editTemplate?->widgets->mapWithKeys(function ($widget) {
            $settings = json_decode($widget->pivot->settings ?: '{}', true);

            return [$widget->id => $settings['chart_type'] ?? 'standard'];
        })->all() ?? []);
        $selectedDepartments = collect(old('assignments.departments', $editTemplate?->assignments->where('assignment_type', 'department')->pluck('assignment_id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
        $selectedRoles = collect(old('assignments.roles', $editTemplate?->assignments->where('assignment_type', 'role')->pluck('assignment_id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
        $selectedUsers = collect(old('assignments.users', $editTemplate?->assignments->where('assignment_type', 'user')->pluck('assignment_id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
        $modalTitle = $editTemplate ? 'Edit Dashboard Template' : 'Create Dashboard Template';
        $shouldOpenModal = (bool) $editTemplate || old('template_id') !== null || old('name') !== null;
        $selectedWidgetPayload = collect($selectedWidgetIds)
            ->map(function ($widgetId) use ($widgets, $widgetWidths, $widgetSections, $widgetChartTypes, $widgetCategory) {
                $widget = $widgets->firstWhere('id', $widgetId);

                if (!$widget) {
                    return null;
                }

                return [
                    'id' => $widget->id,
                    'name' => $widget->name,
                    'code' => $widget->code,
                    'type' => $widget->type,
                    'category' => $widgetCategory($widget),
                    'icon' => $widget->icon ?: 'ti-chart-bar',
                    'color' => $widget->color ?: 'blue',
                    'description' => $widget->description ?: $widget->code,
                    'width' => $widgetWidths[$widget->id] ?? 'medium',
                    'section_id' => $widgetSections[$widget->id] ?? 'section-1',
                    'chart_type' => $widgetChartTypes[$widget->id] ?? 'standard',
                ];
            })
            ->filter()
            ->values()
            ->all();
        $availableWidgetPayload = $widgets
            ->map(fn ($widget) => [
                'id' => $widget->id,
                'name' => $widget->name,
                'code' => $widget->code,
                'type' => $widget->type,
                'category' => $widgetCategory($widget),
                'icon' => $widget->icon ?: 'ti-chart-bar',
                'color' => $widget->color ?: 'blue',
                'description' => $widget->description ?: $widget->code,
                'width' => $widgetWidths[$widget->id] ?? 'medium',
                'chart_type' => $widgetChartTypes[$widget->id] ?? 'standard',
            ])
            ->values()
            ->all();
        $dashboardSections = old('sections_payload')
            ? json_decode(old('sections_payload'), true)
            : ($editTemplate?->settings['sections'] ?? [['id' => 'section-1', 'title' => 'Section 1', 'columns' => '4']]);
        $dashboardSections = is_array($dashboardSections) && count($dashboardSections)
            ? $dashboardSections
            : [['id' => 'section-1', 'title' => 'Section 1', 'columns' => '4']];
    @endphp

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Dashboard Template Lists</h3>
        </div>
        <form method="GET">
            <div class="card-body border-bottom py-3">
                <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                <input type="hidden" name="sortBy" value="{{ $dashboardSortBy }}">
                <input type="hidden" name="sortDir" value="{{ $dashboardSortDir }}">
                <div class="row">
                    <div class="col-12 input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input class="form-control form-control-sm" name="search" value="{{ request('search') }}"
                            placeholder="Search dashboard templates">
                    </div>
                </div>
            </div>
        </form>
        <div class="dashboard-template-list">
            @forelse ($templates as $template)
                @php($cannotDelete = $template->is_default || $template->assignments_count > 0)
                <article class="dashboard-template-card">
                    <div class="dashboard-template-card-head">
                        <div class="dashboard-template-card-index">{{ $templates->firstItem() + $loop->index }}</div>
                        <div class="dashboard-template-card-title">
                            <h3>{{ $template->name }}</h3>
                            <p>{{ $template->description ?: 'No description' }}</p>
                            <code>{{ $template->code }}</code>
                        </div>
                        <span class="badge {{ $template->status ? 'bg-green-lt' : 'bg-red-lt' }}">{{ $template->status ? 'Active' : 'Inactive' }}</span>
                    </div>
                    <div class="dashboard-template-card-meta">
                        <span><i class="ti ti-layout-dashboard"></i>{{ $layoutLabels[$template->layout] ?? $template->layout }}</span>
                        <span><i class="ti ti-layout-grid"></i>{{ $template->widgets_count }} Widgets</span>
                        <span><i class="ti ti-users-group"></i>{{ $template->assignments_count }} Assignments</span>
                        <span><i class="ti ti-sort-ascending"></i>Order {{ $template->display_order }}</span>
                        @if ($template->is_default)<span class="badge bg-yellow-lt"><i class="ti ti-star"></i> Default</span>@endif
                    </div>
                    <div class="dashboard-template-card-actions">
                        <a class="btn btn-outline-primary" href="{{ route('dashboard-templates.index', request()->except('edit') + ['edit' => $template->id]) }}"><i class="ti ti-edit"></i> Edit template</a>
                        <form action="{{ route('dashboard-templates.delete', $template) }}" method="POST" data-confirm-message="Delete this dashboard template?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger" @disabled($cannotDelete) data-bs-toggle="tooltip" title="{{ $cannotDelete ? 'Cannot delete because this template is default or already assigned.' : 'Delete template' }}"><i class="ti ti-trash"></i> Delete</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="dashboard-template-empty"><i class="ti ti-layout-dashboard"></i><span>No dashboard templates found.</span></div>
            @endforelse
        </div>
        <div class="card-footer">
            <div class="dashboard-template-pagination">
                @include('partials.admin-pagination', ['paginator' => $templates])
            </div>
        </div>
    </div>

    <div class="modal fade" id="dashboardTemplateModal" tabindex="-1" aria-hidden="true"
        data-open-on-load="{{ $shouldOpenModal ? '1' : '0' }}">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable dashboard-template-modal">
            <form class="modal-content" method="POST" action="{{ route('dashboard-templates.save') }}">
                @csrf
                <input type="hidden" name="template_id" value="{{ old('template_id', $editTemplate?->id) }}">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $modalTitle }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="dashboard-builder-section dashboard-builder-dnd-section">
                        <div class="dashboard-builder-section-header">
                            <i class="ti ti-layout-dashboard"></i>
                            <span>TEMPLATE INFORMATION</span>
                        </div>
                        <div class="dashboard-builder-grid dashboard-template-info-grid">
                            <label class="dashboard-floating-field">
                                <span>Template Name <b>*</b></span>
                                <input class="form-control" name="name" required
                                    value="{{ old('name', $editTemplate?->name) }}">
                            </label>
                            <label class="dashboard-floating-field">
                                <span>Template Code</span>
                                <input class="form-control" name="code" value="{{ old('code', $editTemplate?->code) }}">
                            </label>
                            <label class="dashboard-floating-field">
                                <span>Layout <b>*</b></span>
                                <select class="form-select" name="layout" required>
                                    @foreach ($layoutLabels as $value => $label)
                                        <option value="{{ $value }}" @selected(old('layout', $editTemplate?->layout ?? 'premium_grid') === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="dashboard-floating-field">
                                <span>Display Order</span>
                                <input class="form-control" type="number" min="0" max="999" name="display_order"
                                    value="{{ old('display_order', $editTemplate?->display_order ?? 0) }}">
                            </label>
                        </div>
                        <label class="dashboard-floating-field mt-3">
                            <span>Description</span>
                            <textarea class="form-control" name="description" rows="2">{{ old('description', $editTemplate?->description) }}</textarea>
                        </label>
                        <div class="dashboard-template-options">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" value="1"
                                    @checked(old('is_default', $editTemplate?->is_default))>
                                <span class="form-check-label">Use as default dashboard</span>
                            </label>
                            <label class="dashboard-status-toggle">
                                <input type="hidden" name="status" value="0">
                                <input type="checkbox" name="status" value="1" @checked((string) old('status', $editTemplate?->status ?? 1) === '1')>
                                <span class="dashboard-status-track">
                                    <span class="dashboard-status-label">ON</span>
                                    <span class="dashboard-status-knob"></span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="dashboard-builder-section">
                        <div class="dashboard-builder-section-header">
                            <i class="ti ti-category"></i>
                            <span>DRAG & DROP WIDGET BUILDER</span>
                        </div>
                        <div class="dashboard-dnd-builder dashboard-section-builder" data-dashboard-builder
                            data-widgets='@json($availableWidgetPayload)'
                            data-selected='@json($selectedWidgetPayload)'
                            data-sections='@json($dashboardSections)'>
                            <div class="dashboard-widget-palette">
                                <div class="dashboard-builder-subtitle">Available Widgets</div>
                                <label class="dashboard-widget-search">
                                    <i class="ti ti-search"></i>
                                    <input type="search" class="form-control" placeholder="Search widgets" data-widget-search>
                                </label>
                                <div class="dashboard-widget-palette-list" data-widget-palette></div>
                            </div>
                            <div class="dashboard-layout-designer">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <div class="dashboard-builder-subtitle">Dashboard Preview</div>
                                        <div class="text-secondary small">Create sections, choose columns, then drag widgets into each section.</div>
                                    </div>
                                    <div class="btn-list">
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-add-dashboard-section>
                                            <i class="ti ti-layout-grid-add"></i> Add Section
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-clear-dashboard-widgets>
                                            <i class="ti ti-trash"></i> Clear Widgets
                                        </button>
                                    </div>
                                </div>
                                <div class="dashboard-sections-canvas" data-dashboard-sections></div>
                                <div data-dashboard-hidden-fields></div>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-builder-section">
                        <div class="dashboard-builder-section-header">
                            <i class="ti ti-users-group"></i>
                            <span>ASSIGN TEMPLATE TO</span>
                        </div>
                        <div class="dashboard-assignment-grid">
                            <div class="dashboard-assignment-card">
                                <div class="dashboard-assignment-title">Departments</div>
                                @forelse ($departments as $department)
                                    <label class="form-check">
                                        <input class="form-check-input" type="checkbox" name="assignments[departments][]"
                                            value="{{ $department->id }}" @checked(in_array($department->id, $selectedDepartments, true))>
                                        <span class="form-check-label">{{ $department->name }}</span>
                                    </label>
                                @empty
                                    <div class="text-secondary">No departments found.</div>
                                @endforelse
                            </div>
                            <div class="dashboard-assignment-card">
                                <div class="dashboard-assignment-title">Roles</div>
                                @forelse ($roles as $role)
                                    <label class="form-check">
                                        <input class="form-check-input" type="checkbox" name="assignments[roles][]"
                                            value="{{ $role->id }}" @checked(in_array($role->id, $selectedRoles, true))>
                                        <span class="form-check-label">{{ $role->name }}</span>
                                    </label>
                                @empty
                                    <div class="text-secondary">No roles found.</div>
                                @endforelse
                            </div>
                            <div class="dashboard-assignment-card">
                                <div class="dashboard-assignment-title">Users</div>
                                @forelse ($users as $user)
                                    <label class="form-check">
                                        <input class="form-check-input" type="checkbox" name="assignments[users][]"
                                            value="{{ $user->id }}" @checked(in_array($user->id, $selectedUsers, true))>
                                        <span class="form-check-label">{{ $user->name ?: $user->username }}</span>
                                    </label>
                                @empty
                                    <div class="text-secondary">No users found.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('dashboard-templates.index') }}" class="btn btn-outline-secondary">Close</a>
                    <button class="btn btn-primary" type="submit">{{ $editTemplate ? 'Update' : 'Create' }}</button>
                </div>
            </form>
        </div>
    </div>
    @vite('resources/js/dashboardCustomize.js')
    @vite('resources/js/dashboardTemplates.js')
@endsection

