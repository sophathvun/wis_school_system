@extends('layouts.app')
@section('title', 'Student Enrollment')
@section('page-header')
<div class="container-fluid">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Students</div>
            <h2 class="page-title">Student Enrollment</h2>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" id="newEnrollment"><i class="ti ti-plus icon"></i> New Enrollment</button>
        </div>
    </div>
</div>
@endsection
@section('content')
@php($reportBranding = \App\Models\BrandingSetting::current())
<div id="student-report-branding" data-report-logo-1="{{ $reportBranding->report_logo_1_path ? asset('storage/'.$reportBranding->report_logo_1_path) : asset('storage/school_logo/student_profile_report_logo.png') }}" data-report-logo-2="{{ $reportBranding->report_logo_2_path ? asset('storage/'.$reportBranding->report_logo_2_path) : '' }}" class="d-none"></div>
<style>
    #enrollmentModal h4 { text-transform: capitalize; }
    #enrollmentModal .modal-content {
        max-height: calc(100vh - 2rem);
        overflow: hidden;
        background: var(--tblr-bg-surface, #fff);
    }
    #enrollmentModal .modal-body {
        overflow-y: auto;
        overflow-x: hidden;
        max-height: calc(100vh - 10rem);
        background: var(--tblr-bg-surface, #fff);
    }
    #enrollmentModal .modal-footer,
    #enrollmentModal .modal-header {
        background: var(--tblr-bg-surface, #fff);
        border-color: var(--tblr-border-color);
    }
    #enrollmentModal .modal-body > h4,
    #enrollmentModal .modal-body > .row,
    #enrollmentModal .modal-body > .nav,
    #enrollmentModal .modal-body > .tab-content {
        max-width: 100%;
    }
    .premium-document-dropzone {
        position: relative;
        min-height: 190px;
        padding: 1.5rem;
        border: 1.5px dashed #b8a9f4;
        border-radius: 18px;
        color: #4b5563;
        background: linear-gradient(135deg, #fff 0%, #faf8ff 100%);
        text-align: center;
        cursor: pointer;
        transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
    }
    .premium-document-dropzone:hover,
    .premium-document-dropzone.is-dragging,
    .premium-document-dropzone:focus-visible {
        border-color: #6b5bd6;
        background: var(--tblr-bg-surface, #fff);
        box-shadow: 0 0 0 4px rgba(107, 91, 214, .12);
        outline: 0;
    }
    .premium-document-dropzone .document-upload-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 3.5rem;
        height: 3.5rem;
        margin-bottom: .65rem;
        border-radius: 16px;
        color: #6b5bd6;
        background: #eeeaff;
        font-size: 2rem;
    }
    .premium-document-dropzone .document-browse-link { color: #6b5bd6; font-weight: 700; }
    .premium-document-dropzone .document-upload-hint { font-size: .78rem; color: var(--tblr-secondary); }
    .premium-document-file-list { display: grid; gap: .6rem; margin-top: .9rem; text-align: left; }
    .premium-document-file-item {
        display: flex;
        align-items: center;
        gap: .75rem;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        padding: .7rem .85rem;
        border: 1px solid #e5e0fb;
        border-radius: 14px;
        background: var(--tblr-bg-surface, #fff);
    }
    .premium-document-file-item .document-file-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.35rem;
        height: 2.35rem;
        border-radius: 10px;
        color: #6b5bd6;
        background: #f0edff;
        font-size: 1.25rem;
    }
    .premium-document-file-item .document-file-name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; overflow-wrap: anywhere; font-weight: 600; }
    .premium-document-file-item .document-file-meta { color: var(--tblr-secondary); font-size: .78rem; }
    .premium-document-file-item .document-file-remove { margin-left: auto; border: 0; color: var(--tblr-secondary); background: transparent; }
    .student-document-upload-layout { display: grid; grid-template-columns: minmax(0, 7fr) minmax(0, 3fr); gap: 1rem; align-items: start; max-width: 100%; }
    .student-document-upload-layout > * { min-width: 0; max-width: 100%; }
    .student-document-upload-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
    .student-document-upload-fields .document-description-field { grid-column: span 1; }
    #enrollmentModal .tab-content,
    #enrollmentModal .student-document-upload-layout,
    #enrollmentModal .table-responsive {
        overflow: visible;
    }
    [data-bs-theme="dark"] #enrollmentModal .modal-content,
    [data-bs-theme="dark"] #enrollmentModal .modal-body,
    [data-bs-theme="dark"] #enrollmentModal .modal-header,
    [data-bs-theme="dark"] #enrollmentModal .modal-footer,
    [data-bs-theme="dark"] .premium-document-file-item {
        background: var(--tblr-bg-surface, #1e293b);
        color: var(--tblr-body-color);
        border-color: var(--tblr-border-color);
    }
    [data-bs-theme="dark"] .premium-document-dropzone {
        background: var(--tblr-bg-forms, #172033);
        color: var(--tblr-body-color);
        border-color: rgba(169, 156, 255, .55);
    }
    [data-bs-theme="dark"] .premium-document-dropzone:hover,
    [data-bs-theme="dark"] .premium-document-dropzone.is-dragging,
    [data-bs-theme="dark"] .premium-document-dropzone:focus-visible {
        background: var(--tblr-bg-surface, #1e293b);
        border-color: #a99cff;
    }
    [data-bs-theme="dark"] .premium-document-dropzone .document-upload-icon,
    [data-bs-theme="dark"] .premium-document-file-item .document-file-icon {
        background: rgba(169, 156, 255, .14);
        color: #c4b5fd;
    }
    [data-bs-theme="dark"] #enrollmentModal .nav-tabs .nav-link {
        color: var(--tblr-body-color);
    }
    [data-bs-theme="dark"] #enrollmentModal .nav-tabs .nav-link.active {
        background: var(--tblr-bg-surface, #1e293b);
        border-color: var(--tblr-border-color);
    }
    @media (max-width: 767.98px) {
        .student-document-upload-layout { grid-template-columns: 1fr; }
        .student-document-upload-fields { grid-template-columns: 1fr; }
        .student-document-upload-fields .document-description-field { grid-column: auto; }
    }
    #enrollmentModal input::placeholder,
    #enrollmentModal textarea::placeholder,
    #enrollments-search::placeholder {
        font-size: .875rem;
        font-family: inherit;
        font-weight: 400;
        color: var(--tblr-secondary);
        opacity: 1;
    }
    .table-sort {
        border: 0;
        background: transparent;
        padding: 0;
        font: inherit;
        font-weight: 700;
        color: inherit;
        text-align: left;
        cursor: pointer;
    }
    .table-sort:hover,
    .table-sort.text-primary {
        color: var(--tblr-primary) !important;
    }
    .table-sort [data-sort-icon] {
        display: inline-block;
        min-width: .75rem;
        margin-left: .15rem;
    }
    .enrollment-list-filter {
        width: 170px;
        min-width: 150px;
    }
    .enrollment-list-filter-group .enrollment-search-combobox {
        width: 170px;
        min-width: 150px;
    }
    .enrollment-list-filter-group #enrollments-filter-student-combobox {
        width: 220px;
        min-width: 190px;
    }
    .enrollment-list-filter-group .location-combobox-toggle {
        min-height: 31px;
        height: 31px;
        border-radius: .35rem;
        padding: .25rem .5rem;
        font-size: .875rem;
        background: var(--tblr-bg-surface, #fff);
    }
    .enrollment-list-filter-group .location-combobox-selected {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .enrollment-list-filter-group .location-combobox-menu {
        min-width: 100%;
        z-index: 1050;
    }
    .enrollment-expanded-row > td {
        background: linear-gradient(135deg, rgba(109, 40, 217, .14), rgba(14, 165, 233, .08));
        padding: 0 1rem 1rem;
        border-top: 0;
    }
    .enrollment-expand-toggle {
        width: 1.5rem;
        height: 1.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-left: .4rem;
        padding: 0;
        border: 0;
        border-radius: 50%;
        color: #6d28d9;
        background: transparent;
        transition: transform .18s ease, color .18s ease, background .18s ease, box-shadow .18s ease;
    }
    .enrollment-expand-toggle:hover,
    .enrollment-expand-toggle:focus-visible {
        color: #4c1d95;
        background: rgba(109, 40, 217, .1);
    }
    .enrollment-student-name-en { display: inline-flex; align-items: center; }
    .enrollment-expand-toggle i {
        font-size: 1.15rem;
        transition: transform .18s ease;
    }
    .enrollment-expand-toggle.is-expanded i {
        transform: rotate(180deg);
    }
    [data-bs-theme="dark"] .enrollment-expand-toggle {
        color: #c4b5fd;
        background: transparent;
        border-color: transparent;
    }
    .enrollment-expanded-card {
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(109, 40, 217, .28);
        border-radius: 1rem;
        background: linear-gradient(135deg, #f5f0ff 0%, #eef8ff 100%);
        box-shadow: 0 1rem 2.5rem rgba(76, 29, 149, .14);
        padding: 1rem;
    }
    .enrollment-expanded-card::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 6px;
        background: linear-gradient(180deg, #6d28d9, #0ea5e9);
    }
    .enrollment-expanded-card .table {
        --tblr-table-bg: transparent;
    }
    .enrollment-expanded-card thead th {
        background: rgba(255, 255, 255, .55);
        color: #4c1d95;
    }
    .enrollment-expanded-card .detail-chip {
        border: 1px solid var(--tblr-border-color);
        border-radius: .85rem;
        padding: .65rem .75rem;
        background: var(--tblr-bg-surface);
        min-height: 58px;
    }
    .enrollment-expanded-card .detail-label {
        color: var(--tblr-secondary);
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: .2rem;
    }
    .enrollment-expanded-card .detail-value {
        font-weight: 600;
        color: var(--tblr-body-color);
        word-break: break-word;
    }
    [data-bs-theme="dark"] .enrollment-expanded-card {
        background: linear-gradient(135deg, rgba(76, 29, 149, .38), rgba(14, 116, 144, .2));
        border-color: rgba(196, 181, 253, .35);
    }
    [data-bs-theme="dark"] .enrollment-expanded-card thead th {
        background: rgba(15, 23, 42, .45);
        color: #ddd6fe;
    }
    #enrollments-filter-student {
        width: 220px;
        min-width: 190px;
    }
    @media (max-width: 767.98px) {
        .enrollment-list-filter,
        .enrollment-list-filter-group,
        .enrollment-list-filter-group .enrollment-search-combobox,
        .enrollment-list-filter-group #enrollments-filter-student-combobox,
        #enrollments-filter-student,
        #enrollments-search {
            width: 100% !important;
            max-width: none !important;
        }
    }
    #enrollmentModal .modal-body .row.g-3 > [class*="col-"] > .form-label .text-danger {
        color: var(--tblr-danger) !important;
    }
    #enrollmentModal .premium-floating-field {
        position: relative;
    }
    #enrollmentModal .premium-floating-field > .form-label {
        position: absolute;
        z-index: 5;
        top: 1rem !important;
        left: calc(var(--tblr-gutter-x, 1rem) * .5 + 1rem) !important;
        margin: 0 !important;
        padding: 0 .35rem !important;
        color: var(--tblr-secondary) !important;
        background: transparent !important;
        font-size: .95rem !important;
        font-weight: 400 !important;
        line-height: 1.1 !important;
        pointer-events: none;
        transition: top .16s ease, left .16s ease, font-size .16s ease, color .16s ease, background-color .16s ease;
    }
    #enrollmentModal .premium-floating-field > .form-control,
    #enrollmentModal .premium-floating-field > .form-select,
    #enrollmentModal .premium-floating-field > .input-icon,
    #enrollmentModal .premium-floating-field > .phone-input-group,
    #enrollmentModal .premium-floating-field > .date-picker,
    #enrollmentModal .premium-floating-field > .location-combobox,
    #enrollmentModal .premium-floating-field > .form-select.d-none + .location-combobox {
        min-height: 52px !important;
    }
    #enrollmentModal .premium-floating-field > .form-control,
    #enrollmentModal .premium-floating-field > .form-select,
    #enrollmentModal .premium-floating-field > .input-icon .form-control,
    #enrollmentModal .premium-floating-field > .phone-input-group .form-control,
    #enrollmentModal .premium-floating-field > .date-picker .form-control,
    #enrollmentModal .premium-floating-field > .location-combobox .location-combobox-toggle {
        height: 52px !important;
        min-height: 52px !important;
        padding-top: 1.25rem !important;
        padding-bottom: .35rem !important;
        border: 1.5px solid #dfe3ea !important;
        border-radius: 14px !important;
        background-color: var(--tblr-bg-surface, #fff) !important;
        box-shadow: 0 2px 7px rgba(31, 41, 55, .04) !important;
        transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
    }
    #enrollmentModal .premium-floating-field > .form-control:focus,
    #enrollmentModal .premium-floating-field > .form-select:focus,
    #enrollmentModal .premium-floating-field > .input-icon .form-control:focus,
    #enrollmentModal .premium-floating-field > .phone-input-group .form-control:focus,
    #enrollmentModal .premium-floating-field > .date-picker .form-control:focus,
    #enrollmentModal .premium-floating-field > .location-combobox .location-combobox-toggle:focus,
    #enrollmentModal .premium-floating-field.has-value > .form-control,
    #enrollmentModal .premium-floating-field.has-value > .form-select,
    #enrollmentModal .premium-floating-field.has-value > .input-icon .form-control,
    #enrollmentModal .premium-floating-field.has-value > .phone-input-group .form-control,
    #enrollmentModal .premium-floating-field.has-value > .date-picker .form-control,
    #enrollmentModal .premium-floating-field.has-value > .location-combobox .location-combobox-toggle,
    #enrollmentModal .premium-floating-field:focus-within > .form-control,
    #enrollmentModal .premium-floating-field:focus-within > .form-select,
    #enrollmentModal .premium-floating-field:focus-within > .input-icon .form-control,
    #enrollmentModal .premium-floating-field:focus-within > .phone-input-group .form-control,
    #enrollmentModal .premium-floating-field:focus-within > .date-picker .form-control,
    #enrollmentModal .premium-floating-field:focus-within > .location-combobox .location-combobox-toggle {
        border-color: #6b5bd6 !important;
        background-color: var(--tblr-bg-surface, #fff) !important;
        box-shadow: 0 0 0 3px rgba(107, 91, 214, .14) !important;
    }
    #enrollmentModal .premium-floating-field.has-value > .form-label,
    #enrollmentModal .premium-floating-field:focus-within > .form-label {
        top: .42rem !important;
        left: calc(var(--tblr-gutter-x, 1rem) * .5 + 1rem) !important;
        padding: 0 .45rem !important;
        background: var(--tblr-bg-surface, #fff) !important;
        color: #5b4bd1 !important;
        font-size: .72rem !important;
        font-weight: 700 !important;
    }
    #enrollmentModal .premium-floating-field:focus-within > .form-label {
        background: var(--tblr-bg-surface, #fff) !important;
    }
    #enrollmentModal .modal-body .premium-floating-field > .form-control,
    #enrollmentModal .modal-body .premium-floating-field > .form-select,
    #enrollmentModal .modal-body .premium-floating-field .input-icon .form-control,
    #enrollmentModal .modal-body .premium-floating-field .phone-input-group .form-control,
    #enrollmentModal .modal-body .premium-floating-field .date-picker-trigger,
    #enrollmentModal .modal-body .premium-floating-field .location-combobox-toggle {
        background-color: var(--tblr-bg-surface, #fff) !important;
    }
    #enrollmentModal .modal-body input.form-control,
    #enrollmentModal .modal-body textarea.form-control,
    #enrollmentModal .modal-body select.form-select,
    #enrollmentModal .modal-body .location-combobox-toggle,
    #enrollmentModal .modal-body .date-picker-trigger {
        background-color: var(--tblr-bg-surface, #fff) !important;
    }
    #enrollmentModal .modal-body input.form-control,
    #enrollmentModal .modal-body textarea.form-control,
    #enrollmentModal .modal-body select.form-select,
    #enrollmentModal .modal-body .location-combobox-toggle,
    #enrollmentModal .modal-body .date-picker-trigger,
    #enrollmentModal .modal-body input.form-control:-webkit-autofill,
    #enrollmentModal .modal-body input.form-control:-webkit-autofill:hover,
    #enrollmentModal .modal-body input.form-control:-webkit-autofill:focus {
        background-color: var(--tblr-bg-surface, #fff) !important;
        -webkit-box-shadow: inset 0 0 0 1000px var(--tblr-bg-surface, #fff) !important;
        box-shadow: inset 0 0 0 1000px var(--tblr-bg-surface, #fff) !important;
    }
    #enrollmentModal .modal-body .input-icon,
    #enrollmentModal .modal-body .phone-input-group,
    #enrollmentModal .modal-body .phone-input-group .iti,
    #enrollmentModal .modal-body .phone-input-group .iti input {
        background: var(--tblr-bg-surface, #fff) !important;
    }
    #enrollmentModal #date_of_birth_trigger.date-picker-calendar-button,
    #enrollmentModal #enrolled_on_trigger.date-picker-calendar-button {
        background: transparent !important;
        box-shadow: none !important;
    }
    #enrollmentModal #date_of_birth_picker,
    #enrollmentModal #enrolled_on_picker {
        height: 52px;
        min-height: 52px;
        overflow: visible;
        border: 1.5px solid #dfe3ea !important;
        border-radius: 14px !important;
        background: var(--tblr-bg-surface, #fff) !important;
        box-shadow: 0 2px 7px rgba(31, 41, 55, .04) !important;
    }
    #enrollmentModal #date_of_birth_picker:focus-within,
    #enrollmentModal #enrolled_on_picker:focus-within {
        border-color: #6b5bd6 !important;
        box-shadow: 0 0 0 3px rgba(107, 91, 214, .14) !important;
    }
    #enrollmentModal .premium-floating-field.has-value > .date-picker {
        border-color: #6b5bd6 !important;
        box-shadow: 0 0 0 3px rgba(107, 91, 214, .14) !important;
    }
    #enrollmentModal #date_of_birth_direct,
    #enrollmentModal #enrolled_on_direct {
        height: 52px !important;
        min-height: 52px !important;
        padding-top: .75rem !important;
        padding-bottom: .75rem !important;
        border: 0 !important;
        border-radius: 14px !important;
        background: transparent !important;
        box-shadow: none !important;
    }
    #enrollmentModal .premium-floating-field:has(> .input-icon) > .form-label {
        left: calc(var(--tblr-gutter-x, 1rem) * .5 + 1rem) !important;
    }
    #enrollmentModal .premium-floating-field:has(> .phone-input-group) > .form-label {
        left: calc(var(--tblr-gutter-x, 1rem) * .5 + 1rem) !important;
    }
    #enrollmentModal .premium-floating-field:not(.has-value):not(:focus-within):has(> .input-icon) > .form-label {
        left: calc(var(--tblr-gutter-x, 1rem) * .5 + 3.6rem) !important;
    }
    #enrollmentModal .premium-floating-field:not(.has-value):not(:focus-within):has(> .phone-input-group) > .form-label {
        left: calc(var(--tblr-gutter-x, 1rem) * .5 + 4.25rem) !important;
    }
    #enrollmentModal .premium-floating-field:has(> .date-picker) > .form-label {
        top: .42rem !important;
        left: calc(var(--tblr-gutter-x, 1rem) * .5 + 1rem) !important;
        padding: 0 .45rem !important;
        background: var(--tblr-bg-surface, #fff) !important;
        color: #5b4bd1 !important;
        font-size: .72rem !important;
        font-weight: 700 !important;
    }
    #enrollmentModal .premium-floating-field:has(> .input-icon) > .input-icon .input-icon-addon {
        height: 52px !important;
        border: 0;
        background: transparent;
        color: var(--tblr-secondary);
    }
    #enrollmentModal .premium-floating-field:has(> .input-icon) > .input-icon .form-control {
        padding-left: 3.55rem !important;
    }
    #enrollmentModal .premium-floating-field:has(> .phone-input-group) > .phone-input-group .form-control {
        padding-left: 5rem !important;
    }
    #enrollmentModal .premium-floating-field:has(> .phone-input-group) > .phone-input-group .iti {
        width: 100%;
        position: relative;
    }
    #enrollmentModal .premium-floating-field:has(> .phone-input-group) > .phone-input-group .iti__country-container {
        width: 4.65rem;
        box-sizing: border-box;
        padding: 1.5px 0 1.5px .7rem;
        overflow: visible;
    }
    #enrollmentModal .premium-floating-field:has(> .phone-input-group) > .phone-input-group .iti__selected-country {
        width: 100%;
        padding: 0 !important;
        white-space: nowrap;
    }
    #enrollmentModal .premium-floating-field:has(> .phone-input-group) > .phone-input-group .iti__selected-country-primary {
        padding: 0 !important;
        flex: 0 0 auto;
    }
    #enrollmentModal .premium-floating-field:has(> .phone-input-group) > .phone-input-group .iti__selected-country-flag {
        margin-right: .25rem;
    }
    #enrollmentModal .premium-floating-field:has(> .phone-input-group) > .phone-input-group .iti__arrow {
        margin-left: .25rem;
    }
    #enrollmentModal .premium-floating-field:has(> .phone-input-group) > .phone-input-group .iti__selected-dial-code {
        margin-left: .2rem;
        white-space: nowrap;
    }
    /* Keep Home Phone visually identical while giving its number text a small
       extra inset to prevent the first character from touching +855. */
    #enrollmentModal .premium-floating-field:has(#home_phone_number) > .phone-input-group .form-control {
        padding-left: 6rem !important;
    }
    #enrollmentModal .premium-floating-field.has-value > .phone-input-group .form-control,
    #enrollmentModal .premium-floating-field:focus-within > .phone-input-group .form-control {
        padding-top: .75rem !important;
        padding-bottom: .75rem !important;
    }
    #enrollmentModal .premium-floating-field > .date-picker .date-picker-calendar-button {
        height: 52px !important;
    }
    #enrollmentModal .premium-floating-field:not(.has-value) > .form-select {
        color: transparent !important;
    }
    #enrollmentModal .premium-floating-field:not(.has-value) > .form-select option {
        color: var(--tblr-body-color) !important;
    }
    #enrollmentModal .premium-floating-field.has-value > .form-select {
        color: var(--tblr-body-color) !important;
    }
    #enrollmentModal .premium-floating-field .form-control::placeholder,
    #enrollmentModal .premium-floating-field textarea::placeholder,
    #enrollmentModal .premium-floating-field .location-combobox-search::placeholder {
        color: transparent !important;
        opacity: 0 !important;
    }
    #enrollmentModal .premium-floating-field .location-combobox-menu .form-control::placeholder {
        color: var(--tblr-secondary) !important;
        opacity: 1 !important;
    }
    #studentInformationFields > [class*="col-"] .form-control,
    #studentInformationFields > [class*="col-"] .form-select,
    #studentInformationFields > [class*="col-"] .location-combobox-toggle,
    #studentInformationFields > [class*="col-"] .phone-input-group {
        min-height: 52px;
        height: 52px;
    }
    #studentInformationFields > [class*="col-"] .phone-input-group .form-control { height: 52px; }
    #date_of_birth_picker,
    #enrolled_on_picker { display: block; position: relative; width: 100%; }
    #date_of_birth_direct,
    #enrolled_on_direct { width: 100%; padding-right: 3.25rem; text-align: left; }
    #date_of_birth_trigger.date-picker-calendar-button,
    #enrolled_on_trigger.date-picker-calendar-button {
        position: absolute !important;
        z-index: 3;
        top: .25rem !important;
        right: .25rem !important;
        width: 42px !important;
        min-width: 42px !important;
        height: 44px !important;
        padding: 0 !important;
        justify-content: center;
        border: 0 !important;
        border-radius: 10px;
        background: var(--tblr-bg-surface, #fff) !important;
    }
    #date_of_birth_trigger.date-picker-calendar-button .date-picker-display,
    #date_of_birth_trigger.date-picker-calendar-button .ti-chevron-down { display: none; }
    #date_of_birth_popup { position: absolute; z-index: 1050; top: calc(100% + .35rem); left: 0; }
    #studentPhotoCropCanvas { width: min(100%, 360px); height: auto; max-height: 480px; display: block; margin: 0 auto; background: var(--tblr-bg-surface, #fff); border: 1px solid var(--tblr-border-color); }
    .student-photo-crop-stage { background: #f1f3f5; border-radius: .5rem; padding: 1rem; }
    #studentPhotoViewModal .modal-dialog { max-width: 560px; }
    #studentPhotoViewModal .modal-content { max-height: calc(100vh - 2rem); }
    #studentPhotoViewModal .modal-body { min-height: 0; overflow: auto !important; }
    #studentPhotoViewImage { display: block; max-width: 100%; max-height: 52vh; margin: 0 auto; object-fit: contain; transition: transform .2s ease; transform-origin: center center; }
    @media (max-width: 575.98px) {
        #studentPhotoViewModal .modal-dialog { width: calc(100vw - 1rem); margin: .5rem auto; }
        #studentPhotoViewModal .modal-footer { flex-wrap: wrap; gap: .5rem; }
        #studentPhotoViewModal .modal-footer > .d-flex { max-width: 100%; flex-wrap: wrap; }
    }
    .student-profile-section-card { border: 1px solid var(--tblr-border-color); border-radius: .85rem; background: var(--tblr-bg-surface); overflow: hidden; box-shadow: 0 1px 2px rgba(32, 41, 56, .04); }
    .student-profile-section-card + .student-profile-section-card { margin-top: 1.25rem; }
    .student-profile-section-card-header { padding: 1.15rem 1.35rem; border-bottom: 1px solid var(--tblr-border-color); }
    .student-profile-section-card-body { padding: 1.35rem; }
    .student-profile-section-header { margin-bottom: 0 !important; }
    .student-profile-paired-rows { display: flex; flex-direction: column; }
    .student-profile-paired-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); column-gap: 1.5rem; }
    .student-profile-paired-row > .d-flex { min-width: 0; }
    .student-siblings-section { margin: 0 0 1.25rem; }
    .student-siblings-section-title { font-weight: 700; margin-bottom: .65rem; }
    .student-sibling-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: .75rem; }
    .student-sibling-card { display: flex; align-items: center; gap: .75rem; padding: .75rem; border: 1px solid var(--tblr-border-color); border-radius: .65rem; background: var(--tblr-bg-surface-secondary, #f8fafc); }
    .student-sibling-photo { width: 56px; height: 72px; flex: 0 0 auto; object-fit: cover; border-radius: .45rem; border: 1px solid var(--tblr-border-color); background: var(--tblr-bg-surface); }
    .student-sibling-info { min-width: 0; }
    .student-sibling-name-kh, .student-sibling-name-en { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .student-sibling-name-en { font-weight: 700; }
    .student-family-parent-heading .khmer-font-muol { font-family: var(--khmer-font-muol) !important; font-weight: 300 !important; color: #1e3a5f !important; }
    .student-family-parent-heading > div { font-size: 1.12rem; }
    .student-family-parent-heading > div:last-child { font-weight: 700; color: #1e3a5f; }
    .student-profile-enrollment-table th,
    .student-profile-enrollment-table td { vertical-align: middle; text-align: center; }
    .student-family-father-heading { border-top: 2px dotted #94a3b8 !important; margin-top: .75rem; padding-top: .75rem; }
    .student-photo-siblings-row { display: flex; align-items: flex-start; gap: 1.25rem; margin-bottom: 1.25rem; }
    .student-photo-siblings-row > .text-start { flex: 0 0 auto; margin-bottom: 0 !important; }
    .student-photo-siblings-row > .student-siblings-section { flex: 1 1 auto; min-width: 0; }
    @media (max-width: 767.98px) {
        .student-photo-siblings-row { flex-direction: column; }
        .student-photo-siblings-row > .student-siblings-section { width: 100%; }
    }
    @media (max-width: 767.98px) { .student-profile-paired-row { grid-template-columns: 1fr; } }
    .student-profile-paired-row > .d-flex { padding-top: .25rem !important; padding-bottom: .25rem !important; }
    #studentProfileModal .modal-dialog { width: calc(100vw - 2rem); max-width: 1200px; }
    .date-picker-input-row { position: relative; display: block; width: 100%; background: transparent !important; }
    #enrolled_on_picker { position: relative; }
    #enrolled_on_popup { position: absolute; z-index: 2000; top: calc(100% + .35rem); left: auto; right: 0; width: 21rem; max-width: min(21rem, calc(100vw - 2rem)); }
    #addressFields > [class*="col-"] .form-control,
    #addressFields > [class*="col-"] .form-select,
    #addressFields > [class*="col-"] .location-combobox-toggle { min-height: 52px; height: 52px; }
    @media (min-width: 992px) {
        #familyInformationFields { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); }
        #familyInformationFields > .col-md-3 { width: auto; max-width: none; }
        #familyInformationFields > .col-12 { grid-column: 1 / -1; }
    }
    .family-member-row { grid-column: 1 / -1; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; align-items: end; }
    .family-member-row > .col-md-3 { width: auto; max-width: none; min-width: 0; }
    .family-member-heading { grid-column: 1 / -1; font-weight: 700; color: var(--tblr-secondary); margin-bottom: -.25rem; }
    .family-member-row .form-control,
    .family-member-row .form-select,
    .family-member-row .location-combobox-toggle,
    .family-member-row .phone-input-group { height: 52px; min-height: 52px; }
    .family-member-row .phone-input-group .form-control { height: 52px; min-height: 52px; }
    @media (max-width: 991.98px) { .family-member-row { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 575.98px) { .family-member-row { grid-template-columns: 1fr; } }
    .family-information .location-combobox-menu { min-width: 220px; }
    .family-information .location-combobox.is-open { z-index: 2001; }
    #enrollmentModal .premium-floating-field .location-combobox-toggle {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: .75rem !important;
        width: 100% !important;
        text-align: left !important;
    }
    #enrollmentModal .premium-floating-field .location-combobox-selected {
        display: flex !important;
        align-items: center !important;
        gap: .35rem !important;
        min-width: 0 !important;
        flex: 1 1 auto !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
        font-size: .875rem !important;
        line-height: 1.2 !important;
    }
    #enrollmentModal .premium-floating-field .location-combobox-selected-text {
        display: block !important;
        min-width: 0 !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }
    #enrollmentModal .premium-floating-field .location-combobox-selected .d-block,
    #enrollmentModal .premium-floating-field .location-combobox-selected .min-w-0 {
        min-width: 0 !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }
    #enrollmentModal .premium-floating-field .location-combobox-selected .location-combobox-english,
    #enrollmentModal .premium-floating-field .location-combobox-selected .location-combobox-khmer {
        display: inline !important;
        font-size: .875rem !important;
        line-height: 1.2 !important;
        white-space: nowrap !important;
    }
    #enrollmentModal .premium-floating-field .location-combobox-selected .location-combobox-khmer:not(:empty)::before {
        content: " / ";
    }
    #enrollmentModal .premium-floating-field .location-combobox-toggle > i {
        flex: 0 0 auto !important;
        color: var(--tblr-secondary) !important;
    }
    #enrollmentModal .premium-floating-field .location-combobox-menu {
        border-radius: 14px !important;
        border-color: #dfe3ea !important;
        box-shadow: 0 .75rem 1.5rem rgba(31, 41, 55, .16) !important;
    }
    .family-information > .col-md-3.family-field-open { position: relative; z-index: 3000; }
    .family-native-nationality { position: relative; }
    .family-native-nationality select { padding-right: 2.5rem; }
    .family-native-nationality-flag { position: absolute; top: 2.2rem; right: 2.25rem; width: 22px; height: 15px; object-fit: cover; pointer-events: none; }
    .family-native-search { height: 38px; }
    .family-information .location-combobox.is-open { z-index: 2001; }
    @media (min-width: 1200px) {
        #enrollmentInformationFields { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
        #enrollmentInformationFields.has-academic-track { grid-template-columns: repeat(8, minmax(0, 1fr)); }
        #enrollmentInformationFields > [class*="col-"] { width: auto; max-width: none; }
        #enrollmentInformationFields .form-label {
            max-width: calc(100% - 1.25rem);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    }
    @media (min-width: 768px) and (max-width: 1199.98px) {
        #enrollmentInformationFields { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); }
        #enrollmentInformationFields > [class*="col-"] { width: auto; max-width: none; }
    }
    @media (min-width: 992px) {
        #addressFields > :nth-child(-n+5) { flex: 0 0 20%; max-width: 20%; }
        #addressFields > :nth-child(n+6):nth-child(-n+9) { flex: 0 0 20%; max-width: 20%; }
    }
