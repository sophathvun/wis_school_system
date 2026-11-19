@extends('layouts.app')
@section('title',$current['label'])
@section('content')
    <div class="row g-3">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header gep-settings-header"><h1 class="card-title text-white mb-0"><i class="ti {{ $current['icon'] }} me-2"></i>{{ $current['label'] }}</h1></div>
                <div class="card-body p-4">
                    <p class="mb-3">{{ $current['description'] }}</p>
                    <div class="rounded-3 bg-primary-lt p-4 text-center">
                        <i class="ti {{ $current['icon'] }} fs-1 d-block mb-2" aria-hidden="true"></i>
                        <div class="fw-semibold mb-1">Ready for setup</div>
                        <div class="text-secondary">The configuration form and list for {{ $current['label'] }} will be added next.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
