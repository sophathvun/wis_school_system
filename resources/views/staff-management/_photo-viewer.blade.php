<div class="modal modal-blur fade staff-directory-photo-modal" id="staffDirectoryPhotoModal" tabindex="-1" aria-hidden="true" aria-labelledby="staffDirectoryPhotoTitle">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="staffDirectoryPhotoTitle">Staff Photo</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close photo"></button>
            </div>
            <div class="modal-body p-0">
                <div class="staff-directory-photo-stage" data-photo-stage>
                    <div class="staff-directory-photo-frame"><img data-photo-image alt="Staff photo" hidden></div>
                </div>
                <div class="small text-secondary px-3 py-2" data-photo-status role="status" aria-live="polite"></div>
                <div class="alert alert-danger mx-3 d-none" data-photo-error role="alert"></div>
            </div>
            <div class="modal-footer gap-2">
                <div class="d-flex align-items-center gap-2 flex-grow-1 staff-directory-photo-controls">
                    <button type="button" class="btn btn-icon btn-outline-secondary" data-photo-out aria-label="Zoom out"><i class="ti ti-minus" aria-hidden="true"></i></button>
                    <input type="range" class="form-range" min="1" max="3" step="0.1" value="1" data-photo-zoom aria-label="Photo zoom">
                    <button type="button" class="btn btn-icon btn-outline-secondary" data-photo-in aria-label="Zoom in"><i class="ti ti-plus" aria-hidden="true"></i></button>
                    <button type="button" class="btn btn-outline-secondary" data-photo-reset>Reset</button>
                    <output data-photo-percent>100%</output>
                </div>
                <a class="btn btn-primary disabled" data-photo-download aria-disabled="true" tabindex="-1"><i class="ti ti-download me-1" aria-hidden="true"></i>Download JPG</a>
            </div>
        </div>
    </div>
</div>
