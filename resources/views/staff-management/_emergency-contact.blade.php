<div class="col-12"><section class="card staff-form-section-card" aria-labelledby="staffEmergencyContactTitle" data-staff-emergency-contact>
    <div class="card-header staff-form-section-header"><h3 class="card-title" id="staffEmergencyContactTitle"><i class="ti ti-phone-call me-2" aria-hidden="true"></i>Emergency Contact</h3></div>
    <div class="card-body"><div class="staff-emergency-contact-grid">
        @include('staff-management._field',['name'=>'emergency_contact_name','label'=>'Contact Name','width'=>'staff-emergency-field'])
        @include('staff-management._phone-field',['name'=>'emergency_contact_phone','label'=>'Contact Phone','width'=>'staff-emergency-field'])
        @include('staff-management._field',['name'=>'emergency_contact_relationship','label'=>'Relationship','width'=>'staff-emergency-field'])
        @include('staff-management._field',['name'=>'emergency_contact_address','label'=>'Emergency Contact Address','type'=>'textarea','width'=>'staff-emergency-address'])
    </div></div>
</section></div>
