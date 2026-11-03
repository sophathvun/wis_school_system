@extends('layouts.app')
@section('title','Attendance Settings')
@section('page-header')
    <div class="container-fluid"><div class="page-pretitle">Academics</div><h2 class="page-title">Attendance Settings</h2></div>
@endsection
@section('content')
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="ti ti-calendar-time me-2"></i>Schedule-based subject attendance</h3></div>
        <div class="card-body">
            <p>Manage teachers, classes, subjects, and session times in Student and Teacher Schedules. Published sessions appear automatically in subject attendance on their scheduled dates.</p>
            <p class="text-secondary">Use a dated revision when a published session changes. Existing attendance records stay linked to the original schedule. Admin-Daily Attendance works independently of teacher schedules.</p>
            <a class="btn btn-primary" href="{{ route('schedules.index') }}"><i class="ti ti-calendar-time me-2"></i>Student and Teacher Schedules</a>
        </div>
    </div>
@endsection
