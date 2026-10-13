            <form method="POST" action="{{ route('hrm.settings.'.$activeTab.'.save') }}" data-hrm-setting-form>
                @csrf
                <input type="hidden" name="{{ $formMarker }}" value="1">
                <input type="hidden" name="{{ $idField }}" value="{{ old($idField, $editRecord?->id) }}">
                <div class="modal-body">
                    @if($errors->any() && old($formMarker))<div class="alert alert-danger" role="alert" data-hrm-setting-errors><strong>Please check the form.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                    <div class="row g-3">
                    <div class="col-md-6"><div class="premium-form-field {{ old('name', $editRecord?->name) ? 'has-value' : '' }}">
                        <label class="form-label" for="hrmSettingName">{{ $entityLabel }} Name <span class="text-danger">*</span></label>
                        <input id="hrmSettingName" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $editRecord?->name) }}" required maxlength="120" @error('name') aria-invalid="true" @enderror>
                    </div></div>
                    <div class="col-md-6"><div class="premium-form-field {{ old('code', $editRecord?->code) ? 'has-value' : '' }}">
                        <label class="form-label" for="hrmSettingCode">Code <span class="text-danger">*</span></label>
                        <input id="hrmSettingCode" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $editRecord?->code) }}" required maxlength="80" @error('code') aria-invalid="true" @enderror>
                    </div></div>
                    @if($isPosition)
                        <div class="col-md-6"><div class="premium-form-field has-value hrm-settings-parent-field">
                            <label class="form-label" for="hrmSettingDepartment">Department <span class="text-danger">*</span></label>
                            <select id="hrmSettingDepartment" name="department_id" class="form-select @error('department_id') is-invalid @enderror" required>
                                <option value="">Select Department</option>
                                @foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id', $editRecord?->department_id) == $department->id)>{{ $department->hierarchy_name }}</option>@endforeach
                                @if($editRecord?->department && !$departments->contains('id', $editRecord->department_id))
                                    <option value="{{ $editRecord->department_id }}" data-hrm-edit-only @selected(old('department_id', $editRecord->department_id) == $editRecord->department_id)>{{ $editRecord->department->hierarchy_name }} (Inactive)</option>
                                @endif
                            </select>
                        </div></div>
                    @else
                        <div class="col-md-6"><div class="premium-form-field has-value hrm-settings-parent-field">
                            <label class="form-label" for="hrmSettingParent">Parent Department</label>
                            <select id="hrmSettingParent" name="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
                                <option value="">None — Main Department</option>
                                @foreach($allParentDepartments as $parent)<option value="{{ $parent->id }}" @selected(old('parent_id', $editRecord?->parent_id) == $parent->id) @disabled($editRecord && !$parentDepartments->contains('id', $parent->id))>{{ $parent->hierarchy_name }}{{ $parent->status ? '' : ' (Inactive)' }}</option>@endforeach
                            </select>
                        </div></div>
                    @endif
                    <div class="col-md-6"><div class="premium-form-field has-value">
                        <label class="form-label" for="hrmSettingStatus">Status</label>
                        @unless($canStatusSetting)<input type="hidden" name="status" value="{{ $status }}">@endunless
                        <select id="hrmSettingStatus" @if($canStatusSetting) name="status" @else disabled @endif class="form-select"><option value="1" @selected($status === '1')>Active</option><option value="0" @selected($status === '0')>Inactive</option></select>
                    </div></div>
                </div></div>
                <div class="modal-footer d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><span data-hrm-setting-submit-label>{{ $editRecord ? 'Update' : 'Save' }} {{ $entityLabel }}</span></button>
                </div>
            </form>
