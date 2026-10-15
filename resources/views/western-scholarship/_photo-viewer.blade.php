<div class="modal modal-blur fade" id="scholarshipPhotoModal" tabindex="-1" aria-labelledby="scholarshipPhotoTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="scholarshipPhotoTitle">Student Photo</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close photo"></button>
            </div>
            <div class="modal-body p-0">
                <p class="text-center text-secondary p-3 mb-0" data-photo-status role="status">Loading photo…</p>
                <div class="scholarship-photo-viewport" tabindex="0" aria-label="Student photo. Scroll to view the enlarged image.">
                    <div class="scholarship-photo-stage"><img data-photo-image alt="Student photo" hidden></div>
                </div>
            </div>
            <div class="modal-footer scholarship-photo-toolbar">
                <div class="d-flex align-items-center gap-2" role="group" aria-label="Photo zoom controls">
                    <button type="button" class="btn btn-outline-secondary btn-icon" data-photo-zoom-out aria-label="Zoom out" title="Zoom out"><i class="ti ti-zoom-out" aria-hidden="true"></i></button>
                    <output class="scholarship-photo-zoom" data-photo-zoom aria-live="polite">100%</output>
                    <button type="button" class="btn btn-outline-secondary btn-icon" data-photo-zoom-in aria-label="Zoom in" title="Zoom in"><i class="ti ti-zoom-in" aria-hidden="true"></i></button>
                    <button type="button" class="btn btn-outline-secondary" data-photo-reset>Reset</button>
                </div>
                <a class="btn btn-primary" data-photo-download download><i class="ti ti-download me-1" aria-hidden="true"></i>Download</a>
            </div>
        </div>
    </div>
</div>
