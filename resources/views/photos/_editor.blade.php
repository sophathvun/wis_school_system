@php
    $editorId = $editorId ?? 'studentPhotoCropModal';
    $allowPhotoSizes = $allowPhotoSizes ?? false;
    $photoWidth = $photoWidth ?? ($allowPhotoSizes ? 354 : 600);
    $photoHeight = $photoHeight ?? ($allowPhotoSizes ? 472 : 800);
    $photoFormat = $allowPhotoSizes ? '3 × 4 cm' : ($photoWidth === $photoHeight ? 'Square photo' : '3:4 portrait');
@endphp
<div class="modal modal-blur fade student-photo-editor" id="{{ $editorId }}" tabindex="-1" aria-hidden="true"
    data-background-url="{{ $backgroundUrl ?? route('student-enrollments.photo.background', [], false) }}"
    data-size-url="{{ $sizeUrl ?? route('photos.size', [], false) }}"
    data-photo-format="{{ $photoFormat }}" style="--photo-editor-ratio: {{ $photoWidth / $photoHeight }}">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <div><h3 class="modal-title"><i class="ti ti-photo-edit me-2"></i>{{ $photoTitle ?? 'Student Photo Studio' }}</h3>
                    <div class="small text-secondary">Crop, improve clarity, and choose a background.</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="photo-editor-layout">
                    <div class="photo-editor-preview">
                        <div class="student-photo-crop-stage" tabindex="0" aria-label="Photo editing viewport"><div class="photo-editor-zoom-surface"><div class="photo-editor-canvas-wrap"><canvas id="studentPhotoCropCanvas" width="{{ $photoWidth }}" height="{{ $photoHeight }}" aria-label="Photo crop preview"></canvas><div class="photo-editor-guide-overlay d-none" id="studentPhotoGuideOverlay" aria-hidden="true"></div></div></div></div>
                        <div class="photo-preview-zoom-toolbar mt-2" role="group" aria-label="Preview zoom">
                            <span class="small text-secondary">Preview zoom</span>
                            <button type="button" class="btn btn-icon btn-outline-secondary btn-sm" id="photoPreviewZoomOut" aria-label="Zoom out preview" title="Zoom out preview"><i class="ti ti-zoom-out" aria-hidden="true"></i></button>
                            <output class="small fw-semibold" id="photoPreviewZoomValue" aria-live="polite">100%</output>
                            <button type="button" class="btn btn-icon btn-outline-secondary btn-sm" id="photoPreviewZoomIn" aria-label="Zoom in preview" title="Zoom in preview"><i class="ti ti-zoom-in" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="photoPreviewFit"><i class="ti ti-arrows-minimize me-1" aria-hidden="true"></i>Fit</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="photoPreviewPan" aria-pressed="false"><i class="ti ti-hand-move me-1" aria-hidden="true"></i>Pan</button>
                        </div>
                        <div class="form-hint mt-1">Zoom in for detail. Turn Pan on to move around; turn it off to edit.</div>
                        <label class="form-check mt-2 mb-0"><input type="checkbox" class="form-check-input" id="studentPhotoGuides"><span class="form-check-label">Show alignment guides</span></label>
                        <div class="small mt-2 fw-semibold" id="photoEditorFileSize" role="status" aria-live="polite">WebP size: —</div>
                        <div class="d-flex justify-content-between align-items-center mt-2 gap-2">
                            <span class="small text-secondary" id="studentPhotoCompareStatus">Edited preview · Drag to reposition</span>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="studentPhotoCompare" aria-pressed="false"><i class="ti ti-columns-2 me-1"></i>View Original</button>
                        </div>
                        <div class="small mt-2 text-secondary" id="studentPhotoEditorStatus" role="status" aria-live="polite">{{ $photoFormat }} · Saved as WebP, up to 40 KB</div>
                        <div class="alert alert-danger d-none mt-2" id="studentPhotoEditorError" role="alert"></div>
                    </div>
                    <div class="photo-editor-tools">
                        <section class="photo-editor-section">
                            <h4><i class="ti ti-crop me-1"></i>Frame</h4>
                            @if ($allowPhotoSizes)
                                <label class="form-label" for="photoEditorSize">Photo Size</label>
                                <select class="form-select mb-2" id="photoEditorSize">
                                    <option value="3x4">3 × 4 cm</option>
                                    <option value="3.5x4.5">3.5 × 4.5 cm</option>
                                    <option value="4x6">4 × 6 cm</option>
                                    <option value="4.5x4.5">4.5 × 4.5 cm</option>
                                    <option value="600x800">600 × 800 px (5.08 × 6.77 cm)</option>
                                    <option value="custom">Customize</option>
                                </select>
                                <div class="row g-2 d-none mb-2" id="photoEditorCustomSize">
                                    <div class="col-6"><label class="form-label" for="photoEditorWidth">Width (cm)</label><input type="number" class="form-control" id="photoEditorWidth" min="1" max="10" step="0.1" value="3" aria-describedby="photoEditorSizeError"></div>
                                    <div class="col-6"><label class="form-label" for="photoEditorHeight">Height (cm)</label><input type="number" class="form-control" id="photoEditorHeight" min="1" max="10" step="0.1" value="4" aria-describedby="photoEditorSizeError"></div>
                                </div>
                                <div class="small text-danger d-none mb-2" id="photoEditorSizeError" role="alert"></div>
                                <div class="form-hint mb-3" id="photoEditorSizeHint">3 × 4 cm · 354 × 472 pixels · JPG at 300 DPI</div>
                            @endif
                            <label class="form-label" for="studentPhotoZoom">Crop zoom</label>
                            <div class="d-flex align-items-center gap-2"><button type="button" class="btn btn-icon btn-outline-secondary btn-sm" id="studentPhotoZoomOut" aria-label="Zoom out"><i class="ti ti-minus"></i></button><input type="range" class="form-range" id="studentPhotoZoom" min="1" max="3" step="0.01" value="1"><button type="button" class="btn btn-icon btn-outline-secondary btn-sm" id="studentPhotoZoomIn" aria-label="Zoom in"><i class="ti ti-plus"></i></button></div>
                            <div class="d-flex gap-2 mt-2"><button type="button" class="btn btn-outline-secondary btn-sm" id="studentPhotoRotateLeft"><i class="ti ti-rotate-2 me-1"></i>Left 90&deg;</button><button type="button" class="btn btn-outline-secondary btn-sm" id="studentPhotoRotateRight"><i class="ti ti-rotate-clockwise-2 me-1"></i>Right 90&deg;</button></div>
                            <label class="form-label mt-3" for="studentPhotoAngle">Straighten (degrees)</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="number" class="form-control" id="studentPhotoAngle" min="-45" max="45" step="0.1" value="0" aria-describedby="studentPhotoAngleHelp">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="studentPhotoAngleReset">Reset angle</button>
                            </div>
                            <input type="range" class="form-range mt-2" id="studentPhotoAngleRange" min="-45" max="45" step="0.1" value="0" aria-label="Straighten angle in degrees" aria-describedby="studentPhotoAngleHelp">
                            <div class="form-hint" id="studentPhotoAngleHelp">Enter an angle, such as 5 or 6. Negative turns left; positive turns right.</div>
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
                            <h4><i class="ti ti-bandage me-1"></i>Healing, Patch &amp; Blur</h4>
                            <div class="form-label" id="photoRetouchToolLabel">Retouch tool</div>
                            <div class="photo-retouch-tool-buttons mb-3" role="group" aria-labelledby="photoRetouchToolLabel">
                                <button type="button" class="btn btn-outline-primary active" data-photo-retouch-tool="move" aria-pressed="true"><i class="ti ti-hand-move" aria-hidden="true"></i><span>Off / Move</span></button>
                                <button type="button" class="btn btn-outline-primary" data-photo-retouch-tool="spot" aria-pressed="false"><i class="ti ti-wand" aria-hidden="true"></i><span>Spot Healing Brush</span></button>
                                <button type="button" class="btn btn-outline-primary" data-photo-retouch-tool="heal" aria-pressed="false"><i class="ti ti-brush" aria-hidden="true"></i><span>Healing Brush</span></button>
                                <button type="button" class="btn btn-outline-primary" data-photo-retouch-tool="patch" aria-pressed="false"><i class="ti ti-lasso" aria-hidden="true"></i><span>Patch Tool</span></button>
                                <button type="button" class="btn btn-outline-primary" data-photo-retouch-tool="blur" aria-pressed="false"><i class="ti ti-blur" aria-hidden="true"></i><span>Blur Tool</span></button>
                            </div>
                            <select class="d-none" id="photoRetouchTool" aria-hidden="true" tabindex="-1">
                                <option value="move">Off — use frame / selection tools</option>
                                <option value="spot">Spot Healing Brush</option>
                                <option value="heal">Healing Brush</option>
                                <option value="patch">Patch Tool</option>
                                <option value="blur">Blur Tool</option>
                            </select>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="photoRetouchSource" aria-pressed="false" disabled><i class="ti ti-focus-2 me-1"></i>Set Source</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="photoRetouchClearPatch" disabled><i class="ti ti-x me-1" aria-hidden="true"></i>Clear Patch</button>
                            </div>
                            <label class="form-label d-flex justify-content-between" for="photoRetouchSize">Brush size<output id="photoRetouchSizeValue" for="photoRetouchSize">24 px</output></label>
                            <input type="range" class="form-range mb-2" id="photoRetouchSize" min="6" max="100" value="24">
                            <label class="form-label d-flex justify-content-between" for="photoRetouchSoftness">Softness<output id="photoRetouchSoftnessValue" for="photoRetouchSoftness">70%</output></label>
                            <input type="range" class="form-range mb-2" id="photoRetouchSoftness" min="10" max="100" value="70">
                            <label class="form-label d-flex justify-content-between" for="photoRetouchStrength">Strength<output id="photoRetouchStrengthValue" for="photoRetouchStrength">80%</output></label>
                            <input type="range" class="form-range mb-2" id="photoRetouchStrength" min="10" max="100" value="80">
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="photoRetouchUndo" disabled><i class="ti ti-arrow-back-up me-1"></i>Undo Retouch</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="photoRetouchRedo" disabled><i class="ti ti-arrow-forward-up me-1"></i>Redo</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="photoRetouchReset" disabled><i class="ti ti-refresh me-1" aria-hidden="true"></i>Reset Retouching</button>
                            </div>
                            <p class="small text-secondary mt-2 mb-0" id="photoRetouchStatus" role="status" aria-live="polite">Choose a retouch tool to begin.</p>
                            <p class="form-hint mt-2 mb-0">For small blemishes, dust or shirt marks. Use short strokes and a clean source on similar texture. Turn retouching Off to move the photo or paint adjustment selections.</p>
                        </section>
                        <section class="photo-editor-section">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><h4 class="mb-0"><i class="ti ti-adjustments me-1"></i>Clarity</h4><button type="button" class="btn btn-sm btn-outline-primary" id="studentPhotoEnhance">Auto Enhance</button></div>
                            <label class="form-label" for="studentPhotoAdjustmentScope">Apply adjustments to</label>
                            <select class="form-select mb-3" id="studentPhotoAdjustmentScope"><option value="whole">Whole Photo</option><option value="selected">Selected Area</option></select>
                            <div class="photo-selection-tools d-none mb-3" id="studentPhotoSelectionTools">
                                <label class="form-label" for="studentPhotoSelectionBrush">Selection tool</label>
                                <select class="form-select form-select-sm" id="studentPhotoSelectionBrush"><option value="paint">Paint selection</option><option value="erase">Erase selection</option><option value="move">Move photo</option></select>
                                <label class="form-label mt-2" for="studentPhotoSelectionSize">Selection brush size</label>
                                <input type="range" class="form-range" id="studentPhotoSelectionSize" min="5" max="150" value="45">
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="studentPhotoSelectionShow" aria-pressed="true">Hide Selection</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="studentPhotoSelectionClear">Clear Selection</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="studentPhotoSelectionReset">Reset Area Adjustments</button>
                                </div>
                                <p class="small text-secondary mt-2 mb-0">Paint over the shirt or another area, then adjust the sliders below. Purple marks the selection; soft edges blend the edit. The selection color is never saved.</p>
                            </div>
                            <label class="form-label">Portrait presets</label>
                            <div class="photo-portrait-presets" role="group" aria-label="Portrait presets">
                                @foreach (['original' => 'Neutral', 'natural' => 'Natural', 'studio' => 'Studio Light', 'warm' => 'Warm Portrait'] as $preset => $presetLabel)
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-photo-preset="{{ $preset }}" aria-pressed="{{ $preset === 'original' ? 'true' : 'false' }}">{{ $presetLabel }}</button>
                                @endforeach
                            </div>
                            <p class="form-hint mt-2 mb-3">Presets replace the adjustments for the chosen scope. You can fine-tune every slider.</p>
                            @php
                                $photoAdjustmentGroups = [
                                    'Light' => [['exposure', 'Exposure', -2, 2, 0, 0.01], ['brightness', 'Brightness', -35, 35, 0, 1], ['contrast', 'Contrast', -30, 40, 0, 1], ['shadows', 'Shadows', -100, 100, 0, 1], ['highlights', 'Highlights', -100, 100, 0, 1]],
                                    'Color & White Balance' => [['warmth', 'Warmth', -100, 100, 0, 1], ['tint', 'Tint', -100, 100, 0, 1], ['vibrance', 'Vibrance', -100, 100, 0, 1], ['saturation', 'Saturation', -100, 100, 0, 1]],
                                    'Texture & Detail' => [['portraitSmooth', 'Portrait smoothing', 0, 60, 0, 1], ['denoise', 'Smooth noise', 0, 60, 0, 1], ['sharpen', 'Sharpen', 0, 100, 0, 1]],
                                ];
                            @endphp
                            @foreach ($photoAdjustmentGroups as $groupLabel => $adjustments)
                                <details class="photo-adjustment-group" open>
                                    <summary>{{ $groupLabel }}</summary>
                                    @if ($groupLabel === 'Color & White Balance')
                                        <p class="form-hint">Warmth: cool to warm. Tint: green to magenta. Vibrance gently boosts muted colors.</p>
                                    @elseif ($groupLabel === 'Texture & Detail')
                                        <p class="form-hint">For a gentle skin touch-up, choose Selected Area, paint over the skin, and use a little Portrait smoothing.</p>
                                    @endif
                                    @foreach ($adjustments as [$key, $label, $min, $max, $value, $step])
                                        <label class="form-label d-flex justify-content-between" for="studentPhoto-{{ $key }}">{{ $label }}<output for="studentPhoto-{{ $key }}" data-photo-value="{{ $key }}">{{ $key === 'exposure' ? '0.00 EV' : $value }}</output></label>
                                        <input type="range" class="form-range mb-2" id="studentPhoto-{{ $key }}" data-photo-adjustment="{{ $key }}" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ $value }}">
                                    @endforeach
                                </details>
                            @endforeach
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="studentPhotoAdjustmentReset"><i class="ti ti-refresh me-1"></i>Reset Adjustments</button>
                        </section>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between"><button type="button" class="btn btn-outline-secondary" id="studentPhotoReset"><i class="ti ti-refresh me-1"></i>Reset All</button><div class="d-flex gap-2"><button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="studentPhotoCropUpload"><i class="ti ti-check me-1"></i>Use Photo</button></div></div>
        </div>
    </div>
</div>
