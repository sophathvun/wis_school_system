<div class="g9-sticker-page-header">
    <table class="g9-sticker-header-layout"><tr>
        <td class="g9-sticker-header-details" width="80%">
            <div class="g9-sticker-heading" style="font-size:11pt;line-height:1.7;margin-bottom:1mm">ប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ</div>
            <div>មណ្ឌលប្រឡង៖ {{ $page['center'] }}</div>
            <div>សម័យប្រឡង៖ <span class="g9-seat-red">{{ $page['examDate'] }}</span></div>
        </td>
        <td class="g9-sticker-header-room" width="20%">
            <div>បន្ទប់លេខ</div>
            <div class="g9-sticker-header-room-number">{{ \App\Support\G9CandidateReport::roomHeading((string)$page['room']) }}</div>
        </td>
    </tr></table>
</div>
<table class="g9-sticker-grid" cellspacing="2" cellpadding="0">
    @foreach(array_chunk(array_pad($page['rows'],18,null),3) as $row)
        <tr>@foreach($row as $student)
            <td width="33.33%" @if(!$student) class="g9-empty-sticker" @endif>
                @if($student)
                    <div class="g9-sticker-heading">ប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ</div>
                    <div class="g9-sticker-session">សម័យប្រឡង៖ {{ $page['examDate'] }}</div>
                    <div class="g9-sticker-name">{{ $student['name'] }}</div>
                    <div class="g9-sticker-dob">ថ្ងៃ ខែ ឆ្នាំកំណើត៖ {{ $student['dob'] }}</div>
                    <div class="g9-sticker-numbers">បន្ទប់៖ <span>{{ \App\Support\G9CandidateReport::roomHeading((string)$page['room']) }}</span> &nbsp;&nbsp; តុ៖ <span>{{ \App\Support\G9CandidateReport::digits($student['seat']) }}</span></div>
                    <div class="g9-sticker-class-note">{{ implode('-',array_filter([$student['campus'] ?? '',$student['grade'] ?? ''],fn($value)=>$value !== '')) }}</div>
                @endif
            </td>
        @endforeach</tr>
    @endforeach
</table>
