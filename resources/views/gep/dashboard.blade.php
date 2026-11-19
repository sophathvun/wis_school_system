@extends('layouts.app')
@section('title','GEP Dashboard')
@section('content')
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-3">
                <span class="avatar avatar-lg bg-primary-lt rounded-3"><i class="ti ti-books fs-1"></i></span>
                <div><div class="text-secondary small text-uppercase fw-bold">General English Program</div><h1 class="h2 mb-1">GEP Dashboard</h1><div class="text-secondary">Welcome, {{ auth()->user()->name }}.</div></div>
            </div>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-header"><h2 class="card-title">Getting started with GEP</h2></div>
        <div class="card-body">
            <p>Your GEP workspace is ready. GEP enrollment, terms, levels, attendance and reports are the next stage of setup.</p>
            <p class="text-secondary mb-0">GEP statistics will appear after GEP enrollment is available. WIS student counts are displayed in the WIS workspace.</p>
        </div>
    </div>
@endsection
