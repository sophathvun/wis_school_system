<div class="modal modal-blur fade student-photo-editor" id="studentPhotoCropModal" tabindex="-1" aria-hidden="true"
    data-background-url="{{ route('student-enrollments.photo.background', [], false) }}">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <div><h3 class="modal-title"><i class="ti ti-photo-edit me-2"></i>Student Photo Studio</h3>
                    <div class="small text-secondary">Crop, improve clarity, and choose a background.</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="photo-editor-layout">
                    <div class="photo-editor-preview">
                        <div class="student-photo-crop-stage"><canvas id="studentPhotoCropCanvas" width="600" height="800" aria-label="Photo crop preview"></canvas></div>
                        <div class="d-flex justify-content-between align-items-center mt-2 gap-2">
                            <span class="small text-secondary" id="studentPhotoCompareStatus">Edited preview · Drag to reposition</span>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="studentPhotoCompare" aria-pressed="false"><i class="ti ti-columns-2 me-1"></i>View Original</button>
                        </div>
                        <div class="small mt-2 text-secondary" id="studentPhotoEditorStatus" role="status" aria-live="polite">3:4 portrait · Saved as WebP, up to 40 KB</div>
                        <div class="alert alert-danger d-none mt-2" id="studentPhotoEditorError" role="alert"></div>
                    </div>
                    <div class="photo-editor-tools">
                        <section class="photo-editor-section">
                            <h4><i class="ti ti-crop me-1"></i>Frame</h4>
                            <label class="form-label" for="studentPhotoZoom">Zoom</label>
                            <div class="d-flex align-items-center gap-2"><button type="button" class="btn btn-icon btn-outline-secondary btn-sm" id="studentPhotoZoomOut" aria-label="Zoom out"><i class="ti ti-minus"></i></button><input type="range" class="form-range" id="studentPhotoZoom" min="1" max="3" step="0.01" value="1"><button type="button" class="btn btn-icon btn-outline-secondary btn-sm" id="studentPhotoZoomIn" aria-label="Zoom in"><i class="ti ti-plus"></i></button></div>
                            <div class="d-flex gap-2 mt-2"><button type="button" class="btn btn-outline-secondary btn-sm" id="studentPhotoRotateLeft"><i class="ti ti-rotate-2 me-1"></i>Left</button><button type="button" class="btn btn-outline-secondary btn-sm" id="studentPhotoRotateRight"><i class="ti ti-rotate-clockwise-2 me-1"></i>Right</button></div>
                        </section>
                        <section class="photo-editor-section">
                            <h4><i class="ti ti-wand me-1"></i>Background</h4>
                            <button type="button" class="btn btn-outline-primary w-100 mb-3" id="studentPhotoRemoveBackground"><i class="ti ti-sparkles me-1"></i>Remove Background</button>
                            <div class="photo-background-options" role="group" aria-label="Background color">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-photo-background="original" aria-pressed="true">Original</button>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-photo-background="#1976d2" aria-pressed="false">Blue</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-photo-background="#ffffff" aria-pressed="false">White</button>
                                <label class="photo-color-label">Custom <input type="color" id="studentPhotoBackgroundColor" value="#1976d2" aria-label="Custom background color"></label>
                            </div>
                            <p class="small text-secondary mt-2 mb-0">Remove the background first, then select a color.</p>
                            <label class="form-label mt-3" for="studentPhotoBrush">Refine edges</label>
                            <select class="form-select form-select-sm" id="studentPhotoBrush"><option value="move">Move photo</option><option value="smooth">Smooth edge</option><option value="erase">Erase background</option><option value="restore">Restore photo</option></select>
                            <p class="small text-secondary mt-2 mb-0">Choose Smooth edge and brush over a rough outline for a gentle touch-up.</p>
                            <label class="form-label mt-2" for="studentPhotoBrushSize">Brush size</label><input type="range" class="form-range" id="studentPhotoBrushSize" min="5" max="100" value="25">
                        </section>
                        <section class="photo-editor-section">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><h4 class="mb-0"><i class="ti ti-adjustments me-1"></i>Clarity</h4><button type="button" class="btn btn-sm btn-outline-primary" id="studentPhotoEnhance">Auto Enhance</button></div>
                            @foreach ([['brightness', 'Brightness', -35, 35, 0], ['contrast', 'Contrast', -30, 40, 0], ['saturation', 'Color', -40, 40, 0], ['denoise', 'Smooth noise', 0, 60, 0], ['sharpen', 'Sharpen', 0, 100, 0]] as [$key, $label, $min, $max, $value])
                                <label class="form-label d-flex justify-content-between" for="studentPhoto-{{ $key }}">{{ $label }}<output data-photo-value="{{ $key }}">{{ $value }}</output></label>
                                <input type="range" class="form-range mb-2" id="studentPhoto-{{ $key }}" data-photo-adjustment="{{ $key }}" min="{{ $min }}" max="{{ $max }}" value="{{ $value }}">
                            @endforeach
                        </section>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between"><button type="button" class="btn btn-outline-secondary" id="studentPhotoReset"><i class="ti ti-refresh me-1"></i>Reset All</button><div class="d-flex gap-2"><button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="studentPhotoCropUpload"><i class="ti ti-check me-1"></i>Use Photo</button></div></div>
        </div>
    </div>
</div>
