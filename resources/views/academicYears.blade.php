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
                    <a href="{{ route('academic-years.pdf') }}" target="_blank"
                        class="btn btn-secondary btn-5 d-none d-sm-inline-block">
                        <i class="ti ti-file-text icon"></i>
                        Export PDF
                    </a>
                    <button id="btnNewAcademicYear" class="btn btn-primary btn-5 d-none d-sm-inline-block"
                        type="button">
                        <i class="ti ti-plus icon"></i>
                        New Academic Year
                    </button>
                    <button id="btnNewAcademicYearMobile" class="btn btn-primary btn-6 d-sm-none btn-icon"
                        type="button">
                        <i class="ti ti-plus icon"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('content')
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Academic Year Lists</h3>
            </div>
            <div class="card-body border-bottom py-3">
                <div class="row col-12 g-2 align-items-center justify-content-between">
                    <div class="col-auto text-secondary">
                        Show
                        <div class="mx-2 d-inline-block">
                            <select class="form-control form-control-sm" name="academic-years-per-page"
                                id="academic-years-per-page">
                                <option value="10" selected>10 / page</option>
                                <option value="25">25 / page</option>
                                <option value="50">50 / page</option>
                                <option value="100">100 / page</option>
                            </select>
                        </div>
                        entries
                    </div>
                    <div class="col-auto d-flex flex-wrap gap-2 align-items-center">
                        <div class="col-auto p-0 d-flex align-items-center">
                            <div class="input-icon">
                                <span class="input-icon-addon">
                                    <i class="ti ti-search icon"></i>
                                </span>
                                <input type="text" id="academic-years-search" class="form-control form-control-sm"
                                    placeholder="Search academic years" aria-label="Search academic years">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive table-vcenter text-nowrap">
                <table class="table card-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Academic Year</th>
                            <th>Type</th>
                            <th>AY Code</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="academicYearsTable">
                        <tr>
                            <td colspan="9" class="text-center">Loading academic years...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
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
@endsection
