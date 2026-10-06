@php
    $fields = [
        ['label'=>'Academic Year', 'id'=>'reportAcademicYearValue', 'name'=>'academic_year_id', 'default'=>'Select Academic Year', 'options'=>$academicYears->map(fn($row)=>['value'=>$row->id,'label'=>$row->academic_year])],
        ['label'=>'Campus', 'id'=>'reportCampusValue', 'name'=>'campus_id', 'default'=>'All Campuses', 'options'=>$campuses->map(fn($row)=>['value'=>$row->id,'label'=>$row->campus_name_en])],
        ['label'=>'Grade', 'id'=>'reportGradeClassValue', 'name'=>'grade_class', 'default'=>'All G9 Classes', 'options'=>$certificateClasses],
        ['label'=>'Student Name', 'id'=>'reportCertificateStudentValue', 'name'=>'certificate_student_id', 'default'=>'All Students', 'options'=>$certificateStudents->map(fn($row)=>['value'=>$row->student_id,'label'=>$row->full_name_en.' ('.$row->student_code.')'])],
    ];
@endphp
<input type="hidden" name="preview_page_size" value="{{ $filters['preview_page_size'] ?? \App\Services\G9CertificateReport::PREVIEW_DEFAULT_SIZE }}">
@foreach($fields as $field)
<div class="col-md-3 report-filter-field">
    <label class="form-label">{{ $field['label'] }}</label>
    <div class="report-filter-combobox" data-target="{{ $field['id'] }}">
        <button type="button" class="report-filter-toggle"><span>{{ collect($field['options'])->first(fn($option)=>(string)$option['value']===(string)($filters[$field['name']]??''))['label'] ?? $field['default'] }}</span><i class="ti ti-chevron-down"></i></button>
        <div class="report-filter-menu">
            <input type="search" class="form-control report-filter-search" placeholder="Search {{ $field['label'] }}" aria-label="Search {{ $field['label'] }}">
            <div class="report-filter-options">
                <button type="button" data-value="">{{ $field['default'] }}</button>
                @foreach($field['options'] as $option)<button type="button" data-value="{{ $option['value'] }}">{{ $option['label'] }}</button>@endforeach
            </div>
        </div>
    </div>
    <input type="hidden" name="{{ $field['name'] }}" id="{{ $field['id'] }}" value="{{ $filters[$field['name']]??'' }}">
</div>
@endforeach
@include('reports._g9-certificate-settings-fields')
