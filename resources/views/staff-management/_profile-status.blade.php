@php
    $profileStatus=old('status',$editStaff?(int)$editStaff->status:1);
    $profileActive=(string)$profileStatus==='1';
    $profileStatusError=$errors->first('status');
@endphp
<div class="staff-work-details-field staff-profile-status-field">
    <label class="form-label" for="staffProfileStatusToggle">Profile Status <span class="text-danger">*</span></label>
    <input id="staff-status" type="hidden" name="status" value="{{ $profileStatus }}" data-staff-profile-status-value>
    <button id="staffProfileStatusToggle" type="button" class="status-toggle {{ $profileActive?'is-active':'' }}" data-staff-profile-status @disabled(!($canStatusStaff??false)) aria-pressed="{{ $profileActive?'true':'false' }}" aria-label="Profile Status: {{ $profileActive?'Active':'Inactive' }}" @if($profileStatusError) aria-invalid="true" aria-describedby="staff-profile-status-error" @endif>
        <span class="status-toggle-label">{{ $profileActive?'ON':'OFF' }}</span><span class="status-toggle-knob"></span>
    </button>
    @if($profileStatusError)<div id="staff-profile-status-error" class="invalid-feedback d-block">{{ $profileStatusError }}</div>@endif
</div>
