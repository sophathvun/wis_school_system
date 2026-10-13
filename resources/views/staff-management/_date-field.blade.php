@php
    $dateValue=old($name,$editStaff?->{$name}?->format('Y-m-d'));
    $dateParsed=is_string($dateValue)?\DateTimeImmutable::createFromFormat('!Y-m-d',$dateValue):false;
    $dateDisplay=$dateParsed && $dateParsed->format('Y-m-d')===$dateValue ? $dateParsed->format('d-M-Y') : '';
    $dateError=$errors->first($name);
    $dateId='staff-'.$name;
@endphp
<div class="{{ $width??'staff-identity-field staff-identity-details' }}">
    <div class="premium-form-field staff-form-field staff-temporal-field {{ $dateDisplay?'has-value':'' }}">
        <label class="form-label" for="{{ $dateId }}-direct">{{ $label }}</label>
        <div class="date-picker staff-document-date-picker" data-premium-date-picker data-premium-date-format="DD-MMM-YYYY" data-premium-date-boundary=".staff-form-section-card, .modal-body">
            <input id="{{ $dateId }}-direct" class="form-control {{ $dateError?'is-invalid':'' }}" type="text" data-premium-date-input value="{{ $dateDisplay }}" placeholder="DD-MMM-YYYY" autocomplete="off" aria-label="{{ $label }}, DD-MMM-YYYY format" @if($dateError) aria-invalid="true" aria-describedby="{{ $dateId }}-error" @endif>
            <input id="{{ $dateId }}" type="hidden" name="{{ $name }}" data-premium-date-value value="{{ is_string($dateValue)?$dateValue:'' }}">
            <button type="button" class="date-picker-trigger date-picker-calendar-button" data-premium-date-toggle aria-label="Open {{ $label }} calendar" aria-expanded="false" aria-controls="{{ $dateId }}-calendar"><i class="ti ti-calendar" aria-hidden="true"></i></button>
            <div id="{{ $dateId }}-calendar" class="date-picker-popup d-none" role="dialog" aria-label="Choose {{ $label }}">
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
            </div>
        </div>
    </div>
    @if($dateError)<div id="{{ $dateId }}-error" class="invalid-feedback d-block">{{ $dateError }}</div>@endif
</div>
