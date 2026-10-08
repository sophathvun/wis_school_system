@extends('layouts.app')
@section('title','Customize '.ucfirst($form).' Form')
@section('page-header')
<div class="container-fluid"><div class="page-pretitle">Students · Stu. Skipping Grade</div><h2 class="page-title">Customize {{ ucfirst($form) }} Form</h2></div>
@endsection
@section('content')
@include('student-skipping-grade._workspace-start',['activeTab'=>$form])
<div class="skipping-page skipping-template-editor" data-skipping-editor data-save-url="{{ route('student-skipping-grade.template.save',$form) }}">
    <div class="card mb-3">
        <div class="card-header skipping-header skipping-editor-header">
            <div class="skipping-editor-heading"><h3 class="card-title">Premium Form Editor · {{ ucfirst($form) }}</h3><span class="small text-white opacity-75" data-template-status role="status">Loading preview…</span></div>
            <div class="skipping-editor-actions"><span class="badge bg-white text-primary">A4 Portrait</span><button class="btn btn-outline-light" type="button" data-template-editor-toggle aria-expanded="false" aria-pressed="false" aria-controls="skippingEditorPanel" disabled><i class="ti ti-edit me-1" aria-hidden="true"></i><span data-template-editor-toggle-label>Edit Report</span></button></div>
        </div>
        <div class="card-body" id="skippingEditorPanel" data-template-editor-panel hidden>
            <div class="skipping-editor-actions"><button class="btn btn-primary" type="button" data-template-save disabled><i class="ti ti-device-floppy me-1"></i>Save Template</button><button class="btn btn-outline-secondary" type="button" data-template-discard disabled>Discard Changes</button><button class="btn btn-outline-secondary" type="button" data-template-restore disabled>Restore Original Layout</button></div>
            <p class="text-secondary mt-3">Select text, a line, a box, a checkbox, or the school logo in the preview. Drag to move, or edit its position and size below. The logo keeps its proportions inside the Width and Height you set. Link an added checkbox to an existing value to replace an original checkbox, or choose New form option and set its Form Option Label. Add a text box beside it for the printed label. Saved templates apply to all campuses. Keep automatic values in braces for student details and dates.</p>
            <div class="skipping-editor-actions mb-3"><button class="btn btn-outline-primary" type="button" data-template-add="text" disabled><i class="ti ti-text-size me-1"></i>Add Text Box</button><button class="btn btn-outline-primary" type="button" data-template-add="box" disabled><i class="ti ti-square me-1"></i>Add Box</button><button class="btn btn-outline-primary" type="button" data-template-add="line" disabled><i class="ti ti-line me-1"></i>Add Dotted Line</button><button class="btn btn-outline-primary" type="button" data-template-add="checkbox" disabled><i class="ti ti-checkbox me-1"></i>Add Checkbox</button></div>
            <fieldset data-template-controls disabled>
                <div class="skipping-editor-controls">
                    <div class="premium-form-field"><label class="form-label" for="skippingBlock">Selected Object</label><select class="form-select" id="skippingBlock" data-template-block-select></select></div>
                    <div class="premium-form-field"><label class="form-label" for="skippingFont">Font</label><select class="form-select" id="skippingFont" data-template-control="font">@foreach($fonts as $font)<option>{{ $font }}</option>@endforeach</select></div>
                    <div class="premium-form-field"><label class="form-label" for="skippingSize">Size (pt)</label><input class="form-control" id="skippingSize" type="number" min="6" max="60" step="0.5" data-template-control="size"></div>
                    <div class="premium-form-field"><label class="form-label" for="skippingColor">Color</label><input class="form-control form-control-color" id="skippingColor" type="color" data-template-control="color"></div>
                    <div class="premium-form-field"><label class="form-label" for="skippingLeft">Left Offset (mm)</label><input class="form-control" id="skippingLeft" type="number" min="-210" max="210" step="0.5" data-template-control="left"></div>
                    <div class="premium-form-field"><label class="form-label" for="skippingTop">Top Offset (mm)</label><input class="form-control" id="skippingTop" type="number" min="-297" max="297" step="0.5" data-template-control="top"></div>
                </div>
                <div class="skipping-editor-controls skipping-editor-shape-controls mt-3">
                    <div class="premium-form-field"><label class="form-label" for="skippingWidth">Width (mm)</label><input class="form-control" id="skippingWidth" type="number" min="1" max="210" step="0.5" data-template-control="width" placeholder="Auto"></div>
                    <div class="premium-form-field"><label class="form-label" for="skippingHeight">Height (mm)</label><input class="form-control" id="skippingHeight" type="number" min="0" max="297" step="0.5" data-template-control="height" placeholder="Auto"></div>
                    <div class="premium-form-field"><label class="form-label" for="skippingBorderStyle">Border Style</label><select class="form-select" id="skippingBorderStyle" data-template-control="border_style">@foreach(['none','solid','dotted','dashed'] as $style)<option value="{{ $style }}">{{ ucfirst($style) }}</option>@endforeach</select></div>
                    <div class="premium-form-field"><label class="form-label" for="skippingBorderWidth">Border (px)</label><input class="form-control" id="skippingBorderWidth" type="number" min="0" max="8" step="0.5" data-template-control="border_width"></div>
                    <div class="premium-form-field"><label class="form-label" for="skippingBorderColor">Border Color</label><input class="form-control form-control-color" id="skippingBorderColor" type="color" data-template-control="border_color"></div>
                    <div class="premium-form-field"><label class="form-label" for="skippingFill">Fill Color</label><input class="form-control form-control-color" id="skippingFill" type="color" data-template-control="fill"></div>
                </div>
                <div class="premium-form-field mt-3" data-template-checkbox-source hidden><label class="form-label" for="skippingCheckboxSource">Checkbox Value</label><select class="form-select" id="skippingCheckboxSource" data-template-control="checkbox_source"><option value="">New form option</option>@foreach($checkboxSources as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                <div class="premium-form-field mt-3" data-template-option-label hidden><label class="form-label" for="skippingOptionLabel">Form Option Label</label><input class="form-control" id="skippingOptionLabel" maxlength="200" data-template-control="option_label"></div>
                <div class="skipping-editor-move mt-3">
                    <label class="form-check"><input class="form-check-input" type="checkbox" data-template-control="bold"><span class="form-check-label">Bold</span></label>
                    <label class="form-check"><input class="form-check-input" type="checkbox" data-template-control="bullet"><span class="form-check-label">Bullets</span></label>
                    <label class="form-check" data-template-checked hidden><input class="form-check-input" type="checkbox" data-template-control="checked"><span class="form-check-label">Selected by default on new forms</span></label>
                    <div class="btn-group" role="group" aria-label="Move selected object by 0.5 millimeters"><button type="button" class="btn btn-outline-primary" data-template-move="left" aria-label="Move left"><i class="ti ti-arrow-left"></i></button><button type="button" class="btn btn-outline-primary" data-template-move="up" aria-label="Move up"><i class="ti ti-arrow-up"></i></button><button type="button" class="btn btn-outline-primary" data-template-move="down" aria-label="Move down"><i class="ti ti-arrow-down"></i></button><button type="button" class="btn btn-outline-primary" data-template-move="right" aria-label="Move right"><i class="ti ti-arrow-right"></i></button></div>
                    <button class="btn btn-outline-secondary" type="button" data-template-no-fill>No Fill</button>
                    <button class="btn btn-outline-secondary" type="button" data-template-reset-block>Reset Selected Object</button>
                    <button class="btn btn-outline-danger" type="button" data-template-remove>Remove Selected Object</button>
                    <select class="form-select skipping-editor-token" data-template-token aria-label="Insert automatic value"><option value="">Insert Automatic Value</option>@foreach($values as $key=>$value)<option value="{{ '{'.$key.'}' }}">{{ ucwords(str_replace('_',' ',$key)) }}</option>@endforeach</select>
                </div>
                <label class="form-label mt-3" for="skippingContent">Content</label><textarea class="form-control skipping-editor-content" id="skippingContent" rows="3" maxlength="4000" data-template-control="text"></textarea><small class="text-secondary">With Bullets enabled, each non-empty line becomes a bullet item.</small>
            </fieldset>
        </div>
    </div>
    <div class="card"><div class="card-header skipping-header"><h3 class="card-title">{{ ucfirst($form) }} Form Preview</h3><span class="small">Sample details</span></div><div class="card-body skipping-editor-preview-wrap"><div class="skipping-editor-frame-wrap"><iframe class="skipping-editor-frame" title="{{ ucfirst($form) }} form template preview" src="{{ route('student-skipping-grade.template.preview',$form) }}" data-template-preview></iframe></div></div></div>
    @php($editorConfig=['template'=>$template,'revision'=>$revision,'defaults'=>$defaults,'definitions'=>$definitions,'values'=>$values,'canEdit'=>$canEdit,'checkboxSources'=>$checkboxSources,'checkboxValues'=>$checkboxValues])
    <script type="application/json" data-template-config>@json($editorConfig)</script>
</div>
@include('student-skipping-grade._workspace-end')
@endsection
@push('styles') @vite(['resources/css/pages/student-skipping-grade.css','resources/css/pages/student-skipping-grade-editor.css']) @endpush
@push('scripts') @vite('resources/js/studentSkippingGradeEditor.js') @endpush
