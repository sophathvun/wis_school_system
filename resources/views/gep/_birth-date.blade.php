@php
    $isEnrollmentDate=($dateField??'date_of_birth')==='enrolled_on';
    $dateName=$isEnrollmentDate?'enrolled_on':'date_of_birth';
    $dateLabel=$isEnrollmentDate?'Enrolled On':'Date of Birth';
    $dateId=$isEnrollmentDate?'gep-enrolled-date':'gep-birth-date';
@endphp
<div class="gep-field gep-birth-date-field">
    <div class="date-picker gep-birth-date-picker" data-premium-date-picker data-premium-date-format="DD-MMM-YYYY" @if(!$isEnrollmentDate) data-premium-date-max="{{ $options['today'] }}" @endif data-premium-date-portal="true">
        <input type="text" id="{{ $dateId }}-display" class="form-control" placeholder=" " autocomplete="off" data-premium-date-input aria-label="{{ $dateLabel }}, for example 12-Nov-2026" @required($isEnrollmentDate)>
        <input type="hidden" id="gep-form-{{ $dateName }}" name="{{ $dateName }}" data-premium-date-value @if(!$isEnrollmentDate) data-profile-field @endif>
        <button type="button" class="date-picker-trigger date-picker-calendar-button" data-premium-date-toggle aria-label="Open {{ $dateLabel }} calendar" aria-expanded="false" aria-controls="{{ $dateId }}-calendar"><i class="ti ti-calendar" aria-hidden="true"></i></button>
        <div id="{{ $dateId }}-calendar" class="date-picker-popup gep-date-calendar d-none" role="dialog" aria-label="Choose {{ $dateLabel }}">
            <div class="date-picker-header">
                <button type="button" class="date-picker-nav" data-premium-date-prev aria-label="Previous month"><i class="ti ti-chevron-left" aria-hidden="true"></i></button>
                <button type="button" class="date-picker-year-toggle" aria-label="Choose year"></button>
                <button type="button" class="date-picker-nav" data-premium-date-next aria-label="Next month"><i class="ti ti-chevron-right" aria-hidden="true"></i></button>
            </div>
            <div class="date-picker-year-popup d-none"><div class="date-picker-years"></div></div>
            <div class="date-picker-grid">
                <div class="date-picker-weekdays"><span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span></div>
                <div class="date-picker-days"></div>
            </div>
            @if($isEnrollmentDate)<div class="gep-calendar-footer"><button type="button" class="btn btn-sm btn-primary" data-premium-date-today>Today</button></div>@endif
        </div>
    </div>
    <label for="{{ $dateId }}-display">{{ $dateLabel }} @if($isEnrollmentDate)<span class="text-danger">*</span>@endif <span class="gep-date-format">DD-MMM-YYYY</span></label>
</div>
