@php
    $editorLayout = $certificateLayout ?? \App\Support\K3CertificateLayout::defaults($certificateSettings?->typography);
    $editorFonts = \App\Support\K3CertificateTypography::fonts();
@endphp
@if($canManageCertificateTemplate??false)
<form method="post" action="{{ route('reports.k3-certificates.template') }}" class="k3-template-editor" data-k3-template-editor data-k3-layout="{{ json_encode($editorLayout) }}" data-k3-default-layout="{{ json_encode(\App\Support\K3CertificateLayout::defaults()) }}">
    @csrf
    <input type="hidden" name="certificate_show_qr" value="{{ $filters['certificate_show_qr'] ?? '0' }}">
    @foreach(['academic_year_id','campus_id','grade_class','certificate_student_id'] as $key)<input type="hidden" name="{{ $key }}" value="{{ $filters[$key]??'' }}">@endforeach
    <input type="hidden" name="template_data" data-k3-template-data>
    <input type="hidden" name="template_version" value="{{ $certificateTemplateVersion??0 }}">
    <div class="k3-editor-actions">
        <button type="button" class="btn btn-outline-primary" data-k3-edit-toggle aria-pressed="false">Edit Template</button>
        <button type="submit" class="btn btn-primary" data-k3-save-template disabled>Save Template</button>
        <button type="button" class="btn btn-outline-secondary" data-k3-undo disabled>Discard Changes</button>
        <button type="button" class="btn btn-outline-secondary" data-k3-reset>Restore Original Layout</button>
        <span class="k3-editor-status" data-k3-editor-status role="status" aria-live="polite">Saved template</span>
    </div>
    <p class="k3-certificate-note">Save Template applies the layout to K3 certificates in all academic years. Student details, certificate numbers, and Given Date fill automatically. The preprinted frame stays fixed.</p>
    <div class="k3-editor-tools" data-k3-editor-tools hidden>
        <label class="k3-editor-block">Selected Block<select class="form-select" data-k3-select-block>
            @foreach(\App\Support\K3CertificateTypography::fields() as $key=>$definition)<option value="{{ $key }}">{{ $definition['label'] }}</option>@endforeach
            <option value="photo">Photo Box</option>
            <option value="qr">QR Code</option>
        </select></label>
        <label data-k3-text-tool>Font<select class="form-select" data-k3-property="font">
            @foreach($editorFonts as $key=>$font)<option value="{{ $key }}" data-font-family="{{ $font['family'] }}">{{ $font['label'] }}</option>@endforeach
        </select></label>
        <label data-k3-text-tool>Size (pt)<input class="form-control" type="number" min="6" max="60" step="0.5" data-k3-property="size"></label>
        <label data-k3-text-tool>Color<input class="form-control form-control-color" type="color" data-k3-property="color"></label>
        <label data-k3-text-tool>Alignment<select class="form-select" data-k3-property="align"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></label>
        <label>Left (mm)<input class="form-control" type="number" min="0" max="297" step="0.1" data-k3-property="x"></label>
        <label>Top (mm)<input class="form-control" type="number" min="0" max="199.5" step="0.1" data-k3-property="y"></label>
        <label>Width (mm)<input class="form-control" type="number" min="0" max="297" step="0.1" data-k3-property="width"></label>
        <div class="k3-editor-qr-move" data-k3-qr-tool hidden>
            <span>Move QR (1 mm)</span>
            <div class="k3-qr-move-buttons">
                <button class="btn btn-outline-primary" type="button" data-k3-qr-nudge="left" aria-label="Move QR left"><i class="ti ti-arrow-left" aria-hidden="true"></i></button>
                <button class="btn btn-outline-primary" type="button" data-k3-qr-nudge="right" aria-label="Move QR right"><i class="ti ti-arrow-right" aria-hidden="true"></i></button>
                <button class="btn btn-outline-primary" type="button" data-k3-qr-nudge="up" aria-label="Move QR up"><i class="ti ti-arrow-up" aria-hidden="true"></i></button>
                <button class="btn btn-outline-primary" type="button" data-k3-qr-nudge="down" aria-label="Move QR down"><i class="ti ti-arrow-down" aria-hidden="true"></i></button>
            </div>
        </div>
        <label data-k3-photo-tool hidden>Height (mm)<input class="form-control" type="number" min="0" max="84" step="0.1" data-k3-property="height"></label>
        <label class="k3-editor-check" data-k3-text-tool><input type="checkbox" data-k3-property="bold"> Bold</label>
        <label class="k3-editor-check" data-k3-text-tool><input type="checkbox" data-k3-property="bold_first_line"> Bold first line</label>
        <button class="btn btn-outline-primary" type="button" data-k3-edit-text data-k3-text-tool>Edit Text</button>
        <label class="k3-qr-toggle"><input type="checkbox" class="form-check-input" data-k3-qr-toggle @checked($certificateShowQr ?? false)> Show QR on certificate</label>
        <label class="k3-editor-content" data-k3-text-tool>Content<textarea class="form-control" rows="2" maxlength="1000" data-k3-property="text"></textarea></label>
        <label class="k3-editor-token" data-k3-text-tool>Insert Automatic Value<select class="form-select" data-k3-insert-token><option value="">Choose a value</option>
            @foreach(['student_name'=>'Student Name','class'=>'Class','campus'=>'Campus','certificate_number'=>'Certificate Number','given_day'=>'Given Day','given_month'=>'Given Month','given_year'=>'Given Year','given_date'=>'Given Date'] as $token=>$label)<option value="{{ $token }}">{{ $label }}</option>@endforeach
        </select></label>
        <p class="k3-certificate-note k3-editor-instructions">Click a block to select it. Drag to move; drag its corner to resize. Double-click text to edit it directly. Arrow keys move the selected block. Keep automatic values in braces for future students.</p>
    </div>
</form>
@else
<div class="k3-template-editor">
    <label class="k3-qr-toggle"><input type="checkbox" class="form-check-input" data-k3-qr-toggle @checked($certificateShowQr ?? false)> Show QR on certificate</label>
    <p class="k3-certificate-note">The Edit Template permission is required to customize the K3 certificate template.</p>
</div>
@endif
