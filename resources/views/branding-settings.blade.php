@extends('layouts.app')

@section('title', 'Branding Settings')

@section('content')
<div class="row row-cards">
    <div class="col-12">
        <form class="card" method="POST" action="{{ route('branding-settings.save') }}" enctype="multipart/form-data">
            @csrf
            <div class="card-header"><h3 class="card-title">Branding Settings</h3></div>
            <div class="card-body">
                @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
                @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
                <div class="row g-4">
                    @foreach([['sidebar_logo','Sidebar Logo','sidebar_logo_path','image/*,.svg'],['login_logo','Login Logo','login_logo_path','image/*,.svg'],['favicon','Favicon','favicon_path','image/*,.ico,.svg'],['footer_logo','Footer Logo','footer_logo_path','image/*,.svg']] as [$field,$label,$path,$accept])
                    <div class="col-md-4">
                        <label class="form-label">{{ $label }}</label>
                        <div class="logo-upload-row d-flex align-items-stretch gap-3">
                            <div class="logo-dropzone flex-fill" data-branding-dropzone="{{ $field }}" tabindex="0">
                                <i class="ti ti-cloud-upload logo-dropzone-icon"></i>
                                <div><strong>Drag and drop {{ strtolower($label) }} here</strong></div>
                                <div class="text-secondary">or click to choose a file</div>
                                <input type="file" name="{{ $field }}" id="{{ $field }}" class="d-none" accept="{{ $accept }}">
                                <div class="branding-preview-wrap {{ $branding->{$path} ? '' : 'd-none' }}" data-branding-preview-wrap="{{ $field }}">
                                    <img src="{{ $branding->{$path} ? asset('storage/'.$branding->{$path}) : '#' }}" alt="{{ $label }} preview" data-branding-preview="{{ $field }}">
                                </div>
                            </div>
                        </div>
                        @if($branding->{$path})
                            <label class="form-check mt-2"><input type="checkbox" name="remove_{{ $field }}" value="1" class="form-check-input"><span class="form-check-label text-danger">Remove current {{ strtolower($label) }}</span></label>
                        @endif
                        <div class="text-secondary small mt-2">Upload a new image to replace the current one.</div>
                    </div>
                    @endforeach
                    <div class="col-12">
                        <label class="form-label">Footer Text</label>
                        <input name="footer_text" class="form-control" maxlength="255" value="{{ old('footer_text', $branding->footer_text) }}" placeholder="All rights reserved.">
                    </div>
                </div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary">Save Branding</button></div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
@vite('resources/js/brandingSettings.js')
@endpush
