@extends('layouts.app')
@section('title','Grade Skipping Campus Settings')
@section('page-header')
<div class="container-fluid"><div class="page-pretitle">Student Skipping Grade</div><h2 class="page-title">Campus Settings</h2></div>
@endsection
@section('content')
@include('student-skipping-grade._workspace-start',['activeTab'=>'campus-settings'])
<div class="skipping-page" data-skipping-page data-success="{{ session('success') }}">
    <div class="card mb-3">
        <div class="card-header skipping-header"><h3 class="card-title">Campus Committee</h3></div>
        <div class="card-body">
            <form method="get" action="{{ route('student-skipping-grade.campus-settings') }}" class="skipping-form-grid mb-3">
                <div class="premium-form-field">
                    <label class="form-label" for="skippingSettingsCampus">Campus</label>
                    <select id="skippingSettingsCampus" name="campus_id" class="form-select" data-skipping-searchable="Campus" data-campus-settings-campus>
                        @forelse($settingsCampuses as $campus)
                            <option value="{{ $campus->id }}" @selected($selectedCampus?->id===$campus->id)>{{ $campus->campus_name_en }}</option>
                        @empty
                            <option value="">No assigned campuses</option>
                        @endforelse
                    </select>
                </div>
            </form>
            @if($selectedCampus)
                <form method="post" action="{{ route('student-skipping-grade.campus-settings.save') }}" data-skipping-form novalidate>
                    @csrf
                    <input type="hidden" name="campus_id" value="{{ $selectedCampus->id }}">
                    <fieldset @disabled(!$canSave)>
                        <div class="skipping-form-grid skipping-campus-committee-grid">
                            @foreach($campusCommitteeRoles as $index=>$role)
                                <div class="premium-form-field">
                                    <label class="form-label" for="skippingCampusCommittee{{ $index }}">{{ $role }}</label>
                                    <input id="skippingCampusCommittee{{ $index }}" class="form-control" name="committee_names[{{ $index }}]" value="{{ old('committee_names.'.$index,$committeeNames[$index]??'') }}" maxlength="200">
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                    <p class="text-secondary mt-3">These names fill automatically when selecting a student for a new request at this campus. Existing requests retain their saved committee names.</p>
                    @if($canSave)<div class="skipping-save-bar"><button class="btn btn-primary skipping-mobile-full-width" type="submit"><i class="ti ti-device-floppy me-1"></i>Save Campus Settings</button></div>@endif
                </form>
            @else
                <p class="text-secondary mb-0">You need an assigned campus to configure committee names.</p>
            @endif
        </div>
    </div>
</div>
@include('student-skipping-grade._workspace-end')
@endsection
@push('styles') @vite('resources/css/pages/student-skipping-grade.css') @endpush
@push('scripts') @vite('resources/js/studentSkippingGrade.js') @endpush
