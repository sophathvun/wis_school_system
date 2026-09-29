@php
    $rows = collect($enrollments ?? []);
    $columns = collect($selectedColumns ?? []);
    $pagination = $studentListPagination ?? null;
    $rowOffset = $pagination && ($pagination['pageSize'] ?? '') !== 'all'
        ? max(0, ((int) ($pagination['page'] ?? 1) - 1) * (int) ($pagination['perPage'] ?? 25))
        : 0;
    $familyMember = static function ($student, string $relationship) {
        return $student?->familyMembers?->first(fn ($member) => ($member->relationship_type ?? $member->pivot?->relationship_type) === $relationship);
    };
    $locationText = static function ($student, string $type, string $language = 'en'): string {
        $suffix = $language === 'kh' ? 'kh' : 'en';
        $parts = $type === 'birth'
            ? [$student?->birthVillage?->{'village_name_' . $suffix}, $student?->birthCommune?->{'commune_name_' . $suffix}, $student?->birthDistrict?->{'district_name_' . $suffix}, $student?->birthProvince?->{'province_name_' . $suffix}, $student?->birthCountry?->{'country_name_' . $suffix}]
            : [$language === 'kh' ? $student?->address_house_no_kh : $student?->address_house_no_en, $language === 'kh' ? $student?->address_street_kh : $student?->address_street_en, $student?->addressVillage?->{'village_name_' . $suffix}, $student?->addressCommune?->{'commune_name_' . $suffix}, $student?->addressDistrict?->{'district_name_' . $suffix}, $student?->addressProvince?->{'province_name_' . $suffix}, $student?->addressCountry?->{'country_name_' . $suffix}];
        return collect($parts)->map(fn ($value) => trim((string) $value))->filter()->join(', ');
    };
    $gradeClassText = static function ($row): string {
        $grade = trim((string) ($row->grade?->grade_short_name ?: $row->grade?->grade));
        $class = trim((string) ($row->schoolClass?->class_name ?? ''));
        if ($grade === '') return $class ?: '-';
        if ($class === '') return $grade;
        if (str_ends_with($grade, '-')) return $grade . $class;
        return preg_match('/^[A-Za-z]/', $grade) ? $grade . '-' . $class : $grade . $class;
    };
    $dateText = static function ($date): string {
        if (!$date) return '-';
        return ($date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date))->format('d-M-Y');
    };
    $khmerDateText = static function ($date): string {
        if (!$date) return '-';
        $date = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        $digits = static fn ($value) => strtr((string) $value, ['0'=>'០','1'=>'១','2'=>'២','3'=>'៣','4'=>'៤','5'=>'៥','6'=>'៦','7'=>'៧','8'=>'៨','9'=>'៩']);
        $months = ['Jan'=>'មករា','Feb'=>'កុម្ភៈ','Mar'=>'មីនា','Apr'=>'មេសា','May'=>'ឧសភា','Jun'=>'មិថុនា','Jul'=>'កក្កដា','Aug'=>'សីហា','Sep'=>'កញ្ញា','Oct'=>'តុលា','Nov'=>'វិច្ឆិកា','Dec'=>'ធ្នូ'];
        return $digits($date->format('d')) . '-' . ($months[$date->format('M')] ?? $date->format('M')) . '-' . $digits($date->format('Y'));
    };
    $locationPart = static function ($student, string $type, string $part, string $language = 'en'): string {
        $suffix = $language === 'kh' ? 'kh' : 'en';
        return match ($type . '_' . $part) {
            'birth_village' => (string) $student?->birthVillage?->{'village_name_' . $suffix},
            'birth_commune' => (string) $student?->birthCommune?->{'commune_name_' . $suffix},
            'birth_district' => (string) $student?->birthDistrict?->{'district_name_' . $suffix},
            'birth_province' => (string) $student?->birthProvince?->{'province_name_' . $suffix},
            'birth_country' => (string) $student?->birthCountry?->{'country_name_' . $suffix},
            'address_house_no' => (string) ($language === 'kh' ? $student?->address_house_no_kh : $student?->address_house_no_en),
            'address_street' => (string) ($language === 'kh' ? $student?->address_street_kh : $student?->address_street_en),
            'address_village' => (string) $student?->addressVillage?->{'village_name_' . $suffix},
            'address_commune' => (string) $student?->addressCommune?->{'commune_name_' . $suffix},
            'address_district' => (string) $student?->addressDistrict?->{'district_name_' . $suffix},
            'address_province' => (string) $student?->addressProvince?->{'province_name_' . $suffix},
            'address_country' => (string) $student?->addressCountry?->{'country_name_' . $suffix},
            default => '',
        };
    };
    $cellValue = static function ($row, string $key) use ($familyMember, $locationText, $locationPart, $gradeClassText, $dateText, $khmerDateText): string {
        $student = $row->student;
        $mother = $familyMember($student, 'mother');
        $father = $familyMember($student, 'father');
        $guardian = $familyMember($student, 'guardian');
        $occupation = static fn ($member) => trim((string) ($member?->occupation_en ?: $member?->occupation_kh ?: $member?->occupation));
        $value = match ($key) {
            'student_id' => $student?->student_id,
            'student_no' => $student?->student_no,
            'family_number' => $student?->family_number,
            'full_name_en' => $student?->full_name_en,
            'full_name_kh' => $student?->full_name_kh,
            'gender' => $student?->gender,
            'gender_kh' => $student?->gender_kh,
            'date_of_birth' => $dateText($student?->date_of_birth),
            'date_of_birth_kh' => $khmerDateText($student?->date_of_birth),
            'nationality' => $student?->nationalityCountry?->nationality_name_en ?: $student?->nationalityCountry?->country_name_en,
            'nationality_en' => $student?->nationalityCountry?->nationality_name_en ?: $student?->nationalityCountry?->country_name_en,
            'nationality_kh' => $student?->nationalityCountry?->nationality_name_kh ?: $student?->nationalityCountry?->country_name_kh,
            'home_phone' => $student?->home_phone,
            'email' => $student?->email,
            'birth_place_en' => $locationText($student, 'birth', 'en'),
            'birth_place_kh' => $locationText($student, 'birth', 'kh'),
            'birth_village_en' => $locationPart($student, 'birth', 'village', 'en'),
            'birth_village_kh' => $locationPart($student, 'birth', 'village', 'kh'),
            'birth_commune_en' => $locationPart($student, 'birth', 'commune', 'en'),
            'birth_commune_kh' => $locationPart($student, 'birth', 'commune', 'kh'),
            'birth_district_en' => $locationPart($student, 'birth', 'district', 'en'),
            'birth_district_kh' => $locationPart($student, 'birth', 'district', 'kh'),
            'birth_province_en' => $locationPart($student, 'birth', 'province', 'en'),
            'birth_province_kh' => $locationPart($student, 'birth', 'province', 'kh'),
            'birth_country_en' => $locationPart($student, 'birth', 'country', 'en'),
            'birth_country_kh' => $locationPart($student, 'birth', 'country', 'kh'),
            'current_address_en' => $student?->current_address_en ?: $locationText($student, 'address', 'en'),
            'current_address_kh' => $student?->current_address_kh ?: $locationText($student, 'address', 'kh'),
            'address_house_no_en' => $locationPart($student, 'address', 'house_no', 'en'),
            'address_house_no_kh' => $locationPart($student, 'address', 'house_no', 'kh'),
            'address_street_en' => $locationPart($student, 'address', 'street', 'en'),
            'address_street_kh' => $locationPart($student, 'address', 'street', 'kh'),
            'address_village_en' => $locationPart($student, 'address', 'village', 'en'),
            'address_village_kh' => $locationPart($student, 'address', 'village', 'kh'),
            'address_commune_en' => $locationPart($student, 'address', 'commune', 'en'),
            'address_commune_kh' => $locationPart($student, 'address', 'commune', 'kh'),
            'address_district_en' => $locationPart($student, 'address', 'district', 'en'),
            'address_district_kh' => $locationPart($student, 'address', 'district', 'kh'),
            'address_province_en' => $locationPart($student, 'address', 'province', 'en'),
            'address_province_kh' => $locationPart($student, 'address', 'province', 'kh'),
            'address_country_en' => $locationPart($student, 'address', 'country', 'en'),
            'address_country_kh' => $locationPart($student, 'address', 'country', 'kh'),
            'previous_school' => $student?->previous_school ?: $row->previous_school,
            'experienced_english' => $student?->experienced_english,
            'test_result' => $student?->test_result,
            'tested_by' => $student?->tested_by,
            'remarks' => $student?->remarks ?: $row->notes,
            'academic_year' => $row->academicYear?->academic_year,
            'period_type' => $row->academicYear?->period_type ? \Illuminate\Support\Str::headline($row->academicYear->period_type) : null,
            'campus' => $row->campus?->campus_name_en,
            'grade_class' => $gradeClassText($row),
            'grade' => $row->grade?->grade_short_name ?: $row->grade?->grade,
            'class' => $row->schoolClass?->class_name,
            'group' => $row->session?->session_short_name ?: $row->session?->session_name,
            'academic_track' => $row->academicTrack?->name_en,
            'student_type' => $row->student_type ? \Illuminate\Support\Str::headline($row->student_type) : null,
            'enrollment_status' => $row->enrollment_status ? \Illuminate\Support\Str::headline($row->enrollment_status) : null,
            'enrolled_on' => $dateText($row->enrolled_on),
            'ended_on' => $dateText($row->ended_on),
            'id_book_list_no' => $row->id_book_list_no,
            'mother_name_en' => $mother?->full_name_en,
            'mother_name_kh' => $mother?->full_name_kh,
            'mother_phone' => $mother?->phone,
            'mother_email' => $mother?->email,
            'mother_occupation' => $occupation($mother),
            'mother_workplace' => $mother?->workplace,
            'father_name_en' => $father?->full_name_en,
            'father_name_kh' => $father?->full_name_kh,
            'father_phone' => $father?->phone,
            'father_email' => $father?->email,
            'father_occupation' => $occupation($father),
            'father_workplace' => $father?->workplace,
            'guardian_name_en' => $guardian?->full_name_en,
            'guardian_name_kh' => $guardian?->full_name_kh,
            'guardian_phone' => $guardian?->phone,
            'guardian_email' => $guardian?->email,
            'guardian_occupation' => $occupation($guardian),
            'guardian_workplace' => $guardian?->workplace,
            default => null,
        };
        $value = trim((string) $value);
        return $value === '' ? '-' : $value;
    };
