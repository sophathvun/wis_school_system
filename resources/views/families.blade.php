@extends('layouts.app')

@section('title', 'Family Management')

@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Students</div>
                <h2 class="page-title">Family Management</h2>
            </div>
        </div>
    </div>
@endsection

@section('content')

    <div class="card family-list-card">
        <div class="card-header family-list-header">
            <h3 class="card-title">Families</h3>
            <div class="family-list-actions ms-auto">
                <select id="families-per-page" class="d-none" aria-hidden="true">
                    <option selected>10</option>
                    <option>25</option>
                    <option>50</option>
                    <option>100</option>
                </select>
                <div class="input-icon family-list-search">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input id="families-search" class="form-control" placeholder="Search family">
                </div>
                <button class="btn btn-primary" id="newFamily"><i class="ti ti-plus icon"></i> New Family</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th><button type="button" class="table-sort" data-family-sort="family_number">Family Number</button></th>
                        <th><button type="button" class="table-sort" data-family-sort="mother_en">Mother Information (English)</button></th>
                        <th><button type="button" class="table-sort" data-family-sort="father_en">Father Information (English)</button></th>
                        <th>Students</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="familiesTable"></tbody>
            </table>
        </div>
        <div id="familiesMobileCards" class="family-mobile-cards"></div>
        <div class="card-footer">
            <div id="families-pagination-container"></div>
        </div>
    </div>

    <div class="modal modal-blur fade" id="membersModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="membersModalTitle" class="modal-title">Family Members</h5><button type="button"
                        class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="text-secondary" id="membersFamilyLabel"></div><button class="btn btn-primary btn-sm"
                            id="newMember"><i class="ti ti-plus icon"></i> Add Mother, Father or Guardian</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Relationship</th>
                                    <th>Phone</th>
                                    <th>Primary Contact</th>
                                    <th>Portal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="membersTable"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal modal-blur fade" id="changeStudentFamilyModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="changeStudentFamilyForm">
                    @csrf
                    <input type="hidden" id="change_family_student_id" name="student_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Change Student Family</h5><button type="button" class="btn-close"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" data-change-family-alert></div>
                        <div class="mb-3">
                            <div class="text-secondary small">Student</div>
                            <div id="change_family_student_label" class="fw-bold"></div>
                        </div>
                        <div class="premium-floating-field has-value">
                            <label class="form-label">New Family</label>
                            <select id="change_family_id" name="family_id" class="form-select d-none" required>
                                <option value=""></option>
                                @foreach ($families as $family)
                                    <option value="{{ $family->id }}">{{ $family->family_number }}</option>
                                @endforeach
                            </select>
                            <div id="change-family-combobox" class="location-combobox family-modal-combobox">
                                <button type="button" id="change-family-toggle" class="location-combobox-toggle">
                                    <span id="change-family-selected" class="location-combobox-selected"></span><i
                                        class="ti ti-chevron-down"></i>
                                </button>
                                <div id="change-family-menu" class="location-combobox-menu d-none">
                                    <input id="change-family-search" type="search"
                                        class="form-control location-combobox-search" placeholder="Search Family">
                                    <div id="change-family-results" class="location-combobox-results"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn me-auto"
                            data-bs-dismiss="modal">Close</button><button class="btn btn-primary"><i
                                class="ti ti-users-group icon"></i> Change Family</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal modal-blur fade" id="memberFormModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form id="memberForm">
                    @csrf
                    <input type="hidden" id="family_member_id" name="family_member_id">
                    <div class="modal-header">
                        <h5 id="memberFormTitle" class="modal-title">Add Family Member</h5><button type="button"
                            class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="max-height:calc(100vh - 190px); overflow-y:auto;">
                        <div class="alert alert-danger d-none" data-member-alert></div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Full Name (English) *</label><input
                                    name="full_name_en" id="member_full_name_en" class="form-control" required></div>
                            <div class="col-md-6"><label class="form-label">Full Name (Khmer)</label><input
                                    name="full_name_kh" id="member_full_name_kh" class="form-control school-profile-khmer">
                            </div>
                            <div class="col-md-4"><label class="form-label">Relationship *</label><select
                                    name="relationship_type" id="relationship_type" class="form-select">
                                    <option value="mother">Mother</option>
                                    <option value="father">Father</option>
                                    <option value="guardian">Guardian</option>
                                </select></div>
                            <div class="col-md-4"><label class="form-label">Phone</label><input name="phone"
                                    id="member_phone" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">Email</label><input name="email"
                                    id="member_email" type="email" class="form-control"></div>
                            <div class="col-md-6"><label class="form-label">Occupation</label><input name="occupation"
                                    id="member_occupation" class="form-control"></div>
                            <div class="col-md-6"><label class="form-label">Status</label><select name="status"
                                    id="member_status" class="form-select">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select></div>
                            <div class="col-md-4"><label class="form-check"><input class="form-check-input"
                                        type="checkbox" name="is_primary_contact" value="1"><span
                                        class="form-check-label">Primary contact</span></label></div>
                            <div class="col-md-4"><label class="form-check"><input class="form-check-input"
                                        type="checkbox" name="has_pickup_authorization" value="1"><span
                                        class="form-check-label">Pickup authorization</span></label></div>
                            <div class="col-md-4"><label class="form-check"><input class="form-check-input"
                                        type="checkbox" name="has_portal_access" value="1"><span
                                        class="form-check-label">Portal access</span></label></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn me-auto"
                            data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Save Member</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal modal-blur fade" id="familyModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="familyForm">
                    @csrf
                    <input type="hidden" id="family_id" name="family_id">
                    <div class="modal-header">
                        <h5 id="familyModalTitle" class="modal-title">New Family</h5><button type="button"
                            class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="max-height:calc(100vh - 190px); overflow-y:auto;">
                        <div class="alert alert-danger d-none" data-alert></div>
                        <div class="mb-3 premium-floating-field"><label class="form-label">Family Number <span
                                    class="text-danger">*</span></label><input id="family_number" name="family_number"
                                class="form-control" required></div>
                        <div class="family-modal-members">
                            @foreach (['mother' => 'Mother', 'father' => 'Father', 'guardian' => 'Guardian'] as $relationship => $label)
                                <div class="family-member-row family-modal-member-row">
                                    <div class="family-member-heading">{{ $label }}</div>
                                    <div class="col-md-6 premium-floating-field"><label
                                            class="form-label">{{ $label }} Full Name (English)
                                            @if ($relationship !== 'guardian')
                                                <span class="text-danger">*</span>
                                            @endif
                                        </label><input class="form-control"
                                            name="members[{{ $relationship }}][full_name_en]"
                                            id="family_{{ $relationship }}_full_name_en"
                                            @if ($relationship !== 'guardian') required @endif></div>
                                    <div class="col-md-6 premium-floating-field"><label
                                            class="form-label">{{ $label }} Full Name (Khmer)</label><input
                                            class="form-control school-profile-khmer"
                                            name="members[{{ $relationship }}][full_name_kh]"
                                            id="family_{{ $relationship }}_full_name_kh"></div>
                                    <div class="col-md-6 premium-floating-field"><label
                                            class="form-label">Occupation</label><select class="form-select d-none"
                                            name="members[{{ $relationship }}][occupation_id]"
                                            id="family_{{ $relationship }}_occupation_id">
                                            <option value=""></option>
                                            @foreach ($occupations as $occupation)
                                                <option value="{{ $occupation->id }}"
                                                    data-kh="{{ $occupation->occupation_name_kh }}">
                                                    {{ $occupation->occupation_name_en }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div id="family-{{ $relationship }}-occupation-combobox"
                                            class="location-combobox family-modal-combobox"><button type="button"
                                                id="family-{{ $relationship }}-occupation-toggle"
                                                class="location-combobox-toggle"><span
                                                    id="family-{{ $relationship }}-occupation-selected"
                                                    class="location-combobox-selected"></span><i
                                                    class="ti ti-chevron-down"></i></button>
                                            <div id="family-{{ $relationship }}-occupation-menu"
                                                class="location-combobox-menu d-none"><input
                                                    id="family-{{ $relationship }}-occupation-search" type="search"
                                                    class="form-control location-combobox-search"
                                                    placeholder="Search Occupation">
                                                <div id="family-{{ $relationship }}-occupation-results"
                                                    class="location-combobox-results"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 premium-floating-field"><label
                                            class="form-label">Nationality</label><select class="form-select d-none"
                                            name="members[{{ $relationship }}][nationality_country_id]"
                                            id="family_{{ $relationship }}_nationality_country_id">
                                            <option value=""></option>
                                            @foreach ($countries as $country)
                                                <option value="{{ $country->id }}"
                                                    data-kh="{{ $country->nationality_name_kh }}"
                                                    data-flag="{{ $country->flag_path }}">
                                                    {{ $country->nationality_name_en ?: $country->country_name_en }}</option>
                                            @endforeach
                                        </select>
                                        <div id="family-{{ $relationship }}-nationality-combobox"
                                            class="location-combobox family-modal-combobox"><button type="button"
                                                id="family-{{ $relationship }}-nationality-toggle"
                                                class="location-combobox-toggle"><span
                                                    id="family-{{ $relationship }}-nationality-selected"
                                                    class="location-combobox-selected"></span><i
                                                    class="ti ti-chevron-down"></i></button>
                                            <div id="family-{{ $relationship }}-nationality-menu"
                                                class="location-combobox-menu d-none"><input
                                                    id="family-{{ $relationship }}-nationality-search" type="search"
                                                    class="form-control location-combobox-search"
                                                    placeholder="Search Nationality">
                                                <div id="family-{{ $relationship }}-nationality-results"
                                                    class="location-combobox-results"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 premium-floating-field"><label class="form-label">Phone Number
                                            @if ($relationship !== 'guardian')
                                                <span class="text-danger">*</span>
                                            @endif
                                        </label>
                                        <div class="phone-input-group"><input type="text" class="form-control"
                                                id="family_{{ $relationship }}_phone_number"
                                                @if ($relationship !== 'guardian') required @endif><input
                                                type="hidden" name="members[{{ $relationship }}][phone]"
                                                id="family_{{ $relationship }}_phone"></div>
                                    </div>
                                    <div class="col-md-6 premium-floating-field"><label class="form-label">Work
                                            Place</label><input class="form-control"
                                            name="members[{{ $relationship }}][workplace]"
                                            id="family_{{ $relationship }}_workplace"></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-3"><select id="family_status" name="status" class="d-none">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select><button type="button" class="status-toggle is-active" id="familyStatusToggle"
                                aria-label="Toggle family status" aria-pressed="true"><span
                                    class="status-toggle-label">ON</span><span class="status-toggle-knob"></span></button>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn me-auto"
                            data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Save Family</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @vite('resources/js/families.js')
    @vite('resources/css/pages/families.css')
@endsection
