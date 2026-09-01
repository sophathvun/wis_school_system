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
        data-confirm-message="Reset your dashboard to the assigned template?">
        @csrf
        @method('DELETE')
    </form>
@endsection

    @vite('resources/js/dashboardCustomize.js')
