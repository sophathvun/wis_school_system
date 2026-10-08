<div data-skipping-image-upload data-upload-label="{{ $uploadLabel }}">
    <label class="form-label" for="skippingUpload{{ ucfirst($uploadName) }}">{{ $uploadLabel }}</label>
    <input class="d-none" type="file" id="skippingUpload{{ ucfirst($uploadName) }}" name="{{ $uploadName }}" accept="image/png,image/jpeg,image/webp" data-upload-input>
    <button class="logo-dropzone skipping-image-dropzone w-100" type="button" data-upload-dropzone aria-label="Upload {{ $uploadLabel }}" aria-describedby="skippingUploadHint{{ ucfirst($uploadName) }}">
        <span class="skipping-image-upload-copy">
            <i class="ti ti-cloud-upload logo-dropzone-icon" aria-hidden="true"></i>
            <strong>Drag and drop {{ strtolower($uploadLabel) }} here</strong>
            <span class="text-secondary">or click, paste, or upload a file</span>
        </span>
        <span class="skipping-image-preview" data-upload-preview @if(!$uploadImage) hidden @endif>
            <img @if($uploadImage) src="{{ $uploadImage }}" @endif alt="{{ $uploadLabel }} preview" data-upload-image>
        </span>
    </button>
    <small class="form-hint" id="skippingUploadHint{{ ucfirst($uploadName) }}">JPG, PNG, or WEBP. Maximum size: 2 MB.</small>
    <div class="skipping-image-upload-status mt-2" data-upload-status aria-live="polite"></div>
    <button class="btn btn-sm mt-2" type="button" data-upload-cancel hidden>Cancel Selection</button>
</div>
