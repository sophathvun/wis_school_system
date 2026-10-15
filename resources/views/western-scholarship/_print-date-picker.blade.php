<div class="scholarship-print-field signature-date date-picker" data-premium-date-picker data-premium-date-format="DD-MMM-YYYY" data-premium-date-boundary=".modal-body" data-premium-date-placement="auto" data-premium-date-validation="inline">
    <label class="form-label" for="{{ $dateId }}">Date <span class="text-danger">*</span></label>
    <input class="form-control" type="text" id="{{ $dateId }}" data-premium-date-input placeholder="DD-MMM-YYYY" autocomplete="off" aria-label="{{ $dateLabel }} in DD-MMM-YYYY format" required>
    <input type="hidden" name="{{ $role }}_date" data-premium-date-value>
    <button type="button" class="date-picker-trigger date-picker-calendar-button" data-premium-date-toggle aria-label="Open {{ $dateLabel }} calendar" aria-expanded="false" aria-controls="{{ $dateId }}Calendar"><i class="ti ti-calendar" aria-hidden="true"></i></button>
    <div class="date-picker-popup d-none" id="{{ $dateId }}Calendar" role="dialog" aria-label="Choose {{ $dateLabel }}">
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
        <div class="scholarship-calendar-footer"><button type="button" class="btn btn-sm btn-primary" data-premium-date-today>Today</button></div>
    </div>
</div>