@endphp

@if(!$hasDataFilter)
    <div class="report-placeholder-preview">
        <div class="report-placeholder-preview-icon"><i class="ti ti-list-search"></i></div>
        <div class="report-placeholder-preview-title">Select Academic Year to preview students.</div>
        <div class="report-placeholder-preview-text">Campus, Grade, and selected columns can narrow the Excel list.</div>
    </div>
@elseif($columns->isEmpty())
    <div class="empty text-muted py-4 text-center">Please select at least one column.</div>
@else
    <table class="table table-vcenter table-bordered get-student-list-preview-table">
        <thead>
            <tr>
                <th>No.</th>
                @foreach($columns as $column)
                    <th>{{ $column['label'] ?? $column['key'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td>{{ $rowOffset + $index + 1 }}</td>
                    @foreach($columns as $column)
                        <td>{{ $cellValue($row, $column['key'] ?? '') }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ $columns->count() + 1 }}" class="text-center text-muted py-4">No students found.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($pagination)
        @php
            $currentPage = (int) ($pagination['page'] ?? 1);
            $lastPage = (int) ($pagination['lastPage'] ?? 1);
            $pages = $lastPage <= 5
                ? range(1, $lastPage)
                : ($currentPage <= 3
                    ? [1, 2, 3, 'ellipsis', $lastPage]
                    : ($currentPage >= $lastPage - 2
                        ? array_merge([1, 'ellipsis'], range($lastPage - 2, $lastPage))
                        : [1, 'ellipsis', $currentPage - 1, $currentPage, $currentPage + 1, 'ellipsis', $lastPage]));
        @endphp
        <div class="premium-pagination mt-3">
            <ul class="pagination premium-pagination-list m-0">
                <li class="page-item {{ $currentPage === 1 || ($pagination['pageSize'] ?? '') === 'all' ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $currentPage > 1 && ($pagination['pageSize'] ?? '') !== 'all' ? request()->fullUrlWithQuery(['preview_page' => $currentPage - 1]) : '#' }}"><i class="ti ti-chevron-left icon icon-1"></i></a>
                </li>
                @foreach($pages as $page)
                    @if($page === 'ellipsis')
                        <li class="premium-pagination-ellipsis" aria-hidden="true">&hellip;</li>
                    @else
                        <li class="page-item {{ $page === $currentPage ? 'active' : '' }} {{ ($pagination['pageSize'] ?? '') === 'all' ? 'disabled' : '' }}">
                            <a class="page-link" href="{{ ($pagination['pageSize'] ?? '') === 'all' ? '#' : request()->fullUrlWithQuery(['preview_page' => $page]) }}">{{ $page }}</a>
                        </li>
                    @endif
                @endforeach
                <li class="page-item {{ $currentPage === $lastPage || ($pagination['pageSize'] ?? '') === 'all' ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $currentPage < $lastPage && ($pagination['pageSize'] ?? '') !== 'all' ? request()->fullUrlWithQuery(['preview_page' => $currentPage + 1]) : '#' }}"><i class="ti ti-chevron-right icon icon-1"></i></a>
                </li>
            </ul>
            <p class="premium-pagination-info m-0">
                Showing <strong>{{ number_format($pagination['from'] ?? 0) }} to {{ number_format($pagination['to'] ?? 0) }}</strong>
                of <strong>{{ number_format($pagination['total'] ?? 0) }} entries</strong>
            </p>
            <div class="premium-pagination-controls">
                <label class="premium-pagination-select">
                    <select class="form-select form-select-sm" aria-label="Entries per page" data-preview-page-size>
                        @foreach(($pagination['sizes'] ?? ['all', '25', '50', '75', '100']) as $size)
                            <option value="{{ $size }}" data-url="{{ request()->fullUrlWithQuery(['preview_page_size' => $size, 'preview_page' => 1]) }}" @selected((string) ($pagination['pageSize'] ?? '25') === (string) $size)>
                                {{ $size === 'all' ? 'All / page' : $size . ' / page' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                @if(($pagination['pageSize'] ?? '') !== 'all')
                    <label class="premium-pagination-goto">
                        <span>Go to</span>
                        <input type="number" class="form-control form-control-sm" min="1" max="{{ $lastPage }}" value="{{ $currentPage }}" data-preview-goto data-preview-goto-url="{{ request()->fullUrlWithQuery(['preview_page' => '__page__']) }}">
                        <span>Page</span>
                    </label>
                @endif
            </div>
        </div>
    @endif
@endif

<style>
    .get-student-list-preview-table { min-width: 1100px; }
    .get-student-list-preview-table th { background: var(--tblr-primary); color: #fff; white-space: nowrap; }
    .get-student-list-preview-table td { white-space: nowrap; }
</style>