</style>
<div class="col-12">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Student Enrollment Lists</h3></div>
        <div class="card-body border-bottom py-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <select id="enrollments-per-page" class="d-none" aria-hidden="true"><option selected>10</option><option>25</option><option>50</option></select>
                <div class="d-flex align-items-center gap-2 flex-wrap enrollment-list-filter-group">
                    <select id="enrollments-filter-academic-year" class="form-select form-select-sm enrollment-list-filter"><option value="">Academic Year</option></select>
                    <select id="enrollments-filter-campus" class="form-select form-select-sm enrollment-list-filter"><option value="">Campus</option></select>
                    <select id="enrollments-filter-grade-class" class="form-select form-select-sm enrollment-list-filter"><option value="">Grade</option></select>
                    <select id="enrollments-filter-group" class="form-select form-select-sm enrollment-list-filter"><option value="">Group</option></select>
                    <select id="enrollments-filter-student" class="form-select form-select-sm enrollment-list-filter"><option value="">Student Name</option></select>
                    <select id="enrollments-filter-status" class="form-select form-select-sm enrollment-list-filter"><option value="">Status</option></select>
                </div>
                <div class="ms-auto flex-shrink-0">
                    <input id="enrollments-search" class="form-control form-control-sm" style="width:260px; max-width:100%;" placeholder="Search student">
                </div>
            </div>
        </div>
        <div class="table-responsive"><table class="table card-table"><thead><tr><th>Photo</th><th><button type="button" class="table-sort" data-sort="student_id">Student ID <span data-sort-icon="student_id"></span></button></th><th><button type="button" class="table-sort" data-sort="student_name">Student Name <span data-sort-icon="student_name"></span></button></th><th data-student-type-column="true"><button type="button" class="table-sort" data-sort="student_type">Type <span data-sort-icon="student_type"></span></button></th><th><button type="button" class="table-sort" data-sort="academic_year">Academic Year <span data-sort-icon="academic_year"></span></button></th><th><button type="button" class="table-sort" data-sort="campus">Campus <span data-sort-icon="campus"></span></button></th><th><button type="button" class="table-sort" data-sort="grade">Grade <span data-sort-icon="grade"></span></button></th><th><button type="button" class="table-sort" data-sort="academic_track">Track <span data-sort-icon="academic_track"></span></button></th><th><button type="button" class="table-sort" data-sort="group">Group <span data-sort-icon="group"></span></button></th><th><button type="button" class="table-sort" data-sort="status">Status <span data-sort-icon="status"></span></button></th><th>Actions</th></tr></thead><tbody id="enrollmentsTable"></tbody></table></div>
        <div class="card-footer"><div id="enrollments-pagination-container"></div></div>
    </div>
