<div class="modal fade" id="scholarshipCenterLocationsModal" tabindex="-1" aria-labelledby="scholarshipCenterLocationsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" action="{{ route('western-scholarship.exams.center-locations',$editingExam) }}" method="POST" data-center-locations-form novalidate>
            @csrf
            <input type="hidden" name="version" value="{{ $editingExam->version }}">
            <div class="modal-header scholarship-card-header"><h3 class="modal-title text-white" id="scholarshipCenterLocationsTitle"><i class="ti ti-map-pin me-2" aria-hidden="true"></i>Exam Center Locations</h3><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="small text-secondary">Reports use each center's city or province before dates. Locations can be updated after seats are confirmed.</p>
                <div class="alert alert-danger" role="alert" data-location-error hidden></div>
                @foreach($savedCenters as $center)
                    <section class="scholarship-signature-card mb-3"><h4>{{ $center->name }}</h4><div class="scholarship-field"><label for="reportCenterLocation{{ $center->id }}">Location <span class="text-danger">*</span></label><input id="reportCenterLocation{{ $center->id }}" name="locations[{{ $center->id }}]" class="form-control" value="{{ $center->location }}" maxlength="80" required placeholder="e.g. Battambang" data-location-center-id="{{ $center->id }}"></div></section>
                @endforeach
            </div>
            <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i>Save Locations</button></div>
        </form>
    </div>
</div>
