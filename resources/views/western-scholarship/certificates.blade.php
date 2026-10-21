@extends('layouts.app')
@section('title','Scholarship Certificates')
@section('content')
<div class="scholarship-workspace scholarship-tabs-collapsed" data-scholarship-page data-certificates>
    <aside class="card scholarship-navigation">
        <div class="card-header scholarship-card-header scholarship-tabs-header"><h3 class="card-title">WIS Scholarship</h3><button type="button" class="btn btn-icon btn-outline-primary scholarship-tabs-toggle" aria-expanded="false" aria-controls="scholarshipTabList" aria-label="Expand scholarship tabs"><i class="ti ti-layout-sidebar-left-expand"></i></button></div>
        <nav class="list-group list-group-flush" id="scholarshipTabList" aria-label="WIS Scholarship sections">
            @foreach($tabs as $key=>[$label,$icon])<a href="{{ route('western-scholarship.index',['tab'=>$key,'academic_year_id'=>$year?->id]) }}" class="list-group-item list-group-item-action {{ $key===$tab?'active':'' }}" aria-label="{{ $label }}" data-scholarship-tab-label="{{ $label }}"><i class="ti {{ $icon }}"></i><span class="scholarship-tab-label">{{ $label }}</span></a>@endforeach
        </nav>
    </aside>
    <main class="scholarship-forms">
        @include('western-scholarship._certificate-filters')
        @if(!$year)<div class="alert alert-info">Create an academic year to prepare certificates.</div>@else
        @if(!$exam?->published_at)<div class="alert alert-info">Publish this academic year’s scholarship results to prepare and issue certificates. You can customize the templates now.</div>@endif
        <section class="card mb-3">
            <div class="card-header scholarship-card-header flex-wrap gap-2"><h3 class="card-title"><i class="ti ti-certificate me-2"></i>Certificates</h3><div class="d-flex flex-wrap gap-2 ms-auto">
                @if($permissions['certificate-print'])
                    <button type="button" class="btn btn-sm btn-outline-light" data-cert-output="print" aria-haspopup="dialog"><i class="ti ti-printer me-1" aria-hidden="true"></i>A4 Print Preview</button>
                @endif
                @if($permissions['certificate-assign'])<button type="button" class="btn btn-sm btn-outline-light" data-cert-plan @disabled(!$exam?->published_at)><i class="ti ti-list-numbers me-1" aria-hidden="true"></i>Assign Cert. No.</button>@endif
                @if($permissions['certificate-settings'])<button type="button" class="btn btn-sm btn-outline-light" data-cert-open-settings aria-haspopup="dialog" aria-controls="certSettings"><i class="ti ti-settings me-1" aria-hidden="true"></i>Settings</button>@endif
                @if($permissions['certificate-template'])<button type="button" class="btn btn-sm btn-outline-light" data-cert-toggle="certEditor" aria-expanded="false"><i class="ti ti-edit me-1" aria-hidden="true"></i>Customize Templates</button>@endif
            </div></div>
            <div data-cert-list>@include('western-scholarship._certificate-list')</div>
        </section>
        @if($permissions['certificate-template'])
        <section class="card mb-3" id="certEditor" hidden><div class="card-header flex-wrap gap-2"><h3 class="card-title">Customize Certificate</h3><span class="badge bg-yellow-lt ms-auto" data-cert-unsaved hidden>Unsaved changes</span><button type="button" class="btn btn-primary" data-cert-save>Save Template</button><button type="button" class="btn btn-sm btn-outline-secondary" data-cert-toggle="certEditor" aria-label="Close template editor">Close</button></div>
            <div class="card-body cert-editor-grid">
                <div class="cert-controls">
                    <label class="form-label" for="certTemplate">Certificate Type</label><select class="form-select mb-3" id="certTemplate">@foreach(\App\Support\ScholarshipCertificateLayout::TYPES as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                    <label class="form-label" for="certField">Select Text / Logo</label><select class="form-select mb-3" id="certField">@foreach($settings['layouts']['class']['fields'] as $key=>$field)<option value="{{ $key }}">{{ $field['label'] }}</option>@endforeach</select>
                    <label class="form-check mb-3"><input type="checkbox" class="form-check-input" data-cert-property="visible"><span class="form-check-label">Display this field</span></label>
                    <div data-cert-text-controls><label class="form-label" for="certText">Text</label><textarea class="form-control mb-2" id="certText" rows="5" maxlength="2000" data-cert-property="text"></textarea>
                        <label class="form-label" for="certToken">Insert Automatic Value</label><select class="form-select mb-3" id="certToken"><option value="">Choose a value…</option>@foreach(\App\Support\ScholarshipCertificateLayout::TOKENS as $token)<option value="{{ $token }}">{{ str_replace('_',' ',$token) }}</option>@endforeach</select>
                        <label class="form-label" for="certFont">Font</label><select class="form-select mb-2" id="certFont" data-cert-property="font">@foreach(\App\Support\ScholarshipCertificateLayout::fonts() as $key=>$font)<option value="{{ $key }}">{{ $font['label'] }}</option>@endforeach</select>
                        <div class="row g-2 mb-2"><div class="col-6"><label class="form-label" for="certSize">Font Size (pt)</label><input class="form-control" id="certSize" type="number" min="6" max="60" step="0.5" data-cert-property="size"></div><div class="col-6"><label class="form-label" for="certColor">Color</label><input class="form-control form-control-color w-100" id="certColor" type="color" data-cert-property="color"></div></div>
                        <div class="d-flex gap-3 align-items-center mb-3"><select class="form-select" data-cert-property="align" aria-label="Text alignment"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select><label class="form-check mb-0"><input type="checkbox" class="form-check-input" data-cert-property="bold"><span class="form-check-label">Bold</span></label></div>
                    </div>
                    <div data-cert-logo-controls hidden><label class="form-label" for="certLogoSource">Logo</label><select class="form-select mb-3" id="certLogoSource"><option value="branding1">Branding — School Logo 1</option><option value="branding2">Branding — School Logo 2</option><option value="upload">Uploaded Logo</option></select></div>
                    <div class="row g-2">@foreach(['x'=>'Left (mm)','y'=>'Top (mm)','width'=>'Width (mm)','height'=>'Height (mm)'] as $key=>$label)<div class="col-6"><label class="form-label" for="certProp{{ $key }}">{{ $label }}</label><input class="form-control" id="certProp{{ $key }}" type="number" min="{{ in_array($key,['x','y'])?0:1 }}" max="{{ in_array($key,['x','width'])?210:297 }}" step="0.5" data-cert-property="{{ $key }}"></div>@endforeach</div>
                    <div class="d-flex gap-2 my-3 align-items-center"><span class="small">Move 1 mm</span>@foreach(['left'=>'←','up'=>'↑','down'=>'↓','right'=>'→'] as $direction=>$symbol)<button type="button" class="btn btn-sm btn-outline-primary" data-cert-nudge="{{ $direction }}" aria-label="Move {{ $direction }}">{{ $symbol }}</button>@endforeach</div>
                    <p class="small text-secondary">Drag a field on the preview to reposition it. Long names automatically shrink to fit their box.</p>
                    <div class="row g-2 mb-3">@foreach(['x'=>'Horizontal','y'=>'Vertical'] as $axis=>$label)<div class="col-6"><label class="form-label" for="certOffset{{ $axis }}">{{ $label }} Print Offset (mm)</label><input class="form-control" id="certOffset{{ $axis }}" type="number" min="-10" max="10" step="0.5" value="0" data-cert-offset="{{ $axis }}"></div>@endforeach</div>
                    <button type="button" class="btn btn-outline-secondary" data-cert-reset>Reset This Template</button>
                </div>
                <div class="cert-preview-column"><div class="d-flex justify-content-between mb-2"><span>Live A4 Preview</span><span class="text-secondary small">Sample content • not issued</span></div><div class="cert-canvas-shell"><div class="cert-sheet" data-cert-canvas aria-label="Certificate layout preview"></div></div></div>
            </div>
        </section>@endif
        <script type="application/json" id="certData">{!! json_encode($editor,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
        @endif
    </main>
    @if($year && $permissions['certificate-settings'])@include('western-scholarship._certificate-settings')@endif
</div>
@vite(['resources/css/pages/western-scholarship.css','resources/css/pages/scholarship-certificates.css','resources/js/westernScholarship.js','resources/js/scholarshipCertificates.js'])
@endsection
