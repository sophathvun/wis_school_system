@extends('layouts.app')

@section('title', 'Stu. ID Card (QR)')

@section('content')
    @vite(['resources/css/pages/student-id-card-qr.css', 'resources/js/studentIdCardQr.js'])

    @php
        $logoPath = $branding?->report_logo_1_path ?: $branding?->report_logo_2_path ?: $branding?->login_logo_path ?: $branding?->sidebar_logo_path;
        $cardUrl = $selectedStudent ? route('student-id-card-qr.index', array_filter(['academic_year_id' => $filters['academic_year_id'], 'campus_id' => $filters['campus_id'], 'grade_id' => $filters['grade_id'], 'class_id' => $filters['class_id'], 'student_id' => $selectedStudent->id, 'print_scope' => $filters['print_scope']])) : route('student-id-card-qr.index');
        $cardCanPrint = $cardPermissions[$filters['print_scope'] === 'class' ? 'print-class' : 'print-card'];
        $cardPrintUrl = route('student-id-card-qr.print', array_filter($filters));
    @endphp

    <div class="student-id-page-shell {{ $filters['print_scope'] === 'class' ? 'is-print-class' : 'is-print-student' }}" data-id-print-allowed="{{ $cardCanPrint ? 'true' : 'false' }}">
        <div class="student-id-card-workspace">
            <section class="student-id-left-panel d-print-none">
                <form class="student-id-filter-form" method="GET" action="{{ route('student-id-card-qr.index') }}">
                    <div class="student-id-filter-header">
                        <div>
                            <h3>Filter Students</h3>
                            <p>Select academic year, campus, and grade/class to find students.</p>
                        </div>
                        <span>{{ $canShowStudents ? $students->count() . ' students' : 'Select filters' }}</span>
                    </div>

                    <div class="student-id-filter-row student-id-filter-row-3">
                        <div>
                            <label class="form-label">Academic Year</label>
                            <select name="academic_year_id" class="form-select" data-student-id-searchable data-student-id-auto-submit>
                                <option value=""></option>
                                @foreach ($academicYears as $academicYear)
                                    <option value="{{ $academicYear->id }}" @selected((int) $filters['academic_year_id'] === $academicYear->id)>
                                        {{ $academicYear->academic_year }}{{ $academicYear->period_type === 'summer' ? ' - Summer' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Campus</label>
                            <select name="campus_id" class="form-select" data-student-id-searchable data-student-id-auto-submit>
                                <option value=""></option>
                                @foreach ($campuses as $campus)
                                    <option value="{{ $campus->id }}" @selected((int) $filters['campus_id'] === $campus->id)>
                                        {{ $campus->campus_name_en ?: $campus->campus_name_kh }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Grade</label>
                            <select name="grade_class" class="form-select" data-student-id-searchable data-student-id-auto-submit>
                                <option value=""></option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->grade_id }}:{{ $class->class_id }}" @selected((int) $filters['grade_id'] === (int) $class->grade_id && (int) $filters['class_id'] === (int) $class->class_id)>
                                        {{ $class->label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="student-id-filter-row student-id-filter-row-2">
                        <div>
                            <label class="form-label">Student Name</label>
                            <select name="student_id" class="form-select" data-student-id-searchable data-student-id-auto-submit @disabled(! $canShowStudents || $students->isEmpty())>
                                <option value=""></option>
                                @foreach ($students as $student)
                                    <option value="{{ $student->id }}" @selected((int) $filters['student_id'] === $student->id)>
                                        {{ $student->full_name_en ?: $student->full_name_kh }} - {{ $student->student_id ?: $student->student_no }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Print Scope</label>
                            <select name="print_scope" class="form-select" data-student-id-searchable data-student-id-auto-submit>
                                <option value="student" @selected($filters['print_scope'] === 'student') @disabled(!$cardPermissions['print-card'])>A Student</option>
                                <option value="class" @selected($filters['print_scope'] === 'class') @disabled(!$cardPermissions['print-class'])>Whole Class</option>
                            </select>
                        </div>
                    </div>

                    <div class="student-id-filter-actions">
                        <a href="{{ route('student-id-card-qr.index') }}" class="btn btn-outline-secondary">
                            <i class="ti ti-eraser me-1"></i> Clear
                        </a>
                        @if($cardPermissions['generate-qr'])
                        <button type="submit" form="student-id-generate-form" class="btn btn-primary" @disabled(! $canShowStudents || $students->isEmpty())>
                            <i class="ti ti-qrcode me-1"></i> Generate QR
                        </button>
                        @endif
                    </div>
                </form>
                @if($cardPermissions['generate-qr'])
                <form method="post" action="{{ route('student-id-card-qr.generate') }}" id="student-id-generate-form" class="d-none">
                    @csrf
                    @foreach(['academic_year_id', 'campus_id', 'grade_id', 'class_id', 'print_scope'] as $key)
                        <input type="hidden" name="{{ $key }}" value="{{ $filters[$key] ?? '' }}">
                    @endforeach
                    <input type="hidden" name="student_id" value="{{ $selectedStudent?->id }}">
                </form>
                @endif

                <div class="student-id-student-list">
                    <div class="student-id-list-header">
                        <h3>Student List</h3>
                        <span>{{ $canShowStudents ? 'Click student to preview card' : 'Select filters first' }}</span>
                    </div>

                    <div class="student-id-list-scroll">
                        @if (! $canShowStudents)
                            <div class="student-id-empty-list">
                                <i class="ti ti-filter"></i>
                                <strong>Select filters to display students</strong>
                                <span>Please select Academic Year, Campus, and Grade.</span>
                            </div>
                        @else
                            @forelse ($students as $student)
                            @php
                                $studentLabel = $student->student_id ?: $student->student_no ?: 'No ID';
                                $studentDisplayName = $student->full_name_en ?: $student->full_name_kh ?: 'Unnamed Student';
                                $studentUrl = route('student-id-card-qr.index', array_filter([
                                    'academic_year_id' => $filters['academic_year_id'],
                                    'campus_id' => $filters['campus_id'],
                                    'grade_id' => $filters['grade_id'],
                                    'class_id' => $filters['class_id'],
                                    'print_scope' => $filters['print_scope'],
                                    'student_id' => $student->id,
                                ]));
                            @endphp
                            <a href="{{ $studentUrl }}" class="student-id-list-item {{ $selectedStudent?->id === $student->id ? 'active' : '' }}">
                                <div class="student-id-list-avatar">
                                    @if ($student->photo_path)
                                        <img src="{{ asset('storage/' . $student->photo_path) }}" alt="{{ $studentDisplayName }}">
                                    @else
                                        <i class="ti ti-user"></i>
                                    @endif
                                </div>
                                <div class="student-id-list-info">
                                    <strong>{{ $studentDisplayName }}</strong>
                                    <span>{{ $studentLabel }}</span>
                                </div>
                                <i class="ti ti-chevron-right"></i>
                            </a>
                            @empty
                                <div class="student-id-empty-list">
                                    <i class="ti ti-users-off"></i>
                                    <strong>No students found</strong>
                                    <span>Please change the filter and try again.</span>
                                </div>
                            @endforelse
                        @endif
                    </div>
                </div>
            </section>

            <section class="student-id-preview-panel">
                <div class="student-id-preview-header d-print-none">
                    <div>
                        <span class="student-id-status-badge">CR80 Plastic Card</span>
                        <h3>Student ID Card Preview</h3>
                        <p>{{ $selectedStudent ? 'ID card for the selected student.' : 'Select a student to preview the ID card.' }}</p>
                    </div>
                    @if($cardCanPrint && $canShowStudents && $students->isNotEmpty())
                    <a href="{{ $cardPrintUrl }}" target="_blank" rel="noopener" class="btn btn-outline-primary" data-id-print>
                        <i class="ti ti-printer me-1"></i> {{ $filters['print_scope'] === 'class' ? 'Print Class' : 'Print Card' }}
                    </a>
                    @endif
                </div>

                @include('student-id-card._editor')
                <div class="student-id-card-preview-wrap" data-id-editor-preview>
                    @include('student-id-card._card', ['cardStudent' => $selectedStudent])
                </div>

                @if($cardPermissions['copy-link'])
                <div class="student-id-preview-tools d-print-none">
                    <div class="student-id-link-row">
                        <input type="text" class="form-control" value="{{ $cardUrl }}" readonly id="student-id-card-link">
                        <button type="button" class="btn btn-icon btn-outline-primary" title="Copy link"
                            onclick="navigator.clipboard?.writeText(document.getElementById('student-id-card-link').value)">
                            <i class="ti ti-copy"></i>
                        </button>
                    </div>
                </div>
                @endif
            </section>
        </div>

        @if ($cardPermissions['print-class'] && $canShowStudents && $students->isNotEmpty())
            <div class="student-id-print-sheet">
                @foreach ($students as $printStudent)
                    <div class="student-id-print-card-page">
                        @include('student-id-card._card', ['cardStudent' => $printStudent])
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection


