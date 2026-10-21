<section class="card photo-editor-workspace" id="photoEditorWorkspace" data-download-url="{{ route('photo-editor.download', [], false) }}">
    <div class="card-header"><h2 class="card-title"><i class="ti ti-photo-edit me-2"></i>Photo Editor</h2></div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6">
                <h3 class="h4">Choose a Photo</h3>
                <div class="photo-editor-upload" id="photoEditorDropzone" tabindex="0" role="button" aria-label="Choose a photo to edit" aria-describedby="photoEditorUploadHint">
                    <i class="ti ti-cloud-upload photo-editor-upload-icon" aria-hidden="true"></i>
                    <span class="fw-semibold">Click to upload or drag a photo here</span>
                    <span class="text-secondary small" id="photoEditorUploadHint">JPG, PNG or WebP · Up to 10 MB</span>
                    <span class="small text-secondary">You can also paste a copied photo.</span>
                    <input type="file" class="d-none" id="photoEditorInput" accept="image/jpeg,image/png,image/webp" aria-label="Photo file">
                </div>
                <p class="text-secondary mt-3 mb-0">Create a natural portrait with presets, white balance, light and color controls, and gentle texture smoothing. Adjust the whole photo or paint a selected area.</p>
            </div>
            <div class="col-md-6">
                <h3 class="h4">Edited Photo</h3>
                <div class="photo-editor-output">
                    <p class="text-secondary mb-0" id="photoEditorEmpty">Your edited photo will appear here.</p>
                    <div class="d-none text-center" id="photoEditorPreviewContainer">
                        <img class="photo-editor-result d-none" id="photoEditorPreview" alt="Edited photo preview">
                        <button type="button" class="btn btn-outline-primary btn-sm d-none mt-2" id="photoEditorEdit"><i class="ti ti-photo-edit me-1"></i>Edit Photo</button>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="button" class="btn btn-primary" data-photo-download="jpg" disabled><i class="ti ti-download me-1"></i>Download JPG</button>
                    <button type="button" class="btn btn-outline-primary" data-photo-download="webp" disabled><i class="ti ti-download me-1"></i>Download WebP</button>
                    <button type="button" class="btn btn-outline-secondary" id="photoEditorClear" disabled><i class="ti ti-trash me-1"></i>Clear</button>
                </div>
                <div class="small text-secondary mt-2">Choose Photo Size in the editor. JPG downloads use 300 DPI for printing; WebP keeps the selected pixel dimensions, up to 40 KB.</div>
                <div class="small mt-2" id="photoEditorDownloadStatus" role="status" aria-live="polite"></div>
            </div>
        </div>
    </div>
</section>
