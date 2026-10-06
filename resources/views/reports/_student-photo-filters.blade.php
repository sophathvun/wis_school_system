@php
    $photoFilterFields = [
        ['name' => 'academic_year_id', 'id' => 'reportAcademicYearValue', 'label' => 'Academic Year', 'default' => 'Select Academic Year', 'options' => $academicYears->map(fn ($year) => ['value' => $year->id, 'label' => $year->academic_year])],
        ['name' => 'campus_id', 'id' => 'reportCampusValue', 'label' => 'Campus', 'default' => 'Select Campus', 'options' => $campuses->map(fn ($campus) => ['value' => $campus->id, 'label' => $campus->campus_name_en])],
        ['name' => 'grade_class', 'id' => 'reportGradeClassValue', 'label' => 'Grade', 'default' => 'All Grades', 'options' => $photoGradeClassOptions],
        ['name' => 'photo_student_id', 'id' => 'reportPhotoStudentValue', 'label' => 'Student Name', 'default' => 'All Students', 'options' => $photoStudentOptions->map(fn ($student) => ['value' => $student->student_id, 'label' => ($student->full_name_en ?: $student->full_name_kh).' ('.$student->student_code.')'])],
    ];
@endphp
@foreach($photoFilterFields as $field)
    <div class="col-12 col-sm-6 col-xl-3 report-filter-field student-photo-filter-field">
        <label class="form-label">{{ $field['label'] }}</label>
        <div class="report-filter-combobox" data-target="{{ $field['id'] }}">
            <button type="button" class="report-filter-toggle"><span>{{ collect($field['options'])->first(fn ($option) => (string) $option['value'] === (string) ($filters[$field['name']] ?? ''))['label'] ?? $field['default'] }}</span><i class="ti ti-chevron-down"></i></button>
            <div class="report-filter-menu">
                <input type="search" class="form-control report-filter-search" placeholder="Search {{ $field['label'] }}" aria-label="Search {{ $field['label'] }}">
                <div class="report-filter-options">
                    <button type="button" data-value="">{{ $field['default'] }}</button>
                    @foreach($field['options'] as $option)<button type="button" data-value="{{ $option['value'] }}">{{ $option['label'] }}</button>@endforeach
                </div>
            </div>
        </div>
        <input type="hidden" name="{{ $field['name'] }}" id="{{ $field['id'] }}" value="{{ $filters[$field['name']] ?? '' }}">
    </div>
@endforeach
