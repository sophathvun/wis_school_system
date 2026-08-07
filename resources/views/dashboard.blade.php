@extends('layouts.app')

@section('title', 'School Dashboard')

@section('page-header')
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">School Dashboard</h2>
                <div class="text-muted mt-1">Welcome back! Here is a quick overview of your school activity.</div>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary">+ Add New Student</button>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="row row-cards">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="font-weight-medium">Total Students</div>
                            <div class="text-muted">1,240</div>
                        </div>
                        <div class="col-auto">
                            <span class="bg-primary text-white avatar">S</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="font-weight-medium">Teachers</div>
                            <div class="text-muted">68</div>
                        </div>
                        <div class="col-auto">
                            <span class="bg-success text-white avatar">T</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="font-weight-medium">Classes</div>
                            <div class="text-muted">24</div>
                        </div>
                        <div class="col-auto">
                            <span class="bg-warning text-white avatar">C</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="font-weight-medium">Attendance</div>
                            <div class="text-muted">92%</div>
                        </div>
                        <div class="col-auto">
                            <span class="bg-info text-white avatar">A</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Academic Overview</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Mathematics</span>
                            <span>78%</span>
                        </div>
                        <div class="progress progress-sm">
                            <div class="progress-bar bg-primary" style="width: 78%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Science</span>
                            <span>84%</span>
                        </div>
                        <div class="progress progress-sm">
                            <div class="progress-bar bg-success" style="width: 84%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>English</span>
                            <span>71%</span>
                        </div>
                        <div class="progress progress-sm">
                            <div class="progress-bar bg-warning" style="width: 71%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Today’s Schedule</h3>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>Morning Assembly</span>
                            <span class="badge bg-primary">08:00</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>Math Class</span>
                            <span class="badge bg-success">09:00</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>Science Lab</span>
                            <span class="badge bg-warning">11:00</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>Sports Activity</span>
                            <span class="badge bg-info">15:00</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <a href="#" class="btn btn-outline-primary w-100">Manage Students</a>
                        </div>
                        <div class="col-6">
                            <a href="#" class="btn btn-outline-success w-100">Add Teachers</a>
                        </div>
                        <div class="col-6">
                            <a href="#" class="btn btn-outline-warning w-100">Class Timetable</a>
                        </div>
                        <div class="col-6">
                            <a href="#" class="btn btn-outline-info w-100">Fee Records</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Notices</h3>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0">
                            <div class="fw-bold">Parent-Teacher Meeting</div>
                            <div class="text-muted small">Scheduled for Friday, 10:00 AM</div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="fw-bold">Exam Timetable Published</div>
                            <div class="text-muted small">Check the updated schedule online</div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="fw-bold">School Holiday Notice</div>
                            <div class="text-muted small">Holiday on next Monday</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection