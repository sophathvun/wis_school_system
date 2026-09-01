@extends('layouts.app')

@section('title', 'School Dashboard')





@section('content')
    @vite('resources/css/pages/dashboard.css')
    {{-- Keep the dashboard styles available when the local Vite manifest is stale. --}}
    <link rel="stylesheet" href="{{ asset('build/assets/dashboard-night.css') }}?v=6">
    <style>
        /* Fallback for deployments serving a stale Vite dashboard bundle. */
        .premium-dashboard-card.premium-dashboard-card--student-statistics {
            --premium-stat-accent: #166534;
            border-top: 4px solid var(--premium-stat-accent);
        }

        .premium-dashboard-card.premium-dashboard-card--student-statistics:hover {
            border-top-color: var(--premium-stat-accent);
        }
    </style>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @php
        $hasFilterWidget = $widgets->contains(fn ($widget) => $widget->code === 'dashboard_filters');
        $selectedAcademicYearLabel = $academicYears->firstWhere('id', $selectedAcademicYearId)?->academic_year;
    @endphp

    @if (!$hasFilterWidget)
    <form class="card premium-dashboard-filter-card mb-3" method="GET" action="{{ route('dashboard') }}">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="dashboard-filter-field">
                        <span>Period</span>
                        <select class="form-select" name="period_type" data-dashboard-auto-submit data-dashboard-searchable-select>
                            <option value="regular" @selected($selectedPeriodType === 'regular')>Regular School Year</option>
                            <option value="summer" @selected($selectedPeriodType === 'summer')>Summer School</option>
                        </select>
                    </label>
                </div>
                <div class="col-md-3">
                    <label class="dashboard-filter-field">
                        <span>Academic Year</span>
                        <select class="form-select" name="academic_year_id" data-dashboard-auto-submit data-dashboard-searchable-select>
                            @foreach ($academicYears as $academicYear)
                                <option value="{{ $academicYear->id }}" @selected((int) $selectedAcademicYearId === (int) $academicYear->id)>
                                    {{ $academicYear->academic_year }}
                                    @if ($academicYear->lifecycle_status === 'started')
                                        — Started
                                    @elseif ($academicYear->lifecycle_status === 'pending')
                                        — Pending
                                    @elseif ($academicYear->lifecycle_status === 'finished')
                                        — Finished
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>
                @if ($canSelectCampus)
                    <div class="col-md-3">
                        <label class="dashboard-filter-field">
                            <span>Campus</span>
                            <select class="form-select" name="campus_id" data-dashboard-auto-submit data-dashboard-searchable-select>
                                <option value="">{{ auth()->user()->isSuperAdmin() || auth()->user()->is_global ? 'All Campuses' : 'All Assigned Campuses' }}</option>
                                @foreach ($campuses as $campus)
                                    <option value="{{ $campus->id }}" @selected((int) $selectedCampusId === (int) $campus->id)>
                                        {{ $campus->campus_name_en }}{{ $campus->campus_name_kh ? ' / ' . $campus->campus_name_kh : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                @endif
                <div class="col-md-3">
                    <button class="btn btn-primary" type="submit">
                        <i class="ti ti-filter"></i> Apply
                    </button>
                </div>
            </div>
        </div>
    </form>
    @endif

    @php
        $widthClasses = [
            'small' => 'dashboard-widget-small',
            'medium' => 'dashboard-widget-medium',
            'large' => 'dashboard-widget-large',
            'col-1' => 'dashboard-widget-col-1',
            'col-2' => 'dashboard-widget-col-2',
            'col-3' => 'dashboard-widget-col-3',
            'col-4' => 'dashboard-widget-col-4',
            'col-5' => 'dashboard-widget-col-5',
            'col-6' => 'dashboard-widget-col-6',
            'full' => 'dashboard-widget-full',
        ];
        $fallbackWidgets = collect([
            (object) ['code' => 'total_students', 'name' => 'Total Students', 'icon' => 'ti-users', 'color' => 'primary', 'type' => 'stat_card', 'pivot' => (object) ['width' => 'small']],
            (object) ['code' => 'active_students', 'name' => 'Active Students', 'icon' => 'ti-user-check', 'color' => 'success', 'type' => 'stat_card', 'pivot' => (object) ['width' => 'small']],
            (object) ['code' => 'new_enrollments', 'name' => 'New Enrolment Today', 'icon' => 'ti-calendar-plus', 'color' => 'info', 'type' => 'stat_card', 'pivot' => (object) ['width' => 'small']],
            (object) ['code' => 'active_academic_year', 'name' => 'Active Academic Year', 'icon' => 'ti-calendar', 'color' => 'azure', 'type' => 'info_card', 'pivot' => (object) ['width' => 'small']],
        ]);
        $renderWidgets = $widgets->isNotEmpty() ? $widgets : $fallbackWidgets;
        $widgetsBySection = $renderWidgets->groupBy(function ($widget) {
            $settings = json_decode($widget->pivot->settings ?? '{}', true);

            return $settings['section_id'] ?? 'section-1';
        });
    @endphp

    <div class="dynamic-dashboard dashboard-layout-{{ $template?->layout ?? 'premium_grid' }}">
        @foreach ($dashboardSections as $section)
            @php
                $sectionWidgets = ($widgetsBySection[$section['id']] ?? collect())
                    ->reject(fn ($widget) => $widget->type === 'profile');
            @endphp
            @if ($sectionWidgets->isEmpty())
                @continue
            @endif
            <section class="premium-dashboard-section">
                <div class="premium-dashboard-grid dashboard-section-columns-{{ $section['columns'] ?? '4' }}">
                    @foreach ($sectionWidgets as $widget)
                        @php
                            $metric = $metrics[$widget->code] ?? ['value' => '-', 'subtitle' => 'No data available', 'url' => route('dashboard')];
                            $widgetSettings = json_decode($widget->pivot->settings ?? '{}', true);
                            $width = $widget->pivot->width ?? 'medium';
                            $columnClass = $widthClasses[$width] ?? $widthClasses['medium'];
                            $chartType = $widgetSettings['chart_type'] ?? 'standard';
                            $sectionColumns = (int) ($section['columns'] ?? 4);
                            $widgetColumn = max(1, (int) ($widgetSettings['column'] ?? 1));
                            $columnSpan = $sectionColumns > 1 ? max(1, (int) floor(12 / $sectionColumns)) : 12;
                            $chartRows = collect($metric['chart'] ?? []);
                            $maxValue = max(1, $chartRows->max('value') ?: 1);
                        @endphp

                        <div class="{{ $columnClass }}" @if($sectionColumns > 1 && $widgetColumn <= $sectionColumns) style="grid-column: {{ (($widgetColumn - 1) * $columnSpan) + 1 }} / span {{ $columnSpan }};" @endif>
                            @if ($widget->type === 'hero')
                                @php
                                    $heroProfile = collect($metric['profiles'] ?? [])->first();
                                @endphp
                                @if ($heroProfile)
                                    <a class="dashboard-staff-hero dashboard-staff-hero--accent" href="{{ $metric['url'] ?? route('profile') }}">
                                        <div class="dashboard-staff-hero-main">
                                            <div class="dashboard-staff-hero-photo">
                                                @if (!empty($heroProfile['photo']))
                                                    <img src="{{ $heroProfile['photo'] }}" alt="{{ $heroProfile['name'] }}">
                                                @else
                                                    <span>{{ $heroProfile['initial'] ?? 'S' }}</span>
                                                @endif
                                            </div>
                                            <div class="dashboard-staff-hero-copy">
                                                <span class="dashboard-staff-hero-eyebrow">{{ $heroProfile['department'] ?? 'Staff' }}</span>
                                                <h2>{{ $heroProfile['name'] ?? 'My Profile' }}</h2>
                                                <div class="dashboard-staff-hero-meta">
                                                    <span>{{ $heroProfile['position'] ?? 'Staff' }}</span>
                                                    <span class="dashboard-staff-hero-status"><i class="ti ti-circle-check"></i> {{ $heroProfile['status'] ?? 'Active' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="dashboard-staff-hero-clock-panel">
                                            <div class="dashboard-staff-hero-date"><i class="ti ti-calendar-event"></i> {{ now()->format('l, F j, Y') }}</div>
                                            <div class="dashboard-staff-hero-clock"><i class="ti ti-clock"></i> <span data-dashboard-hero-clock>00:00:00</span></div>
                                        </div>
                                        <div class="dashboard-staff-hero-action"><i class="ti ti-arrow-up-right"></i></div>
                                    </a>
                                @endif
                            @elseif ($widget->type === 'filter')
                                <form class="card premium-dashboard-card premium-dashboard-filter-card h-100" method="GET" action="{{ route('dashboard') }}">
                                    <div class="card-body">
                                        <div class="premium-dashboard-label mb-3">{{ $widget->name }}</div>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="dashboard-filter-field">
                                                    <span>Period</span>
                                                    <select class="form-select" name="period_type" data-dashboard-auto-submit data-dashboard-searchable-select>
                                                        <option value="regular" @selected($selectedPeriodType === 'regular')>Regular School Year</option>
                                                        <option value="summer" @selected($selectedPeriodType === 'summer')>Summer School</option>
                                                    </select>
                                                </label>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="dashboard-filter-field">
                                                    <span>Academic Year</span>
                                                    <select class="form-select" name="academic_year_id" data-dashboard-auto-submit data-dashboard-searchable-select>
                                                        @foreach ($academicYears as $academicYear)
                                                            <option value="{{ $academicYear->id }}" @selected((int) $selectedAcademicYearId === (int) $academicYear->id)>{{ $academicYear->academic_year }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                            </div>
                                            @if ($canSelectCampus)
                                                <div class="col-md-4">
                                                    <label class="dashboard-filter-field">
                                                        <span>Campus</span>
                                                        <select class="form-select" name="campus_id" data-dashboard-auto-submit data-dashboard-searchable-select>
                                                            <option value="">{{ auth()->user()->isSuperAdmin() || auth()->user()->is_global ? 'All Campuses' : 'All Assigned Campuses' }}</option>
                                                            @foreach ($campuses as $campus)
                                                                <option value="{{ $campus->id }}" @selected((int) $selectedCampusId === (int) $campus->id)>{{ $campus->campus_name_en }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </form>
                            @elseif ($widget->type === 'profile')
                                <div class="card premium-dashboard-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <div>
                                                <div class="premium-dashboard-label">{{ $widget->name }}</div>
                                                <div class="text-secondary">{{ $metric['subtitle'] ?? '' }}</div>
                                            </div>
                                            <span class="premium-dashboard-icon bg-{{ $widget->color ?: 'blue' }}-lt">
                                                <i class="ti {{ $widget->icon ?: 'ti-id-badge-2' }}"></i>
                                            </span>
                                        </div>
                                        <div class="premium-staff-profile-grid">
                                            @forelse (($metric['profiles'] ?? collect()) as $profile)
                                                <a class="premium-staff-profile-card" href="{{ $metric['url'] ?? route('profile') }}">
                                                    <div class="premium-staff-photo">
                                                        @if (!empty($profile['photo']))
                                                            <img src="{{ $profile['photo'] }}" alt="{{ $profile['name'] }}">
                                                        @else
                                                            <span>{{ $profile['initial'] ?? 'S' }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="premium-staff-profile-info">
                                                        <strong>{{ $profile['name'] }}</strong>
                                                        <span>{{ $profile['position'] }}</span>
                                                        <small>{{ $profile['department'] }}</small>
                                                    </div>
                                                    <span class="premium-staff-status">{{ $profile['status'] }}</span>
                                                </a>
                                            @empty
                                                <div class="text-secondary">Your staff profile is not available.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            @elseif ($widget->type === 'table')
                                <div class="card premium-dashboard-card {{ $widget->code === 'student_statistics_by_campus_grade' ? 'premium-dashboard-card--student-statistics' : '' }} h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <div>
                                                <div class="premium-dashboard-label">{{ $widget->name }}</div>
                                                @if ($widget->code === 'student_statistics_by_campus_grade')
                                                    <div class="text-secondary">Academic Year: {{ $selectedAcademicYearLabel ?: 'All Academic Years' }}</div>
                                                @endif
                                                <div class="text-secondary">{{ $metric['subtitle'] ?? '' }}</div>
                                            </div>
                                            <span class="premium-dashboard-icon bg-{{ $widget->color ?: 'blue' }}-lt">
                                                <i class="ti {{ $widget->icon ?: 'ti-table' }}"></i>
                                            </span>
                                        </div>
                                        <div class="premium-campus-grade-mobile d-md-none">
                                            @forelse (($metric['table']['rows'] ?? []) as $row)
                                                <article class="premium-campus-grade-mobile-card">
                                                    <div class="premium-campus-grade-mobile-header">
                                                        <div>
                                                            <div class="premium-campus-grade-mobile-campus">{{ $row['campus'] }}</div>
                                                            <div class="premium-campus-grade-mobile-summary">Total: {{ $row['total'] ?? 0 }} · Female: {{ $row['female'] ?? 0 }}</div>
                                                        </div>
                                                        <span class="premium-campus-grade-mobile-badge">{{ count($metric['table']['headers'] ?? []) }} Grades</span>
                                                    </div>
                                                    <div class="premium-campus-grade-mobile-grid">
                                                        @foreach (($metric['table']['headers'] ?? []) as $index => $header)
                                                            @php
                                                                $cell = $row['grades'][$index] ?? ['total' => 0, 'female' => 0];
                                                            @endphp
                                                            <div class="premium-campus-grade-mobile-cell">
                                                                <span>{{ $header }}</span>
                                                                <strong>{{ $cell['total'] ?? 0 }}</strong>
                                                                <small>F: {{ $cell['female'] ?? 0 }}</small>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </article>
                                            @empty
                                                <div class="text-center text-secondary py-4">No student statistics found.</div>
                                            @endforelse
                                        </div>
                                        <div class="premium-dashboard-table-wrap d-none d-md-block">
                                            <table class="premium-dashboard-table">
                                                <thead>
                                                    <tr>
                                                        <th>Campus</th>
                                                        @foreach (($metric['table']['headers'] ?? []) as $header)
                                                            <th>{{ $header }}</th>
                                                        @endforeach
                                                        <th>Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse (($metric['table']['rows'] ?? []) as $row)
                                                        <tr>
                                                            <th>{{ $row['campus'] }}</th>
                                                            @foreach (($row['grades'] ?? []) as $cell)
                                                                <td>
                                                                    @if ((int) ($cell['total'] ?? 0) !== 0)
                                                                        <span>{{ $cell['total'] }}</span>
                                                                        <small>F: {{ $cell['female'] ?? 0 }}</small>
                                                                    @endif
                                                                </td>
                                                            @endforeach
                                                            <td class="premium-dashboard-total-cell">
                                                                <span>{{ $row['total'] ?? 0 }}</span>
                                                                <small>F: {{ $row['female'] ?? 0 }}</small>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="{{ count($metric['table']['headers'] ?? []) + 2 }}" class="text-center text-secondary">No student statistics found.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                                @if (!empty($metric['table']['rows']))
                                                    <tfoot>
                                                        <tr>
                                                            <th>Grand Total</th>
                                                            @foreach (($metric['table']['grand_grades'] ?? []) as $cell)
                                                                <td>
                                                                    <span>{{ $cell['total'] ?? 0 }}</span>
                                                                    <small>F: {{ $cell['female'] ?? 0 }}</small>
                                                                </td>
                                                            @endforeach
                                                            <td class="premium-dashboard-total-cell">
                                                                <span>{{ $metric['table']['grand_total']['total'] ?? 0 }}</span>
                                                                <small>F: {{ $metric['table']['grand_total']['female'] ?? 0 }}</small>
                                                            </td>
                                                        </tr>
                                                    </tfoot>
                                                @endif
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @elseif ($widget->type === 'chart')
                                <div class="card premium-dashboard-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <div>
                                                <div class="premium-dashboard-label">{{ $widget->name }}</div>
                                                <div class="text-secondary">{{ $metric['subtitle'] ?? '' }}</div>
                                            </div>
                                            <span class="premium-dashboard-icon bg-{{ $widget->color ?: 'blue' }}-lt">
                                                <i class="ti {{ $widget->icon ?: 'ti-chart-bar' }}"></i>
                                            </span>
                                        </div>
                                        @php
                                            $displayChartRows = $chartRows->reject(fn ($bar) => strtolower((string) ($bar['label'] ?? '')) === 'total')->values();
                                            $donutRows = $displayChartRows->isNotEmpty() ? $displayChartRows : $chartRows;
                                            $donutTotal = max(1, (int) $donutRows->sum('value'));
                                            $donutCursor = 0;
                                            $donutSegments = [];
                                            foreach ($donutRows as $bar) {
                                                $slice = (($bar['value'] ?? 0) / $donutTotal) * 100;
                                                $donutSegments[] = ($bar['color'] ?? '#206bc4') . ' ' . $donutCursor . '% ' . ($donutCursor + $slice) . '%';
                                                $donutCursor += $slice;
                                            }
                                            if (!$donutSegments) {
                                                $donutSegments[] = 'var(--dashboard-chart-empty) 0% 100%';
                                            }
                                            $hasSecondaryBars = $chartRows->contains(fn ($bar) => isset($bar['secondary_value']));
                                        @endphp

                                        @if ($chartType === 'donut')
                                            <div class="premium-donut-chart-wrap">
                                                <div class="premium-donut-chart" style="background: conic-gradient({{ implode(', ', $donutSegments) }});">
                                                    <div class="premium-donut-chart-center">
                                                        <strong>{{ number_format($donutRows->sum('value')) }}</strong>
                                                        <span>Total</span>
                                                    </div>
                                                </div>
                                                <div class="premium-donut-legend">
                                                    @foreach ($donutRows as $bar)
                                                        <div class="premium-donut-legend-item">
                                                            <span style="background: {{ $bar['color'] ?? '#206bc4' }}"></span>
                                                            <b>{{ $bar['label'] }}</b>
                                                            <strong>{{ $bar['value'] }}</strong>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @elseif ($chartType === 'grouped_bar')
                                            <div class="premium-grouped-bar-chart">
                                                @foreach ($chartRows as $bar)
                                                    @php
                                                        $secondaryValue = $bar['secondary_value'] ?? null;
                                                        $groupMax = max(1, $bar['value'] ?? 0, $secondaryValue ?? 0);
                                                    @endphp
                                                    <div class="premium-grouped-bar-item">
                                                        <div class="premium-grouped-bar-bars">
                                                            <span class="premium-grouped-bar-primary" title="Total: {{ $bar['value'] }}" style="height: {{ min(100, (($bar['value'] ?? 0) / $groupMax) * 100) }}%; background: {{ $bar['color'] ?? '#206bc4' }}"></span>
                                                            @if ($secondaryValue !== null)
                                                                <span class="premium-grouped-bar-secondary" title="{{ $bar['secondary_label'] ?? 'Secondary' }}: {{ $secondaryValue }}" style="height: {{ min(100, ($secondaryValue / $groupMax) * 100) }}%; background: {{ $bar['secondary_color'] ?? '#f06595' }}"></span>
                                                            @endif
                                                        </div>
                                                        <small>{{ $bar['label'] }}</small>
                                                        <strong>{{ $bar['value'] }}@if ($secondaryValue !== null)<em>/{{ $secondaryValue }}</em>@endif</strong>
                                                    </div>
                                                @endforeach
                                            </div>
                                            @if ($hasSecondaryBars)
                                                <div class="premium-chart-legend">
                                                    <span><i style="background:#4263eb"></i>Total</span>
                                                    <span><i style="background:#f06595"></i>Female</span>
                                                </div>
                                            @endif
                                        @elseif ($chartType === 'horizontal_bar')
                                            <div class="premium-mini-chart">
                                                @foreach ($chartRows as $bar)
                                                    <div class="premium-mini-chart-row">
                                                        <span>{{ $bar['label'] }}</span>
                                                        <div class="premium-mini-chart-track">
                                                            <div class="premium-mini-chart-bar" style="width: {{ min(100, ($bar['value'] / $maxValue) * 100) }}%; background: {{ $bar['color'] }}"></div>
                                                        </div>
                                                        <strong>{{ $bar['value'] }}</strong>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @elseif ($chartType === 'compact_list')
                                            <div class="premium-compact-chart-list">
                                                @foreach ($chartRows as $bar)
                                                    <div class="premium-compact-chart-item">
                                                        <span style="background: {{ $bar['color'] }}"></span>
                                                        <strong>{{ $bar['label'] }}</strong>
                                                        <b>{{ $bar['value'] }}</b>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @elseif ($chartType === 'vertical_bar')
                                            <div class="premium-vertical-bar-chart">
                                                @foreach ($chartRows as $bar)
                                                    <div class="premium-vertical-bar-column">
                                                        <strong>{{ $bar['value'] }}</strong>
                                                        <div class="premium-vertical-bar-track">
                                                            <span style="height: {{ min(100, (($bar['value'] ?? 0) / $maxValue) * 100) }}%; background: {{ $bar['color'] ?? '#206bc4' }}"></span>
                                                        </div>
                                                        <small>{{ $bar['label'] }}</small>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="premium-standard-chart">
                                                @foreach ($chartRows as $bar)
                                                    <div class="premium-standard-chart-column">
                                                        <strong>{{ $bar['value'] }}</strong>
                                                        <div class="premium-standard-chart-track">
                                                            <span style="height: {{ min(100, ($bar['value'] / $maxValue) * 100) }}%; background: {{ $bar['color'] }}"></span>
                                                        </div>
                                                        <small>{{ $bar['label'] }}</small>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @elseif ($widget->type === 'chat')
                                <a class="card premium-dashboard-card premium-chat-card h-100" href="{{ $metric['url'] ?? route('chat.index') }}">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start justify-content-between gap-3">
                                            <div>
                                                <div class="premium-dashboard-label">{{ $widget->name }}</div>
                                                <div class="premium-dashboard-value">{{ $metric['value'] ?? 0 }}</div>
                                                <div class="text-secondary">{{ $metric['subtitle'] ?? 'Open staff chat' }}</div>
                                            </div>
                                            <span class="premium-dashboard-icon bg-{{ $widget->color ?: 'blue' }}-lt">
                                                <i class="ti {{ $widget->icon ?: 'ti-messages' }}"></i>
                                            </span>
                                        </div>
                                        <div class="premium-chat-preview mt-3">
                                            <span class="premium-chat-bubble"></span>
                                            <span class="premium-chat-bubble is-short"></span>
                                            <span class="premium-chat-bubble is-reply"></span>
                                        </div>
                                    </div>
                                </a>
                            @elseif (in_array($widget->type, ['list', 'timeline'], true))
                                @php
                                    $listAccentClass = $widget->code === 'staff_birthdays' ? 'premium-stat-card--staff_birthdays' : '';
                                @endphp
                                <div class="card premium-dashboard-card {{ $listAccentClass }} h-100">
                                    <div class="card-header border-0">
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="premium-dashboard-icon bg-{{ $widget->color ?: 'blue' }}-lt">
                                                <i class="ti {{ $widget->icon ?: 'ti-list' }}"></i>
                                            </span>
                                            <div>
                                                <h3 class="card-title mb-0">{{ $widget->name }}</h3>
                                                <div class="text-secondary small">{{ $metric['subtitle'] ?? '' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body pt-0">
                                        @forelse (($metric['items'] ?? collect()) as $item)
                                            <div class="premium-dashboard-list-item">
                                                @if (is_string($item))
                                                    <span>{{ $item }}</span>
                                                @else
                                                    <span class="fw-semibold">{{ $item->title ?? 'Notification' }}</span>
                                                    <span class="text-secondary small">{{ $item->message ?? '' }}</span>
                                                @endif
                                            </div>
                                        @empty
                                            <div class="text-secondary">No recent records found.</div>
                                        @endforelse
                                    </div>
                                </div>
                            @else
                                @php
                                    $accentStatCodes = ['total_students', 'new_students', 'withdrawn_students', 'new_enrollments', 'graduated_students'];
                                    $accentStatClass = in_array($widget->code, $accentStatCodes, true)
                                        ? 'premium-stat-card--' . $widget->code
                                        : '';
                                @endphp
                                <a class="card premium-dashboard-card premium-stat-card {{ $accentStatClass }} h-100" href="{{ $metric['url'] ?? route('dashboard') }}">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between gap-3" @if ($widget->code === 'new_enrollments') style="display: block !important; position: relative;" @endif>
                                            <div @if ($widget->code === 'new_enrollments') style="width: 100%;" @endif>
                                                <div class="d-flex align-items-center gap-2 {{ $widget->code === 'new_enrollments' ? 'flex-nowrap justify-content-between w-100' : 'flex-wrap' }}">
                                                    <div class="premium-dashboard-label">{{ $widget->name }}</div>
                                                    @if (!empty($metric['badge']))
                                                        <span class="badge bg-blue-lt">{{ $metric['badge'] }}</span>
                                                    @endif
                                                </div>
                                                <div class="premium-dashboard-value" @if (in_array($widget->code, ['total_students', 'new_students', 'withdrawn_students', 'new_enrollments'], true)) style="color: var(--premium-stat-accent) !important;" @endif>{{ $metric['value'] ?? '-' }}</div>
                                                @if (in_array($widget->code, ['total_students', 'new_students', 'withdrawn_students', 'new_enrollments', 'graduated_students'], true))
                                                    <div class="premium-dashboard-gender-summary">
                                                        <span>Male: {{ number_format((int) ($metric['male'] ?? 0)) }}</span>
                                                        <span class="premium-dashboard-gender-separator">|</span>
                                                        <span>Female: {{ number_format((int) ($metric['female'] ?? 0)) }}</span>
                                                    </div>
                                                @endif
                                                <div class="text-secondary">{{ $metric['subtitle'] ?? '' }}</div>
                                            </div>
                                            <span class="premium-dashboard-icon bg-{{ $widget->color ?: 'blue' }}-lt" @if ($widget->code === 'new_enrollments') style="position: absolute; top: 1.5rem; right: 0;" @endif>
                                                <i class="ti {{ $widget->icon ?: 'ti-chart-bar' }}"></i>
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
            @endforeach
    </div>
    @vite('resources/js/dashboard.js')
@endsection

