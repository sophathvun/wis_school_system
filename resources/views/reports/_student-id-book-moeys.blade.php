@php
    $idBookRows = collect($enrollments ?? [])->values();
    $khmerDigits = static fn ($value) => strtr((string) $value, ['0'=>'០','1'=>'១','2'=>'២','3'=>'៣','4'=>'៤','5'=>'៥','6'=>'៦','7'=>'៧','8'=>'៨','9'=>'៩']);
    $khmerMonths = ['Jan'=>'មករា','Feb'=>'កុម្ភៈ','Mar'=>'មីនា','Apr'=>'មេសា','May'=>'ឧសភា','Jun'=>'មិថុនា','Jul'=>'កក្កដា','Aug'=>'សីហា','Sep'=>'កញ្ញា','Oct'=>'តុលា','Nov'=>'វិច្ឆិកា','Dec'=>'ធ្នូ'];
    $khDate = static function ($date) use ($khmerDigits, $khmerMonths) {
        if (!$date) return '';
        $date = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        return $khmerDigits($date->format('d')) . '-' . ($khmerMonths[$date->format('M')] ?? $date->format('M')) . '-' . $khmerDigits($date->format('Y'));
    };
    $khGender = static function ($student) {
        $gender = mb_strtolower(trim((string) ($student?->gender_kh ?: $student?->gender)));
        return str_contains($gender, 'f') || str_contains($gender, 'ស្រី') ? 'ស' : 'ប';
    };
    $locationName = static fn ($model, $en, $kh) => trim((string) ($model?->{$kh} ?: $model?->{$en} ?: ''));
    $familyMember = static function ($student, string $relationship) {
        return $student?->familyMembers?->first(fn ($member) => ($member->relationship_type ?? $member->pivot?->relationship_type) === $relationship);
    };
    $addressLine = static function ($student) {
        return trim('ផ្ទះលេខ ' . trim((string) $student?->address_house_no_kh)
            . '     ផ្លូវ ' . trim((string) $student?->address_street_kh)
            . '     ក្រុម');
    };
    $addressRows = static function ($student) use ($locationName, $addressLine) {
        return [
            'house' => $addressLine($student),
            'commune' => 'សង្កាត់ ' . trim((string) $student?->addressCommune?->commune_name_kh),
            'district' => 'ខណ្ឌ-ស្រុក ' . trim((string) $student?->addressDistrict?->district_name_kh),
            'province' => 'ខេត្តក្រុង ' . trim((string) $student?->addressProvince?->province_name_kh),
        ];
    };
    $birthPlaceRows = static function ($student) use ($locationName) {
        return [
            'ភូមិ​ ' . $locationName($student?->birthVillage, 'village_name_en', 'village_name_kh'),
            'ឃុំ ' . $locationName($student?->birthCommune, 'commune_name_en', 'commune_name_kh'),
            'ស្រុក ' . $locationName($student?->birthDistrict, 'district_name_en', 'district_name_kh'),
            'ខេត្ត-ក្រុង ' . $locationName($student?->birthProvince, 'province_name_en', 'province_name_kh'),
            '',
        ];
    };
