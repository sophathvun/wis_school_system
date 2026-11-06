@php($isProfile = in_array($type,\App\Support\G9CandidateReport::PROFILE_TYPES))
@unless($pdfExport ?? false)
<!DOCTYPE html><html lang="km"><head><meta charset="utf-8"><title>{{ \App\Support\G9CandidateReport::TITLES[$type] }}</title>
<style>
@font-face{font-family:g9body;src:url('data:font/ttf;base64,{{ base64_encode(file_get_contents(public_path('fonts/khmer/KhmerOSsiemreap.ttf'))) }}')}
@font-face{font-family:g9heading;src:url('data:font/ttf;base64,{{ base64_encode(file_get_contents(public_path('fonts/khmer/KhmerOSmuollight.ttf'))) }}')}
@font-face{font-family:g9ornament;src:url('data:font/ttf;base64,{{ base64_encode(file_get_contents(public_path('fonts/khmer/Tacteing.ttf'))) }}')}
{!! file_get_contents(resource_path('css/pages/g9-candidate-print.css')) !!}
@if($isProfile)
@page{size:A4 portrait;margin:0}
.profile-sheet{width:210mm;height:297mm;margin:0 auto;page-break-after:always;background:#fff;overflow:hidden}
.profile-sheet:last-child{page-break-after:auto}
.profile-half{position:relative;width:210mm;height:148.5mm;overflow:hidden}
.profile-half:first-child{border-bottom:.15mm dashed #b4b4b4}
.profile-half>.profile-page{width:297mm;height:210mm;min-height:210mm;padding:8mm 8mm 8mm 30mm;margin:0;box-shadow:none;transform:scale(.7070707071);transform-origin:top left;page-break-after:auto}
@media screen{.profile-sheet{margin-bottom:16px;box-shadow:0 2px 12px #0002}}
@endif
</style></head><body>
@endunless
@if($isProfile && !($pdfExport ?? false))
    @foreach(array_chunk($pages,2) as $sheet)
        <section class="profile-sheet">
            @foreach($sheet as $card)
                <div class="profile-half"><section class="report-page profile-page">
                    @if($type === 'profile-cover')
                        @include('g9-national-exams.personal-profile-cover',['page'=>$card])
                    @else
                        @include('g9-national-exams.personal-profile',['profile'=>$card['profile']])
                    @endif
                </section></div>
            @endforeach
        </section>
    @endforeach
@else
@foreach($pages as $page)
    @if(($pdfExport ?? false) && !$loop->first)<pagebreak />@endif
    <section class="report-page {{ $isProfile ? 'profile-page' : '' }}" @if($pdfExport ?? false) style="page-break-after:auto;{{ $isProfile ? 'width:259mm' : '' }}" @endif>
    @if($type === 'seat-stickers')
        @include('g9-national-exams.seat-stickers',['page'=>$page])
    @elseif($type === 'room-seat-plan')
        @include('g9-national-exams.room-seat-plan',['page'=>$page])
    @elseif($type === 'profile-cover')
        @include('g9-national-exams.personal-profile-cover',['page'=>$page])
    @elseif($type === 'profile')
        @include('g9-national-exams.personal-profile',['profile'=>$page['profile']])
    @elseif($type === 'cover')
        <div class="report-cover">
            <div class="cover-motto">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ<br><span class="motto-ornament">5</span></div>
            <div class="cover-department">{{ $page['department'] }}<br>មណ្ឌលប្រឡង៖ {{ $page['center'] }}</div>
            <div class="cover-title">បញ្ជីស្រង់វត្តមានសិស្ស</div>
            <div class="cover-subtitle">ប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ</div>
            <div class="cover-room">បន្ទប់លេខ៖ <span class="cover-number">{{ \App\Support\G9CandidateReport::roomHeading((string)$page['room']) ?: '—' }}</span></div>
            <div class="cover-bottom">សម័យប្រឡង៖ <span class="cover-number">{{ $page['examDate'] }}</span><br>ចំនួន {{ $page['total'] }} នាក់<br>ស្រី {{ $page['female'] }} នាក់</div>
        </div>
    @else
        <div class="report-motto">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ</div><div class="report-ornament motto-ornament {{ in_array($type,['candidates','attendance-1','attendance-2']) ? 'candidate-motto-ornament' : '' }}">5</div>
        <table class="report-header {{ in_array($type,['attendance-1','attendance-2']) ? 'attendance-one-header' : '' }}"><tr>
            <td class="heading-left {{ in_array($type,['candidates','attendance-1','attendance-2']) ? 'candidate-heading-left' : '' }}">{{ $type === 'candidates' ? \App\Support\G9CandidateReport::locationHeading($page['location']) : $page['location'] }}<br>មណ្ឌលប្រឡង៖ {{ $page['center'] }}<br>បន្ទប់លេខ៖ {{ in_array($type,['candidates','attendance-1','attendance-2']) ? (\App\Support\G9CandidateReport::roomHeading((string)$page['room']) ?: '—') : ($page['room'] ?: '—') }}</td>
            <td class="heading-right {{ $type === 'candidates' ? 'candidate-heading-right' : '' }}" @if(in_array($type,['attendance-1','attendance-2'])) style="text-align:center;font-family:g9heading;font-weight:400;width:40%" @endif>{{ $type === 'candidates' ? 'សម្រាប់បិទផ្សាយ' : ($type === 'attendance-1' ? 'សន្លឹកទី១' : 'សន្លឹកទី២') }}<br>{{ $type === 'candidates' ? '' : 'បញ្ជីសម្រាប់ស្រង់វត្តមានបេក្ខជន' }}</td>
        </tr></table>
        <div class="report-title">បញ្ជីរាយនាមបេក្ខជនប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ</div>
        <div class="report-exam-date">សម័យប្រឡង៖ {{ $page['examDate'] }}</div>
        @php($subjects = \App\Support\G9CandidateReport::SUBJECTS[$type] ?? [])
        <table class="report-table {{ $subjects ? 'attendance' : '' }}">
            <colgroup>
                @foreach($subjects ? [5,17,4.5,11,17,7] : [6,24,5,13,28,12,12] as $width)<col style="width:{{ $width }}%">@endforeach
                @if($subjects)@foreach($subjects as $subject)<col style="width:{{ 38.5/count($subjects) }}%">@endforeach @endif
            </colgroup>
            <thead><tr>
                @foreach(['លេខតុ','នាមត្រកូល និងនាមខ្លួន','ភេទ','ថ្ងៃខែឆ្នាំកំណើត','អាគតដ្ឋាន','ភាសាបរទេស'] as $label)<th width="{{ ($subjects ? [5,17,4.5,11,17,7] : [6,24,5,13,28,12])[$loop->index] }}%" @if($subjects) rowspan="2" @endif>{{ $label }}</th>@endforeach
                @if($subjects)<th colspan="{{ count($subjects) }}">ហត្ថលេខាបេក្ខជនតាមមុខវិជ្ជា</th>@else<th>សេចក្តីផ្សេងៗ</th>@endif
            </tr>@if($subjects)<tr>@foreach($subjects as $subject)<th width="{{ 38.5/count($subjects) }}%">{{ $subject }}</th>@endforeach</tr>@endif</thead>
            <tbody>@foreach(array_pad($page['rows'],25,[]) as $row)<tr>
                <td class="center">{{ $row['seat'] ?? '' }}</td><td>{{ $row['name'] ?? '' }}</td><td class="center">{{ $row['gender'] ?? '' }}</td><td class="center">{{ $row['dob'] ?? '' }}</td><td class="school-cell"><span data-fit-school style="font-size:{{ $row['school_font_size'] ?? ($subjects ? 8 : 10) }}pt">{{ $row['school'] ?? '' }}</span></td><td class="center">{{ $row['language'] ?? '' }}</td>
                @if($subjects)@foreach($subjects as $subject)<td>&nbsp;</td>@endforeach @else<td>&nbsp;</td>@endif
            </tr>@endforeach</tbody>
        </table>
        <table class="report-footer"><tr><td>បេក្ខជនសរុប៖ {{ $page['total'] }} នាក់ ក្នុងនោះស្រី៖ {{ $page['female'] }} នាក់<br>អង់គ្លេស៖ {{ $page['english'] }} នាក់ &nbsp; បារាំង៖ {{ $page['french'] }} នាក់</td><td class="signature">{{ $page['lunar'] }}<br>{{ $page['solar'] }}<br><span class="report-signer">{{ $page['signer'] }}</span>@if(!empty($page['signer_name']))<br>{{ $page['signer_name'] }}@endif</td></tr></table>
    @endif
    </section>
@endforeach
@endif
@unless($pdfExport ?? false)
<script>
const fitSchools=()=>document.querySelectorAll('[data-fit-school]').forEach(text=>{
    const cell=text.closest('td'),style=getComputedStyle(cell),available=cell.clientWidth-parseFloat(style.paddingLeft)-parseFloat(style.paddingRight)-2;
    const base=text.closest('.attendance')?8:10; text.style.whiteSpace='nowrap';text.style.fontSize=base+'pt';
    const width=text.getBoundingClientRect().width;
    if(width>available&&available>0)text.style.fontSize=Math.max(5.5,base*available/width)+'pt';
    if(text.getBoundingClientRect().width>available)text.style.whiteSpace='normal';
});
const fitCenters=()=>document.querySelectorAll('[data-fit-center]').forEach(text=>{
    const available=text.parentElement.clientWidth-2,base=18.3857;
    text.style.maxWidth='none';text.style.whiteSpace='nowrap';text.style.fontSize=base+'pt';
    const width=text.offsetWidth;
    if(width>available&&available>0)text.style.fontSize=Math.max(12.73,base*available/width)+'pt';
    text.style.maxWidth='100%';
    if(text.offsetWidth>available)text.style.whiteSpace='normal';
});
const fitSeatNames=()=>document.querySelectorAll('[data-fit-seat-name]').forEach(text=>{
    const available=text.parentElement.clientWidth-2,base=Number(text.dataset.baseSize);
    text.style.fontSize=base+'pt';
    const width=text.offsetWidth;
    if(width>available&&available>0)text.style.fontSize=(base*available/width)+'pt';
});
const fitReportText=()=>{fitSchools();fitCenters();fitSeatNames();};
window.addEventListener('load',async()=>{await document.fonts.ready;fitReportText();window.print();});
window.addEventListener('beforeprint',fitReportText);
window.addEventListener('resize',fitReportText);
</script>
</body></html>
@endunless
