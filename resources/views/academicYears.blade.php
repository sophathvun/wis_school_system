@extends('layouts.app')
@section('title', 'Academic Year')
@php
    // $categories = \App\Helpers\SelectHelper::categories();
    // $units = \App\Helpers\SelectHelper::units();
@endphp

@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle ">Overview</div>
                <h2 class="page-title">
                    Academic Year
                </h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <a href="{{ route('academic-years.print') }}" target="_blank"
                        class="btn btn-secondary btn-5 d-none d-sm-inline-block">
                        <i class="ti ti-printer icon"></i>
                        Print
                    </a>
                    <a href="{{ route('academic-years.excel') }}" class="btn btn-outline-success btn-5 d-none d-sm-inline-block">
                        <i class="ti ti-file-spreadsheet icon"></i>
                        Excel
                    </a>
                    <button id="btnNewAcademicYear" class="btn btn-primary btn-5 d-none d-sm-inline-block" type="button">
                        <i class="ti ti-plus icon"></i>
                        New Academic Year
                    </button>
                    <button id="btnToggleDeletedAcademicYears" class="btn btn-outline-secondary btn-5 d-none d-sm-inline-block" type="button">
                        <i class="ti ti-trash icon"></i>
                        Show Deleted
                    </button>
                    <button id="btnNewAcademicYearMobile" class="btn btn-primary btn-6 d-sm-none btn-icon" type="button">
                        <i class="ti ti-plus icon"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('content')
    <div class="col-12">
        <div class="card academic-years-list-card">
            <div class="card-header">
                <h3 class="card-title">Academic Year Lists</h3>
            </div>
            <div class="card-body border-bottom py-3">
                <input type="hidden" name="academic-years-per-page" id="academic-years-per-page" value="10">
                <div class="row g-2 align-items-end academic-year-filter-row">
                    <div class="col-md-auto academic-year-filter-column">
                        <div class="academic-year-combobox" data-filter-combobox="academic-year">
                            <input type="hidden" id="academic-year-filter">
                            <button type="button" class="academic-year-combobox-toggle">
                                <span>Academic Year</span><i class="ti ti-chevron-down"></i>
                            </button>
                            <div class="academic-year-combobox-menu d-none">
                                <input type="search" class="form-control form-control-sm academic-year-combobox-search"
                                    placeholder="Search academic year" autocomplete="off">
                                <div class="academic-year-combobox-results"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-auto academic-year-filter-column academic-year-type-column">
                        <div class="academic-year-combobox" data-filter-combobox="period-type">
                            <input type="hidden" id="academic-year-type-filter">
                            <button type="button" class="academic-year-combobox-toggle">
                                <span>Academic Year Type</span><i class="ti ti-chevron-down"></i>
                            </button>
                            <div class="academic-year-combobox-menu d-none">
                                <input type="search" class="form-control form-control-sm academic-year-combobox-search"
                                    placeholder="Search type" autocomplete="off">
                                <div class="academic-year-combobox-results"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 ms-md-auto">
                        <div class="input-icon">
                            <span class="input-icon-addon"><i class="ti ti-search icon"></i></span>
                            <input type="search" id="academic-years-search" class="form-control form-control-sm"
                                placeholder="Search academic years" aria-label="Search academic years">
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive table-vcenter text-nowrap">
                <table class="table card-table">
                    <thead>
                        <tr>
                            <th><button type="button" class="table-sort-button" data-sort="id">No.</button></th>
                            <th><button type="button" class="table-sort-button" data-sort="academic_year">Academic Year</button></th>
                            <th><button type="button" class="table-sort-button" data-sort="period_type">Type</button></th>
                            <th><button type="button" class="table-sort-button" data-sort="academic_year_code">AY Code</button></th>
                            <th><button type="button" class="table-sort-button" data-sort="start_date">Start Date</button></th>
                            <th><button type="button" class="table-sort-button" data-sort="end_date">End Date</button></th>
                            <th><button type="button" class="table-sort-button" data-sort="description">Description</button></th>
                            <th><button type="button" class="table-sort-button" data-sort="lifecycle_status">Status</button></th>
                            <th class="text-center"><button type="button" class="table-sort-button" data-sort="lifecycle_status">Started</button></th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="academicYearsTable">
                        <tr>
                            <td colspan="10" class="text-center">Loading academic years...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div id="academicYearsMobileCards" class="academic-years-mobile-cards"></div>
            <div class="card-footer">
                <div class="row g-2 justify-content-center justify-content-sm-between">
                    <div class="col-12 d-flex justify-content-center" id="academic-years-pagination-container">

                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal modal-blur fade" id="academicYearModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered" role="document">
            <div class="modal-content">
                <form id="academicYearForm" novalidate>
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Create Academic Year</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('partials.academicYear-form')
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" id="submitBtn" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal modal-blur fade" id="academicYearViewModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Academic Year Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="academicYearViewBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @vite('resources/js/academicYears.js')
    @vite('resources/css/pages/academic-years.css')
@endsection


