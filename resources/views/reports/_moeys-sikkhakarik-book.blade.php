@php
    $rows = collect($enrollments ?? []);
    $transcriptLevel = $filters['transcript_level'] ?? '';
    $isSecondaryTranscript = $transcriptLevel === 'secondary';
    $transcriptLevelLabel = $isSecondaryTranscript ? 'Secondary Transcript Book' : 'Primary Transcript Book';
    $transcriptCoverSubtitle = $isSecondaryTranscript ? 'មធ្យមសិក្សា' : 'បឋមសិក្សាចំណេះទូទៅ';
    $primarySubjects = ['ភាសាខ្មែរ','គណិតវិទ្យា','វិទ្យាសាស្ត្រ','សិក្សាសង្គម','អប់រំកាយ','អប់រំសិល្បៈ','សីលធម៌','ភាសាបរទេស','សរុបពិន្ទុ','មធ្យមភាគ'];
    $secondarySubjects = ['ភាសាខ្មែរ','សីលធម៌-ពលរដ្ឋវិជ្ជា','ប្រវត្តិវិទ្យា','ភូមិវិទ្យា','គណិតវិទ្យា','រូបវិទ្យា','គីមី','ជីវវិទ្យា','ផែនដីវិទ្យា','ភាសាបរទេស','បច្ចេកវិទ្យា','សេដ្ឋកិច្ច','អប់រំសិល្បៈ','អប់រំកាយ','សរុបពិន្ទុ','មធ្យមភាគ'];
    $subjects = $isSecondaryTranscript ? $secondarySubjects : $primarySubjects;
    $khmerDate = static function ($date): string {
        if (!$date) return '................................';
        $date = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        return $date->format('d-m-Y');
    };
    $gradeClassText = static function ($row): string {
        $grade = trim((string) ($row->grade?->grade_short_name ?: $row->grade?->grade));
        $class = trim((string) ($row->schoolClass?->class_name ?? ''));
        if ($grade === '') return $class ?: '................................';
        if ($class === '') return $grade;
        if (str_ends_with($grade, '-')) return $grade . $class;
        return preg_match('/^[A-Za-z]/', $grade) ? $grade . '-' . $class : $grade . $class;
    };
    $familyMember = static function ($student, string $relationship) {
        return $student?->familyMembers?->first(fn ($member) => ($member->relationship_type ?? $member->pivot?->relationship_type) === $relationship);
    };
    $locationText = static function ($student, string $type): string {
        $parts = $type === 'birth'
            ? [$student?->birthVillage?->village_name_kh, $student?->birthCommune?->commune_name_kh, $student?->birthDistrict?->district_name_kh, $student?->birthProvince?->province_name_kh]
            : [$student?->current_address_kh, $student?->addressVillage?->village_name_kh, $student?->addressCommune?->commune_name_kh, $student?->addressDistrict?->district_name_kh, $student?->addressProvince?->province_name_kh];
        return collect($parts)->map(fn ($value) => trim((string) $value))->filter()->join(' ');
    };
@endphp

@if(!$hasDataFilter)
    <div class="report-placeholder-preview khmer-font-siemreap">
        <div class="report-placeholder-preview-icon"><i class="ti ti-book-2"></i></div>
        <div class="report-placeholder-preview-title">សៀវភៅសិក្ខាគារិក (MoEYS)</div>
        <div class="report-placeholder-preview-text">Please select Academic Year and Transcript Level to preview the transcript books.</div>
    </div>
@elseif($rows->isEmpty())
    <div class="empty text-muted py-4 text-center khmer-font-siemreap">No students found.</div>
