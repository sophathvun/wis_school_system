@php
    $maritalValue=old('marital_status',$editStaff?->marital_status);
    if (is_string($maritalValue) && array_key_exists(strtolower($maritalValue),$maritalLabels)) $maritalValue=strtolower($maritalValue);
    $maritalError=$errors->first('marital_status');
@endphp
<div class="col-md-6">
    <div class="premium-form-field staff-form-field {{ filled($maritalValue)?'has-value':'' }}">
        <label class="form-label" for="staff-marital-status">Marital Status</label>
        <select id="staff-marital-status" class="form-select {{ $maritalError?'is-invalid':'' }}" name="marital_status" data-staff-marital-status @if($maritalError) aria-invalid="true" aria-describedby="staff-marital-status-error" @endif>
            <option value="" @selected(!filled($maritalValue))>Select Marital Status</option>
            @foreach($maritalLabels as $value=>$english)
                <option value="{{ $value }}" data-en="{{ $english }}" data-kh="{{ \App\Support\StaffProfile::MARITAL_KH[$value]??'' }}" @selected($maritalValue===$value)>{{ \App\Support\StaffProfile::MARITAL_KH[$value]??'' }} / {{ $english }}</option>
            @endforeach
            @if(filled($maritalValue) && !array_key_exists((string)$maritalValue,$maritalLabels))<option value="{{ $maritalValue }}" data-en="{{ $maritalValue }}" selected>{{ $maritalValue }}</option>@endif
        </select>
    </div>
    @if($maritalError)<div id="staff-marital-status-error" class="invalid-feedback d-block">{{ $maritalError }}</div>@endif
</div>
