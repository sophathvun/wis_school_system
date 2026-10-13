@php
    $workingCampusIds=array_map('strval',(array)old('campuses',$editStaff?->campuses?->modelKeys()??[]));
    $workingPrimaryId=old('primary_campus_id',$editStaff?->primary_campus_id);
    if($workingPrimaryId && !in_array((string)$workingPrimaryId,$workingCampusIds,true)) $workingCampusIds[]=(string)$workingPrimaryId;
    $workingCampusChoices=$campuses->merge($editStaff?->campuses??collect())->unique('id')->sortBy('campus_name_en');
    $campusMessages=collect($errors->messages())->first(fn($messages,$key)=>str_starts_with($key,'campuses.'))??[];
    $workingCampusError=$errors->first('campuses') ?: ($campusMessages[0]??null);
@endphp
<div class="{{ $width??'col-md-6' }}">
    <div class="premium-form-field staff-form-field {{ count($workingCampusIds)?'has-value':'' }}">
        <label class="form-label" for="staff-working-campuses">Working Campuses</label>
        <select id="staff-working-campuses" class="form-select {{ $workingCampusError?'is-invalid':'' }}" name="campuses[]" multiple data-staff-working-campuses @if($workingCampusError) aria-invalid="true" aria-describedby="staff-working-campuses-error" @endif>
            @foreach($workingCampusChoices as $campus)<option value="{{ $campus->id }}" data-en="{{ $campus->campus_name_en }}" data-kh="{{ $campus->campus_name_kh }}" @selected(in_array((string)$campus->id,$workingCampusIds,true))>{{ $campus->campus_name_en }}</option>@endforeach
        </select>
    </div>
    @if($workingCampusError)<div id="staff-working-campuses-error" class="invalid-feedback d-block">{{ $workingCampusError }}</div>@endif
</div>
