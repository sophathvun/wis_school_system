@php
    $birthDate=old('date_of_birth',$editStaff?->date_of_birth?->format('Y-m-d'));
    $birthDateParsed=is_string($birthDate)?\DateTimeImmutable::createFromFormat('!Y-m-d',$birthDate):false;
    $birthDateDisplay=$birthDateParsed && $birthDateParsed->format('Y-m-d')===$birthDate ? $birthDateParsed->format('d-M-Y') : '';
    $birthDateKhmer=$birthDateDisplay ? \App\Support\StaffProfile::dateKhmer($birthDateParsed) : '';
    $birthDateError=$errors->first('date_of_birth');
@endphp
<div class="col-md-6">
    <div class="premium-form-field staff-form-field {{ $birthDateDisplay?'has-value':'' }}">
        <label class="form-label" for="staff-birth-date-direct">Date of Birth</label>
        <div class="date-picker staff-birth-date-picker" data-premium-date-picker data-premium-date-format="DD-MMM-YYYY" data-premium-date-max="{{ today()->toDateString() }}">
            <output id="staff-birth-date-kh" class="staff-birth-date-kh staff-khmer" for="staff-birth-date-direct" data-staff-birth-date-kh aria-label="Date of Birth in Khmer" aria-live="polite">{{ $birthDateKhmer }}</output>
            <input id="staff-birth-date-direct" class="form-control {{ $birthDateError?'is-invalid':'' }}" type="text" data-premium-date-input value="{{ $birthDateDisplay }}" placeholder="DD-MMM-YYYY" autocomplete="off" aria-label="Date of Birth in English, DD-MMM-YYYY format" @if($birthDateError) aria-invalid="true" aria-describedby="staff-birth-date-error" @endif>
            <input id="staff-date_of_birth" type="hidden" name="date_of_birth" data-premium-date-value value="{{ is_string($birthDate)?$birthDate:'' }}">
            <button type="button" class="date-picker-trigger date-picker-calendar-button" data-premium-date-toggle aria-label="Open Date of Birth calendar" aria-expanded="false" aria-controls="staff-birth-date-calendar"><i class="ti ti-calendar" aria-hidden="true"></i></button>
            <div id="staff-birth-date-calendar" class="date-picker-popup d-none" role="dialog" aria-label="Choose Date of Birth">
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
    @if($birthDateError)<div id="staff-birth-date-error" class="invalid-feedback d-block">{{ $birthDateError }}</div>@endif
</div>
