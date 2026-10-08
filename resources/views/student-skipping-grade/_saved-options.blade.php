@if($savedOptions)
<div class="mt-3">
    <h4>{{ $optionsTitle }}</h4>
    <div class="skipping-criteria">
        @foreach($savedOptions as $option)
            <div class="skipping-criterion"><i class="ti {{ $option['checked']?'ti-checkbox text-success':'ti-square text-secondary' }}"></i><span class="skipping-option-label">{{ $option['label'] }}</span></div>
        @endforeach
    </div>
</div>
@endif
