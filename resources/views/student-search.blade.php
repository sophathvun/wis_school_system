@extends('layouts.app')

@section('title', 'Search Students')

@section('content')
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h2 class="page-title">Search Students</h2>
                    <div class="text-secondary">Find students by academic year, campus, grade/class, or group.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl" id="studentSearchPage" data-options-url="{{ route('searchStudent.options') }}"
            data-fetch-url="{{ route('searchStudent.fetch') }}">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Student Search</h3>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3"><label class="form-label" for="search-year">Academic Year</label><select
                                id="search-year" class="form-select">
                                <option value="">All Academic Years</option>
                            </select></div>
                        <div class="col-md-3"><label class="form-label" for="search-campus">Campus</label><select
                                id="search-campus" class="form-select">
                                <option value="">All Campuses</option>
                            </select></div>
                        <div class="col-md-3"><label class="form-label" for="search-class">Grade / Class</label><select
                                id="search-class" class="form-select">
                                <option value="">All Grades / Classes</option>
                            </select></div>
                        <div class="col-md-3"><label class="form-label" for="search-group">Group</label><select
                                id="search-group" class="form-select">
                                <option value="">All Groups</option>
                            </select></div>
                        <div class="col-md-5"><label class="form-label" for="search-text">Student</label>
                            <div class="input-icon"><span class="input-icon-addon"><i class="ti ti-search"></i></span><input
                                    id="search-text" type="search" class="form-control"
                                    placeholder="Search student ID or name"></div>
                        </div>
                        <div class="col-md-7 d-flex justify-content-end"><button type="button" id="search-reset"
                                class="btn btn-outline-secondary"><i class="ti ti-refresh me-1"></i>Reset</button></div>
                    </div>
                </div>
            </div>
            <div class="card mt-3">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Student No.</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Academic Year</th>
                                <th>Campus</th>
                                <th>Grade / Class</th>
                                <th>Group</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="search-results">
                            <tr>
                                <td colspan="8" class="text-center text-secondary py-5">Loading students...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex align-items-center">
                    <p id="search-summary" class="m-0 text-secondary"></p>
                    <ul id="search-pagination" class="pagination m-0 ms-auto"></ul>
                </div>
            </div>
        </div>
    </div>
    @vite('resources/js/studentSearch.js')
@endsection
