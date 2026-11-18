@extends('layouts.app')
@section('title','WMI · '.$config['label'])
@section('content')
@vite(['resources/css/pages/wmi-management.css','resources/js/wmiManagement.js'])
@unless($section==='preliminary-round' && $tab==='pre-registration')
<section class="card wmi-heading mb-3"><div class="card-body d-flex align-items-center gap-3"><img src="{{ asset('images/wmi_logo.png') }}" alt="WMI" width="56" height="56"><div><div class="page-pretitle">WMI Management</div><h1 class="page-title">{{ $config['label'] }}</h1></div></div></section>
@endunless
<div class="wmi-workspace {{ empty($config['tabs']) ? 'wmi-workspace-single' : '' }} {{ $section==='settings' ? 'wmi-settings' : '' }}" data-wmi-workspace>
    @if($config['tabs'])
    <aside class="card wmi-navigation">
        <div class="card-header wmi-navigation-header"><h2 class="card-title">{{ $config['label'] }}</h2><button class="btn btn-icon wmi-navigation-toggle" type="button" data-wmi-toggle aria-controls="wmiNavigation" aria-expanded="true" aria-label="Collapse section navigation"><i class="ti ti-layout-sidebar-left-collapse" aria-hidden="true"></i></button></div>
        <nav id="wmiNavigation" class="wmi-navigation-list" aria-label="{{ $config['label'] }} sections">
            @foreach($config['tabs'] as $key=>$item)
            <a class="wmi-navigation-item {{ $tab===$key ? 'is-active' : '' }}" href="{{ route('wmi-management.section',['section'=>$section,'tab'=>$key]) }}" title="{{ $item[0] }}" aria-label="{{ $item[0] }}" @if($tab===$key) aria-current="page" @endif><span class="wmi-navigation-icon"><i class="ti {{ $item[1] }}" aria-hidden="true"></i></span><span class="wmi-navigation-label">{{ $item[0] }}</span><i class="ti ti-chevron-right wmi-navigation-arrow" aria-hidden="true"></i></a>
            @endforeach
        </nav>
    </aside>
    @endif
    <div class="wmi-content">
        @if($section==='preliminary-round' && in_array($tab,['pre-registration','candidates'],true))
            @include('wmi._registrations')
        @elseif($section==='settings' && $tab==='academic-year')
            @include('wmi._years')
        @elseif($section==='settings' && $tab==='exam-centers')
            @include('wmi._exam-centers')
        @elseif($section==='settings' && $tab==='schools')
            @include('wmi._schools')
        @elseif($section==='settings' && $tab==='room-seats')
            @include('wmi._room-seats')
        @elseif($section==='import-export')
            @include('wmi._import-export')
        @else
            <section class="card h-100"><div class="card-header gap-3"><span class="wmi-content-icon"><i class="ti {{ $current[1] }}" aria-hidden="true"></i></span><div><div class="page-pretitle">{{ $config['label'] }}</div><h2 class="card-title mb-0">{{ $current[0] }}</h2></div></div><div class="card-body wmi-section-body"><span class="wmi-empty-icon"><i class="ti {{ $current[1] }}" aria-hidden="true"></i></span><h3>{{ $current[0] }}</h3><p class="text-secondary mb-2">{{ $current[2] }}</p><p class="form-hint mb-0">This section is ready for the next stage of WMI setup.</p></div></section>
        @endif
    </div>
</div>
@if($section==='settings' && $tab==='academic-year')
    @include('wmi._year-modal')
@endif
@if($section==='settings' && $tab==='exam-centers')
    @include('wmi._exam-center-modal')
@endif
@if($section==='settings' && $tab==='schools')
    @include('wmi._school-modal')
@endif
@if($section==='preliminary-round' && in_array($tab,['pre-registration','candidates'],true))
    @include('wmi._registration-modal')
@endif
@if($section==='settings' && $tab==='room-seats')
    @include('wmi._seat-modal')
@endif
@if($section==='import-export')
    @include('wmi._id-import-modal')
@endif
@endsection
