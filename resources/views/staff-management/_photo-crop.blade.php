<div class="modal modal-blur fade" id="hrmStaffPhotoCropModal" tabindex="-1" aria-labelledby="hrmStaffPhotoCropTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content staff-modal-content">
        <div class="modal-header staff-modal-header"><h3 class="modal-title" id="hrmStaffPhotoCropTitle">Crop Staff Photo</h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cancel photo crop"></button></div>
        <div class="modal-body">
            <p class="text-secondary small">Adjust the photo inside the square frame. The final image will be exactly 400 × 400 px.</p>
            <div class="staff-photo-crop-stage"><canvas width="400" height="400" aria-label="Photo crop preview. Drag to reposition the photo."></canvas></div>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary btn-icon" data-staff-photo-zoom-step="-0.1" aria-label="Zoom out"><i class="ti ti-zoom-out" aria-hidden="true"></i></button>
                <input type="range" class="form-range staff-photo-crop-zoom" min="1" max="3" step="0.01" value="1" aria-label="Zoom photo" data-staff-photo-zoom>
                <button type="button" class="btn btn-outline-secondary btn-icon" data-staff-photo-zoom-step="0.1" aria-label="Zoom in"><i class="ti ti-zoom-in" aria-hidden="true"></i></button>
                <button type="button" class="btn btn-outline-secondary" data-staff-photo-rotate="-90"><i class="ti ti-rotate-2 me-1" aria-hidden="true"></i>Left</button>
                <button type="button" class="btn btn-outline-secondary" data-staff-photo-rotate="90"><i class="ti ti-rotate-clockwise-2 me-1" aria-hidden="true"></i>Right</button>
                <button type="button" class="btn btn-outline-secondary" data-staff-photo-reset>Reset</button>
            </div>
        </div>
        <div class="modal-footer staff-modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" data-staff-photo-apply><i class="ti ti-crop me-1" aria-hidden="true"></i>Crop and Upload</button></div>
    </div></div>
</div>
