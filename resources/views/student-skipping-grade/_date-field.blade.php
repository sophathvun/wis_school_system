@php
    $dateValue = old($dateName, now()->toDateString());
    $dateParsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue ?: '');
    $dateDisplay = $dateParsed && $dateParsed->format('Y-m-d') === $dateValue ? $dateParsed->format('d-M-Y') : $dateValue;
    $dateId = 'skipping-'.$dateName;
@endphp
<div class="premium-form-field skipping-date-field">
    <label class="form-label" for="{{ $dateId }}" title="{{ $dateLabel }}">{{ $dateLabel }} <span class="text-danger">*</span></label>
    <div class="date-picker skipping-date-picker" data-premium-date-picker data-premium-date-format="DD-MMM-YYYY">
        <input type="hidden" name="{{ $dateName }}" data-premium-date-value value="{{ $dateValue }}">
        <input id="{{ $dateId }}" class="form-control" type="text" data-premium-date-input value="{{ $dateDisplay }}" placeholder="DD-MMM-YYYY" autocomplete="off" aria-label="{{ $dateLabel }} in DD-MMM-YYYY format" required>
        <button type="button" class="date-picker-trigger date-picker-calendar-button" data-premium-date-toggle aria-label="Open {{ $dateLabel }} calendar" aria-expanded="false" aria-controls="{{ $dateId }}-calendar"><i class="ti ti-calendar"></i></button>
        <div id="{{ $dateId }}-calendar" class="date-picker-popup d-none" role="dialog" aria-label="Choose {{ $dateLabel }}">
            <div class="date-picker-header">
                <button type="button" class="date-picker-nav" data-premium-date-prev aria-label="Previous month"><i class="ti ti-chevron-left"></i></button>
                <button type="button" class="date-picker-year-toggle" aria-label="Choose year"></button>
                <button type="button" class="date-picker-nav" data-premium-date-next aria-label="Next month"><i class="ti ti-chevron-right"></i></button>
            </div>
            <div class="date-picker-year-popup d-none"><div class="date-picker-years"></div></div>
            <div class="date-picker-grid">
                <div class="date-picker-weekdays"><span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span></div>
                <div class="date-picker-days"></div>
            </div>
            <div class="skipping-calendar-footer"><button type="button" class="btn btn-sm btn-primary" data-premium-date-today>Today</button></div>
        </div>
    </div>
</div>
