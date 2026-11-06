@extends('layouts.app')
@section('title', 'G9 Setting')
@section('page-header', '')
@section('content')
    @vite(['resources/css/pages/g9-national-exams.css', 'resources/css/pages/g9-settings.css', 'resources/js/g9Settings.js'])
    <div class="g9-settings" data-g9-settings data-base="{{ url('/g9-national-exams/settings') }}">
        <section class="card g9-settings-context">
            <div class="card-header g9-header">
                <span class="g9-settings-header-icon" aria-hidden="true"><i class="ti ti-adjustments-horizontal"></i></span>
                <div><h3 class="card-title">Settings Scope</h3><p>Choose the academic year and exam center.</p></div>
                <span class="g9-settings-access"><i class="ti {{ $canManage ? 'ti-lock-open' : 'ti-lock' }}" aria-hidden="true"></i>{{ $canManage ? 'Manage settings' : 'Read only' }}</span>
            </div>
            <div class="card-body g9-editor-grid">
                <div class="premium-form-field staff-form-field"><label class="form-label" for="g9SettingYear">Academic Year</label><select class="form-select" id="g9SettingYear" data-setting-year>@foreach($years as $year)<option value="{{ $year->id }}" @selected($year->id == $configuredYear)>{{ $year->academic_year }}</option>@endforeach</select></div>
                <div class="premium-form-field staff-form-field"><label class="form-label" for="g9SettingCenter">Exam Center</label><select class="form-select" id="g9SettingCenter" data-setting-center><option value="">Academic-year defaults</option></select></div>
            </div>
        </section>
        <div class="g9-settings-layout">
            <aside class="card g9-settings-nav" aria-label="G9 settings navigation">
                <div class="card-header g9-header"><h3 class="card-title">Settings</h3><button class="btn g9-settings-nav-toggle" type="button" data-settings-nav-toggle aria-expanded="true" aria-controls="g9SettingsNav" aria-label="Collapse settings navigation"><i class="ti ti-layout-sidebar-left-collapse" aria-hidden="true"></i></button></div>
                <div id="g9SettingsNav" class="g9-settings-nav-body" data-settings-nav-body>
                    <div class="g9-settings-tabs" role="tablist" aria-label="Settings sections" aria-orientation="vertical">
                        <button type="button" id="g9CenterTab" role="tab" aria-selected="true" aria-controls="g9CenterPanel" data-settings-tab="center"><span class="g9-settings-tab-icon"><i class="ti ti-building-community" aria-hidden="true"></i></span><span><strong>Exam Center</strong><small>Location &amp; signature</small></span><i class="ti ti-chevron-right" aria-hidden="true"></i></button>
                        <button type="button" id="g9DatesTab" role="tab" tabindex="-1" aria-selected="false" aria-controls="g9DatesPanel" data-settings-tab="dates"><span class="g9-settings-tab-icon"><i class="ti ti-calendar-event" aria-hidden="true"></i></span><span><strong>Document Dates</strong><small>Issue dates &amp; defaults</small></span><i class="ti ti-chevron-right" aria-hidden="true"></i></button>
                        <button type="button" id="g9CampusTab" role="tab" tabindex="-1" aria-selected="false" aria-controls="g9EoyCampusSettings" data-settings-tab="campus"><span class="g9-settings-tab-icon"><i class="ti ti-users" aria-hidden="true"></i></span><span><strong>Campus Names</strong><small>Annual score card</small></span><i class="ti ti-chevron-right" aria-hidden="true"></i></button>
                    </div>
                    <div class="g9-settings-nav-note"><i class="ti ti-info-circle" aria-hidden="true"></i><span>Center settings apply to the selected academic year. Campus names can be shared across all years.</span></div>
                </div>
            </aside>
            <div class="g9-settings-content">
                <form data-date-settings novalidate>
                    <section class="card" id="g9CenterPanel" role="tabpanel" aria-labelledby="g9CenterTab" tabindex="0" data-settings-panel="center">
                        <div class="card-header g9-header"><span class="g9-settings-header-icon" aria-hidden="true"><i class="ti ti-building-community"></i></span><div><h3 class="card-title" data-center-settings-title>Exam Center Settings</h3><p>Location, signing authority, and student photo signature.</p></div></div>
                        <div class="card-body">
                            <div class="g9-editor-grid">
                                <div class="premium-form-field"><label class="form-label" for="g9DateLocation">City / Province</label><input class="form-control school-profile-khmer" id="g9DateLocation" data-setting-location maxlength="150" placeholder=" " disabled></div>
                                <div class="premium-form-field" data-center-only hidden><label class="form-label" for="g9CenterDepartment">Education Department</label><input class="form-control school-profile-khmer" id="g9CenterDepartment" data-center-field="department" maxlength="255" placeholder=" " disabled></div>
                                <div class="premium-form-field" data-center-only hidden><label class="form-label" for="g9CenterSigner">Signer Title</label><input class="form-control school-profile-khmer" id="g9CenterSigner" data-center-field="signer" maxlength="150" placeholder=" " disabled></div>
                                <div class="premium-form-field" data-center-only hidden><label class="form-label" for="g9CenterSignerName">Signer Name</label><input class="form-control school-profile-khmer" id="g9CenterSignerName" data-center-field="signer_name" maxlength="255" placeholder=" " disabled></div>
                            </div>
                            <div class="g9-settings-signature" data-center-only hidden>
                                <div class="g9-settings-signature-intro"><span class="g9-settings-section-icon"><i class="ti ti-signature" aria-hidden="true"></i></span><div><h4>Student Photo Signature</h4><p>Appears on the student photo in សលាកបត្រឯកត្តជន. Use a transparent PNG for a clean result.</p></div></div>
                                <div class="g9-settings-signature-preview"><img data-signature-preview alt="Exam center signature" hidden></div>
                                <button type="button" class="g9-signature-dropzone" data-signature-dropzone disabled><i class="ti ti-cloud-upload" aria-hidden="true"></i><strong data-signature-title>Drop signature here</strong><span>or click to browse · PNG / JPG · Max 2 MB</span></button>
                                <input type="file" accept=".png,.jpg,.jpeg" data-signature-file aria-label="Upload exam center signature" hidden disabled>
                                <button type="button" class="btn btn-sm btn-outline-danger g9-settings-remove-signature" data-signature-remove disabled><i class="ti ti-trash me-1" aria-hidden="true"></i>Remove Signature</button>
                            </div>
                            <div class="g9-settings-notice"><i class="ti ti-info-circle" aria-hidden="true"></i><p data-setting-scope></p></div>
                        </div>
                    </section>
                    <section id="g9DatesPanel" role="tabpanel" aria-labelledby="g9DatesTab" tabindex="0" data-settings-panel="dates" hidden>
                        <div class="card g9-settings-dates-intro"><div class="card-header g9-header"><span class="g9-settings-header-icon" aria-hidden="true"><i class="ti ti-calendar-event"></i></span><div><h3 class="card-title">Document Dates</h3><p>Use academic-year defaults or set individual dates for this exam center.</p></div></div><div class="card-body"><i class="ti ti-sparkles" aria-hidden="true"></i><span>Choosing an issue date fills the Khmer date text and lunar date. You can adjust the text before saving.</span></div></div>
                        <div class="g9-date-settings-grid">
                            @foreach($sections as $key => $section)
                                <section class="card g9-settings-date-card" data-date-section="{{ $key }}">
                                    <div class="card-header"><span class="g9-settings-section-icon"><i class="ti ti-{{ $key === 'certDate' ? 'certificate' : ($key === 'cardDate' ? 'id' : 'file-description') }}" aria-hidden="true"></i></span><h4 class="card-title">{{ $section['label'] }}</h4><button class="btn btn-sm btn-outline-primary g9-settings-date-edit ms-auto" type="button" data-setting-edit-date aria-label="Edit {{ $section['label'] }}" hidden disabled><i class="ti ti-edit me-1" aria-hidden="true"></i>Edit Date</button></div>
                                    <div class="card-body">
                                        <label class="form-check form-switch g9-settings-inherit" data-inherit-control hidden><input class="form-check-input" type="checkbox" data-setting-inherit disabled><span class="form-check-label">Use academic-year default</span></label>
                                        <p class="g9-settings-date-state" id="g9DateState-{{ $key }}" data-setting-date-state>Loading date settings…</p>
                                        <div class="premium-form-field mb-3"><label class="form-label" for="g9Date-{{ $key }}">Issue Date</label><input class="form-control" type="date" id="g9Date-{{ $key }}" data-setting-date aria-label="{{ $section['label'] }}" @disabled(!$canManage)></div>
                                        <label class="form-label" for="g9Solar-{{ $key }}">Khmer Date Text</label><textarea class="form-control mb-3 school-profile-khmer" id="g9Solar-{{ $key }}" data-setting-solar rows="2" maxlength="1000" @disabled(!$canManage)></textarea>
                                        <label class="form-label" for="g9Lunar-{{ $key }}">Khmer Lunar Date</label><textarea class="form-control school-profile-khmer" id="g9Lunar-{{ $key }}" data-setting-lunar rows="2" maxlength="1000" @disabled(!$canManage)></textarea>
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    </section>
                    <div class="card g9-settings-actions" data-settings-actions><div class="card-body"><div class="g9-settings-status"><i class="ti ti-info-circle" aria-hidden="true"></i><span data-setting-message role="status" aria-live="polite"></span></div>@if($canManage)<button type="submit" class="btn btn-primary" data-setting-save disabled><i class="ti ti-device-floppy me-2" aria-hidden="true"></i>Save Settings</button>@endif</div></div>
                </form>
                <section class="card" id="g9EoyCampusSettings" role="tabpanel" aria-labelledby="g9CampusTab" tabindex="0" data-settings-panel="campus" data-eoy-campus-settings data-url="{{ url('/g9-national-exams/settings/eoy-campus') }}" hidden>
                    <div class="card-header g9-header"><span class="g9-settings-header-icon" aria-hidden="true"><i class="ti ti-users"></i></span><div><h3 class="card-title">Annual Score Card · Campus Name List</h3><p>Manage all-year defaults and names for the selected academic year.</p></div><button type="button" class="btn btn-light ms-auto" data-eoy-campus-new hidden disabled><i class="ti ti-plus me-1" aria-hidden="true"></i>New</button></div>
                    <div class="table-responsive g9-settings-campus-list"><table class="table table-vcenter mb-0"><thead><tr><th>No.</th><th>Campus</th><th>Applies To</th><th>អ្នកស្រង់ពិន្ទុ</th><th>ឈ្មោះនាយកសាខា</th><th class="text-end">Action</th></tr></thead><tbody data-eoy-campus-list></tbody></table></div>
                    <div class="g9-settings-campus-list-footer"><span data-eoy-campus-list-message role="status" aria-live="polite"></span><small>Year-specific names take priority over all-year defaults.</small></div>
                </section>
            </div>
        </div>
        <div class="modal fade g9-settings-campus-modal" id="g9EoyCampusModal" data-eoy-campus-modal tabindex="-1" aria-labelledby="g9EoyCampusModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><form class="modal-content" data-eoy-campus-form>
                <div class="modal-header g9-header"><span class="g9-settings-header-icon" aria-hidden="true"><i class="ti ti-user-edit"></i></span><div><h3 class="modal-title" id="g9EoyCampusModalTitle" data-eoy-campus-title>New Campus Names</h3><p>Annual score card signing names</p></div><button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" data-eoy-dismiss aria-label="Close"></button></div>
                <div class="modal-body">
                    <p class="g9-settings-campus-year"><i class="ti ti-calendar-event me-1" aria-hidden="true"></i><span data-eoy-campus-year></span></p>
                    <div class="g9-editor-grid">
                        <div class="premium-form-field staff-form-field"><label class="form-label" for="g9EoyCampus">Campus</label><select id="g9EoyCampus" name="campus_id" class="form-select" required>@foreach($eoyCampuses as $campus)<option value="{{ $campus->id }}">{{ $campus->campus_name_en }}</option>@endforeach</select></div>
                        <div class="premium-form-field staff-form-field"><label class="form-label" for="g9EoyScope">Apply To</label><select id="g9EoyScope" name="scope" class="form-select"><option value="all">All academic years (default)</option><option value="year">Selected academic year only</option></select></div>
                        <div class="premium-form-field"><label class="form-label" for="g9EoyCompiler">អ្នកស្រង់ពិន្ទុ</label><input id="g9EoyCompiler" name="compiler_name" class="form-control school-profile-khmer" maxlength="255" placeholder=" " disabled></div>
                        <div class="premium-form-field"><label class="form-label" for="g9EoyDirector">ឈ្មោះនាយកសាខា</label><input id="g9EoyDirector" name="director_name" class="form-control school-profile-khmer" maxlength="255" placeholder=" " disabled></div>
                    </div>
                    <div class="g9-settings-notice"><i class="ti ti-info-circle" aria-hidden="true"></i><p>All-year defaults apply to existing and future years without their own names. Saved year-specific names are preserved.</p></div>
                    <p class="g9-settings-campus-editor-message" data-eoy-campus-message role="status" aria-live="polite"></p>
                </div>
                <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal" data-eoy-dismiss>Cancel</button><button type="submit" class="btn btn-primary" data-eoy-campus-save disabled><i class="ti ti-device-floppy me-2" aria-hidden="true"></i>Save Campus Names</button></div>
            </form></div>
        </div>
    </div>
@endsection
