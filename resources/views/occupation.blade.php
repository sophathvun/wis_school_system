@extends('layouts.app')
@section('title', 'Occupations')
@section('page-header')<div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Settings</div>
                <h2 class="page-title">Occupations</h2>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-outline-primary" id="printOccupations"><i class="ti ti-printer icon"></i> Print</button>
                <button class="btn btn-outline-success" id="excelOccupations"><i class="ti ti-file-spreadsheet icon"></i> Excel</button>
                <button class="btn btn-primary" id="newOccupation"><i class="ti ti-plus icon"></i> New Occupation</button>
            </div>
        </div>
</div>@endsection
@section('content')
    <div class="card occupation-list-card">
        <div class="card-header">
            <h3 class="card-title">Occupation List</h3>
        </div>
        <div class="card-body border-bottom py-3 d-flex justify-content-end">
            <input type="hidden" id="occupations-per-page" value="10">
            <input id="occupations-search" class="form-control form-control-sm w-auto" placeholder="Search occupation">
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead>
                    <tr>
                        <th><button type="button" class="table-sort-button text-uppercase" data-sort="occupation_name_en">OCCUPATION (ENGLISH)</button></th>
                        <th><button type="button" class="table-sort-button" data-sort="occupation_name_kh">មុខរបរ</button></th>
                        <th><button type="button" class="table-sort-button text-uppercase" data-sort="status">STATUS</button></th>
                        <th class="text-center text-uppercase">ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="occupationsTable"></tbody>
            </table>
        </div>
        <div id="occupationsMobileCards" class="occupation-mobile-cards"></div>
        <div class="card-footer">
            <div id="occupations-pagination-container"></div>
        </div>
    </div>
    <div class="modal modal-blur fade" id="occupationModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
            <div class="modal-content">
                <form id="occupationForm">@csrf<input type="hidden" name="occupation_id" id="occupation_id">
                    <div class="modal-header">
                        <h5 id="occupationModalTitle" class="modal-title">New Occupation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" data-alert></div>
                        <div class="mb-3"><label class="form-label">Occupation (English) *</label><input name="occupation_name_en" id="occupation_name_en" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">Occupation (Khmer)</label><input name="occupation_name_kh" id="occupation_name_kh" class="form-control school-profile-khmer"></div>
                        <div><label class="form-label">Status</label><input type="hidden" name="status" id="occupation_status" value="1">
                            <button type="button" id="occupation-status-toggle" class="status-toggle is-active" aria-pressed="true">
                                <span class="status-toggle-label">ON</span><span class="status-toggle-knob"></span>
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Save Occupation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @vite('resources/js/occupation.js')
    @vite('resources/css/pages/occupation.css')
@endsection