@else
    <div class="sikkhakarik-report">
        @foreach($rows as $row)
            @php
                $student = $row->student;
                $mother = $familyMember($student, 'mother');
                $father = $familyMember($student, 'father');
                $photoUrl = $student?->photo_path ? asset('storage/' . ltrim($student->photo_path, '/')) : null;
                $campusName = $row->campus?->campus_name_kh ?: $row->campus?->campus_name_en;
                $schoolName = $row->campus?->school_name_kh ?: 'សាលាវេស្ទើនអន្តរជាតិ';
            @endphp
            <section class="sikkhakarik-page sikkhakarik-cover-page">
                <div class="sikkhakarik-cover-frame">
                    <div class="sikkhakarik-top-title">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ</div>
                    <div class="sikkhakarik-ministry">ក្រសួងអប់រំ យុវជន និងកីឡា</div>
                    <h1>សៀវភៅសិក្ខាគារិក</h1>
                    <h2>{{ $transcriptCoverSubtitle }}</h2>
                    <div class="sikkhakarik-cover-lines">
                        <div>សាលារៀន <span>{{ $schoolName }}</span></div>
                        <div>ឆ្នាំ-សិក្សា <span>{{ $row->academicYear?->academic_year ?: '................................' }}</span> ក្រុង-ខណ្ឌ <span>................................</span> ខេត្ត-ក្រុង <span>................................</span></div>
                        <div>នាមត្រកូល និងនាមខ្លួន <span>{{ $student?->full_name_kh ?: '................................' }}</span></div>
                        <div>ថ្ងៃ ខែ ឆ្នាំកំណើត <span>{{ $khmerDate($student?->date_of_birth) }}</span></div>
                        <div>ទីកន្លែងកំណើត <span>{{ $locationText($student, 'birth') ?: '................................' }}</span></div>
                    </div>
                </div>
            </section>

            <section class="sikkhakarik-page sikkhakarik-rules-page">
                <h2>សេចក្ដីណែនាំ</h2>
                <ol>
                    <li>សៀវភៅនេះប្រើសម្រាប់កត់ត្រាលទ្ធផលសិក្សា និងព័ត៌មានសិក្សារបស់សិស្ស។</li>
                    <li>ត្រូវបំពេញនាមត្រកូល នាមខ្លួន ភេទ ថ្ងៃខែឆ្នាំកំណើត ទីកន្លែងកំណើត និងព័ត៌មានអាណាព្យាបាលឲ្យបានត្រឹមត្រូវ។</li>
                    <li>គ្រូបន្ទុកថ្នាក់ត្រូវពិនិត្យ និងបំពេញពិន្ទុប្រឡងឆមាសទី១ និងឆមាសទី២។</li>
                    <li>នាយកសាលាត្រូវពិនិត្យ និងចុះហត្ថលេខាបញ្ជាក់នៅចុងឆ្នាំសិក្សា។</li>
                    <li>ប្រសិនបើសិស្សផ្ទេរសាលា ត្រូវកត់ត្រាការផ្ទេរ និងលទ្ធផលសិក្សាចុងក្រោយ។</li>
                </ol>
            </section>

            <section class="sikkhakarik-page sikkhakarik-profile-page">
                <div class="sikkhakarik-photo-box">@if($photoUrl)<img src="{{ $photoUrl }}" alt="Student photo">@else រូបថត<br>៤x៦ @endif</div>
                <h1>សៀវភៅសិក្ខាគារិក</h1>
                <div class="sikkhakarik-info-lines">
                    <div>នាមត្រកូល និង នាមខ្លួន : <span>{{ $student?->full_name_kh ?: '................................' }}</span></div>
                    <div>ថ្ងៃ ខែ ឆ្នាំកំណើត : <span>{{ $khmerDate($student?->date_of_birth) }}</span></div>
                    <div>ទីកន្លែងកំណើត : <span>{{ $locationText($student, 'birth') ?: '................................' }}</span></div>
                    <div>ឪពុក និង មុខរបរ : <span>{{ $father?->full_name_kh ?: '................................' }} {{ $father?->occupation_kh ?: $father?->occupation ?: '' }}</span></div>
                    <div>ម្តាយ និង មុខរបរ : <span>{{ $mother?->full_name_kh ?: '................................' }} {{ $mother?->occupation_kh ?: $mother?->occupation ?: '' }}</span></div>
                    <div>អាសយដ្ឋានសព្វថ្ងៃ : <span>{{ $locationText($student, 'address') ?: '................................' }}</span></div>
                </div>
                <div class="sikkhakarik-signature">ថ្ងៃទី ........ ខែ ........ ឆ្នាំ ........<br>នាយកសាលា</div>
                <table class="sikkhakarik-small-table">
                    <thead><tr><th>ឆ្នាំសិក្សា</th><th>ថ្នាក់</th><th>គ្រឹះស្ថានសិក្សា</th><th>ថ្ងៃ ខែ ឆ្នាំចូលរៀន</th><th>ថ្ងៃ ខែ ឆ្នាំចេញ</th><th>អត្តលេខ</th></tr></thead>
                    <tbody>
                        <tr><td>{{ $row->academicYear?->academic_year }}</td><td>{{ $gradeClassText($row) }}</td><td>{{ $campusName }}</td><td>{{ $khmerDate($row->enrolled_on) }}</td><td>{{ $khmerDate($row->ended_on) }}</td><td>{{ $student?->student_id ?: $student?->student_no }}</td></tr>
                        @for($i = 0; $i < 5; $i++)<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr>@endfor
                    </tbody>
                </table>
            </section>

            <section class="sikkhakarik-page sikkhakarik-score-page">
                <div class="sikkhakarik-score-grid">
                    <div>
                        <div class="sikkhakarik-line-title">នាមត្រកូល និង នាមខ្លួន {{ $student?->full_name_kh }} &nbsp;&nbsp; ថ្នាក់ទី {{ $gradeClassText($row) }}</div>
                        <table class="sikkhakarik-score-table">
                            <thead><tr><th rowspan="2">មុខវិជ្ជា</th><th colspan="2">ឆមាសទី ១</th><th colspan="2">ឆមាសទី ២</th></tr><tr><th>ពិន្ទុ</th><th>ចំណាត់ថ្នាក់</th><th>ពិន្ទុ</th><th>ចំណាត់ថ្នាក់</th></tr></thead>
                            <tbody>
                                @foreach($subjects as $subject)
                                    <tr><td>{{ $subject }}</td><td></td><td></td><td></td><td></td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div>
                        <div class="sikkhakarik-line-title">សាលា {{ $campusName }} &nbsp;&nbsp; ឆ្នាំសិក្សា {{ $row->academicYear?->academic_year }}</div>
                        <table class="sikkhakarik-score-table">
                            <thead><tr><th>ឆមាសទី ១</th><th>ឆមាសទី ២</th><th>ប្រចាំឆ្នាំ</th></tr></thead>
                            <tbody><tr><td style="height:34mm"></td><td></td><td></td></tr></tbody>
                        </table>
                        <div class="sikkhakarik-note-box">មតិយោបល់ប្រចាំឆ្នាំ<br><br>........................................................................................................<br>........................................................................................................</div>
                        <div class="sikkhakarik-note-box">ការសរសើរ និង កំណត់សម្គាល់<br><br>ថ្ងៃទី ........ ខែ ........ ឆ្នាំ ........<br>គ្រូបន្ទុកថ្នាក់</div>
                        <div class="sikkhakarik-note-box">មូលហេតុបញ្ចប់ឬផ្ទេរសាលា<br><br>........................................................................................................<br><br>ថ្ងៃទី ........ ខែ ........ ឆ្នាំ ........<br>នាយកសាលា</div>
                    </div>
                </div>
            </section>
        @endforeach
    </div>