</div>

<div class="modal modal-blur fade" id="enrollmentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <form id="enrollmentForm" novalidate>
                @csrf
                <input type="hidden" id="enrollment_id" name="enrollment_id">
                <input type="hidden" id="student_record_id" name="student_record_id">
                <div class="modal-header">
                    <h5 id="enrollmentModalTitle" class="modal-title">Create Student Enrollment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" data-alert></div>

                    <h4 class="mb-3">Student Information</h4>
                    <div class="row g-3 mb-4" id="studentInformationFields">
                        <div class="col-12">
                            <label class="form-label">Student Photo</label>
                            <div class="student-photo-upload-row">
                                <div class="logo-dropzone" id="studentPhotoDropzone" tabindex="0">
                                    <i class="ti ti-cloud-upload logo-dropzone-icon"></i>
                                    <div><strong>Drag and drop student photo here</strong></div>
                                    <div class="text-secondary">or click, paste, or upload a file</div>
                                    <input type="file" class="d-none" name="photo" id="student_photo" accept="image/jpeg,image/png,image/webp">
                                </div>
                                <div class="d-none student-photo-preview-wrap" id="studentPhotoPreviewContainer">
                                    <img id="studentPhotoPreview" src="#" alt="Student photo preview" class="student-photo-preview">
                                </div>
                            </div>
                            <small class="form-hint">JPG, PNG, or WEBP. Maximum size: 2 MB.</small>
                        </div>
                        <div class="col-md-4 premium-floating-field">
                            <label class="form-label">Student No. (Auto)</label>
                            <input id="student_no" name="student_no" class="form-control" readonly placeholder="Auto generated">
                        </div>
                        <div class="col-md-4 premium-floating-field">
                            <label class="form-label">Student ID <span class="text-danger">*</span></label>
                            <input id="student_id" name="student_id" class="form-control" placeholder=" ">
                        </div>
                        <div class="col-md-4 premium-floating-field">
                            <label class="form-label">Existing Family / Sibling</label>
                            <select id="existing_family_number" name="existing_family_number" class="form-select"></select>
                        </div>
                        <div class="col-md-4 premium-floating-field">
                            <label class="form-label">Family Number</label>
                            <input id="family_number" name="family_number" class="form-control" readonly placeholder="Auto from Student ID">
                        </div>
                        <div class="col-md-4 premium-floating-field">
                            <label class="form-label">Full Name (English) <span class="text-danger">*</span></label>
                            <input id="full_name_en" name="full_name_en" class="form-control" placeholder=" ">
                        </div>
                        <div class="col-md-4 premium-floating-field">
                            <label class="form-label">Date of Birth</label>
                            <div class="date-picker" id="date_of_birth_picker">
                                <button type="button" id="date_of_birth_trigger" class="date-picker-trigger date-picker-calendar-button">
                                    <i class="ti ti-calendar"></i>
                                    <span id="date_of_birth_display" class="date-picker-display">Choose your date</span>
                                    <i class="ti ti-chevron-down"></i>
                                </button>
                                <input type="hidden" id="date_of_birth" name="date_of_birth">
                                <input type="text" id="date_of_birth_direct" class="form-control" inputmode="numeric" placeholder="DD-MM-YYYY" aria-label="Enter date of birth directly">
                                <div id="date_of_birth_popup" class="date-picker-popup d-none">
                                    <div class="date-picker-header">
                                        <button type="button" id="date_of_birth_prev" class="date-picker-nav" aria-label="Previous month">
                                            <i class="ti ti-chevron-left"></i>
                                        </button>
                                        <button type="button" id="date_of_birth_year_toggle" class="date-picker-year-toggle">
                                            <span id="date_of_birth_month_label">July 2026</span>
                                        </button>
                                        <button type="button" id="date_of_birth_next" class="date-picker-nav" aria-label="Next month">
                                            <i class="ti ti-chevron-right"></i>
                                        </button>
                                    </div>
                                    <div id="date_of_birth_year_popup" class="date-picker-year-popup d-none">
                                        <div id="date_of_birth_years" class="date-picker-years"></div>
                                    </div>
                                    <div class="date-picker-grid">
                                        <div class="date-picker-weekdays">
                                            <span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span>
                                        </div>
                                        <div id="date_of_birth_days" class="date-picker-days"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 premium-floating-field">
                            <label class="form-label">Full Name (Khmer)</label>
                            <input id="full_name_kh" name="full_name_kh" class="form-control school-profile-khmer" placeholder=" ">
                        </div>
                        <div class="col-md-4 premium-floating-field">
                            <label class="form-label school-profile-khmer">ថ្ងៃខែឆ្នាំកំណើត (Date of Birth)</label>
                            <input id="date_of_birth_kh" class="form-control school-profile-khmer" readonly placeholder="ថ្ងៃ-ខែ-ឆ្នាំ">
                        </div>
                    </div>

                    <h4 class="mb-3" id="contactInformationHeading">Contact and Nationality</h4>
                    <div class="row g-3 mb-4" id="contactInformationFields">
                        <div class="col-md-4 premium-floating-field">
                            <label class="form-label">Nationality (Khmer / English)</label>
                            <select id="nationality_country_id" name="nationality_country_id" class="form-select d-none"></select>
                            <div id="student-nationality-combobox" class="location-combobox">
                                <button type="button" id="student-nationality-toggle" class="location-combobox-toggle">
                                    <span id="student-nationality-selected" class="location-combobox-selected"></span>
                                    <i class="ti ti-chevron-down"></i>
                                </button>
                                <div id="student-nationality-menu" class="location-combobox-menu d-none">
                                    <input id="student-nationality-search" type="search" class="form-control location-combobox-search" placeholder="Search Nationality">
                                    <div id="student-nationality-results" class="location-combobox-results"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 premium-floating-field">
                            <label class="form-label">Gender (Khmer / English)</label>
                            <select id="gender" name="gender" class="form-select d-none"></select>
                            <div id="student-gender-combobox" class="location-combobox">
                                <button type="button" id="student-gender-toggle" class="location-combobox-toggle">
                                    <span id="student-gender-selected" class="location-combobox-selected"></span>
                                    <i class="ti ti-chevron-down"></i>
                                </button>
                                <div id="student-gender-menu" class="location-combobox-menu d-none">
                                    <div id="student-gender-results" class="location-combobox-results"></div>
                                </div>
                            </div>
                            <input type="hidden" name="gender_kh" id="gender_kh">
                        </div>
                        <div class="col-md-4 premium-floating-field"><label class="form-label">Home Phone</label><div class="phone-input-group"><input type="text" id="home_phone_number" class="form-control" placeholder=" "><input type="hidden" name="home_phone" id="home_phone"></div></div>
                        <div class="col-md-4 premium-floating-field"><label class="form-label">E-mail</label><div class="input-icon"><span class="input-icon-addon"><i class="ti ti-mail"></i></span><input type="email" id="email" name="email" class="form-control" placeholder=" "></div></div>
                        <div class="col-md-4 premium-floating-field"><label class="form-label">Remarks</label><textarea id="remarks" name="remarks" class="form-control" rows="1" placeholder=" "></textarea></div>
                    </div>

                    <h4 class="mb-3">Place of Birth</h4>
                    <div class="row row-cols-1 row-cols-md-5 g-3 mb-4" id="birthPlaceFields">
                        <div class="col premium-floating-field">
                            <label class="form-label">Country</label>
                            <select id="birth_country_id" name="birth_country_id" class="form-select d-none"></select>
                            <div id="birth-country-combobox" class="location-combobox">
                                <button type="button" id="birth-country-toggle" class="location-combobox-toggle">
                                    <span id="birth-country-selected" class="location-combobox-selected"></span>
                                    <i class="ti ti-chevron-down"></i>
                                </button>
                                <div id="birth-country-menu" class="location-combobox-menu d-none">
                                    <input id="birth-country-search" type="text" class="form-control location-combobox-search" placeholder="Search Country">
                                    <div id="birth-country-results" class="location-combobox-results"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col premium-floating-field">
                            <label class="form-label">Province / City</label>
                            <select id="birth_province_id" name="birth_province_id" class="form-select d-none"></select>
                            <div id="birth-province-combobox" class="location-combobox">
                                <button type="button" id="birth-province-toggle" class="location-combobox-toggle">
                                    <span id="birth-province-selected" class="location-combobox-selected"></span>
                                    <i class="ti ti-chevron-down"></i>
                                </button>
                                <div id="birth-province-menu" class="location-combobox-menu d-none">
                                    <input id="birth-province-search" type="text" class="form-control location-combobox-search" placeholder="Search Province / City">
                                    <div id="birth-province-results" class="location-combobox-results"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col premium-floating-field">
                            <label class="form-label">District / Khan</label>
                            <select id="birth_district_id" name="birth_district_id" class="form-select d-none"></select>
                            <div id="birth-district-combobox" class="location-combobox">
                                <button type="button" id="birth-district-toggle" class="location-combobox-toggle">
                                    <span id="birth-district-selected" class="location-combobox-selected"></span>
                                    <i class="ti ti-chevron-down"></i>
                                </button>
                                <div id="birth-district-menu" class="location-combobox-menu d-none">
                                    <input id="birth-district-search" type="text" class="form-control location-combobox-search" placeholder="Search District / Khan">
                                    <div id="birth-district-results" class="location-combobox-results"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col premium-floating-field">
                            <label class="form-label">Commune</label>
                            <select id="birth_commune_id" name="birth_commune_id" class="form-select d-none"></select>
                            <div id="birth-commune-combobox" class="location-combobox">
                                <button type="button" id="birth-commune-toggle" class="location-combobox-toggle">
                                    <span id="birth-commune-selected" class="location-combobox-selected"></span>
                                    <i class="ti ti-chevron-down"></i>
                                </button>
                                <div id="birth-commune-menu" class="location-combobox-menu d-none">
                                    <input id="birth-commune-search" type="text" class="form-control location-combobox-search" placeholder="Search Commune">
                                    <div id="birth-commune-results" class="location-combobox-results"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col premium-floating-field">
                            <label class="form-label">Village</label>
                            <select id="birth_village_id" name="birth_village_id" class="form-select d-none"></select>
                            <div id="birth-village-combobox" class="location-combobox">
                                <button type="button" id="birth-village-toggle" class="location-combobox-toggle">
                                    <span id="birth-village-selected" class="location-combobox-selected"></span>
                                    <i class="ti ti-chevron-down"></i>
                                </button>
                                <div id="birth-village-menu" class="location-combobox-menu d-none">
                                    <input id="birth-village-search" type="text" class="form-control location-combobox-search" placeholder="Search Village">
                                    <div id="birth-village-results" class="location-combobox-results"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4 class="mb-3">Home Address</h4>
                    <div class="row g-3 mb-4" id="addressFields">
                        <div class="col-md-4 premium-floating-field"><label class="form-label">Country</label><select id="address_country_id" name="address_country_id" class="form-select"></select></div>
                        <div class="col-md-4 premium-floating-field"><label class="form-label">Province/City</label><select id="address_province_id" name="address_province_id" class="form-select"></select></div>
                        <div class="col-md-4 premium-floating-field"><label class="form-label">District/Khan</label><select id="address_district_id" name="address_district_id" class="form-select"></select></div>
                        <div class="col-md-4 premium-floating-field"><label class="form-label">Commune</label><select id="address_commune_id" name="address_commune_id" class="form-select"></select></div>
                        <div class="col-md-4 premium-floating-field"><label class="form-label">Village</label><select id="address_village_id" name="address_village_id" class="form-select"></select></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">House No. (English)</label><input id="address_house_no_en" name="address_house_no_en" class="form-control" placeholder=" "></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">House No. (Khmer)</label><input id="address_house_no_kh" name="address_house_no_kh" class="form-control school-profile-khmer" placeholder=" "></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Street (English)</label><input id="address_street_en" name="address_street_en" class="form-control" placeholder=" "></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Street (Khmer)</label><input id="address_street_kh" name="address_street_kh" class="form-control school-profile-khmer" placeholder=" "></div>
                        <div class="col-md-6 premium-floating-field"><label class="form-label">Current Address (English)</label><textarea id="current_address_en" name="current_address_en" class="form-control" rows="1" readonly></textarea></div>
                        <div class="col-md-6 premium-floating-field"><label class="form-label">Current Address (Khmer)</label><textarea id="current_address_kh" name="current_address_kh" class="form-control school-profile-khmer" rows="1" readonly></textarea></div>
                    </div>

                    <h4 class="mb-3">Previous School and Assessment</h4>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Previous School</label><input id="previous_school" name="previous_school" class="form-control" placeholder=" "></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Tested By</label><input id="tested_by" name="tested_by" class="form-control" placeholder=" "></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Experienced English</label><textarea id="experienced_english" name="experienced_english" class="form-control" rows="1" placeholder=" "></textarea></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Test Result</label><textarea id="test_result" name="test_result" class="form-control" rows="1" placeholder=" "></textarea></div>
                    </div>

                    <h4 class="mb-3">Enrollment Information</h4>
                    <div class="row g-3" id="enrollmentInformationFields">
                        <div class="col-md-4 premium-floating-field">
                            <label class="form-label">Academic Year *</label>
                            <select id="academic_year_id" name="academic_year_id" class="form-select"></select>
                        </div>
                        <div class="col-md-4 premium-floating-field">
                            <label class="form-label">Campus *</label>
                            <select id="campus_id" name="campus_id" class="form-select d-none"></select>
                            <div id="campus-combobox" class="location-combobox">
                                <button type="button" id="campus-toggle" class="location-combobox-toggle">
                                    <span id="campus-selected" class="location-combobox-selected"></span>
                                    <i class="ti ti-chevron-down"></i>
                                </button>
                                <div id="campus-menu" class="location-combobox-menu d-none">
                                    <input type="search" id="campus-search" class="form-control location-combobox-search" placeholder="Search Campus">
                                    <div id="campus-results" class="location-combobox-results"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 premium-floating-field">
                            <label class="form-label">Grade *</label>
                            <select id="grade_id" name="grade_id" class="form-select"></select>
                        </div>
                        <div class="col-md-4 premium-floating-field">
                            <label class="form-label">Class *</label>
                            <select id="class_id" name="class_id" class="form-select"></select>
                        </div>
                        <div class="col-md-4 premium-floating-field d-none" id="academic_track_field">
                            <label class="form-label">Academic Track <span class="text-danger">*</span></label>
                            <select id="academic_track_id" name="academic_track_id" class="form-select"></select>
                        </div>
                        <div class="col-md-4 premium-floating-field">
                            <label class="form-label">Group <span class="text-danger">*</span></label>
                            <select id="session_id" name="session_id" class="form-select" required></select>
                        </div>
                        <div class="col-md-4 premium-floating-field">
                            <label class="form-label">Enrollment Status</label>
                            <select id="enrollment_status" name="enrollment_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="withdrawn">Withdrawn</option>
                                <option value="transferred">Transferred</option>
                                <option value="graduated">Graduated</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-4 premium-floating-field">
                            <label class="form-label">Enrolled On</label>
                            <div class="date-picker" id="enrolled_on_picker">
                                <div class="date-picker-input-row">
                                    <input type="text" id="enrolled_on_direct" class="form-control" inputmode="numeric" placeholder="DD-MM-YYYY" aria-label="Enter enrollment date directly">
                                    <button type="button" id="enrolled_on_trigger" class="date-picker-trigger date-picker-calendar-button"><i class="ti ti-calendar"></i><span class="date-picker-display d-none"></span><i class="ti ti-chevron-down d-none"></i></button>
                                </div>
                                <input type="hidden" id="enrolled_on" name="enrolled_on">
                                <div id="enrolled_on_popup" class="date-picker-popup d-none">
                                    <div class="date-picker-header"><button type="button" id="enrolled_on_prev" class="date-picker-nav"><i class="ti ti-chevron-left"></i></button><button type="button" id="enrolled_on_year_toggle" class="date-picker-year-toggle"><span id="enrolled_on_month_label"></span></button><button type="button" id="enrolled_on_next" class="date-picker-nav"><i class="ti ti-chevron-right"></i></button></div>
                                    <div id="enrolled_on_year_popup" class="date-picker-year-popup d-none"><div id="enrolled_on_years" class="date-picker-years"></div></div>
                                    <div class="date-picker-grid"><div class="date-picker-weekdays"><span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span></div><div id="enrolled_on_days" class="date-picker-days"></div></div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="status" name="status" value="1">
                    </div>
    <h4 class="mb-3 mt-4">Family Information <span class="text-secondary fw-normal fs-5">(Optional)</span></h4>
                    <div class="row g-3 mb-4 family-information" id="familyInformationFields">
                        <div class="family-member-row"><div class="family-member-heading">Mother</div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Mother Full Name (English) <span class="text-danger">*</span></label><input name="mother_name_en" class="form-control" placeholder=" " required></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Mother Full Name (Khmer)</label><input name="mother_name_kh" class="form-control school-profile-khmer" placeholder=" "></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Occupation (English)</label><select name="mother_occupation_id" id="mother_occupation_id" class="form-select d-none"><option value="">Occupation (English)</option></select><div id="mother-occupation-combobox" class="location-combobox"><button type="button" id="mother-occupation-toggle" class="location-combobox-toggle"><span id="mother-occupation-selected" class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div id="mother-occupation-menu" class="location-combobox-menu d-none"><input id="mother-occupation-search" type="search" class="form-control location-combobox-search" placeholder="Search Occupation"><div id="mother-occupation-results" class="location-combobox-results"></div></div></div></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Nationality (English)</label><select name="mother_nationality_country_id" id="mother_nationality_country_id" class="form-select d-none"><option value="">Nationality (English)</option></select><div id="mother-nationality-combobox" class="location-combobox"><button type="button" id="mother-nationality-toggle" class="location-combobox-toggle"><span id="mother-nationality-selected" class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div id="mother-nationality-menu" class="location-combobox-menu d-none"><input id="mother-nationality-search" type="search" class="form-control location-combobox-search" placeholder="Search Nationality"><div id="mother-nationality-results" class="location-combobox-results"></div></div></div></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Phone Number <span class="text-danger">*</span></label><div class="phone-input-group"><input type="text" id="mother_phone_number" class="form-control" placeholder=" " required><input type="hidden" name="mother_phone" id="mother_phone"></div></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Work Place</label><input name="mother_workplace" class="form-control" placeholder=" "></div></div>
                        <div class="family-member-row"><div class="family-member-heading">Father</div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Father Full Name (English) <span class="text-danger">*</span></label><input name="father_name_en" class="form-control" placeholder=" " required></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Father Full Name (Khmer)</label><input name="father_name_kh" class="form-control school-profile-khmer" placeholder=" "></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Occupation (English)</label><select name="father_occupation_id" id="father_occupation_id" class="form-select d-none"><option value="">Occupation (English)</option></select><div id="father-occupation-combobox" class="location-combobox"><button type="button" id="father-occupation-toggle" class="location-combobox-toggle"><span id="father-occupation-selected" class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div id="father-occupation-menu" class="location-combobox-menu d-none"><input id="father-occupation-search" type="search" class="form-control location-combobox-search" placeholder="Search Occupation"><div id="father-occupation-results" class="location-combobox-results"></div></div></div></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Nationality (English)</label><select name="father_nationality_country_id" id="father_nationality_country_id" class="form-select d-none"><option value="">Nationality (English)</option></select><div id="father-nationality-combobox" class="location-combobox"><button type="button" id="father-nationality-toggle" class="location-combobox-toggle"><span id="father-nationality-selected" class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div id="father-nationality-menu" class="location-combobox-menu d-none"><input id="father-nationality-search" type="search" class="form-control location-combobox-search" placeholder="Search Nationality"><div id="father-nationality-results" class="location-combobox-results"></div></div></div></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Phone Number <span class="text-danger">*</span></label><div class="phone-input-group"><input type="text" id="father_phone_number" class="form-control" placeholder=" " required><input type="hidden" name="father_phone" id="father_phone"></div></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Work Place</label><input name="father_workplace" class="form-control" placeholder=" "></div></div>
                        <div class="family-member-row"><div class="family-member-heading">Guardian</div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Guardian Full Name (English)</label><input name="guardian_name_en" class="form-control" placeholder=" "></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Guardian Full Name (Khmer)</label><input name="guardian_name_kh" class="form-control school-profile-khmer" placeholder=" "></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Occupation (English)</label><select name="guardian_occupation_id" id="guardian_occupation_id" class="form-select d-none"><option value="">Occupation (English)</option></select><div id="guardian-occupation-combobox" class="location-combobox"><button type="button" id="guardian-occupation-toggle" class="location-combobox-toggle"><span id="guardian-occupation-selected" class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div id="guardian-occupation-menu" class="location-combobox-menu d-none"><input id="guardian-occupation-search" type="search" class="form-control location-combobox-search" placeholder="Search Occupation"><div id="guardian-occupation-results" class="location-combobox-results"></div></div></div></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Nationality (English)</label><select name="guardian_nationality_country_id" id="guardian_nationality_country_id" class="form-select d-none"><option value="">Nationality (English)</option></select><div id="guardian-nationality-combobox" class="location-combobox"><button type="button" id="guardian-nationality-toggle" class="location-combobox-toggle"><span id="guardian-nationality-selected" class="location-combobox-selected"></span><i class="ti ti-chevron-down"></i></button><div id="guardian-nationality-menu" class="location-combobox-menu d-none"><input id="guardian-nationality-search" type="search" class="form-control location-combobox-search" placeholder="Search Nationality"><div id="guardian-nationality-results" class="location-combobox-results"></div></div></div></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Phone Number</label><div class="phone-input-group"><input type="text" id="guardian_phone_number" class="form-control" placeholder=" "><input type="hidden" name="guardian_phone" id="guardian_phone"></div></div>
                        <div class="col-md-3 premium-floating-field"><label class="form-label">Work Place</label><input name="guardian_workplace" class="form-control" placeholder=" "></div></div>
                    </div>

                    <h4 class="mb-3">Student Documents <span class="text-secondary fw-normal fs-5">(Optional)</span></h4>
                    <ul class="nav nav-tabs mb-3" role="tablist"><li class="nav-item"><button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#enrollment-document-upload-tab">Upload Document</button></li><li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#enrollment-document-list-tab" id="enrollment-document-list-tab-button">Submitted Documents</button></li></ul>
                    <div class="tab-content"><div class="tab-pane fade show active" id="enrollment-document-upload-tab"><div class="student-document-upload-layout mb-4"><div class="student-document-upload-fields" id="studentDocumentsFields"><div><label class="form-label">Document Type</label><select id="enrollment-document-type" class="form-select"></select></div><div><label class="form-label">Document Title</label><input id="enrollment-document-title" class="form-control"></div><div><label class="form-label">Document Number</label><input id="enrollment-document-number" class="form-control"></div><div class="document-description-field"><label class="form-label">Description</label><textarea id="enrollment-document-description" class="form-control" rows="2"></textarea><div class="form-hint">Documents are saved after the enrollment is saved.</div></div></div><div><div id="enrollmentDocumentDropzone" class="premium-document-dropzone" tabindex="0"><span class="document-upload-icon"><i class="ti ti-cloud-upload"></i></span><div class="fw-bold fs-3">Drop Files Here</div><div class="mt-1">or <span class="document-browse-link">Browse File</span></div><div class="document-upload-hint mt-3">Supports PDF, JPG, PNG, DOC, DOCX · Maximum 2 MB per file</div><div id="enrollmentDocumentFileList" class="premium-document-file-list"></div><input type="file" id="enrollment-document-file" class="d-none" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" multiple></div></div></div></div><div class="tab-pane fade" id="enrollment-document-list-tab"><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Document Type</th><th>Title</th><th>Number</th><th>File</th></tr></thead><tbody id="enrollment-document-list"><tr><td colspan="4" class="text-center text-secondary">Save or select a student to view submitted documents.</td></tr></tbody></table></div></div></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn me-auto" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-primary" id="enrollmentSubmit">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal modal-blur fade" id="studentProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h3 class="modal-title">Student Profile</h3><div class="d-flex align-items-center gap-2 ms-auto"><button type="button" class="btn btn-primary btn-sm" id="studentProfilePrint"><i class="ti ti-printer me-1"></i>Print Report</button><button type="button" class="btn btn-outline-primary btn-sm" id="studentProfilePdf"><i class="ti ti-file-type-pdf me-1"></i>PDF</button><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div></div>
            <div class="modal-body" id="studentProfileContent"></div>
        </div>
    </div>
