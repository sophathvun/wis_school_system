@if($customOptions)
<div class="mt-3">
    <h4>{{ $optionsTitle }}</h4>
    <div class="skipping-criteria">
        @foreach($customOptions as $key=>$option)
            <label class="skipping-criterion">
                <input type="hidden" name="custom_options[{{ $key }}]" value="0">
                <input class="form-check-input" type="checkbox" name="custom_options[{{ $key }}]" value="1" @checked(old('custom_options.'.$key,$selectedOptions[$key]['checked']??($useDefaults?$option['default']:false)))>
                <span class="skipping-option-label">{{ $option['label'] }}</span>
            </label>
        @endforeach
    </div>
</div>
@endif
