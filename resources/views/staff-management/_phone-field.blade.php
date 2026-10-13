@php
    $phoneValue=old($name,$editStaff?->{$name});
    $phoneError=$errors->first($name);
@endphp
<div class="{{ $width??'col-md-6' }}">
    <div class="premium-form-field staff-form-field staff-phone-field {{ filled($phoneValue)?'has-value':'' }}">
        <label class="form-label" for="staff-{{ $name }}-number">{{ $label }}</label>
        <div class="phone-input-group staff-phone-input" data-staff-phone>
            <input id="staff-{{ $name }}-number" class="form-control {{ $phoneError?'is-invalid':'' }}" type="tel" value="{{ $phoneValue }}" inputmode="tel" autocomplete="tel-national" placeholder=" " data-staff-phone-number @if($phoneError) aria-invalid="true" aria-describedby="staff-error-{{ $name }}" @endif>
            <input type="hidden" name="{{ $name }}" value="{{ $phoneValue }}" data-staff-phone-value>
        </div>
    </div>
    @if($phoneError)<div id="staff-error-{{ $name }}" class="invalid-feedback d-block">{{ $phoneError }}</div>@endif
</div>
