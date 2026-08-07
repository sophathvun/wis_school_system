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
</style>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Families</h3>
    </div>
    <div class="card-body border-bottom py-3 d-flex justify-content-between">
        <div>Show <select id="families-per-page" class="form-control form-control-sm d-inline-block w-auto"><option selected>10</option><option>25</option><option>50</option><option>100</option></select> entries</div>
        <input id="families-search" class="form-control form-control-sm w-auto" placeholder="Search family">
    </div>
    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead><tr><th>Family Number</th><th>Family Name</th><th>Mother Information</th><th>Father Information</th><th>Phone</th><th>Email</th><th>Students</th><th>Status</th><th class="text-center">Actions</th></tr></thead>
            <tbody id="familiesTable"></tbody>
        </table>
    </div>
    <div class="card-footer"><div id="families-pagination-container"></div></div>
</div>

<div class="modal modal-blur fade" id="membersModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
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
                <div class="modal-body">
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="familyForm">
                @csrf
                <input type="hidden" id="family_id" name="family_id">
                <div class="modal-header"><h5 id="familyModalTitle" class="modal-title">New Family</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" data-alert></div>
                    <div class="mb-3"><label class="form-label">Family Number *</label><input id="family_number" name="family_number" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Family Name</label><input id="family_name" name="family_name" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Family Name (Khmer)</label><input id="family_name_kh" name="family_name_kh" class="form-control school-profile-khmer"></div>
                    <div class="row g-3"><div class="col-md-6"><label class="form-label">Primary Phone</label><input id="primary_phone" name="primary_phone" class="form-control"></div><div class="col-md-6"><label class="form-label">Primary Email</label><input id="primary_email" name="primary_email" type="email" class="form-control"></div></div>
                    <div class="mt-3"><label class="form-label">Address</label><textarea id="address" name="address" class="form-control" rows="3"></textarea></div>
                    <div class="mt-3"><label class="form-label">Status</label><select id="family_status" name="status" class="form-select"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Save Family</button></div>
            </form>
        </div>
    </div>
</div>
@vite('resources/js/families.js')
@endsection
