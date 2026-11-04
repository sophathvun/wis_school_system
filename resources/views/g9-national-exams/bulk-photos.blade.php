@extends('layouts.app')
@section('title', 'G9 Bulk Stu. Photo')
@section('page-header', '')
@section('content')
    @vite(['resources/css/pages/g9-national-exams.css','resources/js/g9BulkPhotos.js'])
    <div data-g9-bulk-photos data-base="{{ url('/g9-national-exams/bulk-photos') }}">
        <section class="card mb-3">
            <div class="card-header g9-header"><h3 class="card-title"><i class="ti ti-photo me-2"></i>Bulk Student Photos</h3></div>
            <form class="card-body" data-photo-upload-form>
                <p class="text-secondary">Select an academic year and upload MoEYS photos. Filenames match the candidate's imported imageName; names are never guessed from the student's name or ID.</p>
                <div class="g9-editor-grid">
                    <div class="premium-form-field staff-form-field"><label class="form-label" for="g9PhotoYear">Academic Year</label><select class="form-select" name="academic_year_id" id="g9PhotoYear" required>@foreach($years as $year)<option value="{{ $year->id }}">{{ $year->academic_year }}</option>@endforeach</select></div>
                    <div><label class="form-label" for="g9PhotoFiles">Student Photos / ZIP</label><input class="form-control" type="file" name="files[]" id="g9PhotoFiles" accept=".jpg,.jpeg,.png,.webp,.zip" multiple required @disabled(!$canUpload)></div>
                </div>
                <p class="form-hint mt-3">JPG, PNG, WebP or ZIP. Up to 5 MB per image, 50 MB per ZIP, and 5,000 images per batch. For large collections, use ZIP files or smaller batches within the server upload limits. ZIP subfolders are supported.</p>
                @if($canReplace)<label class="form-check my-3"><input class="form-check-input" type="checkbox" name="replace_existing" value="1"><span class="form-check-label">Replace existing photos for matched students</span></label>@else<p class="form-hint">Existing student photos will be preserved.</p>@endif
                <div class="d-flex align-items-center gap-3 flex-wrap">@if($canUpload)<button type="submit" class="btn btn-primary" data-photo-preview><i class="ti ti-eye me-2"></i>Preview Photos</button>@else<span class="text-secondary">Photo upload permission is required.</span>@endif<span data-photo-progress role="status"></span></div>
                <div class="text-danger mt-2" data-photo-error role="alert"></div><div class="text-success mt-2" data-photo-success role="status"></div>
            </form>
        </section>
        <section class="card" data-photo-preview-panel hidden>
            <div class="card-header g9-header"><h3 class="card-title">Photo Matching Preview</h3><button type="button" class="btn btn-light ms-auto" data-photo-confirm disabled>Confirm Upload</button></div>
            <div class="card-body"><div class="g9-import-summary" data-photo-summary></div><p class="form-hint mt-3 mb-0">Only Ready photos will be attached. Existing, unmatched, duplicate, ambiguous, or invalid files are skipped. The first 200 uploaded images and first 100 missing candidates are shown below.</p></div>
            <div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>File</th><th>Student ID</th><th>Student</th><th>Status</th><th>Details</th></tr></thead><tbody data-photo-rows></tbody></table></div>
            <details class="card-body"><summary>Missing Uploaded Photos</summary><p class="form-hint mt-2">Candidates with no matching uploaded image, including candidates without an imageName.</p><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Student ID</th><th>Student Name</th><th>Expected Photo Filename</th></tr></thead><tbody data-photo-missing></tbody></table></div></details>
        </section>
    </div>
@endsection
