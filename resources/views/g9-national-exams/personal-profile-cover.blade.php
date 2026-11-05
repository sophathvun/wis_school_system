@if(!($pdfExport ?? false) && !empty($page['background']))<div class="profile-background" aria-hidden="true"><img src="{{ $page['background'] }}" alt=""></div>@endif
<div class="profile-cover-motto">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ</div>
<div class="profile-ornament motto-ornament">5</div>
<div class="profile-cover-title">សៀវភៅកម្រងសលាកបត្រឯកត្តជន</div>
<div class="profile-cover-exam">ប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ</div>
<div class="profile-cover-session">សម័យប្រឡង៖ {{ $page['examDate'] }}</div>
<div class="profile-cover-center"><span data-fit-center style="font-size:{{ $page['center_font_size'] ?? 18.3857 }}pt">មណ្ឌលប្រឡង: {{ $page['center'] }}</span></div>
<table class="profile-cover-room"><tr>
    <td>បន្ទប់លេខ: <span>{{ \App\Support\G9CandidateReport::roomHeading((string)$page['room']) }}</span></td>
    <td>ពីតុលេខ៖ <span>{{ \App\Support\G9CandidateReport::roomHeading((string)$page['seatFrom']) }}</span></td>
    <td>ដល់តុលេខ៖ <span>{{ \App\Support\G9CandidateReport::roomHeading((string)$page['seatTo']) }}</span></td>
</tr></table>
<div class="profile-cover-count">ចំនួនសិស្ស៖ <span>{{ \App\Support\G9CandidateReport::digits($page['total']) }}</span> នាក់ &nbsp;&nbsp; ស្រី៖ <span>{{ \App\Support\G9CandidateReport::digits($page['female']) }}</span> នាក់</div>
