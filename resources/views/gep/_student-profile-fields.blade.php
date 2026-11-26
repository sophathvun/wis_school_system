<div class="row g-3">
    <div class="col-12 col-sm-6 col-xl-3"><div class="gep-field has-value" data-gep-always-float><input id="gep-student-number" class="form-control" readonly placeholder="Assigned automatically"><label for="gep-student-number">Student No. (Auto)</label></div></div>
    <div class="col-12 col-sm-6 col-xl-3"><div class="gep-field"><input id="gep-form-student_identifier" name="student_identifier" class="form-control" maxlength="30" placeholder=" " data-profile-field><label for="gep-form-student_identifier">GEP Student ID (Auto if blank)</label></div></div>
    <div class="col-12 col-sm-6 col-xl-3"><div class="gep-field"><select id="gep-form-existing_family_id" name="existing_family_id" data-profile-field aria-label="Existing GEP Family / Sibling"></select><label for="gep-form-existing_family_id">Existing GEP Family / Sibling</label></div></div>
    <div class="col-12 col-sm-6 col-xl-3"><div class="gep-field"><input id="gep-form-family_number" name="family_number" class="form-control" maxlength="30" placeholder=" " data-profile-field><label for="gep-form-family_number">Family Number (Auto)</label></div></div>
    @foreach(['full_name_en'=>'Full Name (English)','full_name_kh'=>'Full Name (Khmer)'] as $name=>$label)
        <div class="col-12 col-sm-6 col-xl-3"><div class="gep-field"><input id="gep-form-{{ $name }}" name="{{ $name }}" class="form-control" placeholder=" " maxlength="160" data-profile-field @if($name==='full_name_en') data-profile-required @endif><label for="gep-form-{{ $name }}">{{ $label }} @if($name==='full_name_en')<span class="text-danger">*</span>@endif</label></div></div>
    @endforeach
    <div class="col-12 col-sm-6 col-xl-3"><div class="gep-field"><select id="gep-form-gender" name="gender" data-profile-field><option value=""></option><option value="M">ប្រុស / Male</option><option value="F">ស្រី / Female</option></select><label for="gep-form-gender">Gender (Khmer / English)</label></div></div>
    <div class="col-12 col-sm-6 col-xl-3"><div class="gep-field"><select id="gep-form-nationality_country_id" name="nationality_country_id" data-country-select="nationality" data-profile-field></select><label for="gep-form-nationality_country_id">Nationality (Khmer / English)</label></div></div>
    <div class="col-12 col-sm-6 col-xl-3">@include('gep._birth-date')</div>
    <div class="col-12 col-sm-6 col-xl-3"><div class="gep-field"><input id="gep-dob-kh" class="form-control school-profile-khmer" readonly placeholder=" "><label for="gep-dob-kh">ថ្ងៃខែឆ្នាំកំណើត (Date of Birth)</label></div></div>
    <div class="col-12 col-sm-6 col-xl-3"><div class="gep-field gep-home-phone-field">
        <input id="gep-home-phone-display" class="form-control" type="tel" autocomplete="tel" maxlength="50" aria-label="Home Phone">
        <input id="gep-form-home_phone" name="home_phone" type="hidden" data-profile-field>
        <label for="gep-home-phone-display">Home Phone</label>
    </div></div>
    <div class="col-12 col-sm-6 col-xl-3"><div class="gep-field gep-email-field" data-gep-always-float>
        <div class="input-icon gep-email-control"><i class="ti ti-mail" aria-hidden="true"></i><input type="email" id="gep-form-email" name="email" class="form-control" placeholder=" " maxlength="150" data-profile-field></div>
        <label for="gep-form-email">Email</label>
    </div></div>
    <div class="col-12"><div class="gep-field"><textarea id="gep-form-remarks" name="remarks" class="form-control" placeholder=" " maxlength="5000" data-profile-field></textarea><label for="gep-form-remarks">Remarks</label></div></div>
</div>
@foreach(['birth'=>'Place of Birth','address'=>'Home Address'] as $prefix=>$title)
    <div class="gep-profile-subsection"><h4>{{ $title }}</h4><div class="gep-location-grid">
        @foreach(['country'=>'Country','province'=>'Province / City','district'=>'District / Khan','commune'=>'Commune','village'=>'Village'] as $type=>$label)
            <div class="gep-field"><select id="gep-form-{{ $prefix }}_{{ $type }}_id" name="{{ $prefix }}_{{ $type }}_id" data-location-prefix="{{ $prefix }}" data-location-type="{{ $type }}" data-profile-field @if($type==='country') data-country-select="country" @endif></select><label for="gep-form-{{ $prefix }}_{{ $type }}_id">{{ $label }}</label></div>
        @endforeach
    </div>
    @if($prefix==='address')
        @foreach(['en'=>'English','kh'=>'Khmer'] as $lang=>$language)
            <div class="gep-address-grid mt-3">
                @foreach(['house_no'=>'House No.','street'=>'Street'] as $field=>$label)
                    <div class="gep-field"><input id="gep-form-address_{{ $field }}_{{ $lang }}" name="address_{{ $field }}_{{ $lang }}" class="form-control" placeholder=" " maxlength="{{ $field==='street'?150:100 }}" data-profile-field><label for="gep-form-address_{{ $field }}_{{ $lang }}">{{ $label }} ({{ $language }})</label></div>
                @endforeach
                <div class="gep-field"><textarea id="gep-form-current_address_{{ $lang }}" name="current_address_{{ $lang }}" class="form-control" placeholder=" " maxlength="2000" data-profile-field></textarea><label for="gep-form-current_address_{{ $lang }}">Current Address ({{ $language }})</label></div>
            </div>
        @endforeach
    @endif
    </div>
@endforeach
<div class="row g-3 mt-1">
    @foreach(['previous_school'=>'Previous School','experienced_english'=>'English Learning Experience','test_result'=>'Placement Test Result','tested_by'=>'Tested By'] as $name=>$label)
        <div class="col-12 col-sm-6 col-xl-3"><div class="gep-field"><input id="gep-form-{{ $name }}" name="{{ $name }}" class="form-control" placeholder=" " maxlength="{{ in_array($name,['experienced_english','test_result'])?5000:160 }}" data-profile-field><label for="gep-form-{{ $name }}">{{ $label }}</label></div></div>
    @endforeach
</div>
