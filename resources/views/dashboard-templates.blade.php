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
                <div class="row justify-content-end">
                    <div class="col-auto input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input class="form-control form-control-sm" name="search" value="{{ request('search') }}"
                            placeholder="Search dashboard templates">
                    </div>
                </div>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>
                            <a class="table-sort-button" href="{{ $sortUrl('name') }}">Template {{ $sortIcon('name') }}</a>
                        </th>
                        <th>
                            <a class="table-sort-button" href="{{ $sortUrl('code') }}">Code {{ $sortIcon('code') }}</a>
                        </th>
                        <th>
                            <a class="table-sort-button" href="{{ $sortUrl('layout') }}">Layout {{ $sortIcon('layout') }}</a>
                        </th>
                        <th>Widgets</th>
                        <th>Assignments</th>
                        <th>
                            <a class="table-sort-button" href="{{ $sortUrl('display_order') }}">Order {{ $sortIcon('display_order') }}</a>
                        </th>
                        <th>Default</th>
                        <th>
                            <a class="table-sort-button" href="{{ $sortUrl('status') }}">Status {{ $sortIcon('status') }}</a>
                        </th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($templates as $template)
                        @php($cannotDelete = $template->is_default || $template->assignments_count > 0)
                        <tr>
                            <td>{{ $templates->firstItem() + $loop->index }}</td>
                            <td>
                                <div class="fw-semibold">{{ $template->name }}</div>
                                <div class="text-secondary small">{{ $template->description ?: 'No description' }}</div>
                            </td>
                            <td><code>{{ $template->code }}</code></td>
                            <td>{{ $layoutLabels[$template->layout] ?? $template->layout }}</td>
                            <td>
                                <span class="badge bg-blue-lt">{{ $template->widgets_count }} Widgets</span>
                            </td>
                            <td>
                                <span class="badge bg-purple-lt">{{ $template->assignments_count }} Assignments</span>
                            </td>
                            <td>{{ $template->display_order }}</td>
                            <td>
                                @if ($template->is_default)
                                    <span class="badge bg-yellow-lt">Default</span>
                                @else
                                    <span class="text-secondary">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $template->status ? 'bg-green-lt' : 'bg-red-lt' }}">
                                    {{ $template->status ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-list justify-content-end flex-nowrap">
                                    <a class="btn btn-sm btn-outline-primary"
                                        href="{{ route('dashboard-templates.index', request()->except('edit') + ['edit' => $template->id]) }}">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <form action="{{ route('dashboard-templates.delete', $template) }}" method="POST"
                                        class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                            @disabled($cannotDelete)
                                            data-bs-toggle="tooltip"
                                            title="{{ $cannotDelete ? 'Cannot delete because this template is default or already assigned.' : 'Delete template' }}"
                                            onclick="return confirm('Delete this dashboard template?')">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-secondary py-4">No dashboard templates found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $templates->links() }}
        </div>
    </div>

    <div class="modal fade" id="dashboardTemplateModal" tabindex="-1" aria-hidden="true">
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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => new bootstrap.Tooltip(element));

            const modalElement = document.getElementById('dashboardTemplateModal');
            const modal = new bootstrap.Modal(modalElement);

            document.getElementById('btnNewDashboardTemplate')?.addEventListener('click', () => {
                history.replaceState(null, '', "{{ route('dashboard-templates.index') }}#new");
                setTimeout(() => modal.show(), 30);
            });

            if (window.location.hash === '#new' || @json($shouldOpenModal)) {
                modal.show();
            }

            modalElement.addEventListener('hidden.bs.modal', () => {
                if (window.location.search.includes('edit=') || window.location.hash === '#new') {
                    window.location.href = "{{ route('dashboard-templates.index') }}";
                }
            });

            document.querySelectorAll('[data-dashboard-builder]').forEach(builder => {
                const widgets = JSON.parse(builder.dataset.widgets || '[]');
                let selected = JSON.parse(builder.dataset.selected || '[]');
                let sections = JSON.parse(builder.dataset.sections || '[]');
                if (!sections.length) {
                    sections = [{ id: 'section-1', title: 'Section 1', columns: '4' }];
                }
                const palette = builder.querySelector('[data-widget-palette]');
                const widgetSearch = builder.querySelector('[data-widget-search]');
                const sectionsCanvas = builder.querySelector('[data-dashboard-sections]');
                const hiddenFields = builder.querySelector('[data-dashboard-hidden-fields]');
                const modalBody = builder.closest('.modal-body');

                const widthOptions = {
                    'col-1': '1 Column',
                    'col-2': 'Merge 2 Columns',
                    'col-3': 'Merge 3 Columns',
                    'col-4': 'Merge 4 Columns',
                    'col-6': 'Merge 6 Columns',
                    full: 'Full Row',
                };
                const chartTypeOptions = {
                    standard: 'Standard Chart',
                    donut: 'Donut Chart',
                    vertical_bar: 'Vertical Bar Chart',
                    grouped_bar: 'Grouped Bar Chart',
                    horizontal_bar: 'Horizontal Bar',
                    compact_list: 'Compact List',
                };
                const sectionColumnOptions = {
                    '6': '6 Columns',
                    '4': '4 Columns',
                    '3': '3 Columns',
                    '2': '2 Columns',
                    '8-4': '2 Columns (8 / 4)',
                    '4-8': '2 Columns (4 / 8)',
                    '7-5': '2 Columns (7 / 5)',
                    '5-7': '2 Columns (5 / 7)',
                };

                const widgetById = id => widgets.find(widget => Number(widget.id) === Number(id));
                const isSelected = id => selected.some(widget => Number(widget.id) === Number(id));
                const sectionById = id => sections.find(section => section.id === id);
                const normalizeWidth = width => ({
                    small: 'col-1',
                    medium: 'col-2',
                    large: 'col-3',
                }[width] || width || 'col-2');
                const defaultWidgetWidth = widget => {
                    if (widget.type === 'filter') return 'full';
                    if (widget.type === 'table') return 'full';
                    if (widget.type === 'chart') return 'col-3';
                    if (widget.type === 'profile') return 'col-3';
                    if (['list', 'timeline'].includes(widget.type)) return 'col-3';

                    return 'col-2';
                };
                const previewBody = widget => {
                    if (widget.type === 'filter') {
                        return `
                            <div class="dashboard-preview-filter-grid">
                                <span>Academic Year</span>
                                <span>Campus</span>
                            </div>
                        `;
                    }

                    if (widget.type === 'chart') {
                        const chartType = widget.chart_type || 'standard';

                        if (chartType === 'donut') {
                            return `
                                <div class="dashboard-preview-donut">
                                    <span></span>
                                    <div><i></i><i></i><i></i></div>
                                </div>
                            `;
                        }

                        if (chartType === 'horizontal_bar') {
                            return `
                                <div class="dashboard-preview-horizontal-bars">
                                    <span style="width: 76%"></span>
                                    <span style="width: 52%"></span>
                                    <span style="width: 88%"></span>
                                    <span style="width: 64%"></span>
                                </div>
                            `;
                        }

                        if (chartType === 'grouped_bar') {
                            return `
                                <div class="dashboard-preview-grouped-bars">
                                    <span><i style="height: 70%"></i><b style="height: 42%"></b></span>
                                    <span><i style="height: 52%"></i><b style="height: 34%"></b></span>
                                    <span><i style="height: 88%"></i><b style="height: 48%"></b></span>
                                    <span><i style="height: 63%"></i><b style="height: 37%"></b></span>
                                </div>
                            `;
                        }

                        if (chartType === 'compact_list') {
                            return `
                                <div class="dashboard-preview-compact-list">
                                    <span></span><span></span><span></span>
                                </div>
                            `;
                        }

                        return `
                            <div class="dashboard-preview-chart ${chartType === 'vertical_bar' ? 'is-vertical' : ''}">
                                <span style="height: 44%"></span>
                                <span style="height: 72%"></span>
                                <span style="height: 55%"></span>
                                <span style="height: 88%"></span>
                                <span style="height: 64%"></span>
                            </div>
                        `;
                    }

                    if (widget.type === 'chat') {
                        return `
                            <div class="dashboard-preview-chat">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        `;
                    }

                    if (widget.type === 'table') {
                        return `
                            <div class="dashboard-preview-table">
                                <span></span><span></span><span></span><span></span>
                                <span></span><span></span><span></span><span></span>
                                <span></span><span></span><span></span><span></span>
                            </div>
                        `;
                    }

                    if (widget.type === 'profile') {
                        return `
                            <div class="dashboard-preview-profile">
                                <span></span>
                                <div><b></b><i></i></div>
                                <span></span>
                                <div><b></b><i></i></div>
                            </div>
                        `;
                    }

                    return `<div class="dashboard-preview-number">Preview</div>`;
                };

                const renderPalette = () => {
                    const orderedGroups = ['Filters', 'Students', 'Staff', 'Chats', 'Communication', 'System'];
                    const searchTerm = (widgetSearch?.value || '').trim().toLowerCase();
                    const filteredWidgets = widgets.filter(widget => {
                        if (!searchTerm) return true;

                        return [
                            widget.name,
                            widget.description,
                            widget.category,
                            widget.code,
                        ].some(value => String(value || '').toLowerCase().includes(searchTerm));
                    });
                    const grouped = filteredWidgets.reduce((groups, widget) => {
                        const category = widget.category || 'System';
                        groups[category] = groups[category] || [];
                        groups[category].push(widget);

                        return groups;
                    }, {});

                    const paletteHtml = orderedGroups
                        .filter(group => grouped[group]?.length)
                        .map(group => `
                            <div class="dashboard-widget-category">
                                <div class="dashboard-widget-category-title">${group}</div>
                                <div class="dashboard-widget-category-list">
                                    ${grouped[group].map(widget => {
                                        const disabled = isSelected(widget.id) ? 'is-disabled' : '';
                                        return `
                                            <button type="button" class="dashboard-palette-widget ${disabled}" draggable="${disabled ? 'false' : 'true'}" data-widget-id="${widget.id}">
                                                <span class="dashboard-widget-icon bg-${widget.color}-lt"><i class="ti ${widget.icon}"></i></span>
                                                <span>
                                                    <span class="dashboard-widget-name">${widget.name}</span>
                                                    <span class="dashboard-widget-description">${widget.description}</span>
                                                </span>
                                                <i class="ti ti-grip-vertical ms-auto"></i>
                                            </button>
                                        `;
                                    }).join('')}
                                </div>
                            </div>
                        `).join('');

                    palette.innerHTML = paletteHtml || `
                        <div class="dashboard-widget-empty">
                            <i class="ti ti-search-off"></i>
                            <span>No widgets found</span>
                        </div>
                    `;
                };

                const renderSelected = () => {
                    sectionsCanvas.innerHTML = sections.map((section, index) => `
                        <div class="dashboard-section-preview" data-section-id="${section.id}">
                            <div class="dashboard-section-toolbar">
                                <input class="form-control form-control-sm" value="${section.title}" data-section-title aria-label="Section title">
                                <select class="form-select form-select-sm" data-section-columns>
                                    ${Object.entries(sectionColumnOptions).map(([value, label]) => `<option value="${value}" ${value === section.columns ? 'selected' : ''}>${label}</option>`).join('')}
                                </select>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-remove-section ${sections.length <= 1 ? 'disabled' : ''}>
                                    <i class="ti ti-x"></i>
                                </button>
                            </div>
                            <div class="dashboard-section-drop-zone dashboard-section-columns-${section.columns}" data-section-drop-zone>
                                <div class="dashboard-drop-empty ${selected.some(widget => widget.section_id === section.id) ? 'd-none' : ''}">
                                    <i class="ti ti-hand-move"></i>
                                    <span>Drop widgets into ${section.title}</span>
                                </div>
                            </div>
                        </div>
                    `).join('');
                    hiddenFields.innerHTML = '';

                    selected.forEach((widget, index) => {
                        const section = sectionById(widget.section_id) ? widget.section_id : sections[0].id;
                        widget.section_id = section;
                        const dropZone = sectionsCanvas.querySelector(`[data-section-id="${section}"] [data-section-drop-zone]`);
                        if (!dropZone) return;
                        const card = document.createElement('div');
                        widget.width = normalizeWidth(widget.width);
                        card.className = `dashboard-selected-widget dashboard-preview-widget dashboard-width-${widget.width}`;
                        card.draggable = true;
                        card.dataset.widgetId = widget.id;
                        card.innerHTML = `
                            <div class="dashboard-selected-handle"><i class="ti ti-grip-vertical"></i></div>
                            <div class="dashboard-preview-card-top">
                                <span class="dashboard-widget-icon bg-${widget.color}-lt"><i class="ti ${widget.icon}"></i></span>
                                <span class="dashboard-widget-copy">
                                    <span class="dashboard-widget-name">${widget.name}</span>
                                    <span class="dashboard-widget-description">${widget.description}</span>
                                </span>
                            </div>
                            ${previewBody(widget)}
                            <div class="dashboard-preview-card-actions">
                                ${widget.type === 'chart' ? `
                                    <select class="form-select form-select-sm" data-widget-chart-type>
                                        ${Object.entries(chartTypeOptions).map(([value, label]) => `<option value="${value}" ${value === (widget.chart_type || 'standard') ? 'selected' : ''}>${label}</option>`).join('')}
                                    </select>
                                ` : ''}
                                <select class="form-select form-select-sm" data-widget-width>
                                    ${Object.entries(widthOptions).map(([value, label]) => `<option value="${value}" ${value === (widget.width || 'medium') ? 'selected' : ''}>${label}</option>`).join('')}
                                </select>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-remove-widget><i class="ti ti-x"></i></button>
                            </div>
                        `;
                        dropZone.appendChild(card);

                        hiddenFields.insertAdjacentHTML('beforeend', `
                            <input type="hidden" name="widget_ids[]" value="${widget.id}">
                            <input type="hidden" name="widget_widths[${widget.id}]" value="${widget.width || 'medium'}">
                            <input type="hidden" name="widget_sections[${widget.id}]" value="${widget.section_id}">
                            <input type="hidden" name="widget_chart_types[${widget.id}]" value="${widget.chart_type || 'standard'}">
                        `);
                    });
                    hiddenFields.insertAdjacentHTML('beforeend', `<input type="hidden" name="sections_payload" value='${JSON.stringify(sections).replace(/'/g, '&apos;')}'>`);

                    renderPalette();
                };

                const addWidget = (id, sectionId = null) => {
                    const widget = widgetById(id);
                    if (!widget || isSelected(id)) return;
                    selected.push({ ...widget, width: defaultWidgetWidth(widget), section_id: sectionId || sections[0].id });
                    renderSelected();
                };

                const moveWidget = (draggedId, targetId, sectionId = null) => {
                    if (!draggedId) return;
                    const fromIndex = selected.findIndex(widget => Number(widget.id) === Number(draggedId));
                    if (fromIndex < 0) return;
                    const [moved] = selected.splice(fromIndex, 1);
                    moved.section_id = sectionId || moved.section_id;
                    const toIndex = targetId ? selected.findIndex(widget => Number(widget.id) === Number(targetId)) : -1;
                    if (toIndex < 0) {
                        selected.push(moved);
                    } else {
                        selected.splice(toIndex, 0, moved);
                    }
                    renderSelected();
                };

                const autoScrollModalDuringDrag = event => {
                    if (!modalBody) return;

                    const rect = modalBody.getBoundingClientRect();
                    const edgeSize = 110;
                    const maxSpeed = 28;
                    const topDistance = event.clientY - rect.top;
                    const bottomDistance = rect.bottom - event.clientY;

                    if (topDistance < edgeSize) {
                        modalBody.scrollTop -= Math.max(8, Math.round(((edgeSize - topDistance) / edgeSize) * maxSpeed));
                    } else if (bottomDistance < edgeSize) {
                        modalBody.scrollTop += Math.max(8, Math.round(((edgeSize - bottomDistance) / edgeSize) * maxSpeed));
                    }
                };

                palette.addEventListener('dragstart', event => {
                    const widgetButton = event.target.closest('[data-widget-id]');
                    if (!widgetButton || widgetButton.classList.contains('is-disabled')) return;
                    event.dataTransfer.setData('text/plain', widgetButton.dataset.widgetId);
                    event.dataTransfer.effectAllowed = 'copy';
                });

                widgetSearch?.addEventListener('input', renderPalette);

                builder.addEventListener('dragover', autoScrollModalDuringDrag);

                sectionsCanvas.addEventListener('dragstart', event => {
                    const card = event.target.closest('.dashboard-selected-widget');
                    if (!card) return;
                    event.dataTransfer.setData('text/plain', `selected:${card.dataset.widgetId}`);
                    event.dataTransfer.effectAllowed = 'move';
                });

                sectionsCanvas.addEventListener('dragover', event => {
                    autoScrollModalDuringDrag(event);
                    const dropZone = event.target.closest('[data-section-drop-zone]');
                    if (!dropZone) return;
                    event.preventDefault();
                    dropZone.classList.add('is-drag-over');
                });

                sectionsCanvas.addEventListener('dragleave', event => {
                    const dropZone = event.target.closest('[data-section-drop-zone]');
                    if (dropZone && !dropZone.contains(event.relatedTarget)) dropZone.classList.remove('is-drag-over');
                });

                sectionsCanvas.addEventListener('drop', event => {
                    const dropZone = event.target.closest('[data-section-drop-zone]');
                    if (!dropZone) return;
                    event.preventDefault();
                    dropZone.classList.remove('is-drag-over');
                    const payload = event.dataTransfer.getData('text/plain');
                    const targetCard = event.target.closest('.dashboard-selected-widget');
                    const sectionId = dropZone.closest('[data-section-id]')?.dataset.sectionId || sections[0].id;
                    if (payload.startsWith('selected:')) {
                        moveWidget(payload.replace('selected:', ''), targetCard?.dataset.widgetId, sectionId);
                    } else {
                        addWidget(payload, sectionId);
                    }
                });

                builder.addEventListener('click', event => {
                    const paletteWidget = event.target.closest('.dashboard-palette-widget:not(.is-disabled)');
                    if (paletteWidget) {
                        addWidget(paletteWidget.dataset.widgetId);
                    }

                    const removeButton = event.target.closest('[data-remove-widget]');
                    if (removeButton) {
                        const card = removeButton.closest('.dashboard-selected-widget');
                        selected = selected.filter(widget => Number(widget.id) !== Number(card.dataset.widgetId));
                        renderSelected();
                    }

                    if (event.target.closest('[data-add-dashboard-section]')) {
                        sections.push({
                            id: `section-${Date.now()}`,
                            title: `Section ${sections.length + 1}`,
                            columns: '4',
                        });
                        renderSelected();
                    }

                    if (event.target.closest('[data-remove-section]')) {
                        const sectionId = event.target.closest('[data-section-id]')?.dataset.sectionId;
                        if (sectionId && sections.length > 1) {
                            const fallbackSection = sections.find(section => section.id !== sectionId)?.id;
                            selected = selected.map(widget => widget.section_id === sectionId ? { ...widget, section_id: fallbackSection } : widget);
                            sections = sections.filter(section => section.id !== sectionId);
                            renderSelected();
                        }
                    }

                    if (event.target.closest('[data-clear-dashboard-widgets]')) {
                        selected = [];
                        renderSelected();
                    }
                });

                builder.addEventListener('change', event => {
                    const widthSelect = event.target.closest('[data-widget-width]');
                    if (widthSelect) {
                        const card = widthSelect.closest('.dashboard-selected-widget');
                        selected = selected.map(widget => Number(widget.id) === Number(card.dataset.widgetId)
                            ? { ...widget, width: widthSelect.value }
                            : widget);
                        renderSelected();
                    }

                    const chartTypeSelect = event.target.closest('[data-widget-chart-type]');
                    if (chartTypeSelect) {
                        const card = chartTypeSelect.closest('.dashboard-selected-widget');
                        selected = selected.map(widget => Number(widget.id) === Number(card.dataset.widgetId)
                            ? { ...widget, chart_type: chartTypeSelect.value }
                            : widget);
                        renderSelected();
                    }

                    const sectionColumns = event.target.closest('[data-section-columns]');
                    if (sectionColumns) {
                        const sectionId = sectionColumns.closest('[data-section-id]').dataset.sectionId;
                        sections = sections.map(section => section.id === sectionId ? { ...section, columns: sectionColumns.value } : section);
                        renderSelected();
                    }
                });

                builder.addEventListener('input', event => {
                    const titleInput = event.target.closest('[data-section-title]');
                    if (!titleInput) return;
                    const sectionId = titleInput.closest('[data-section-id]').dataset.sectionId;
                    sections = sections.map(section => section.id === sectionId ? { ...section, title: titleInput.value || 'Section' } : section);
                    hiddenFields.querySelector('input[name="sections_payload"]')?.setAttribute('value', JSON.stringify(sections));
                });

                renderSelected();
            });
        });
    </script>
@endsection
