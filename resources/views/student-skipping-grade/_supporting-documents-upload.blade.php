@php
    $documentsEditable=$documentsEditable??true;
    $documents=$record->supporting_documents??[];
@endphp
<div class="skipping-documents-upload" @if($documentsEditable) data-skipping-documents-upload @endif data-existing-count="{{ count($documents) }}">
    @if($documentsEditable)<label for="skippingSupportingDocuments" class="form-label">Attach Supporting Documents</label>@endif
    @if($documentsEditable)<input type="hidden" name="document_markers" value="1"><input type="hidden" name="document_count" value="{{ count($documents) }}">@endif
    <div class="premium-document-dropzone {{ $documentsEditable?'':'is-readonly' }}" @if($documentsEditable) tabindex="0" data-documents-dropzone aria-label="Supporting documents: browse, drop, or paste files" aria-describedby="skippingDocumentsHint skippingDocumentsStatus" @endif>
        @if($documentsEditable)
            <span class="document-upload-icon"><i class="ti ti-cloud-upload"></i></span>
            <div class="fw-bold fs-3">Drop Files Here</div>
            <div class="mt-1">or <span class="document-browse-link">Browse File</span></div>
            <div id="skippingDocumentsHint" class="document-upload-hint mt-3">Supports PDF, JPG, PNG, WEBP &middot; Maximum 25 MB per file</div>
            <div class="document-upload-hint mt-1">PDF files larger than 500 KB are automatically optimized. JPG and PNG files are saved as WebP below 100 KB.</div>
            <div class="document-upload-hint mt-1">You can also copy and paste a file here</div>
            <div class="document-upload-hint mt-1">Include the parent-signed request here and mark its file card as Parent-signed Request.</div>
            <div class="document-upload-hint mt-1">Up to 10 files per upload and 20 files per request. Selected files upload when you {{ $documentsSaveAction??'save the draft' }}.</div>
            <input id="skippingSupportingDocuments" type="file" class="d-none" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/png,image/jpeg,image/webp" data-documents-input>
            <div id="skippingDocumentsStatus" class="text-danger small mt-2" role="status" aria-live="polite" data-documents-status></div>
        @endif
        <div class="premium-document-file-list">
            @foreach($documents as $index=>$document)
                <div class="premium-document-file-item document-file-card">
                    <span class="document-file-icon"><i class="ti {{ ($document['mime']??'')==='application/pdf'?'ti-file-type-pdf':'ti-photo' }}"></i></span>
                    <div class="document-file-details">
                        @if(!empty($document['archived_at']))
                            <span class="document-file-name d-block">{{ $document['name'] }}</span>
                            <div class="document-file-meta">Archived · {{ \Illuminate\Support\Carbon::parse($document['archived_at'])->format('d-M-Y') }}</div>
                            <small class="text-secondary text-break">Backup: {{ $document['archive_reference']??$document['archive_id'] }}</small>
                        @else
                            <a class="document-file-open d-block" target="_blank" rel="noopener" aria-label="View {{ $document['name'] }}" href="{{ route('student-skipping-grade.attachment',['skipping'=>$record,'index'=>$index]) }}"><span class="document-file-name d-block">{{ $document['name'] }}</span></a>
                            <div class="document-file-meta">
                                Uploaded
                                @if(isset($document['size']))
                                    · {{ number_format($document['size']/1048576,2) }} MB
                                @endif
                            </div>
                            @if(($document['optimization_type']??null)==='pdf' && ($document['compressed']??false))<small class="text-success">PDF optimized from {{ number_format(($document['original_size']??0)/1048576,2) }} MB</small>
                            @elseif(($document['optimization_type']??null)==='image')<small class="text-success">Converted to WebP from {{ number_format(($document['original_size']??0)/1024,0) }} KB</small>@endif
                        @endif
                        @if($documentsEditable)
                            <label class="document-parent-signed"><input type="checkbox" class="form-check-input" name="parent_signed_documents[]" value="{{ $index }}" @checked($document['is_parent_signed']??false)> Parent-signed Request</label>
                        @elseif($document['is_parent_signed']??false)<span class="badge bg-primary-lt mt-1">Parent-signed Request</span>@endif
                    </div>
                </div>
            @endforeach
        </div>
        @if($documentsEditable)<div class="premium-document-file-list" data-documents-file-list></div>
        @elseif(!$documents)<p class="text-secondary mb-0">No supporting documents attached.</p>@endif
    </div>
</div>
