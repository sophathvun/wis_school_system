@php
    $editorLayout = $certificateLayout ?? \App\Support\G9CertificateLayout::defaults($certificateSettings?->typography);
    $editorFonts = \App\Support\G9CertificateTypography::fonts();
@endphp
@if($canManageCertificateTemplate??false)
<form method="post" action="{{ route('reports.g9-certificates.template') }}" class="g9-template-editor" data-g9-template-editor data-g9-layout="{{ json_encode($editorLayout) }}" data-g9-default-layout="{{ json_encode(\App\Support\G9CertificateLayout::defaults()) }}">
    @csrf
    <input type="hidden" name="certificate_show_qr" value="{{ $filters['certificate_show_qr'] ?? '0' }}">
    @foreach(['academic_year_id','campus_id','grade_class','certificate_student_id'] as $key)<input type="hidden" name="{{ $key }}" value="{{ $filters[$key]??'' }}">@endforeach
    <input type="hidden" name="template_data" data-g9-template-data>
    <input type="hidden" name="template_version" value="{{ $certificateTemplateVersion??0 }}">
    <div class="g9-editor-actions">
        <button type="button" class="btn btn-outline-primary" data-g9-edit-toggle aria-pressed="false">Edit Template</button>
        <button type="submit" class="btn btn-primary" data-g9-save-template disabled>Save Template</button>
        <button type="button" class="btn btn-outline-secondary" data-g9-undo disabled>Discard Changes</button>
        <button type="button" class="btn btn-outline-secondary" data-g9-reset>Restore Original Layout</button>
        <span class="g9-editor-status" data-g9-editor-status role="status" aria-live="polite">Saved template</span>
    </div>
    <p class="g9-certificate-note">Save Template applies the layout to G9 certificates in all academic years. Student details, certificate numbers, and Given Date fill automatically. The preprinted frame stays fixed.</p>
    <div class="g9-editor-tools" data-g9-editor-tools hidden>
        <label class="g9-editor-block">Selected Block<select class="form-select" data-g9-select-block>
            @foreach(\App\Support\G9CertificateTypography::fields() as $key=>$definition)<option value="{{ $key }}">{{ $definition['label'] }}</option>@endforeach
            <option value="photo">Photo Box</option>
            <option value="qr">QR Code</option>
        </select></label>
        <label data-g9-text-tool>Font<select class="form-select" data-g9-property="font">
            @foreach($editorFonts as $key=>$font)<option value="{{ $key }}" data-font-family="{{ $font['family'] }}">{{ $font['label'] }}</option>@endforeach
        </select></label>
        <label data-g9-text-tool>Size (pt)<input class="form-control" type="number" min="6" max="60" step="0.5" data-g9-property="size"></label>
        <label data-g9-text-tool>Color<input class="form-control form-control-color" type="color" data-g9-property="color"></label>
        <label class="g9-editor-curve" data-g9-title-tool hidden>Curve (°)
            <span class="g9-curve-inputs">
                <input class="form-range" type="range" min="0" max="90" step="1" data-g9-property="curve" aria-label="Title curve">
                <input class="form-control" type="number" min="0" max="90" step="1" data-g9-property="curve" aria-label="Title curve in degrees">
            </span>
            <span class="g9-curve-help">0 = straight; increase for a deeper arch.</span>
        </label>
        <label data-g9-text-tool>Alignment<select class="form-select" data-g9-property="align"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></label>
        <label>Left (mm)<input class="form-control" type="number" min="0" max="297" step="0.1" data-g9-property="x"></label>
        <label>Top (mm)<input class="form-control" type="number" min="0" max="199.5" step="0.1" data-g9-property="y"></label>
        <label>Width (mm)<input class="form-control" type="number" min="0" max="297" step="0.1" data-g9-property="width"></label>
        <div class="g9-editor-qr-move" data-g9-qr-tool hidden>
            <span>Move QR (1 mm)</span>
            <div class="g9-qr-move-buttons">
                <button class="btn btn-outline-primary" type="button" data-g9-qr-nudge="left" aria-label="Move QR left"><i class="ti ti-arrow-left" aria-hidden="true"></i></button>
                <button class="btn btn-outline-primary" type="button" data-g9-qr-nudge="right" aria-label="Move QR right"><i class="ti ti-arrow-right" aria-hidden="true"></i></button>
                <button class="btn btn-outline-primary" type="button" data-g9-qr-nudge="up" aria-label="Move QR up"><i class="ti ti-arrow-up" aria-hidden="true"></i></button>
                <button class="btn btn-outline-primary" type="button" data-g9-qr-nudge="down" aria-label="Move QR down"><i class="ti ti-arrow-down" aria-hidden="true"></i></button>
            </div>
        </div>
        <label data-g9-photo-tool hidden>Height (mm)<input class="form-control" type="number" min="0" max="84" step="0.1" data-g9-property="height"></label>
        <label class="g9-editor-check" data-g9-text-tool><input type="checkbox" data-g9-property="bold"> Bold</label>
        <label class="g9-editor-check" data-g9-text-tool><input type="checkbox" data-g9-property="bold_first_line"> Bold first line</label>
        <button class="btn btn-outline-primary" type="button" data-g9-edit-text data-g9-text-tool>Edit Text</button>
        <label class="g9-qr-toggle"><input type="checkbox" class="form-check-input" data-g9-qr-toggle @checked($certificateShowQr ?? false)> Show QR on certificate</label>
        <label class="g9-editor-content" data-g9-text-tool>Content<textarea class="form-control" rows="2" maxlength="1000" data-g9-property="text"></textarea></label>
        <label class="g9-editor-token" data-g9-text-tool>Insert Automatic Value<select class="form-select" data-g9-insert-token><option value="">Choose a value</option>
            @foreach(['student_name'=>'Student Name','class'=>'Class','campus'=>'Campus','certificate_number'=>'Certificate Number','given_day'=>'Given Day','given_month'=>'Given Month','given_year'=>'Given Year','given_date'=>'Given Date'] as $token=>$label)<option value="{{ $token }}">{{ $label }}</option>@endforeach
        </select></label>
        <p class="g9-certificate-note g9-editor-instructions">Click a block to select it. Drag to move; drag its corner to resize. Double-click text to edit it directly. Arrow keys move the selected block. Keep automatic values in braces for future students.</p>
    </div>
</form>
@else
<div class="g9-template-editor">
    <label class="g9-qr-toggle"><input type="checkbox" class="form-check-input" data-g9-qr-toggle @checked($certificateShowQr ?? false)> Show QR on certificate</label>
    <p class="g9-certificate-note">The Edit Template permission is required to customize the G9 certificate template.</p>
</div>
@endif
