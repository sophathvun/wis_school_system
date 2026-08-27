@extends('layouts.app')

@section('title', 'Customize My Dashboard')

@section('page-header')
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">My Dashboard</div>
                <h2 class="page-title">Customize My Dashboard</h2>
                <div class="text-muted mt-1">
                    Arrange only the widgets assigned to your role/template.
                </div>
            </div>
            <div class="col-auto">
                <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}">
                    <i class="ti ti-arrow-left icon"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
@endsection

@section('content')
    @vite('resources/css/pages/dashboard-templates.css')

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('dashboard.customize.save') }}" class="card dashboard-customize-card">
        @csrf
        <div class="card-header d-flex align-items-center justify-content-between">
            <div>
                <h3 class="card-title mb-1">Personal Dashboard Layout</h3>
                <div class="text-secondary small">Assigned template: {{ $template?->name ?? 'Default Dashboard' }}</div>
            </div>
            <div class="btn-list">
                <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}">Cancel</a>
                <button class="btn btn-primary" type="submit">
                    <i class="ti ti-device-floppy icon"></i> Save My Dashboard
                </button>
            </div>
        </div>
        <div class="card-body">
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
        <div class="card-footer d-flex justify-content-between">
            @if ($canResetDashboard ?? false)
                <button class="btn btn-outline-danger" type="submit" form="reset-dashboard-customization">
                    <i class="ti ti-refresh"></i> Reset to Assigned Template
                </button>
            @else
                <span></span>
            @endif
            <button class="btn btn-primary" type="submit">
                <i class="ti ti-device-floppy icon"></i> Save My Dashboard
            </button>
        </div>
    </form>

    <form id="reset-dashboard-customization" method="POST" action="{{ route('dashboard.customize.reset') }}"
        onsubmit="return confirm('Reset your dashboard to the assigned template?')">
        @csrf
        @method('DELETE')
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const escapeHtml = value => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            document.querySelectorAll('[data-dashboard-builder]').forEach(builder => {
                const widgets = JSON.parse(builder.dataset.widgets || '[]');
                let selected = JSON.parse(builder.dataset.selected || '[]');
                let sections = JSON.parse(builder.dataset.sections || '[]');
                if (!sections.length) sections = [{ id: 'section-1', title: 'Section 1', columns: '4' }];

                const palette = builder.querySelector('[data-widget-palette]');
                const widgetSearch = builder.querySelector('[data-widget-search]');
                const sectionsCanvas = builder.querySelector('[data-dashboard-sections]');
                const hiddenFields = builder.querySelector('[data-dashboard-hidden-fields]');

                const widthOptions = {
                    'col-1': '1 Column',
                    'col-2': 'Merge 2 Columns',
                    'col-3': 'Merge 3 Columns',
                    'col-4': 'Merge 4 Columns',
                    'col-5': 'Merge 5 Columns',
                    'col-6': 'Merge 6 Columns',
                    full: 'Full Width',
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
                const normalizeWidth = width => ({ small: 'col-1', medium: 'col-2', large: 'col-3' }[width] || width || 'col-2');
                const defaultWidgetWidth = widget => {
                    if (widget.type === 'filter' || widget.type === 'table' || widget.type === 'hero') return 'full';
                    if (widget.type === 'chart' || widget.type === 'profile' || ['list', 'timeline'].includes(widget.type)) return 'col-3';
                    return 'col-2';
                };

                const previewBody = widget => {
                    if (widget.type === 'filter') {
                        return `<div class="dashboard-preview-filter-grid"><span>Academic Year</span><span>Campus</span></div>`;
                    }
                    if (widget.type === 'chart') {
                        const chartType = widget.chart_type || 'standard';
                        if (chartType === 'donut') return `<div class="dashboard-preview-donut"><span></span><div><i></i><i></i><i></i></div></div>`;
                        if (chartType === 'horizontal_bar') return `<div class="dashboard-preview-horizontal-bars"><span style="width: 76%"></span><span style="width: 52%"></span><span style="width: 88%"></span><span style="width: 64%"></span></div>`;
                        if (chartType === 'grouped_bar') return `<div class="dashboard-preview-grouped-bars"><span><i style="height: 70%"></i><b style="height: 42%"></b></span><span><i style="height: 52%"></i><b style="height: 34%"></b></span><span><i style="height: 88%"></i><b style="height: 48%"></b></span><span><i style="height: 63%"></i><b style="height: 37%"></b></span></div>`;
                        if (chartType === 'compact_list') return `<div class="dashboard-preview-compact-list"><span></span><span></span><span></span></div>`;
                        return `<div class="dashboard-preview-chart ${chartType === 'vertical_bar' ? 'is-vertical' : ''}"><span style="height: 44%"></span><span style="height: 72%"></span><span style="height: 55%"></span><span style="height: 88%"></span><span style="height: 64%"></span></div>`;
                    }
                    if (widget.type === 'chat') return `<div class="dashboard-preview-chat"><span></span><span></span><span></span></div>`;
                    if (widget.type === 'table') return `<div class="dashboard-preview-table"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>`;
                    if (widget.type === 'profile') return `<div class="dashboard-preview-profile"><span></span><div><b></b><i></i></div><span></span><div><b></b><i></i></div></div>`;
                    return `<div class="dashboard-preview-number">Preview</div>`;
                };

                const renderPalette = () => {
                    const orderedGroups = ['Filters', 'Students', 'Staff', 'Chats', 'Communication', 'System'];
                    const searchTerm = (widgetSearch?.value || '').trim().toLowerCase();
                    const filteredWidgets = widgets.filter(widget => !searchTerm || [widget.name, widget.description, widget.category, widget.code].some(value => String(value || '').toLowerCase().includes(searchTerm)));
                    const grouped = filteredWidgets.reduce((groups, widget) => {
                        const category = widget.category || 'System';
                        groups[category] = groups[category] || [];
                        groups[category].push(widget);
                        return groups;
                    }, {});

                    palette.innerHTML = orderedGroups.filter(group => grouped[group]?.length).map(group => `
                        <div class="dashboard-widget-category">
                            <div class="dashboard-widget-category-title">${group}</div>
                            <div class="dashboard-widget-category-list">
                                ${grouped[group].map(widget => {
                                    const disabled = isSelected(widget.id) ? 'is-disabled' : '';
                                    return `<button type="button" class="dashboard-palette-widget ${disabled}" draggable="${disabled ? 'false' : 'true'}" data-widget-id="${widget.id}">
                                        <span class="dashboard-widget-icon bg-${widget.color}-lt"><i class="ti ${widget.icon}"></i></span>
                                        <span class="dashboard-widget-copy">
                                            <span class="dashboard-widget-name">${escapeHtml(widget.name)}</span>
                                            <span class="dashboard-widget-description">${escapeHtml(widget.description)}</span>
                                        </span>
                                        <i class="ti ti-grip-vertical"></i>
                                    </button>`;
                                }).join('')}
                            </div>
                        </div>
                    `).join('') || `<div class="dashboard-widget-empty"><i class="ti ti-search"></i><span>No widgets found.</span></div>`;
                };

                const renderSelected = () => {
                    sectionsCanvas.innerHTML = sections.map((section, index) => `
                        <div class="dashboard-section-preview" data-section-id="${section.id}">
                            <div class="dashboard-section-toolbar">
                                <input type="text" class="form-control form-control-sm" value="${escapeHtml(section.title)}" data-section-title>
                                <select class="form-select form-select-sm" data-section-columns>
                                    ${Object.entries(sectionColumnOptions).map(([value, label]) => `<option value="${value}" ${value === section.columns ? 'selected' : ''}>${label}</option>`).join('')}
                                </select>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-remove-section ${sections.length <= 1 ? 'disabled' : ''}><i class="ti ti-x"></i></button>
                            </div>
                            <div class="dashboard-section-drop-zone dashboard-section-columns-${section.columns}" data-section-drop-zone>
                                <div class="dashboard-drop-empty ${selected.some(widget => widget.section_id === section.id) ? 'd-none' : ''}">
                                    <i class="ti ti-hand-click"></i><span>Drop widgets into ${escapeHtml(section.title || `Section ${index + 1}`)}</span>
                                </div>
                            </div>
                        </div>
                    `).join('');
                    hiddenFields.innerHTML = '';

                    selected.forEach((widget, index) => {
                        widget.section_id = sectionById(widget.section_id) ? widget.section_id : sections[0].id;
                        widget.width = normalizeWidth(widget.width);
                        const dropZone = sectionsCanvas.querySelector(`[data-section-id="${widget.section_id}"] [data-section-drop-zone]`);
                        if (!dropZone) return;
                        const card = document.createElement('div');
                        card.className = `dashboard-selected-widget dashboard-preview-widget dashboard-width-${widget.width}`;
                        card.draggable = true;
                        card.dataset.widgetId = widget.id;
                        card.innerHTML = `
                            <div class="dashboard-selected-handle"><i class="ti ti-grip-vertical"></i></div>
                            <div class="dashboard-preview-card-top">
                                <span class="dashboard-widget-icon bg-${widget.color}-lt"><i class="ti ${widget.icon}"></i></span>
                                <span class="dashboard-widget-copy">
                                    <span class="dashboard-widget-name">${escapeHtml(widget.name)}</span>
                                    <span class="dashboard-widget-description">${escapeHtml(widget.description)}</span>
                                </span>
                            </div>
                            ${previewBody(widget)}
                            <div class="dashboard-preview-card-actions">
                                ${widget.type === 'chart' ? `<select class="form-select form-select-sm" data-widget-chart-type>${Object.entries(chartTypeOptions).map(([value, label]) => `<option value="${value}" ${value === (widget.chart_type || 'standard') ? 'selected' : ''}>${label}</option>`).join('')}</select>` : ''}
                                <select class="form-select form-select-sm" data-widget-width>${Object.entries(widthOptions).map(([value, label]) => `<option value="${value}" ${value === widget.width ? 'selected' : ''}>${label}</option>`).join('')}</select>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-remove-widget><i class="ti ti-x"></i></button>
                            </div>
                        `;
                        dropZone.appendChild(card);
                        hiddenFields.insertAdjacentHTML('beforeend', `
                            <input type="hidden" name="widget_ids[]" value="${widget.id}">
                            <input type="hidden" name="widget_widths[${widget.id}]" value="${widget.width}">
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
                    const fromIndex = selected.findIndex(widget => Number(widget.id) === Number(draggedId));
                    if (fromIndex < 0) return;
                    const [moved] = selected.splice(fromIndex, 1);
                    moved.section_id = sectionId || moved.section_id;
                    const toIndex = targetId ? selected.findIndex(widget => Number(widget.id) === Number(targetId)) : -1;
                    toIndex < 0 ? selected.push(moved) : selected.splice(toIndex, 0, moved);
                    renderSelected();
                };

                palette.addEventListener('dragstart', event => {
                    const widgetButton = event.target.closest('[data-widget-id]');
                    if (!widgetButton || widgetButton.classList.contains('is-disabled')) return;
                    event.dataTransfer.setData('text/plain', widgetButton.dataset.widgetId);
                    event.dataTransfer.effectAllowed = 'copy';
                });
                widgetSearch?.addEventListener('input', renderPalette);

                sectionsCanvas.addEventListener('dragstart', event => {
                    const card = event.target.closest('.dashboard-selected-widget');
                    if (!card) return;
                    event.dataTransfer.setData('text/plain', `selected:${card.dataset.widgetId}`);
                    event.dataTransfer.effectAllowed = 'move';
                });
                sectionsCanvas.addEventListener('dragover', event => {
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
                    const sectionId = dropZone.closest('[data-section-id]')?.dataset.sectionId;
                    const dragged = event.dataTransfer.getData('text/plain');
                    const targetCard = event.target.closest('.dashboard-selected-widget');
                    dragged.startsWith('selected:') ? moveWidget(dragged.replace('selected:', ''), targetCard?.dataset.widgetId, sectionId) : addWidget(dragged, sectionId);
                });

                builder.addEventListener('click', event => {
                    const paletteWidget = event.target.closest('.dashboard-palette-widget:not(.is-disabled)');
                    if (paletteWidget) addWidget(paletteWidget.dataset.widgetId);

                    const removeButton = event.target.closest('[data-remove-widget]');
                    if (removeButton) {
                        const card = removeButton.closest('.dashboard-selected-widget');
                        selected = selected.filter(widget => Number(widget.id) !== Number(card.dataset.widgetId));
                        renderSelected();
                    }

                    if (event.target.closest('[data-add-dashboard-section]')) {
                        const next = sections.length + 1;
                        sections.push({ id: `section-${Date.now()}`, title: `Section ${next}`, columns: '4' });
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
                        const id = widthSelect.closest('.dashboard-selected-widget').dataset.widgetId;
                        selected = selected.map(widget => Number(widget.id) === Number(id) ? { ...widget, width: widthSelect.value } : widget);
                        renderSelected();
                    }

                    const chartTypeSelect = event.target.closest('[data-widget-chart-type]');
                    if (chartTypeSelect) {
                        const id = chartTypeSelect.closest('.dashboard-selected-widget').dataset.widgetId;
                        selected = selected.map(widget => Number(widget.id) === Number(id) ? { ...widget, chart_type: chartTypeSelect.value } : widget);
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
@endpush
