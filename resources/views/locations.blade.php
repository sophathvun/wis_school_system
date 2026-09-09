@extends('layouts.app')
@section('title', 'Locations')
@section('page-header')<div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Settings</div>
                <h2 class="page-title">Locations</h2>
            </div>
            <div class="col-auto">
                <div class="btn-list">
                    <button type="button" class="btn btn-secondary" id="printLocations"><i class="ti ti-printer icon"></i>
                        Print</button>
                    <button type="button" class="btn btn-outline-success" id="excelLocations"><i
                            class="ti ti-file-spreadsheet icon"></i> Excel</button>
                    <button class="btn btn-primary" id="newLocation"><i class="ti ti-plus icon"></i> New
                        Location</button>
                </div>
            </div>
        </div>
</div>@endsection
@section('content')
    <div class="col-12">
        <div class="card locations-list-card">
            <div class="card-header">
                <h3 class="card-title">Country and Location Lists</h3>
            </div>
            <div class="card-body border-bottom py-3">
                <input type="hidden" id="locations-per-page" value="10">
                <div class="row g-2 align-items-center">
                    <div class="col-lg-3 col-12">
                        <select id="location-level" class="d-none">
                            <option value="country">Country</option>
                            <option value="province">Province / City</option>
                            <option value="district">District / Khan</option>
                            <option value="commune">Commune</option>
                            <option value="village">Village</option>
                        </select>
                        <div id="location-level-combobox" class="location-combobox location-level-combobox">
                            <button type="button" id="location-level-toggle" class="location-combobox-toggle">
                                <span id="location-level-selected">Country</span><i class="ti ti-chevron-down"></i>
                            </button>
                            <div id="location-level-menu" class="location-combobox-menu d-none">
                                <input type="search" id="location-level-search" class="form-control form-control-sm" placeholder="Search level">
                                <div id="location-level-results" class="location-combobox-results"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5 col-12">
                        <div id="location-filter-row" class="d-flex align-items-center flex-wrap gap-2"></div>
                    </div>

                    <div class="col-lg-4 col-12 ms-auto d-flex justify-content-end">
                        <div class="input-icon" style="width: 50%; min-width: 160px; margin-left: auto;">
                            <span class="input-icon-addon"><i class="ti ti-search icon"></i></span>
                            <input id="locations-search" class="form-control form-control-sm"
                                placeholder="Search locations" style="width: 100%;">
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive table-vcenter text-nowrap">
                <table class="table card-table">
                    <thead id="locations-head"></thead>
                    <tbody id="locationsTable">
                        <tr>
                            <td colspan="6" class="text-center">Loading locations...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div id="locationsMobileCards" class="location-mobile-cards"></div>
            <div class="card-footer">
                <div class="row g-2">
                    <div class="col-12 d-flex justify-content-center" id="locations-pagination-container"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal modal-blur fade" id="locationModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="locationForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="locationModalTitle">Create Location</h5><button type="button"
                            class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" data-alert></div><input type="hidden" id="location_id">
                        <div class="mb-3 d-none" id="location-parent-wrap"><label class="form-label"
                                id="location-parent-label">Parent</label><select id="location-parent"
                                class="form-select d-none"></select>
                            <div id="location-parent-combobox" class="location-combobox"><button type="button"
                                    id="location-parent-toggle" class="location-combobox-toggle"><span
                                        id="location-parent-selected">Select Parent</span><i
                                        class="ti ti-chevron-down"></i></button>
                                <div id="location-parent-menu" class="location-combobox-menu d-none"><input type="search"
                                        id="location-parent-search" class="form-control school-profile-khmer"
                                        placeholder="Search">
                                    <div id="location-parent-results" class="location-combobox-results"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3"><label class="form-label">Name (English) *</label><input id="location-name-en"
                                class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">Name (Khmer)</label><input id="location-name-kh"
                                class="form-control"></div>
                        <div class="row g-3" id="country-extra">
                            <div class="col-6"><label class="form-label">Country Code</label><input id="country-code"
                                    class="form-control" placeholder="KH"></div>
                            <div class="col-6"><label class="form-label">International Phone Code</label><input id="international-phone-code"
                                    class="form-control" placeholder="+855"></div>
                            <div class="col-6"><label class="form-label">Flag Path</label><input id="flag-path"
                                    class="form-control" placeholder="flags/cambodia.svg"></div>
                        </div>
                        <div class="mt-3"><label class="form-label">Status</label><input type="hidden" id="location-status" value="1">
                            <button type="button" id="location-status-toggle" class="status-toggle is-active" aria-pressed="true">
                                <span class="status-toggle-label">ON</span><span class="status-toggle-knob"></span>
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn me-auto"
                            data-bs-dismiss="modal">Close</button><button class="btn btn-primary"
                            id="locationSubmit">Save</button></div>
                </form>
            </div>
        </div>
    </div>
    @vite('resources/js/locations.js')
    @vite('resources/css/pages/locations.css')
@endsection

