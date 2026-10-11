@if($canEditCardTemplate && $cardTemplateReady)
<form method="post" action="{{ route('student-id-card-qr.template') }}" class="student-id-template-editor d-print-none" data-id-template-editor data-id-layout="{{ json_encode($cardLayout) }}" data-id-default-layout="{{ json_encode(\App\Support\StudentIdCardLayout::defaults()) }}">
    @csrf
    @foreach(['academic_year_id', 'campus_id', 'grade_id', 'class_id', 'student_id', 'print_scope'] as $key)
        <input type="hidden" name="{{ $key }}" value="{{ $filters[$key] ?? '' }}">
    @endforeach
    <input type="hidden" name="template_version" value="{{ $cardTemplateVersion }}">
    <input type="hidden" name="template_data" data-id-template-data>
    <div class="student-id-editor-actions">
        <button type="button" class="btn btn-outline-primary" data-id-edit-toggle aria-pressed="false">Edit Template</button>
        <button type="submit" class="btn btn-primary" data-id-save disabled>Save Template</button>
        <button type="button" class="btn btn-outline-secondary" data-id-discard disabled>Discard Changes</button>
        <button type="button" class="btn btn-outline-secondary" data-id-reset>Restore Original Layout</button>
        <span class="student-id-editor-status" data-id-status role="status" aria-live="polite">Saved template</span>
    </div>
    <p class="student-id-editor-note">Save Template applies this layout to ID cards in all academic years. Student details fill automatically.</p>
    <div class="student-id-editor-tools" data-id-tools hidden>
        <label class="student-id-editor-wide">Selected Block<select class="form-select" data-id-select-block></select></label>
        <label data-id-text-tool>Font<select class="form-select" data-id-property="font">
            @foreach(\App\Support\StudentIdCardLayout::fonts() as $key => $font)
                <option value="{{ $key }}" data-font-family="{{ $font['family'] }}">{{ $font['label'] }}</option>
            @endforeach
        </select></label>
        <label data-id-text-tool>Size (pt)<input type="number" class="form-control" min="4" max="40" step="0.5" data-id-property="size"></label>
        <label data-id-text-tool>Color<input type="color" class="form-control form-control-color" data-id-property="color"></label>
        <label data-id-text-tool>Alignment<select class="form-select" data-id-property="align"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></label>
        <label>Left (mm)<input type="number" class="form-control" min="0" max="53" step="0.1" data-id-property="x"></label>
        <label>Top (mm)<input type="number" class="form-control" min="0" max="89" step="0.1" data-id-property="y"></label>
        <label>Width (mm)<input type="number" class="form-control" min="1" max="54" step="0.1" data-id-property="width"></label>
        <label>Height (mm)<input type="number" class="form-control" min="1" max="90" step="0.1" data-id-property="height"></label>
        <label class="student-id-editor-check" data-id-text-tool><input type="checkbox" data-id-property="bold"> Bold</label>
        <label class="student-id-editor-check"><input type="checkbox" data-id-property="visible"> Show selected block</label>
        <label class="student-id-editor-check"><input type="checkbox" data-id-show-qr> Show QR code</label>
        <div class="student-id-editor-wide student-id-editor-nudge">
            <span>Move selected block (1 mm)</span>
            <div>
                @foreach(['left', 'right', 'up', 'down'] as $direction)
                    <button type="button" class="btn btn-outline-primary" data-id-nudge="{{ $direction }}" aria-label="Move block {{ $direction }}"><i class="ti ti-arrow-{{ $direction }}" aria-hidden="true"></i></button>
                @endforeach
            </div>
        </div>
        <label class="student-id-editor-content" data-id-text-tool>Content<textarea rows="2" maxlength="1000" class="form-control" data-id-property="text"></textarea></label>
        <label class="student-id-editor-wide" data-id-text-tool>Insert Automatic Value<select class="form-select" data-id-insert-token><option value="">Choose a value</option>
            @foreach(\App\Support\StudentIdCardLayout::tokens() as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
        </select></label>
        <button type="button" class="btn btn-outline-primary" data-id-edit-text data-id-text-tool>Edit Text</button>
        <button type="button" class="btn btn-outline-danger" data-id-remove>Remove Block</button>
        <label class="student-id-editor-wide">Add Field<select class="form-select" data-id-add-field><option value="">Choose a field</option>
            <option value="text">Custom Text</option>
            @foreach(\App\Support\StudentIdCardLayout::tokens() as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            <option value="logo">School Logo</option><option value="photo">Student Photo</option><option value="qr">QR Code</option>
        </select></label>
        <p class="student-id-editor-note student-id-editor-content">Click a block to select it. Drag to move; drag its corner to resize. Double-click text to edit. Arrow keys move 0.1 mm; Shift + arrow moves 1 mm. Keep automatic values in braces for future students.</p>
    </div>
</form>
@elseif($canEditCardTemplate)
    <p class="student-id-editor-note d-print-none">Run the latest database migrations to enable the ID Card template editor.</p>
@endif
