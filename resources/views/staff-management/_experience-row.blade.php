<div class="staff-repeat-card" data-repeat-row>
    <div class="d-flex justify-content-between mb-3"><h4 class="mb-0"><i class="ti ti-building text-primary me-2" aria-hidden="true"></i>Previous Employment</h4><button type="button" class="btn btn-sm btn-icon btn-outline-danger" data-staff-remove aria-label="Remove previous employment"><i class="ti ti-x"></i></button></div>
    <div class="row g-3">
        @foreach(['company_name'=>'Employer','position'=>'Position','started_on'=>'From','ended_on'=>'To','responsibilities'=>'Remarks','employment_type'=>'Employment Type (Optional)'] as $key=>$label)
            @php($error=$errors->first('experiences.'.$index.'.'.$key))
            <div class="col-md-6"><div class="premium-form-field staff-form-field {{ in_array($key,['started_on','ended_on'])?'staff-temporal-field':'' }} {{ filled($row[$key]??null)?'has-value':'' }}"><label class="form-label" for="staff-experience-{{ $index }}-{{ $key }}">{{ $label }}</label><input id="staff-experience-{{ $index }}-{{ $key }}" class="form-control {{ $error?'is-invalid':'' }}" name="experiences[{{ $index }}][{{ $key }}]" value="{{ $row[$key]??'' }}" type="{{ in_array($key,['started_on','ended_on'])?'date':'text' }}" @if($error) aria-invalid="true" aria-describedby="experience-error-{{ $index }}-{{ $key }}" @endif></div>@if($error)<div id="experience-error-{{ $index }}-{{ $key }}" class="invalid-feedback d-block">{{ $error }}</div>@endif</div>
        @endforeach
        <div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" name="experiences[{{ $index }}][is_current]" value="1" @checked(!empty($row['is_current']))><span class="form-check-label">Still employed here</span></label></div>
    </div>
</div>
