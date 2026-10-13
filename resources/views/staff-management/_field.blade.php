@php
    $value=old($name,$value??$editStaff?->{$name});
    if ($value instanceof \DateTimeInterface) $value=$value->format('Y-m-d');
    $error=$errors->first($name);
@endphp
<div class="{{ $width??'col-md-6' }}">
    <div class="premium-form-field {{ filled($value)?'has-value':'' }} staff-form-field {{ in_array($type??'text',['date','time'])?'staff-temporal-field':'' }}">
    <label class="form-label" for="staff-{{ $name }}">{{ $label }} @if($required??false)<span class="text-danger">*</span>@endif</label>
    @if(isset($options))
        <select id="staff-{{ $name }}" class="form-select {{ $error?'is-invalid':'' }}" name="{{ $name }}" @if($searchable??false) data-staff-work-select @endif @if($bilingualGender??false) data-staff-gender @endif @required($required??false) @if($error) aria-invalid="true" aria-describedby="staff-error-{{ $name }}" @endif>
            @unless($required??false)<option value=""></option>@endunless
            @foreach($options as $key=>$text)<option value="{{ $key }}" @if(isset($optionDepartments) && array_key_exists($key,$optionDepartments)) data-department="{{ $optionDepartments[$key] }}" @endif @selected((string)$value===(string)$key)>{{ $text }}</option>@endforeach
            @if(filled($value) && !array_key_exists((string)$value,$options))<option value="{{ $value }}" selected>{{ $value }}</option>@endif
        </select>
        @if($showArrow??false)<i class="ti ti-chevron-down staff-select-chevron" aria-hidden="true"></i>@endif
    @elseif(($type??'text')==='textarea')
        <textarea id="staff-{{ $name }}" class="form-control {{ str_ends_with($name,'_kh')?'staff-khmer':'' }} {{ $error?'is-invalid':'' }}" name="{{ $name }}" rows="3" @readonly($readonly??false) @if($error) aria-invalid="true" aria-describedby="staff-error-{{ $name }}" @endif>{{ $value }}</textarea>
    @else
        <input id="staff-{{ $name }}" class="form-control {{ str_ends_with($name,'_kh')?'staff-khmer':'' }} {{ $error?'is-invalid':'' }}" type="{{ $type??'text' }}" name="{{ $name }}" value="{{ $value }}" @required($required??false) @readonly($readonly??false) @if(($type??'text')==='time') step="60" @endif @if(($type??'text')==='email') inputmode="email" @endif @if($error) aria-invalid="true" aria-describedby="staff-error-{{ $name }}" @endif>
    @endif
    </div>
    @if($error)<div id="staff-error-{{ $name }}" class="invalid-feedback d-block">{{ $error }}</div>@endif
</div>
