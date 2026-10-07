@php
    $editorLayout = $certificateLayout ?? \App\Support\G12CertificateLayout::defaults($certificateSettings?->typography);
    $editorFonts = \App\Support\G12CertificateTypography::fonts();
@endphp
@if($canManageCertificateTemplate??false)
<form method="post" action="{{ route('reports.g12-certificates.template') }}" class="g12-template-editor" data-g12-template-editor data-g12-layout="{{ json_encode($editorLayout) }}" data-g12-default-layout="{{ json_encode(\App\Support\G12CertificateLayout::defaults()) }}">
    @csrf
    <input type="hidden" name="certificate_show_qr" value="{{ $filters['certificate_show_qr'] ?? '0' }}">
    @foreach(['academic_year_id','campus_id','grade_class','certificate_student_id'] as $key)<input type="hidden" name="{{ $key }}" value="{{ $filters[$key]??'' }}">@endforeach
    <input type="hidden" name="template_data" data-g12-template-data>
    <input type="hidden" name="template_version" value="{{ $certificateTemplateVersion??0 }}">
    <div class="g12-editor-actions">
        <button type="button" class="btn btn-outline-primary" data-g12-edit-toggle aria-pressed="false">Edit Template</button>
        <button type="submit" class="btn btn-primary" data-g12-save-template disabled>Save Template</button>
        <button type="button" class="btn btn-outline-secondary" data-g12-undo disabled>Discard Changes</button>
        <button type="button" class="btn btn-outline-secondary" data-g12-reset>Restore Original Layout</button>
        <span class="g12-editor-status" data-g12-editor-status role="status" aria-live="polite">Saved template</span>
    </div>
    <p class="g12-certificate-note">Save Template applies the layout to G12 certificates in all academic years. Student details, certificate numbers, and Given Date fill automatically. The preprinted frame stays fixed.</p>
    <div class="g12-editor-tools" data-g12-editor-tools hidden>
        <label class="g12-editor-block">Selected Block<select class="form-select" data-g12-select-block>
            @foreach(\App\Support\G12CertificateTypography::fields() as $key=>$definition)<option value="{{ $key }}">{{ $definition['label'] }}</option>@endforeach
            <option value="photo">Photo Box</option>
            <option value="qr">QR Code</option>
        </select></label>
        <label data-g12-text-tool>Font<select class="form-select" data-g12-property="font">
            @foreach($editorFonts as $key=>$font)<option value="{{ $key }}" data-font-family="{{ $font['family'] }}">{{ $font['label'] }}</option>@endforeach
        </select></label>
        <label data-g12-text-tool>Size (pt)<input class="form-control" type="number" min="6" max="60" step="0.5" data-g12-property="size"></label>
        <label data-g12-text-tool>Color<input class="form-control form-control-color" type="color" data-g12-property="color"></label>
        <label class="g12-editor-curve" data-g12-title-tool hidden>Curve (°)
            <span class="g12-curve-inputs">
                <input class="form-range" type="range" min="0" max="90" step="1" data-g12-property="curve" aria-label="Title curve">
                <input class="form-control" type="number" min="0" max="90" step="1" data-g12-property="curve" aria-label="Title curve in degrees">
            </span>
            <span class="g12-curve-help">0 = straight; increase for a deeper arch.</span>
        </label>
        <label data-g12-text-tool>Alignment<select class="form-select" data-g12-property="align"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></label>
        <label>Left (mm)<input class="form-control" type="number" min="0" max="297" step="0.1" data-g12-property="x"></label>
        <label>Top (mm)<input class="form-control" type="number" min="0" max="199.5" step="0.1" data-g12-property="y"></label>
        <label>Width (mm)<input class="form-control" type="number" min="0" max="297" step="0.1" data-g12-property="width"></label>
        <div class="g12-editor-qr-move" data-g12-qr-tool hidden>
            <span>Move QR (1 mm)</span>
            <div class="g12-qr-move-buttons">
                <button class="btn btn-outline-primary" type="button" data-g12-qr-nudge="left" aria-label="Move QR left"><i class="ti ti-arrow-left" aria-hidden="true"></i></button>
                <button class="btn btn-outline-primary" type="button" data-g12-qr-nudge="right" aria-label="Move QR right"><i class="ti ti-arrow-right" aria-hidden="true"></i></button>
                <button class="btn btn-outline-primary" type="button" data-g12-qr-nudge="up" aria-label="Move QR up"><i class="ti ti-arrow-up" aria-hidden="true"></i></button>
                <button class="btn btn-outline-primary" type="button" data-g12-qr-nudge="down" aria-label="Move QR down"><i class="ti ti-arrow-down" aria-hidden="true"></i></button>
            </div>
        </div>
        <label data-g12-photo-tool hidden>Height (mm)<input class="form-control" type="number" min="0" max="84" step="0.1" data-g12-property="height"></label>
        <label class="g12-editor-check" data-g12-text-tool><input type="checkbox" data-g12-property="bold"> Bold</label>
        <label class="g12-editor-check" data-g12-text-tool><input type="checkbox" data-g12-property="bold_first_line"> Bold first line</label>
        <button class="btn btn-outline-primary" type="button" data-g12-edit-text data-g12-text-tool>Edit Text</button>
        <label class="g12-qr-toggle"><input type="checkbox" class="form-check-input" data-g12-qr-toggle @checked($certificateShowQr ?? false)> Show QR on certificate</label>
        <label class="g12-editor-content" data-g12-text-tool>Content<textarea class="form-control" rows="2" maxlength="1000" data-g12-property="text"></textarea></label>
        <label class="g12-editor-token" data-g12-text-tool>Insert Automatic Value<select class="form-select" data-g12-insert-token><option value="">Choose a value</option>
            @foreach(['student_name'=>'Student Name','class'=>'Class','campus'=>'Campus','certificate_number'=>'Certificate Number','given_day'=>'Given Day','given_month'=>'Given Month','given_year'=>'Given Year','given_date'=>'Given Date'] as $token=>$label)<option value="{{ $token }}">{{ $label }}</option>@endforeach
        </select></label>
        <p class="g12-certificate-note g12-editor-instructions">Click a block to select it. Drag to move; drag its corner to resize. Double-click text to edit it directly. Arrow keys move the selected block. Keep automatic values in braces for future students.</p>
    </div>
</form>
@else
<div class="g12-template-editor">
    <label class="g12-qr-toggle"><input type="checkbox" class="form-check-input" data-g12-qr-toggle @checked($certificateShowQr ?? false)> Show QR on certificate</label>
    <p class="g12-certificate-note">The Edit Template permission is required to customize the G12 certificate template.</p>
</div>
@endif
