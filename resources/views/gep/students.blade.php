@extends('layouts.app')
@section('title',$current['label'])
@section('content')
<div class="card shadow-sm">
    <div class="card-header gep-settings-header"><h1 class="card-title text-white mb-0"><i class="ti {{ $current['icon'] }} me-2" aria-hidden="true"></i>{{ $current['label'] }}</h1></div>
    <div class="card-body py-5 text-center"><span class="avatar avatar-lg bg-primary-lt mb-3"><i class="ti {{ $current['icon'] }} fs-1" aria-hidden="true"></i></span><h2 class="h3">{{ $current['label'] }}</h2><p class="text-secondary mb-0">This GEP module is being prepared. Its functions will be available in a future update.</p></div>
</div>
@endsection
