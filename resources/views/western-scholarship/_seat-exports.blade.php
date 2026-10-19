@php
    $reportIcons=['candidates'=>'ti-users','attendance'=>'ti-clipboard-check','attendance-cover'=>'ti-file-check','envelope-cover'=>'ti-mail','seat-labels'=>'ti-sticker','room-plan'=>'ti-layout-grid'];
@endphp
<div class="scholarship-seat-exports ms-auto" aria-label="Seat plan reports">
@foreach(['print'=>['Print','ti-printer'],'xlsx'=>['Excel','ti-file-spreadsheet'],'pdf'=>['PDF','ti-file-type-pdf']] as $format=>[$label,$icon])
    <div class="dropdown">
        <button type="button" class="btn btn-sm btn-outline-light dropdown-toggle" id="seatReport{{ $format }}" data-bs-toggle="dropdown" data-bs-config='{"popperConfig":{"modifiers":[{"name":"offset","options":{"offset":[0,8]}},{"name":"preventOverflow","options":{"padding":8}}]}}' aria-expanded="false"><i class="ti {{ $icon }} me-1" aria-hidden="true"></i>{{ $label }}</button>
        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="seatReport{{ $format }}">
        @foreach(\App\Support\ScholarshipSeatReports::DOCUMENTS as $document=>$title)
            <a class="dropdown-item" data-seat-report-link data-report-format="{{ $format }}" data-report-title="{{ $title }}" href="{{ route('western-scholarship.seats.report',[$exam,$document,$format]) }}" @if($format==='print') target="_blank" rel="noopener" @endif><span class="scholarship-report-icon" aria-hidden="true"><i class="ti {{ $reportIcons[$document]??'ti-file-text' }}"></i></span><span>{{ $title }}</span></a>
        @endforeach
        </div>
    </div>
@endforeach
    <button type="button" class="btn btn-sm btn-outline-light" data-seat-print-settings aria-label="Seat Plan Print Settings" title="Print Settings"><i class="ti ti-settings me-1" aria-hidden="true"></i>Settings</button>
</div>
