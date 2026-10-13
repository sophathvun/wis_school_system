<div class="staff-detail-grid">
    @foreach($fields as $key=>$label)
        @php
            $display=$viewStaff->{$key};
            if($display instanceof \DateTimeInterface) $display=$display->format('d-M-Y');
            $display=match($key){
                'marital_status'=>$maritalLabels[$display]??$display,
                'identity_document_type'=>$identityLabels[$display]??$display,
                default=>$display,
            };
        @endphp
        <div class="staff-detail-item {{ in_array($key,['staff_code','nationality_note','notes','emergency_contact_address'])?'staff-detail-wide':'' }}"><div class="staff-detail-label">{{ $label }}</div>
            @if(in_array($key,['birthCountry','addressCountry']))
                <div class="staff-detail-value staff-birth-country-label">
                    @if($display)
                        @if($display->flag_path)<img class="location-flag" src="{{ \Illuminate\Support\Str::startsWith($display->flag_path,['http://','https://','/'])?$display->flag_path:asset($display->flag_path) }}" alt="">@endif
                        <span><span class="staff-khmer d-block">{{ $display->country_name_kh }}</span><small class="text-secondary d-block">{{ $display->country_name_en }}</small></span>
                    @else — @endif
                </div>
            @else<div class="staff-detail-value {{ str_ends_with($key,'_kh')?'staff-khmer':'' }}">{{ filled($display)?$display:'—' }}</div>@endif
        </div>
    @endforeach
</div>