@endphp
<div class="id-book-report">
    <table class="id-book-table">
        <thead>
            <tr>
                <th>លេខកូដក្នុង<br>បញ្ជី</th>
                <th>អត្តលេខ<br>នៅWIS</th>
                <th>ឈ្មោះសិស្ស<br>រូបថត</th>
                <th>ភេទ</th>
                <th>ថ្នាក់</th>
                <th>ថ្ងៃ ខែ ឆ្នាំ<br>និងទីកន្លែងកំណើត</th>
                <th>ឈ្មោះ ឪពុកម្តាយ<br>មុខរបរ និង ទីលំនៅ</th>
                <th>ទីលំនៅបច្ចុប្បន្នរបស់<br>អាណាព្យាបាលសិស្ស</th>
                <th>រយះពេលសិក្សា</th>
                <th>សេចក្តីផ្សេងៗ</th>
            </tr>
        </thead>
        <tbody>
            @forelse($idBookRows as $index => $row)
                @php
                    $student = $row->student;
                    $father = $familyMember($student, 'father');
                    $mother = $familyMember($student, 'mother');
                    $birthRows = $birthPlaceRows($student);
                    $addr = $addressRows($student);
                    $startDate = $row->enrolled_on ?: $row->created_at;
                    $photoUrl = $student?->photo_path ? asset('storage/' . ltrim($student->photo_path, '/')) : null;
                    $gradeName = trim((string) ($row->grade?->grade_short_name ?: $row->grade?->grade ?? ''));
                    $className = trim((string) ($row->schoolClass?->class_name ?? ''));
                    $gradeClass = $className && $gradeName && !str_starts_with($className, $gradeName)
                        ? $gradeName . $className
                        : ($className ?: $gradeName);
                @endphp
                <tr class="id-book-record-start">
                    <td class="id-book-vertical-value" rowspan="6">{{ $row->id_book_list_no }}</td>
                    <td class="id-book-vertical-value" rowspan="6">{{ $student?->student_id }}</td>
                    <td class="id-book-photo-cell" rowspan="6">
                        @if($photoUrl)<img src="{{ $photoUrl }}" alt="">@endif
                        <div class="id-book-name">{{ $student?->full_name_kh ?: $student?->full_name_en }} <span class="id-book-status-badge">New</span></div>
                    </td>
                    <td rowspan="6">{{ $khGender($student) }}</td>
                    <td rowspan="6">{{ $gradeClass }}</td>
                    <td class="id-book-birth-cell">កើតថ្ងៃទី {{ $khDate($student?->date_of_birth) }}</td>
                    <td class="id-book-parent-cell">ឈ្មោះឪពុក {{ $father?->full_name_kh ?: $father?->full_name_en }}</td>
                    <td>{{ $addr['house'] }}</td>
                    <td class="id-book-study-cell">ចូលថ្ងៃទី {{ str_replace('-', ' ', $khDate($startDate)) }}</td>
                    <td rowspan="6"></td>
                </tr>
                <tr class="id-book-record-middle">
                    <td class="id-book-birth-cell">{{ $birthRows[0] }}</td>
                    <td class="id-book-parent-cell">មុខរបរ {{ $father?->occupation_kh ?: $father?->occupation_en ?: $father?->occupation }}</td>
                    <td>{{ $addr['commune'] }}</td>
                    <td class="id-book-study-cell">មកពីសាលា {{ $student?->previous_school }}</td>
                </tr>
                <tr class="id-book-record-middle">
                    <td class="id-book-birth-cell">{{ $birthRows[1] }}</td>
                    <td class="id-book-parent-cell">ឈ្មោះម្តាយ {{ $mother?->full_name_kh ?: $mother?->full_name_en }}</td>
                    <td>{{ $addr['district'] }}</td>
                    <td class="id-book-study-cell">ចេញថ្ងៃទី</td>
                </tr>
                <tr class="id-book-record-middle">
                    <td class="id-book-birth-cell">{{ $birthRows[2] }}</td>
                    <td class="id-book-parent-cell">មុខរបរ {{ $mother?->occupation_kh ?: $mother?->occupation_en ?: $mother?->occupation }}</td>
                    <td>{{ $addr['province'] }}</td>
                    <td class="id-book-study-cell id-book-dot-line"></td>
                </tr>
                <tr class="id-book-record-middle">
                    <td class="id-book-birth-cell">{{ $birthRows[3] }}</td>
                    <td class="id-book-parent-cell">ទីលំនៅពិតប្រាកដ {{ $student?->current_address_kh }}</td>
                    <td></td>
                    <td class="id-book-study-cell id-book-dot-line"></td>
                </tr>
                <tr class="id-book-record-end">
                    <td class="id-book-birth-cell">{{ $birthRows[4] }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @empty
                <tr><td colspan="10" class="id-book-empty">No students found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<style>
    .id-book-report{min-width:1180px;overflow:auto;color:#000;font-family:'Khmer OS Siemreap','Khmer OS Siem Reap',Arial,sans-serif}
    .id-book-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:12px}
    .id-book-table th,.id-book-table td{border:1px solid #222;padding:5px 5px 3px;vertical-align:top;line-height:1.25;height:25.5px}
    .id-book-table th{text-align:center;vertical-align:middle;font-weight:600;height:33px;white-space:normal}
    .report-preview-body .id-book-table thead th{background:var(--tblr-primary,#3b73c9);color:#fff;border-color:rgba(var(--tblr-primary-rgb,59,115,201),.75);font-weight:400}
    .id-book-table tbody tr.id-book-record-start td:not([rowspan]){border-bottom-width:0}
    .id-book-table tbody tr.id-book-record-middle td{border-top-width:0;border-bottom-width:0}
    .id-book-table tbody tr.id-book-record-end td{border-top-width:0}
    .id-book-table th:nth-child(1),.id-book-table th:nth-child(2){width:7.85%}
    .id-book-table th:nth-child(3){width:20.4%}
    .id-book-table th:nth-child(4){width:5.1%}
    .id-book-table th:nth-child(5){width:6.3%}
    .id-book-table th:nth-child(6){width:25%}
    .id-book-table th:nth-child(7){width:32.5%}
    .id-book-table th:nth-child(8){width:26.7%}
    .id-book-table th:nth-child(9){width:25.3%}
    .id-book-table th:nth-child(10){width:18.7%}
    .id-book-table td:nth-child(1),.id-book-table td:nth-child(2),.id-book-table td:nth-child(4),.id-book-table td:nth-child(5),.id-book-table td:nth-child(10){text-align:center;vertical-align:middle}
    .id-book-photo-cell{text-align:center;vertical-align:middle!important}
    .id-book-photo-cell img{width:38mm;height:42mm;max-width:100%;object-fit:cover;border-radius:8px}
    .id-book-name{text-align:center!important;vertical-align:middle!important;font-weight:600;margin-top:8px}
    .id-book-status-badge{display:inline-block;margin-left:4px;padding:1px 6px;border-radius:999px;background:var(--tblr-primary,#3b73c9);color:#fff;font-size:10px;font-weight:400;line-height:1.3}
    .id-book-print-page .id-book-status-badge,body.is-pdf-export .id-book-status-badge{display:none!important}
    .id-book-birth-cell{text-align:left!important}
    .id-book-parent-cell{text-align:left!important}
    .id-book-study-cell{text-align:left!important}
    .id-book-dot-line{position:relative}
    .id-book-dot-line::after{content:"";display:block;border-bottom:1px dotted currentColor;width:100%;margin-top:.65em}
    .id-book-vertical-value{writing-mode:vertical-rl;text-orientation:mixed;transform:rotate(180deg);white-space:nowrap}
    .id-book-empty{text-align:center!important;padding:16px!important}
    @media screen and (max-width:767px){.id-book-report{transform:scale(.34);transform-origin:top left;width:294%;margin-bottom:-60%}}
    body.dark-mode .report-preview-body .id-book-report,[data-bs-theme="dark"] .report-preview-body .id-book-report{color:#eaf2ff}
    body.dark-mode .report-preview-body .id-book-table th,body.dark-mode .report-preview-body .id-book-table td,[data-bs-theme="dark"] .report-preview-body .id-book-table th,[data-bs-theme="dark"] .report-preview-body .id-book-table td{border-color:#52627a}
    body.dark-mode .report-preview-body .id-book-table tbody td,[data-bs-theme="dark"] .report-preview-body .id-book-table tbody td{background:#182235;color:#eaf2ff}
</style>
