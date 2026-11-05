@php($d = $profile['data'])
@if(!($pdfExport ?? false) && !empty($profile['background']))<div class="profile-background" aria-hidden="true"><img src="{{ $profile['background'] }}" alt=""></div>@endif
<div class="profile-title">សលាកបត្រឯកត្តជន</div>
<div class="profile-ornament motto-ornament">5</div>
<table class="profile-layout"><tr>
    <td class="profile-information" width="78%">
        <table class="profile-details">
            <colgroup><col style="width:31mm"><col style="width:25mm"><col style="width:22mm"><col style="width:20mm"><col style="width:22mm"><col></colgroup>
            <tr><td class="profile-label">ឈ្មោះ:</td><td colspan="5" class="profile-name">{{ $profile['name'] }}</td></tr>
            <tr><td class="profile-label">កើតថ្ងៃទី:</td><td colspan="5" class="profile-red">{{ $profile['dob'] }}</td></tr>
            <tr><td class="profile-label" width="31mm">ភេទ:</td><td width="25mm">{{ $profile['gender'] }}</td><td class="profile-label" width="22mm">ជនជាតិ:</td><td width="20mm" style="text-align:center">{{ $d['ethnicity'] ?? '' }}</td><td class="profile-label" width="22mm">សញ្ជាតិ:</td><td>{{ $d['nationality'] ?? '' }}</td></tr>
            <tr><td class="profile-label">កើតនៅ:</td><td colspan="5" class="profile-red">{{ $d['place_of_birth'] ?? '' }}</td></tr>
            <tr><td class="profile-label">ជាសិស្សថ្នាក់ទី:</td><td>{{ $d['grade'] ?? '' }}</td><td colspan="4"><span class="profile-label">នៃ:</span> {{ $d['school_name'] ?? '' }}</td></tr>
            <tr><td class="profile-label">ជ្រើសរើសភាសា:</td><td class="profile-red">{{ ['english'=>'អង់គ្លេស','french'=>'បារាំង','khmer'=>'ខ្មែរ'][mb_strtolower(trim($d['foreign_language'] ?? ''))] ?? ($d['foreign_language'] ?? '') }}</td><td colspan="4">សម្រាប់មុខវិជ្ជាភាសាបរទេស</td></tr>
            <tr><td class="profile-label">ឪពុកឈ្មោះ:</td><td colspan="3">{{ $d['father_name'] ?? '' }}</td><td class="profile-label">មុខរបរ:</td><td>{{ $d['father_occupation'] ?? '' }}</td></tr>
            <tr><td class="profile-label">ម្តាយឈ្មោះ:</td><td colspan="3">{{ $d['mother_name'] ?? '' }}</td><td class="profile-label">មុខរបរ:</td><td>{{ $d['mother_occupation'] ?? '' }}</td></tr>
        </table>
    </td>
    <td class="profile-photo-column" width="22%">
        <div class="profile-photo-frame">
        @if($profile['photo'])<img class="profile-photo" style="width:42mm;height:56mm" src="{{ $profile['photo'] }}" alt="Student photo">@else<div class="profile-photo-placeholder">រូបថត</div>@endif
        @if($profile['signature'] && !($pdfExport ?? false))<div class="profile-signature-overlay"><img src="{{ $profile['signature'] }}" alt="Exam center signature"></div>@endif
        </div>
    </td>
</tr></table>
<div class="profile-section-label">អាសយដ្ឋានបច្ចុប្បន្នរបស់បេក្ខជន: <span class="profile-address-value">{{ $profile['address_kh'] ?? '' }}</span></div>
<table class="profile-examination"><tr><td class="profile-label">ប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិចំណេះទូទៅ</td><td><span class="profile-label">សម័យប្រឡង:</span> {{ $profile['examDate'] }}</td></tr>
<tr><td class="profile-red"><span class="profile-label">នៅមណ្ឌល:</span> {{ $d['exam_center'] ?? '' }}</td><td><span class="profile-label">បន្ទប់លេខ:</span> <span class="profile-red">{{ \App\Support\G9CandidateReport::roomHeading((string)($d['room_number'] ?? '')) }}</span> <span class="profile-field-gap">&nbsp;</span><span class="profile-label">លេខតុ:</span> <span class="profile-red">{{ \App\Support\G9CandidateReport::digits((string)($d['seat_number'] ?? '')) }}</span> <span class="profile-field-gap">&nbsp;</span><span class="profile-label">និទ្ទេស:</span> <span class="profile-red">{{ $profile['mention'] ?? '' }}</span></td></tr></table>
<table class="profile-bottom"><tr><td class="profile-phone">ទូរសព្ទទំនាក់ទំនង: {{ $d['phone_number'] ?? '' }}</td><td class="profile-dates">{{ $profile['lunar'] }}<br>{{ $profile['solar'] }}<br>ហត្ថលេខាបេក្ខជន និងឈ្មោះបេក្ខជន</td></tr></table>
