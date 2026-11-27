@extends('layouts.app')
@section('title','GEP Dashboard')
@section('content')
@vite(['resources/css/pages/gep-dashboard.css','resources/js/gepDashboard.js'])
<div class="gep-dashboard">
    <section class="gep-dash-hero">
        <div><div class="gep-dash-eyebrow"><span></span> GENERAL ENGLISH PROGRAM</div><h1>Your GEP overview</h1><p>Welcome, {{ auth()->user()->name }}. A clear view of your students and their learning journey.</p>
            @if($canView)<div class="gep-dash-context"><span><i class="ti ti-calendar"></i> {{ $yearLabel }}</span><span>{{ $term?->term?->term_name??'All terms' }}</span><span><i class="ti ti-building"></i> {{ $campusLabel }}</span></div>@endif
        </div>
        <div class="gep-dash-hero-side"><span class="gep-dash-book"><i class="ti ti-language"></i></span><span>{{ now('Asia/Bangkok')->format('l, d M Y') }}</span></div>
    </section>
    @if(!$canView)
        <section class="gep-dash-panel mt-3"><h2>Welcome to GEP</h2><p class="text-secondary mb-0">Enrollment insights are available to staff with GEP enrollment access. Contact your administrator to enable access for your campus.</p></section>
    @else
        @include('gep._dashboard-content')
    @endif
</div>
@endsection
