<div class="staff-repeat-card" data-repeat-row>
    <div class="d-flex justify-content-between mb-3"><h4 class="mb-0"><i class="ti ti-school text-primary me-2" aria-hidden="true"></i>Education</h4><button type="button" class="btn btn-sm btn-icon btn-outline-danger" data-staff-remove aria-label="Remove education"><i class="ti ti-x"></i></button></div>
    <div class="row g-3">
        @foreach(['degree'=>'Degree','field_of_study'=>'Major','institution_name'=>'Institution / School / University','country'=>'Country','graduated_year'=>'Year of Graduation','notes'=>'Remarks','started_on'=>'From (Optional)','ended_on'=>'To (Optional)','grade_or_result'=>'Result (Optional)'] as $key=>$label)
            @php($error=$errors->first('educations.'.$index.'.'.$key))
            <div class="{{ in_array($key,['institution_name','notes'])?'col-md-6':'col-md-4' }}"><div class="premium-form-field staff-form-field {{ in_array($key,['started_on','ended_on'])?'staff-temporal-field':'' }} {{ filled($row[$key]??null)?'has-value':'' }}"><label class="form-label" for="staff-education-{{ $index }}-{{ $key }}">{{ $label }}</label><input id="staff-education-{{ $index }}-{{ $key }}" class="form-control {{ $error?'is-invalid':'' }}" name="educations[{{ $index }}][{{ $key }}]" value="{{ $row[$key]??'' }}" type="{{ in_array($key,['started_on','ended_on'])?'date':($key==='graduated_year'?'number':'text') }}" @if($key==='graduated_year') min="1900" max="{{ now()->year }}" @endif @if($error) aria-invalid="true" aria-describedby="education-error-{{ $index }}-{{ $key }}" @endif></div>@if($error)<div id="education-error-{{ $index }}-{{ $key }}" class="invalid-feedback d-block">{{ $error }}</div>@endif</div>
        @endforeach
    </div>
</div>
