@extends('layouts.app')
@section('title','Session Setup')
@section('page-header')
    <div class="container-fluid"><div class="page-pretitle">Schedules</div><h2 class="page-title">Session Setup</h2></div>
@endsection
@section('content')
    @vite(['resources/css/pages/schedules.css','resources/js/schedulePeriods.js'])
    <div data-schedule-periods data-base="{{ url('/schedules/periods') }}">
        <div class="card">
            <div class="card-header schedule-header"><div><h3 class="card-title">Shared Session Periods</h3><small>One timetable of periods for all campuses.</small></div><button type="button" class="btn btn-light ms-auto" data-period-add><i class="ti ti-plus me-1"></i>Add Period</button></div>
            <div class="card-body">
                <p class="text-secondary">Choose a morning or afternoon period when creating a teaching schedule. Updating a period does not change published schedules or past attendance.</p>
                <div class="table-responsive"><table class="table table-vcenter schedule-table"><thead><tr><th>Order</th><th>Session</th><th>Period</th><th>Time</th><th>Applicable Groups</th><th>Grades</th><th>Status</th><th>Action</th></tr></thead><tbody data-period-list><tr><td colspan="8">Loading periods…</td></tr></tbody></table></div>
            </div>
        </div>
        <div class="modal fade" id="schedulePeriodModal" tabindex="-1" aria-labelledby="schedulePeriodTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" data-period-editor>
            <div class="modal-header schedule-header"><h3 class="modal-title" id="schedulePeriodTitle">Add Period</h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="form-hint">These settings are shared by every campus. Existing published sessions keep their original times.</p>
                <input type="hidden" name="id"><input type="hidden" name="version" value="0">
                <div class="schedule-editor-fields">
                    <div class="premium-form-field"><label class="form-label" for="periodName">Period Name</label><input class="form-control" id="periodName" name="name" placeholder="Period name" maxlength="80" required></div>
                    <div class="premium-form-field"><label class="form-label" for="periodSession">Session</label><select class="form-select" id="periodSession" name="session"><option value="morning">Morning Session</option><option value="afternoon">Afternoon Session</option></select></div>
                    <div class="premium-form-field"><label class="form-label" for="periodStart">Start Time</label><input class="form-control" type="time" id="periodStart" name="starts_at" required></div>
                    <div class="premium-form-field"><label class="form-label" for="periodEnd">End Time</label><input class="form-control" type="time" id="periodEnd" name="ends_at" required></div>
                    <div class="premium-form-field"><label class="form-label" for="periodOrder">Display Order</label><input class="form-control" type="number" id="periodOrder" name="display_order" min="0" max="999" value="1" required></div>
                    <div class="premium-form-field"><label class="form-label" for="periodActive">Status</label><select class="form-select" id="periodActive" name="active"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                    <fieldset class="schedule-period-groups"><legend>Applicable Groups</legend>
                        @foreach(['M'=>'M — Morning','A'=>'A — Afternoon','III'=>'III — Full Day'] as $value=>$label)
                            <label class="form-check"><input type="checkbox" class="form-check-input" name="applicable_groups[]" value="{{ $value }}"><span class="form-check-label">{{ $label }}</span></label>
                        @endforeach
                    </fieldset>
                    <fieldset class="schedule-period-groups"><legend>Group III Applicable Grades</legend>
                        <p class="form-hint">Select the full-day grades that can use this period. M and A follow Applicable Groups above.</p>
                        <div class="schedule-grade-choices">
                            @foreach(array_merge(['N','K1','K2','K3'],range(1,12)) as $grade)
                                <label class="form-check"><input type="checkbox" class="form-check-input" name="full_day_grades[]" value="{{ $grade }}"><span class="form-check-label">{{ $grade }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" data-period-save>Save Period</button></div>
        </form></div></div>
    </div>
@endsection