</div>
<div class="modal modal-blur fade" id="studentPhotoCropModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h3 class="modal-title">Crop Student Photo</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="text-secondary small">Adjust the photo inside the locked 3:4 frame. The final image will be exactly 600 x 800 px.</p>
                <div class="student-photo-crop-stage"><canvas id="studentPhotoCropCanvas" width="600" height="800"></canvas></div>
                <div class="row g-2 align-items-center mt-3">
                    <div class="col-auto"><button type="button" class="btn btn-outline-secondary" id="studentPhotoZoomOut"><i class="ti ti-zoom-out"></i></button></div>
                    <div class="col"><input type="range" class="form-range" id="studentPhotoZoom" min="1" max="3" step="0.01" value="1" aria-label="Zoom photo"></div>
                    <div class="col-auto"><button type="button" class="btn btn-outline-secondary" id="studentPhotoZoomIn"><i class="ti ti-zoom-in"></i></button></div>
                    <div class="col-auto"><button type="button" class="btn btn-outline-secondary" id="studentPhotoRotateLeft"><i class="ti ti-rotate-2"></i> Left</button></div>
                    <div class="col-auto"><button type="button" class="btn btn-outline-secondary" id="studentPhotoRotateRight"><i class="ti ti-rotate-clockwise-2"></i> Right</button></div>
                    <div class="col-auto"><button type="button" class="btn btn-outline-secondary" id="studentPhotoReset">Reset</button></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="studentPhotoCropUpload"><i class="ti ti-crop me-1"></i>Crop and Upload</button></div>
        </div>
    </div>
