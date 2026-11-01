@extends('layouts.app')

@section('title', 'Student Placement Test')

@section('content')
@php($sections = \App\Support\PlacementTestPermissions::SECTIONS)
<div id="placementTestApp" class="pt-shell pt-tabs-collapsed" data-section="{{ $section }}" data-data-url="{{ route('placement-tests.data') }}">
    <div class="pt-workspace">
        <aside class="pt-tabs-column">
            <div class="pt-tabs-card">
                <header class="pt-tabs-header">
                    <div class="pt-tabs-heading"><span class="pt-tabs-brand"><i class="ti ti-clipboard-check"></i></span><div><small>ADMISSIONS</small><h3>Placement Test</h3></div></div>
                    <button type="button" class="btn btn-icon pt-tabs-toggle" aria-expanded="false" aria-controls="placementTestTabs" aria-label="Expand placement test sections" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-custom-class="pt-tabs-tooltip" data-bs-title="Expand placement test sections"><i class="ti ti-layout-sidebar-left-expand"></i></button>
                </header>
                <nav class="pt-section-nav" id="placementTestTabs" aria-label="Placement test sections">
                    @foreach($sections as $key => [$label, $icon, $action])
                        @if($permissions[$action] ?? false)
                            <a href="{{ route('placement-tests.index', ['section' => $key]) }}" class="{{ $section === $key ? 'active' : '' }}" data-tab-label="{{ $label }}" aria-label="{{ $label }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-custom-class="pt-tabs-tooltip" data-bs-title="{{ $label }}">
                                <span class="pt-tab-icon"><i class="ti {{ $icon }}"></i></span><span class="pt-tab-label">{{ $label }}</span>@if($section === $key)<span class="pt-tab-active-dot" aria-hidden="true"></span>@endif
                            </a>
                        @endif
                    @endforeach
                </nav>
                <footer class="pt-tabs-footer"><i class="ti ti-shield-check"></i><span>Secure assessment workspace</span></footer>
            </div>
        </aside>

        <main class="pt-main-column">
            @if($section === 'dashboard')
                <section class="pt-hero">
                    <div>
                        <span class="pt-eyebrow"><i class="ti ti-sparkles"></i> ADMISSIONS ASSESSMENT</span>
                        <h1>Student Placement Test</h1>
                        <p>One professional workflow for online testing, printed examinations, smart answer-sheet scanning, results, and enrollment.</p>
                    </div>
                    <div class="pt-hero-mark" aria-hidden="true"><i class="ti ti-clipboard-check"></i></div>
                </section>
            @endif

            <section class="pt-filter-card">
        <div class="pt-filter-title"><span><i class="ti ti-adjustments-horizontal"></i> Data Filters</span><button class="btn btn-icon" type="button" data-filter-toggle aria-expanded="true" aria-label="Collapse data filters"><i class="ti ti-chevron-up"></i></button></div>
        <div class="pt-filters" data-filter-body>
            <label class="pt-field"><span>Academic Year</span><select id="ptAcademicYear" class="form-select"><option value="">All academic years</option></select></label>
            <label class="pt-field"><span>Campus</span><select id="ptCampus" class="form-select"><option value="">All campuses</option></select></label>
            <label class="pt-field"><span>Target Grade</span><select id="ptGrade" class="form-select"><option value="">All grades</option></select></label>
            <label class="pt-field pt-search"><span>Search</span><i class="ti ti-search"></i><input id="ptSearch" class="form-control" type="search" placeholder="Name, code, subject…"></label>
        </div>
            </section>

            <section class="pt-metrics" aria-label="Placement test overview">
        <article><span class="pt-metric-icon blue"><i class="ti ti-users"></i></span><div><small>Applicants</small><strong data-summary="applicants">0</strong><em>Registered candidates</em></div></article>
        <article><span class="pt-metric-icon amber"><i class="ti ti-progress"></i></span><div><small>In Progress</small><strong data-summary="testing">0</strong><em>Assigned or testing</em></div></article>
        <article><span class="pt-metric-icon green"><i class="ti ti-rosette-discount-check"></i></span><div><small>Passed</small><strong data-summary="passed">0</strong><em>Verified placements</em></div></article>
        <article><span class="pt-metric-icon violet"><i class="ti ti-school"></i></span><div><small>Enrollment Ready</small><strong data-summary="enrollment_ready">0</strong><em>Waiting for registrar</em></div></article>
            </section>

            <section class="pt-content-card">
        <header class="pt-content-head">
            <div><span class="pt-content-kicker">{{ strtoupper($sections[$section][0]) }}</span><h2>{{ $sections[$section][0] }}</h2><p id="ptSectionDescription"></p></div>
            <div id="ptPrimaryActions" class="pt-actions"></div>
        </header>
        <div id="ptContent" class="pt-content"><div class="pt-loading"><span class="spinner-border"></span><p>Loading placement test workspace…</p></div></div>
            </section>
        </main>
    </div>
</div>

<div class="modal fade" id="ptEditorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content pt-modal">
        <div class="modal-header"><div><span class="pt-modal-kicker" id="ptModalKicker">PLACEMENT TEST</span><h3 class="modal-title" id="ptModalTitle">Add record</h3></div><button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <form id="ptEditorForm"><div class="modal-body" id="ptModalBody"></div><div class="modal-footer"><button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Save</button></div></form>
    </div></div>
</div>

<div class="modal fade" id="ptActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable"><div class="modal-content pt-modal">
        <div class="modal-header"><div><span class="pt-modal-kicker">ASSESSMENT WORKFLOW</span><h3 class="modal-title" id="ptActionTitle">Placement Test</h3></div><button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <form id="ptActionForm"><div class="modal-body" id="ptActionBody"></div><div class="modal-footer"><button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="ptActionSubmit"><i class="ti ti-check"></i> Confirm</button></div></form>
    </div></div>
</div>

<script type="application/json" id="ptPermissions">@json($permissions)</script>
@vite(['resources/css/pages/placement-tests.css', 'resources/js/placementTests.js'])
@endsection
