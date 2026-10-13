@php
    $documentType=old('identity_document_type',$editStaff?->identity_document_type);
    $documentNumberLabel=match($documentType){'national_id'=>'ID Number','passport'=>'Passport Number',default=>'Document Number'};
@endphp
<div class="col-12"><section class="card staff-form-section-card" aria-labelledby="staffIdentificationTitle" data-staff-identification data-document-type="{{ $documentType }}">
    <div class="card-header staff-form-section-header"><h3 class="card-title" id="staffIdentificationTitle"><i class="ti ti-id me-2" aria-hidden="true"></i>Identification</h3></div>
    <div class="card-body"><div class="staff-identification-grid">
        @include('staff-management._field',['name'=>'identity_document_type','label'=>'Document Type','options'=>$identityLabels,'width'=>'staff-identity-field'])
        @include('staff-management._field',['name'=>'identity_document_name','label'=>'Document Name','width'=>'staff-identity-field staff-identity-name'])
        @include('staff-management._field',['name'=>'identity_document_number','label'=>$documentNumberLabel,'width'=>'staff-identity-field staff-identity-details'])
        @include('staff-management._date-field',['name'=>'identity_document_issued_on','label'=>'Issue Date'])
        @include('staff-management._date-field',['name'=>'identity_document_expires_on','label'=>'Expiry Date'])
    </div></div>
</section></div>
