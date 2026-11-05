@extends('layouts.app')
@section('title', 'G9 Setting')
@section('page-header', '')
@section('content')
    @vite(['resources/css/pages/g9-national-exams.css','resources/js/g9Settings.js'])
    <div data-g9-settings data-base="{{ url('/g9-national-exams/settings') }}">
        <section class="card mb-3">
            <div class="card-header g9-header"><h3 class="card-title"><i class="ti ti-calendar-cog me-2"></i>Academic-Year Date Settings</h3></div>
            <div class="card-body">
                <p class="text-secondary">These dates apply to all G9 candidates in the selected academic year. Existing Khmer date text is preserved until you edit it or choose a different date.</p>
                <div class="g9-editor-grid">
                    <div class="premium-form-field staff-form-field"><label class="form-label" for="g9SettingYear">Academic Year</label><select class="form-select" id="g9SettingYear" data-setting-year>@foreach($years as $year)<option value="{{ $year->id }}" @selected($year->id == $configuredYear)>{{ $year->academic_year }}</option>@endforeach</select></div>
                    <div class="premium-form-field"><label class="form-label" for="g9DateLocation">Date Location</label><input class="form-control" id="g9DateLocation" data-setting-location placeholder=" " @disabled(!$canManage)></div>
                </div>
                @unless($canManage)<p class="form-hint mt-3 mb-0">Shared dates are read only. A central user with G9 date-setting permission can edit them.</p>@endunless
            </div>
        </section>
        <form data-date-settings>
            <section class="card mb-3">
                <div class="card-header g9-header"><h3 class="card-title">Exam Center Report Settings</h3></div>
                <div class="card-body"><p class="text-secondary mb-0">Set the city/province and education department for each exam center. Example: ខេត្តព្រះសីហនុ. Candidate and attendance reports use these headings and the Candidate List Issue Date below. The exam date comes from candidate records. Locations left blank appear blank on reports.</p></div>
                <div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>Exam Center</th><th>City / Province</th><th>Education Department</th><th>Signer Title</th><th>Signature on Student Photo</th></tr></thead><tbody data-g9-report-centers></tbody></table></div>
                <div class="card-body form-hint">Upload a transparent PNG signature for each exam center (JPG also accepted, up to 2 MB). It appears across the photo in Student Personal Profile. Profiles use Student Card Issue Date below. Click Save Dates to save signatures.</div>
            </section>
            <div class="g9-date-settings-grid">
                @foreach($sections as $key=>$section)
                    <section class="card" data-date-section="{{ $key }}">
                        <div class="card-header g9-header"><h3 class="card-title">{{ $section['label'] }}</h3></div>
                        <div class="card-body">
                            <div class="premium-form-field mb-3"><label class="form-label" for="g9Date-{{ $key }}">Issue Date</label><input class="form-control" type="date" id="g9Date-{{ $key }}" data-setting-date aria-label="{{ $section['label'] }}" @disabled(!$canManage)></div>
                            <label class="form-label" for="g9Solar-{{ $key }}">Khmer Date Text</label><textarea class="form-control mb-3 school-profile-khmer" id="g9Solar-{{ $key }}" data-setting-solar rows="2" maxlength="1000" @disabled(!$canManage)></textarea>
                            <label class="form-label" for="g9Lunar-{{ $key }}">Khmer Lunar Date</label><textarea class="form-control school-profile-khmer" id="g9Lunar-{{ $key }}" data-setting-lunar rows="2" maxlength="1000" @disabled(!$canManage)></textarea>
                        </div>
                    </section>
                @endforeach
            </div>
            <div class="card mt-3"><div class="card-body d-flex justify-content-between align-items-center gap-3"><span data-setting-message role="status"></span>@if($canManage)<button type="submit" class="btn btn-primary" data-setting-save disabled><i class="ti ti-device-floppy me-2"></i>Save Dates</button>@endif</div></div>
        </form>
    </div>
@endsection
