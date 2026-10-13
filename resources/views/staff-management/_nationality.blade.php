@php
    $nationalityEn=old('nationality',$editStaff?->nationality);
    $nationalityKh=old('nationality_kh',$editStaff?->nationality_kh);
    $nationalityMatches=$nationalityCountries->filter(fn($country)=>is_string($nationalityEn)
        && strcasecmp($country->nationality_name_en ?: $country->country_name_en,$nationalityEn)===0
        && (!$nationalityKh || ($country->nationality_name_kh ?: $country->country_name_kh)===$nationalityKh));
    $nationalityRecord=$nationalityCountries->firstWhere('id',$editStaff?->nationality_country_id) ?? ($nationalityMatches->count()===1?$nationalityMatches->first():null);
    $defaultNationality=$nationalityCountries->first(fn($country)=>in_array(strtolower($country->country_code??''),['kh','khm'],true) || strtolower($country->country_name_en)==='cambodia');
    $nationalityId=old('nationality_country_id',$editStaff?->nationality_country_id ?? $nationalityRecord?->id ?? (!$editStaff && !filled($nationalityEn)?$defaultNationality?->id:null));
    $legacyNationality=filled($nationalityEn) && !$nationalityId;
    $nationalityError=$errors->first('nationality_country_id') ?: $errors->first('nationality');
@endphp
<div class="col-md-6">
    <div class="premium-form-field staff-form-field {{ $nationalityId || $legacyNationality?'has-value':'' }}">
        <label class="form-label" for="staff-nationality-country">Nationality</label>
        <input type="hidden" name="nationality" value="{{ $nationalityEn }}">
        <input type="hidden" name="nationality_kh" value="{{ $nationalityKh }}">
        <input type="hidden" name="nationality_note" value="{{ \App\Support\StaffProfile::nationalityNote(is_string($nationalityEn)?$nationalityEn:null,is_string($nationalityKh)?$nationalityKh:null) }}">
        <select id="staff-nationality-country" class="form-select {{ $nationalityError?'is-invalid':'' }}" name="nationality_country_id" data-staff-nationality @if($nationalityError) aria-invalid="true" aria-describedby="staff-nationality-error" @endif>
            <option value="" @selected(!$nationalityId && !$legacyNationality)>Select Nationality</option>
            @if($legacyNationality)<option value="" data-en="{{ $nationalityEn }}" data-kh="{{ $nationalityKh }}" selected>{{ $nationalityKh }} / {{ $nationalityEn }}</option>@endif
            @foreach($nationalityCountries as $country)
                @php($nationalityFlag=$country->flag_path ? (\Illuminate\Support\Str::startsWith($country->flag_path,['http://','https://','/'])?$country->flag_path:asset($country->flag_path)) : '')
                <option value="{{ $country->id }}" data-en="{{ $country->nationality_name_en ?: $country->country_name_en }}" data-kh="{{ $country->nationality_name_kh ?: $country->country_name_kh }}" data-flag="{{ $nationalityFlag }}" @selected((string)$nationalityId===(string)$country->id)>{{ $country->nationality_name_kh ?: $country->country_name_kh }} / {{ $country->nationality_name_en ?: $country->country_name_en }}</option>
            @endforeach
        </select>
    </div>
    @if($nationalityError)<div id="staff-nationality-error" class="invalid-feedback d-block">{{ $nationalityError }}</div>@endif
</div>
