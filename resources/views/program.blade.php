@extends('layouts.app')
@section('title', 'Programs')
@section('page-header')<div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Academic Setup</div>
                <h2 class="page-title">Programs / Curriculums</h2>
            </div>
            <div class="col-auto"><button class="btn btn-primary" id="newProgram"><i class="ti ti-plus icon"></i> New
                    Program</button></div>
        </div>
</div>@endsection
@section('content')<div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Program Lists</h3>
            </div>
            <div class="card-body border-bottom py-3 d-flex justify-content-end">
                <input type="hidden" id="programs-per-page" value="10">
                <input id="programs-search" class="form-control form-control-sm w-auto"
                    placeholder="Search programs">
            </div>
            <div class="table-responsive">
                <table class="table card-table">
                    <thead>
                        <tr>
                            <th>Program</th>
                            <th>Code</th>
                            <th>Academic Year</th>
                            <th>Level</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="programsTable"></tbody>
                </table>
            </div>
            <div class="card-footer">
                <div id="programs-pagination-container"></div>
            </div>
        </div>
    </div>
    <div class="modal modal-blur fade" id="programModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
            <div class="modal-content">
                <form id="programForm" novalidate>
                    @csrf
                    <input type="hidden" id="program_id" name="program_id">
                    <div class="modal-header">
                        <h5 id="programModalTitle" class="modal-title">Create Program</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" data-alert></div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Academic Year</label>
                                <select id="academic_year_id" name="academic_year_id" class="form-select"></select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Education Level <span class="text-danger">*</span></label>
                                <select id="education_level_id" name="education_level_id" class="form-select"></select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Program Name <span class="text-danger">*</span></label>
                                <input id="program_name" name="program_name" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Program Code <span class="text-danger">*</span></label>
                                <input id="program_code" name="program_code" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea id="description" name="description" class="form-control"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Status</label>
                                <select id="status" name="status" class="form-select">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn me-auto" data-bs-dismiss="modal">Close</button>
                        <button class="btn btn-primary" id="programSubmit">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @vite('resources/js/program.js')
    <script>
        document.getElementById('programModal')?.addEventListener('shown.bs.modal', () => {
            ['academic_year_id', 'education_level_id'].forEach((id) => {
                const select = document.getElementById(id);
                if (select && select.options.length && select.options[0].value === '') {
                    select.remove(0);
                }
            });
        });
    </script>
@endsection
