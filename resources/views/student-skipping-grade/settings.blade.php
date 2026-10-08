@extends('layouts.app')
@section('title','Grade Skipping Approval Settings')
@section('page-header')
<div class="container-fluid"><div class="page-pretitle">Student Skipping Grade</div><h2 class="page-title">Approval Settings</h2></div>
@endsection
@section('content')
@include('student-skipping-grade._workspace-start',['activeTab'=>'settings'])
<div class="skipping-page" data-skipping-page data-success="{{ session('success') }}"><form method="post" action="{{ route('student-skipping-grade.settings.save') }}" enctype="multipart/form-data" data-skipping-form novalidate>@csrf
    <fieldset @disabled(!$canSave)>
    <div class="card mb-3"><div class="card-header skipping-header"><h3 class="card-title">VP Digital Signature and Stamp</h3></div><div class="card-body"><div class="skipping-form-grid">
        <label>VP Name Khmer <span class="text-danger">*</span><input class="form-control skipping-kh" name="signer_name_kh" value="{{ $settings->signer_name_kh }}" maxlength="200"></label>
        <label>VP Name English<input class="form-control" name="signer_name_en" value="{{ $settings->signer_name_en }}" maxlength="200"></label>
        <label>Title Khmer <span class="text-danger">*</span><input class="form-control skipping-kh" name="signer_title_kh" value="{{ $settings->signer_title_kh }}" maxlength="200"></label>
        <label>Title English <span class="text-danger">*</span><input class="form-control" name="signer_title_en" value="{{ $settings->signer_title_en }}" maxlength="200"></label>
        <label>Approval Number Prefix <span class="text-danger">*</span><input class="form-control" name="number_prefix" value="{{ $settings->number_prefix }}" maxlength="16"><small class="text-secondary">Prefix + requested year's AY Code + request ID. Example: SG + AY Code 2526 → SG2526-001.</small></label>
    </div><div class="skipping-upload-grid mt-3">
        @include('student-skipping-grade._image-upload',['uploadName'=>'signature','uploadLabel'=>'Digital Signature','uploadImage'=>$signature])
        @include('student-skipping-grade._image-upload',['uploadName'=>'stamp','uploadLabel'=>'School Stamp','uploadImage'=>$stamp])
    </div><p class="text-secondary mt-3 mb-0">Transparent PNG or WEBP images work best. Uploads remain private; approved forms retain the original signature and stamp.</p></div></div>
    <div class="card mb-3"><div class="card-header skipping-header"><h3 class="card-title">Default Committee Names</h3></div><div class="card-body"><div class="skipping-form-grid">@foreach($centralCommitteeRoles as $index=>$role)<label>{{ $loop->iteration }}. {{ $role }}<input class="form-control" name="committee_names[{{ $index }}]" value="{{ $settings->committee_names[$index]??'' }}" maxlength="200"></label>@endforeach</div></div></div>
    </fieldset>
    @if($canSave)<div class="skipping-save-bar"><button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>Save Approval Settings</button></div>@endif
</form></div>
@include('student-skipping-grade._workspace-end')
@endsection
@push('styles') @vite('resources/css/pages/student-skipping-grade.css') @endpush
@push('scripts') @vite('resources/js/studentSkippingGrade.js') @endpush