</div>
<div class="modal modal-blur fade" id="studentPhotoViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h3 class="modal-title" id="studentPhotoViewTitle">Student Photo</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body text-center overflow-hidden"><img id="studentPhotoViewImage" src="#" alt="Student photo"></div>
            <div class="modal-footer justify-content-between">
                <div class="d-flex align-items-center gap-2"><button type="button" class="btn btn-outline-secondary" id="studentPhotoViewZoomOut"><i class="ti ti-zoom-out"></i></button><input type="range" id="studentPhotoViewZoom" min="1" max="3" step=".05" value="1" style="width:150px" aria-label="Zoom student photo"><button type="button" class="btn btn-outline-secondary" id="studentPhotoViewZoomIn"><i class="ti ti-zoom-in"></i></button><button type="button" class="btn btn-outline-secondary" id="studentPhotoViewZoomReset">Reset</button></div>
                <a class="btn btn-primary" id="studentPhotoViewDownload" href="#" download><i class="ti ti-download me-1"></i>Download</a>
            </div>
        </div>
    </div>
</div>
<div class="modal modal-blur fade" id="enrollmentHistoryModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h3 class="modal-title" id="enrollmentHistoryTitle">Enrollment History</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Type</th><th>Action</th><th>Academic Year</th><th>Campus</th><th>Grade</th><th>Class</th><th>Track</th><th>Group</th><th>Status</th><th>Updated Date &amp; Time</th><th>Updated By</th></tr></thead><tbody id="enrollmentHistoryTable"><tr><td colspan="11" class="text-center">No history found.</td></tr></tbody></table></div></div></div></div></div>
@vite('resources/js/studentEnrollment.js')
@endsection
