@extends('layouts.app')

@section('title', 'Stu. ID Card (QR)')

@section('content')
    @vite(['resources/css/pages/student-id-card-qr.css', 'resources/js/studentIdCardQr.js'])

    @php
        $logoPath = $branding?->report_logo_1_path ?: $branding?->report_logo_2_path ?: $branding?->login_logo_path ?: $branding?->sidebar_logo_path;
        $studentName = $selectedStudent?->full_name_en ?: $selectedStudent?->full_name_kh ?: 'Student Name';
        $studentCode = $selectedStudent?->student_id ?: $selectedStudent?->student_no ?: 'Student ID';
        $studentPhone = $selectedStudent?->home_phone ?: 'Home Phone';
        $cardUrl = $selectedStudent ? route('student-id-card-qr.index', array_filter(['academic_year_id' => $filters['academic_year_id'], 'campus_id' => $filters['campus_id'], 'grade_id' => $filters['grade_id'], 'class_id' => $filters['class_id'], 'student_id' => $selectedStudent->id, 'print_scope' => $filters['print_scope']])) : route('student-id-card-qr.index');
    @endphp

    <div class="student-id-page-shell {{ $filters['print_scope'] === 'class' ? 'is-print-class' : 'is-print-student' }}">
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
                                <option value="student" @selected($filters['print_scope'] === 'student')>A Student</option>
                                <option value="class" @selected($filters['print_scope'] === 'class')>Whole Class</option>
                            </select>
                        </div>
                    </div>

                    <div class="student-id-filter-actions">
                        <a href="{{ route('student-id-card-qr.index') }}" class="btn btn-outline-secondary">
                            <i class="ti ti-eraser me-1"></i> Clear
                        </a>
                        <button type="submit" class="btn btn-primary" @disabled(! $canShowStudents || $students->isEmpty())>
                            <i class="ti ti-qrcode me-1"></i> Generate QR
                        </button>
                    </div>
                </form>

                <div class="student-id-student-list">
                    <div class="student-id-list-header">
                        <h3>Student List</h3>
                        <span>{{ $canShowStudents ? 'Click student to generate card' : 'Select filters first' }}</span>
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
                        <p>{{ $selectedStudent ? 'QR code is generated for the selected student.' : 'Select a student to generate QR code.' }}</p>
                    </div>
                    <button type="button" class="btn btn-outline-primary" onclick="window.print()" @disabled(! $canShowStudents || $students->isEmpty())>
                        <i class="ti ti-printer me-1"></i> {{ $filters['print_scope'] === 'class' ? 'Print Class' : 'Print Card' }}
                    </button>
                </div>

                <div class="student-id-card-preview-wrap">
                    <div class="student-id-card student-id-card-preview">
                        <div class="student-id-card-bg"></div>
                        <div class="student-id-card-logo">
                            @if ($logoPath)
                                <img src="{{ asset('storage/' . $logoPath) }}" alt="School logo">
                            @else
                                <strong>Western International School</strong>
                            @endif
                        </div>

                        <div class="student-id-card-photo-wrap">
                            @if ($selectedStudent?->photo_path)
                                <img class="student-id-card-photo" src="{{ asset('storage/' . $selectedStudent->photo_path) }}" alt="{{ $studentName }}">
                            @else
                                <div class="student-id-card-photo student-id-card-photo-empty"><i class="ti ti-user"></i></div>
                            @endif
                        </div>

                        <div class="student-id-card-info">
                            <div class="student-id-card-name">{{ $studentName }}</div>
                            <div class="student-id-card-code">{{ $studentCode }}</div>
                            <div class="student-id-card-phone"><i class="ti ti-phone"></i> {{ $studentPhone }}</div>
                        </div>

                        @if ($selectedStudent)
                            <div class="student-id-card-qr">
                                <img src="{{ route('student-id-card-qr.qr', $selectedStudent) }}?v={{ urlencode($selectedStudent->id_card_qr_code ?? 'compact-qr') }}" alt="Student QR code">
                                <span>SCAN</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="student-id-preview-tools d-print-none">
                    <div class="student-id-link-row">
                        <input type="text" class="form-control" value="{{ $cardUrl }}" readonly id="student-id-card-link">
                        <button type="button" class="btn btn-icon btn-outline-primary" title="Copy link"
                            onclick="navigator.clipboard?.writeText(document.getElementById('student-id-card-link').value)">
                            <i class="ti ti-copy"></i>
                        </button>
                    </div>
                </div>
            </section>
        </div>

        @if ($canShowStudents && $students->isNotEmpty())
            <div class="student-id-print-sheet">
                @foreach ($students as $printStudent)
                    @php
                        $printStudentName = $printStudent->full_name_en ?: $printStudent->full_name_kh ?: 'Student Name';
                        $printStudentCode = $printStudent->student_id ?: $printStudent->student_no ?: 'Student ID';
                        $printStudentPhone = $printStudent->home_phone ?: 'Home Phone';
                    @endphp
                    <div class="student-id-print-card-page">
                        <div class="student-id-card student-id-card-preview">
                            <div class="student-id-card-bg"></div>
                            <div class="student-id-card-logo">
                                @if ($logoPath)
                                    <img src="{{ asset('storage/' . $logoPath) }}" alt="School logo">
                                @else
                                    <strong>Western International School</strong>
                                @endif
                            </div>

                            <div class="student-id-card-photo-wrap">
                                @if ($printStudent->photo_path)
                                    <img class="student-id-card-photo" src="{{ asset('storage/' . $printStudent->photo_path) }}" alt="{{ $printStudentName }}">
                                @else
                                    <div class="student-id-card-photo student-id-card-photo-empty"><i class="ti ti-user"></i></div>
                                @endif
                            </div>

                            <div class="student-id-card-info">
                                <div class="student-id-card-name">{{ $printStudentName }}</div>
                                <div class="student-id-card-code">{{ $printStudentCode }}</div>
                                <div class="student-id-card-phone"><i class="ti ti-phone"></i> {{ $printStudentPhone }}</div>
                            </div>

                            <div class="student-id-card-qr">
                                <img src="{{ route('student-id-card-qr.qr', $printStudent) }}?v={{ urlencode($printStudent->id_card_qr_code ?? 'compact-qr') }}" alt="Student QR code">
                                <span>SCAN</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection


