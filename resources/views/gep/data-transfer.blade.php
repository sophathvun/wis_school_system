@extends('layouts.app')
@section('title','GEP Import/Export Data')
@section('content')
<div class="card shadow-sm">
    <div class="card-header gep-settings-header"><h1 class="card-title text-white mb-0"><i class="ti ti-file-import me-2"></i>GEP Import/Export Data</h1></div>
    <div class="card-body">
        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }} No rows from this upload were saved.</div>@endif
        @include('gep._transfer-preview',['preview'=>$preview??null])
        <p>Import <strong>Student Information first</strong>, then Family and Enrollment in either order. Each sample CSV contains two fictional records. Replace the sample data and setting names before importing.</p>
        <p class="text-secondary">Keep Student IDs, Family Numbers and phone columns as Text in Excel. Dates use YYYY-MM-DD. Use full names, not database IDs: birthplace and address have separate English (_en) and Khmer (_kh) columns, including country, province/city, district/khan, commune and village. You may supply either or both languages. House numbers, streets and full addresses also have English and Khmer columns. Enrollment settings use their names; Group uses its code (M, A or E). Family rows use Family Number and relationship (mother, father or guardian). Give siblings the same Family Number in Student Information.</p>
        <p class="text-secondary">Original location spellings are preserved, including names that do not match the current location list. Only unique names within the supplied parent location are linked automatically. Unmatched or ambiguous names remain as imported text for review; the system does not guess a different address. Family nationality and occupation also accept names and retain unmatched text.</p>
        <div class="alert alert-info">Blank cells preserve saved values. Student Information imports create profiles without enrollments. Historical enrollment statuses are retained. Family information is linked by Family Number and shared by siblings, without an academic year or term. WIS profiles and families are unchanged.</div>
        <div class="row g-3">
            @foreach(['students'=>'Student Information','enrollments'=>'Enrollment','families'=>'Family'] as $type=>$label)
                <div class="col-12"><section class="card">
                    <div class="card-header"><h2 class="card-title">{{ $loop->iteration }}. {{ $label }}</h2></div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <a class="btn btn-outline-primary" href="{{ route('gep.transfer.download',[$type,'template']) }}"><i class="ti ti-download me-1"></i>Sample CSV (2 records)</a>
                            <a class="btn btn-outline-success" href="{{ route('gep.transfer.download',[$type,'xlsx']) }}"><i class="ti ti-file-spreadsheet me-1"></i>Export Excel</a>
                            <a class="btn btn-outline-secondary" href="{{ route('gep.transfer.download',[$type,'csv']) }}">Export CSV</a>
                        </div>
                        @if($canImport)
                            <form method="post" action="{{ route('gep.transfer.upload',$type) }}" enctype="multipart/form-data" class="gep-transfer-upload skipping-documents-upload" data-gep-csv-import>
                                @csrf
                                <label class="form-label" for="gep-transfer-{{ $type }}">{{ $label }} CSV</label>
                                <input class="form-control" type="file" name="file" id="gep-transfer-{{ $type }}" accept=".csv,.txt,text/csv" required>
                                <div class="premium-document-dropzone" data-gep-csv-dropzone tabindex="0" role="button" aria-label="Choose {{ $label }} CSV file" aria-describedby="gep-transfer-{{ $type }}-hint gep-transfer-{{ $type }}-error" hidden>
                                    <span class="document-upload-icon"><i class="ti ti-cloud-upload" aria-hidden="true"></i></span>
                                    <div class="fw-bold fs-3">Drop File Here</div>
                                    <div class="mt-1">or <span class="document-browse-link">Browse File</span></div>
                                    <div class="document-upload-hint mt-3" id="gep-transfer-{{ $type }}-hint">CSV or TXT &middot; Maximum 25 MB &middot; One file</div>
                                    <div class="document-upload-hint mt-1">You can also copy and paste a file here</div>
                                </div>
                                <div class="premium-document-file-list" data-gep-csv-file-list aria-live="polite"></div>
                                <div class="text-danger small mt-2" data-gep-csv-error id="gep-transfer-{{ $type }}-error" role="alert" hidden></div>
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <button class="btn btn-primary" type="submit" name="apply" value="0">Preview</button>
                                </div>
                            </form>
                            @if(($preview['result']['type']??null)===$type)
                                @include('gep._transfer-apply')
                            @endif
                        @endif
                    </div>
                </section></div>
            @endforeach
        </div>
    </div>
</div>
@vite('resources/js/gepDataTransfer.js')
@endsection
