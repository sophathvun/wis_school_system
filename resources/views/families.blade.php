@extends('layouts.app')

@section('title', 'Family Management')

@section('page-header')
<div class="container-fluid">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Students</div>
            <h2 class="page-title">Family Management</h2>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" id="newFamily"><i class="ti ti-plus icon"></i> New Family</button>
        </div>
    </div>
</div>
@endsection

@section('content')
<style>
    .family-member-summary { min-width: 180px; line-height: 1.35; }
    .family-member-summary strong { display: block; }
    .family-member-summary .family-phone { display: block; margin-top: .2rem; }
    .family-member-summary .family-phone i { margin-right: .25rem; color: var(--tblr-primary); }
    .family-expand-toggle { width: 1.5rem; height: 1.5rem; display: inline-flex; align-items: center; justify-content: center; margin-left: .35rem; padding: 0; border: 0; border-radius: 50%; color: #6d28d9; background: transparent; transition: color .18s ease, background .18s ease; }
    .family-expand-toggle:hover, .family-expand-toggle:focus-visible { color: #4c1d95; background: rgba(109, 40, 217, .1); }
    .family-expand-toggle i { transition: transform .18s ease; }
    .family-expand-toggle.is-expanded i { transform: rotate(180deg); }
    .family-expanded-row > td { background: linear-gradient(135deg, rgba(109, 40, 217, .08), rgba(14, 165, 233, .05)); padding: .75rem 1rem; }
    .family-expanded-card { border: 1px solid rgba(109, 40, 217, .2); border-radius: .85rem; background: var(--tblr-bg-surface); padding: .75rem 1rem; }
    .family-student-photo { width: 42px; height: 56px; object-fit: cover; border-radius: .45rem; display: inline-flex; align-items: center; justify-content: center; background: var(--tblr-bg-surface-secondary); color: var(--tblr-secondary); }
    .family-student-list { display: grid; gap: .75rem; }
    .family-student-card { display: grid; grid-template-columns: minmax(250px, 1.25fr) minmax(150px, .7fr) minmax(310px, 1.7fr) minmax(90px, .4fr); gap: 1rem; align-items: center; padding: .9rem; border: 1px solid var(--tblr-border-color); border-radius: .8rem; background: var(--tblr-bg-surface); }
    .family-student-profile { display: flex; align-items: center; gap: 1.25rem; min-width: 0; }
    .family-student-photo-large { width: 72px; height: 96px; border-radius: .6rem; flex: 0 0 auto; }
    .family-student-identity { min-width: 0; }
    .family-student-name-kh, .family-student-name-en { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .family-student-name-kh { color: var(--tblr-secondary); font-size: 1.2rem; }
    .family-student-name-en { font-weight: 700; font-size: 1rem; }
    .family-student-gender { margin-top: .2rem; }
    .family-student-age { color: var(--tblr-primary); font-size: .85rem; font-weight: 600; margin-top: .35rem; }
    .family-detail-label { color: var(--tblr-secondary); font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; margin-bottom: .3rem; }
    .family-enrollment-header, .family-enrollment-item { display: grid; grid-template-columns: 1.05fr 1fr .8fr .45fr 7rem; gap: .45rem; align-items: center; text-align: center; }
    .family-enrollment-header { color: var(--tblr-secondary); font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; padding: .25rem .45rem .35rem; }
    .family-enrollment-item { padding: .3rem .45rem; border-bottom: 1px solid var(--tblr-border-color-translucent, rgba(98,105,118,.16)); font-size: .85rem; }
    .family-enrollment-status { display: flex; justify-content: center; align-items: center; min-width: 0; }
    .family-enrollment-status .badge { white-space: nowrap; }
    .family-enrollment-item:last-child { border-bottom: 0; }
    .family-enrollment-year { font-weight: 700; }
    .family-enrollment-campus, .family-enrollment-grade, .family-enrollment-group { color: var(--tblr-secondary); }
    .family-enrollment-grade { font-weight: 600; }
    @media (max-width: 1100px) { .family-student-card { grid-template-columns: minmax(240px, 1fr) minmax(140px, .7fr); } .family-student-enrollments { grid-column: 1 / -1; } .family-student-status { position: absolute; margin-left: 210px; } }
    @media (max-width: 640px) { .family-student-card { grid-template-columns: 1fr; } .family-student-status { position: static; margin-left: 0; } .family-student-enrollments { grid-column: auto; } }
    .family-student-photo-placeholder { font-size: 1.25rem; }
    .family-parent-editor { border: 1px solid var(--tblr-border-color); border-radius: .8rem; padding: .85rem; background: var(--tblr-bg-surface-secondary); }
    .family-parent-editor h5 { margin-bottom: .75rem; color: var(--tblr-primary); }
    #familyModal .modal-dialog { max-height: calc(100vh - 2rem); }
    #familyModal .modal-content { max-height: calc(100vh - 2rem); }
    #familyModal .modal-body { overflow-y: auto; }
</style>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Families</h3>
    </div>
    <div class="card-body border-bottom py-3 d-flex align-items-center justify-content-end">
        <select id="families-per-page" class="d-none" aria-hidden="true"><option selected>10</option><option>25</option><option>50</option><option>100</option></select>
        <input id="families-search" class="form-control form-control-sm" style="width:420px; max-width:100%; height:40px;" placeholder="Search family">
    </div>
    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead><tr><th>No.</th><th>Family Number</th><th>Mother Information</th><th>Father Information</th><th>Students</th><th>Status</th><th class="text-center">Actions</th></tr></thead>
            <tbody id="familiesTable"></tbody>
        </table>
    </div>
    <div class="card-footer"><div id="families-pagination-container"></div></div>
</div>

<div class="modal modal-blur fade" id="membersModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 id="membersModalTitle" class="modal-title">Family Members</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3"><div class="text-secondary" id="membersFamilyLabel"></div><button class="btn btn-primary btn-sm" id="newMember"><i class="ti ti-plus icon"></i> Add Mother, Father or Guardian</button></div>
                <div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Name</th><th>Relationship</th><th>Phone</th><th>Primary Contact</th><th>Portal</th><th></th></tr></thead><tbody id="membersTable"></tbody></table></div>
            </div>
        </div>
    </div>
</div>

<div class="modal modal-blur fade" id="memberFormModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="memberForm">
                @csrf
                <input type="hidden" id="family_member_id" name="family_member_id">
                <div class="modal-header"><h5 id="memberFormTitle" class="modal-title">Add Family Member</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body" style="max-height:calc(100vh - 190px); overflow-y:auto;">
                    <div class="alert alert-danger d-none" data-member-alert></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Full Name (English) *</label><input name="full_name_en" id="member_full_name_en" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Full Name (Khmer)</label><input name="full_name_kh" id="member_full_name_kh" class="form-control school-profile-khmer"></div>
                        <div class="col-md-4"><label class="form-label">Relationship *</label><select name="relationship_type" id="relationship_type" class="form-select"><option value="mother">Mother</option><option value="father">Father</option><option value="guardian">Guardian</option></select></div>
                        <div class="col-md-4"><label class="form-label">Phone</label><input name="phone" id="member_phone" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Email</label><input name="email" id="member_email" type="email" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Occupation</label><input name="occupation" id="member_occupation" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Status</label><select name="status" id="member_status" class="form-select"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                        <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_primary_contact" value="1"><span class="form-check-label">Primary contact</span></label></div>
                        <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="has_pickup_authorization" value="1"><span class="form-check-label">Pickup authorization</span></label></div>
                        <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="has_portal_access" value="1"><span class="form-check-label">Portal access</span></label></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Save Member</button></div>
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
                <div class="modal-header"><h5 id="familyModalTitle" class="modal-title">New Family</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body" style="max-height:calc(100vh - 190px); overflow-y:auto;">
                    <div class="alert alert-danger d-none" data-alert></div>
                    <div class="mb-3"><label class="form-label">Family Number *</label><input id="family_number" name="family_number" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Family Name</label><input id="family_name" name="family_name" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Family Name (Khmer)</label><input id="family_name_kh" name="family_name_kh" class="form-control school-profile-khmer"></div>
                    <div class="row g-3"><div class="col-md-6"><label class="form-label">Primary Phone</label><input id="primary_phone" name="primary_phone" class="form-control"></div><div class="col-md-6"><label class="form-label">Primary Email</label><input id="primary_email" name="primary_email" type="email" class="form-control"></div></div>
                    <div class="mt-3"><label class="form-label">Address</label><textarea id="address" name="address" class="form-control" rows="3"></textarea></div>
                    <div class="mt-4"><h4 class="mb-3">Mother, Father and Guardian Information</h4><div class="row g-3">
                        @foreach(['mother' => 'Mother', 'father' => 'Father', 'guardian' => 'Guardian'] as $relationship => $label)
                            <div class="col-12"><div class="family-parent-editor"><h5>{{ $label }}</h5><div class="row g-2">
                                <div class="col-md-3"><label class="form-label">Full Name (English)</label><input class="form-control" name="members[{{ $relationship }}][full_name_en]" id="family_{{ $relationship }}_full_name_en"></div>
                                <div class="col-md-3"><label class="form-label school-profile-khmer">Full Name (Khmer)</label><input class="form-control school-profile-khmer" name="members[{{ $relationship }}][full_name_kh]" id="family_{{ $relationship }}_full_name_kh"></div>
                                <div class="col-md-3"><label class="form-label">Occupation (English)</label><input class="form-control" name="members[{{ $relationship }}][occupation_en]" id="family_{{ $relationship }}_occupation_en"></div>
                                <div class="col-md-3"><label class="form-label school-profile-khmer">Occupation (Khmer)</label><input class="form-control school-profile-khmer" name="members[{{ $relationship }}][occupation_kh]" id="family_{{ $relationship }}_occupation_kh"></div>
                                <div class="col-md-3"><label class="form-label">Nationality (English)</label><input class="form-control" name="members[{{ $relationship }}][nationality_en]" id="family_{{ $relationship }}_nationality_en"></div>
                                <div class="col-md-3"><label class="form-label school-profile-khmer">Nationality (Khmer)</label><input class="form-control school-profile-khmer" name="members[{{ $relationship }}][nationality_kh]" id="family_{{ $relationship }}_nationality_kh"></div>
                                <div class="col-md-3"><label class="form-label">Phone</label><input class="form-control" name="members[{{ $relationship }}][phone]" id="family_{{ $relationship }}_phone"></div>
                                <div class="col-md-3"><label class="form-label">Work Place</label><input class="form-control" name="members[{{ $relationship }}][workplace]" id="family_{{ $relationship }}_workplace"></div>
                                <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="members[{{ $relationship }}][email]" id="family_{{ $relationship }}_email"></div>
                            </div></div></div>
                        @endforeach
                    </div></div>
                    <div class="mt-3"><select id="family_status" name="status" class="d-none"><option value="1">Active</option><option value="0">Inactive</option></select><button type="button" class="status-toggle is-active" id="familyStatusToggle" aria-label="Toggle family status" aria-pressed="true"><span class="status-toggle-label">ON</span><span class="status-toggle-knob"></span></button></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Save Family</button></div>
            </form>
        </div>
    </div>
</div>
@vite('resources/js/families.js')
@endsection
