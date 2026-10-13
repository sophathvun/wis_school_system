<div class="col-12"><section class="card staff-form-section-card" aria-labelledby="staffCurrentAddressTitle" data-staff-address data-options-url="{{ route('staff-management.address-options') }}">
    <div class="card-header staff-form-section-header"><h3 class="card-title" id="staffCurrentAddressTitle"><i class="ti ti-map-pin me-2" aria-hidden="true"></i>Current Address</h3></div>
    <div class="card-body">
        <div class="staff-address-location-grid">
            @foreach($addressLocations as $level=>$location)
                @php($addressError=$errors->first('address_'.$level.'_id'))
                <div>
                    <div class="premium-form-field staff-form-field {{ $location['id'] || $location['english']?'has-value':'' }}">
                        <label class="form-label" for="staff-address-{{ $level }}">{{ $location['label'] }}</label>
                        @unless($level==='country')
                            <input type="hidden" name="{{ $level }}_en" value="{{ $location['english'] }}">
                            <input type="hidden" name="{{ $level }}_kh" value="{{ $location['khmer'] }}">
                        @endunless
                        <select id="staff-address-{{ $level }}" name="address_{{ $level }}_id" class="form-select {{ $addressError?'is-invalid':'' }}" data-staff-address-level="{{ $level }}" @if($addressError) aria-invalid="true" aria-describedby="staff-address-{{ $level }}-error" @endif>
                            <option value="" @selected(!$location['id'] && !$location['english'])>Select {{ $location['label'] }}</option>
                            @if(!$location['id'] && $location['english'])<option value="" data-en="{{ $location['english'] }}" data-kh="{{ $location['khmer'] }}" selected>{{ $location['khmer'] }} / {{ $location['english'] }}</option>@endif
                            @foreach($location['options'] as $item)
                                @php($flag=$level==='country' && $item->flag_path ? (\Illuminate\Support\Str::startsWith($item->flag_path,['http://','https://','/'])?$item->flag_path:asset($item->flag_path)) : '')
                                <option value="{{ $item->id }}" data-en="{{ $item->{$level.'_name_en'} }}" data-kh="{{ $item->{$level.'_name_kh'} }}" data-flag="{{ $flag }}" @selected((string)$location['id']===(string)$item->id)>{{ $item->{$level.'_name_kh'} }} / {{ $item->{$level.'_name_en'} }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($addressError)<div id="staff-address-{{ $level }}-error" class="invalid-feedback d-block">{{ $addressError }}</div>@endif
                </div>
            @endforeach
        </div>
        <div class="staff-address-details-grid mt-3">
            @include('staff-management._field',['name'=>'house_number_en','label'=>'House Number (English)'])
            @include('staff-management._field',['name'=>'street_en','label'=>'Street (English)'])
            @include('staff-management._field',['name'=>'current_address','label'=>'Current Address (English)','type'=>'textarea','readonly'=>true])
            @include('staff-management._field',['name'=>'house_number_kh','label'=>'House Number (Khmer)','readonly'=>true])
            @include('staff-management._field',['name'=>'street_kh','label'=>'Street (Khmer)','readonly'=>true])
            @include('staff-management._field',['name'=>'current_address_kh','label'=>'Current Address (Khmer)','type'=>'textarea','readonly'=>true])
        </div>
    </div>
</section></div>