@endif

<style>
    @font-face{font-family:"Khmer OS Siemreap";src:url("{{ asset('fonts/khmer/KhmerOSsiemreap.ttf') }}") format("truetype")}
    @font-face{font-family:"Khmer OS Muol Light";src:url("{{ asset('fonts/khmer/KhmerOSmuollight.ttf') }}") format("truetype")}
    .sikkhakarik-report{display:flex;flex-direction:column;gap:14px;align-items:center;background:#f1f5f9;padding:12px;min-width:1120px}
    .sikkhakarik-page{width:297mm;min-height:210mm;height:210mm;background:#fff;color:#000;font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif;page-break-after:always;padding:10mm 12mm;box-shadow:0 1px 8px rgba(0,0,0,.12);position:relative;overflow:hidden}
    .sikkhakarik-page:last-child{page-break-after:auto}.sikkhakarik-cover-frame{width:125mm;height:188mm;margin-left:auto;border:3px double #16365f;border-radius:14px;padding:13mm 10mm;display:flex;flex-direction:column;text-align:center}
    .sikkhakarik-top-title,.sikkhakarik-ministry,.sikkhakarik-cover-page h1,.sikkhakarik-cover-page h2,.sikkhakarik-profile-page h1,.sikkhakarik-rules-page h2{font-family:"Khmer OS Muol Light","Khmer OS Muol",serif;font-weight:400}
    .sikkhakarik-top-title{font-size:15px;line-height:1.35}.sikkhakarik-ministry{font-size:17px;margin-top:22mm}.sikkhakarik-cover-page h1{font-size:31px;margin:20mm 0 13mm}.sikkhakarik-cover-page h2{font-size:18px;margin:0 0 18mm}
    .sikkhakarik-cover-lines{margin-top:auto;text-align:left;font-size:12.5px;line-height:1.8}.sikkhakarik-cover-lines span,.sikkhakarik-info-lines span{display:inline-block;min-width:32mm;border-bottom:1px dotted #000;padding:0 2mm}
    .sikkhakarik-rules-page{border:0;font-size:15px;line-height:1.75}.sikkhakarik-rules-page::before{content:"";position:absolute;left:12mm;top:10mm;width:132mm;height:188mm;border:1px solid #000}.sikkhakarik-rules-page h2,.sikkhakarik-rules-page ol{position:relative;z-index:1;width:120mm;margin-left:7mm}.sikkhakarik-rules-page h2{text-align:center;font-size:29px;margin-top:7mm;margin-bottom:8mm}.sikkhakarik-rules-page li{margin-bottom:2mm}
    .sikkhakarik-profile-page{border:0}.sikkhakarik-profile-page::before{content:"";position:absolute;right:12mm;top:10mm;width:132mm;height:188mm;border:1px solid #000}.sikkhakarik-profile-page h1{position:relative;z-index:1;text-align:center;font-size:30px;margin:12mm 0 10mm auto;width:120mm}.sikkhakarik-photo-box{position:absolute;right:110mm;top:17mm;width:27mm;height:36mm;border:1px solid #999;display:flex;align-items:center;justify-content:center;text-align:center;font-size:14px;z-index:1}.sikkhakarik-photo-box img{width:100%;height:100%;object-fit:cover}
    .sikkhakarik-info-lines{position:relative;z-index:1;font-size:14.5px;line-height:1.75;width:95mm;margin:0 7mm 6mm auto}.sikkhakarik-signature{position:relative;z-index:1;text-align:center;margin:4mm 8mm 5mm auto;width:55mm;font-size:14.5px;line-height:1.55}
    .sikkhakarik-small-table,.sikkhakarik-score-table{width:100%;border-collapse:collapse;font-size:12px}.sikkhakarik-small-table{position:relative;z-index:1;width:132mm;margin-left:auto}.sikkhakarik-small-table th,.sikkhakarik-small-table td,.sikkhakarik-score-table th,.sikkhakarik-score-table td{border:1px solid #000;padding:2px 3px;text-align:center;vertical-align:middle}.sikkhakarik-small-table td{height:6.8mm}
    .sikkhakarik-score-grid{display:grid;grid-template-columns:1fr 1fr;gap:16mm}.sikkhakarik-line-title{font-size:15px;margin-bottom:2mm}.sikkhakarik-score-table td{height:7.6mm}.sikkhakarik-note-box{border:1px solid #000;border-top:0;padding:4mm 4mm;text-align:center;font-size:14px;line-height:1.5;min-height:29mm}
    @media print{.sikkhakarik-report{display:block;background:#fff;padding:0;min-width:0}.sikkhakarik-page{box-shadow:none;margin:0;width:297mm;height:210mm;min-height:210mm;break-after:page}.sikkhakarik-page:last-child{break-after:auto}}
</style>
