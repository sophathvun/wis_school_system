@php
    $templateLevel = ($filters['transcript_level'] ?? 'primary') === 'secondary' ? 'secondary' : 'primary';
    $templateMode = ($filters['print_mode'] ?? request('print_mode', 'content')) === 'cover' ? 'cover' : 'content';
    $templateRows = collect($enrollments ?? [])->values();
    $templateBase = storage_path('app/imports/' . ($templateLevel === 'secondary' ? 'SECONDARY TRANSCRIPT BOOK' : 'PRIMARY TRANSCRIPT BOOK'));
    $templateFiles = $templateLevel === 'secondary'
        ? [
            'cover' => ['Secondary Transcript Book Page_1.jpg', 'Secondary Transcript Book Page_2.jpg'],
            'content_intro' => 'Secondary Transcript Book Page_3.jpg',
            'content_repeat' => 'Secondary Transcript Book Page_4.jpg',
            'repeat_count' => 10,
        ]
        : [
            'cover' => ['Primary Transcript Book Page1 (1).jpg', 'Primary Transcript Book Page2.jpg'],
            'content_intro' => 'Primary Transcript Book Page3.jpg',
            'content_repeat' => 'Primary Transcript Book Page4.jpg',
            'repeat_count' => 8,
        ];
    $templateImage = static function (string $file) use ($templateBase): string {
        $path = $templateBase . DIRECTORY_SEPARATOR . $file;
        return is_file($path) ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($path)) : '';
    };
    $khmerDate = static function ($date): string {
        if (!$date) return '';
        $date = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        $digits = static fn ($value) => strtr((string) $value, ['0'=>'០','1'=>'១','2'=>'២','3'=>'៣','4'=>'៤','5'=>'៥','6'=>'៦','7'=>'៧','8'=>'៨','9'=>'៩']);
        $months = ['Jan'=>'មករា','Feb'=>'កុម្ភៈ','Mar'=>'មីនា','Apr'=>'មេសា','May'=>'ឧសភា','Jun'=>'មិថុនា','Jul'=>'កក្កដា','Aug'=>'សីហា','Sep'=>'កញ្ញា','Oct'=>'តុលា','Nov'=>'វិច្ឆិកា','Dec'=>'ធ្នូ'];
        return $digits($date->format('d')) . ' ' . ($months[$date->format('M')] ?? $date->format('M')) . ' ' . $digits($date->format('Y'));
    };
    $khmerDateParts = static function ($date): array {
        if (!$date) return ['day' => '', 'month' => '', 'year' => ''];
        $date = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        $digits = static fn ($value) => strtr((string) $value, ['0'=>'០','1'=>'១','2'=>'២','3'=>'៣','4'=>'៤','5'=>'៥','6'=>'៦','7'=>'៧','8'=>'៨','9'=>'៩']);
        $months = ['Jan'=>'មករា','Feb'=>'កុម្ភៈ','Mar'=>'មីនា','Apr'=>'មេសា','May'=>'ឧសភា','Jun'=>'មិថុនា','Jul'=>'កក្កដា','Aug'=>'សីហា','Sep'=>'កញ្ញា','Oct'=>'តុលា','Nov'=>'វិច្ឆិកា','Dec'=>'ធ្នូ'];
        return [
            'day' => $digits((int) $date->format('d')),
            'month' => $months[$date->format('M')] ?? $date->format('M'),
            'year' => $digits($date->format('Y')),
        ];
    };
    $birthPlace = static function ($student): string {
        $province = trim((string) $student?->birthProvince?->province_name_kh);
        $isPhnomPenh = str_contains($province, 'ភ្នំពេញ');
        $labeledParts = [
            ['ភូមិ', trim((string) $student?->birthVillage?->village_name_kh)],
            [$isPhnomPenh ? 'សង្កាត់' : 'ឃុំ', trim((string) $student?->birthCommune?->commune_name_kh)],
            [$isPhnomPenh ? 'ខណ្ឌ' : 'ស្រុក', trim((string) $student?->birthDistrict?->district_name_kh)],
        ];
        $parts = collect($labeledParts)
            ->filter(fn ($part) => $part[1] !== '')
            ->map(fn ($part) => $part[0] . $part[1]);
        if ($province !== '') {
            $parts->push($isPhnomPenh ? 'រាជធានីភ្នំពេញ' : (str_starts_with($province, 'ខេត្ត') ? $province : 'ខេត្ត' . $province));
        }
        return $parts->join(' ');
    };
    $birthProvinceOnly = static function ($student): string {
        $province = trim((string) $student?->birthProvince?->province_name_kh);
        if ($province === '') return '';
        if (str_contains($province, 'ភ្នំពេញ')) return 'រាជធានីភ្នំពេញ';
        return str_starts_with($province, 'ខេត្ត') ? $province : 'ខេត្ត' . $province;
    };
    $currentAddress = static function ($student): string {
        $address = trim((string) $student?->current_address_kh);
        if ($address !== '') return $address;
        return collect([
            $student?->address_house_no_kh,
            $student?->address_street_kh,
            $student?->addressVillage?->village_name_kh,
            $student?->addressCommune?->commune_name_kh,
            $student?->addressDistrict?->district_name_kh,
            $student?->addressProvince?->province_name_kh,
        ])->map(fn ($value) => trim((string) $value))->filter()->join(' ');
    };
    $familyMember = static function ($student, string $relationship) {
        return $student?->familyMembers?->first(fn ($member) => ($member->relationship_type ?? $member->pivot?->relationship_type) === $relationship);
    };
    $campusAddressPart = static function ($campus, string $part): string {
        return match ($part) {
            'commune' => trim((string) $campus?->addressCommune?->commune_name_kh),
            'district' => trim((string) $campus?->addressDistrict?->district_name_kh),
            'province' => trim((string) $campus?->addressProvince?->province_name_kh),
            default => '',
        };
    };
    $pageImages = $templateMode === 'cover'
        ? collect($templateFiles['cover'])->map($templateImage)->filter()->values()
        : collect([$templateImage($templateFiles['content_intro'])])
            ->merge(collect(range(1, $templateFiles['repeat_count']))->map(fn () => $templateImage($templateFiles['content_repeat'])))
            ->filter()
            ->values();
