@php
    $birthplaceEn=old('place_of_birth_en',$editStaff?->place_of_birth_en);
    $birthplaceKh=old('place_of_birth_kh',$editStaff?->place_of_birth_kh);
    $birthplaceMatches=$birthProvinces->filter(fn($province)=>$province->province_name_en===$birthplaceEn && (!$birthplaceKh || $province->province_name_kh===$birthplaceKh)
        && (!$editStaff?->birth_country_id || (int)$province->country_id===$editStaff->birth_country_id));
    $birthplaceRecord=$birthProvinces->firstWhere('id',$editStaff?->birth_province_id) ?? ($birthplaceMatches->count()===1?$birthplaceMatches->first():null);
    $birthCountryId=old('birth_country_id',$editStaff?->birth_country_id ?? $birthplaceRecord?->country_id ?? $defaultBirthCountryId);
    $birthplaceId=old('birth_province_id',$editStaff?->birth_province_id ?? $birthplaceRecord?->id);
    $legacyBirthplace=filled($birthplaceEn) && !$birthplaceId;
    $birthplaceError=$errors->first('birth_province_id') ?: $errors->first('place_of_birth_en');
@endphp
<div class="col-md-6">
    <div class="premium-form-field staff-form-field {{ $birthCountryId?'has-value':'' }}">
        <label class="form-label" for="staff-birth-country">Country of Birth</label>
        <select id="staff-birth-country" class="form-select {{ $errors->has('birth_country_id')?'is-invalid':'' }}" name="birth_country_id" data-staff-birth-country @if($errors->has('birth_country_id')) aria-invalid="true" aria-describedby="staff-birth-country-error" @endif>
            <option value="" @selected(!$birthCountryId)>Select Country</option>
            @foreach($birthCountries as $country)
                @php($countryFlag=$country->flag_path ? (\Illuminate\Support\Str::startsWith($country->flag_path,['http://','https://','/'])?$country->flag_path:asset($country->flag_path)) : '')
                <option value="{{ $country->id }}" data-en="{{ $country->country_name_en }}" data-kh="{{ $country->country_name_kh }}" data-flag="{{ $countryFlag }}" @selected((string)$birthCountryId===(string)$country->id)>{{ $country->country_name_kh }} / {{ $country->country_name_en }}</option>
            @endforeach
        </select>
    </div>
    @error('birth_country_id')<div id="staff-birth-country-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="col-md-6">
    <div class="premium-form-field staff-form-field {{ $birthplaceId || $legacyBirthplace?'has-value':'' }}">
        <label class="form-label" for="staff-birth-province">Province/City of Birth</label>
        <input type="hidden" name="place_of_birth_en" value="{{ $birthplaceEn }}">
        <input type="hidden" name="place_of_birth_kh" value="{{ $birthplaceKh }}">
        <select id="staff-birth-province" class="form-select {{ $birthplaceError?'is-invalid':'' }}" name="birth_province_id" data-staff-birth-province @if($birthplaceError) aria-invalid="true" aria-describedby="staff-birthplace-error" @endif>
            <option value="" @selected(!$birthplaceId && !$legacyBirthplace)>Select Province / City</option>
            @if($legacyBirthplace)<option value="" data-country="{{ $birthCountryId }}" data-en="{{ $birthplaceEn }}" data-kh="{{ $birthplaceKh }}" selected>{{ $birthplaceKh }} / {{ $birthplaceEn }}</option>@endif
            @foreach($birthProvinces as $province)<option value="{{ $province->id }}" data-country="{{ $province->country_id }}" data-en="{{ $province->province_name_en }}" data-kh="{{ $province->province_name_kh }}" @selected((string)$birthplaceId===(string)$province->id) @if((string)$province->country_id!==(string)$birthCountryId) hidden disabled @endif>{{ $province->province_name_kh }} / {{ $province->province_name_en }}</option>@endforeach
        </select>
    </div>
    @if($birthplaceError)<div id="staff-birthplace-error" class="invalid-feedback d-block">{{ $birthplaceError }}</div>@endif
</div>