@endphp

@if($templateRows->isEmpty())
    <div class="empty">No students found.</div>
@else
    <div class="transcript-template-report">
        @foreach($templateRows as $templateRow)
            @foreach($pageImages as $pageIndex => $pageImage)
                @php
                    $student = $templateRow->student;
                    $campus = $templateRow->campus;
                @endphp
                <section class="transcript-template-page transcript-template-{{ $templateLevel }}">
                    <img src="{{ $pageImage }}" alt="Transcript book template">
                    @if($templateMode === 'cover' && $pageIndex === 0)
                        <div class="transcript-cover-field transcript-cover-school">វេស្ទើនអន្តរជាតិ</div>
                        <div class="transcript-cover-field transcript-cover-commune">{{ $campusAddressPart($campus, 'commune') }}</div>
                        <div class="transcript-cover-field transcript-cover-district">{{ $campusAddressPart($campus, 'district') }}</div>
                        <div class="transcript-cover-field transcript-cover-province">{{ $campusAddressPart($campus, 'province') }}</div>
                        <div class="transcript-cover-field transcript-cover-student-name">{{ $student?->full_name_kh }}</div>
                        <div class="transcript-cover-field transcript-cover-dob">{{ $khmerDate($student?->date_of_birth) }}</div>
                        <div class="transcript-cover-field transcript-cover-birth-place">{{ $birthProvinceOnly($student) }}</div>
                    @endif
                    @if($templateMode === 'content' && $pageIndex === 0)
                        @php
                            $father = $familyMember($student, 'father');
                            $mother = $familyMember($student, 'mother');
                            $printDate = $khmerDateParts($filters['report_date'] ?? now()->format('Y-m-d'));
                            $contentAddress = $currentAddress($student);
                            if ($templateLevel === 'secondary') {
                                $contentAddress = trim(preg_replace('/(?:[,;]\s*)?(?:ព្រះរាជាណាចក្រ)?កម្ពុជា/u', '', $contentAddress), " \t\n\r\0\x0B,;");
                            }
                        @endphp
                        <div class="transcript-content-field transcript-content-student-name">{{ $student?->full_name_kh }}</div>
                        @if($templateLevel === 'secondary')
                            <div class="transcript-content-field transcript-content-student-name-en">{{ $student?->full_name_en }}</div>
                        @endif
                        <div class="transcript-content-field transcript-content-dob">{{ $khmerDate($student?->date_of_birth) }}</div>
                        <div class="transcript-content-field transcript-content-birth-place">{{ $birthProvinceOnly($student) }}</div>
                        <div class="transcript-content-field transcript-content-father-name">{{ $father?->full_name_kh }}</div>
                        <div class="transcript-content-field transcript-content-father-occupation">{{ $father?->occupation_kh ?: ($templateLevel === 'primary' ? $father?->occupation : '') }}</div>
                        <div class="transcript-content-field transcript-content-mother-name">{{ $mother?->full_name_kh }}</div>
                        <div class="transcript-content-field transcript-content-mother-occupation">{{ $mother?->occupation_kh ?: ($templateLevel === 'primary' ? $mother?->occupation : '') }}</div>
                        <div class="transcript-content-field transcript-content-current-address">{{ $contentAddress }}</div>
                        @if($templateLevel === 'primary')
                            <div class="transcript-content-field transcript-content-print-day">{{ $printDate['day'] }}</div>
                            <div class="transcript-content-field transcript-content-print-month">{{ $printDate['month'] }}</div>
                            <div class="transcript-content-field transcript-content-print-year">{{ $printDate['year'] }}</div>
                        @endif
                    @endif
                </section>
            @endforeach
        @endforeach
    </div>
@endif
