@php
    $layout = $certificateLayout ?? \App\Support\G9CertificateLayout::defaults($certificateSettings?->typography);
    $values = \App\Support\G9CertificateLayout::tokens($certificate,$certificateSettings);
    $definitions = \App\Support\G9CertificateTypography::fields();
@endphp
<section class="g9-certificate-page {{ ($showFrame ?? false) ? 'g9-with-frame' : '' }}" data-g9-values="{{ json_encode($values) }}">
    @if($showFrame ?? false)<img class="g9-certificate-frame" src="{{ asset('images/report-templates/g9-certificate/frame.jpg') }}?v={{ substr(sha1_file(public_path('images/report-templates/g9-certificate/frame.jpg')), 0, 12) }}" alt="G9 certificate frame reference">@endif
    @foreach($layout['fields'] as $fontKey=>$field)
        @if($fontKey==='photo')
            <div class="g9-certificate-photo" data-g9-layout-key="photo" data-g9-x="{{ $field['x'] }}" data-g9-y="{{ $field['y'] }}" data-g9-width="{{ $field['width'] }}" data-g9-height="{{ $field['height'] }}"></div>
        @else
            <div class="g9-certificate-field {{ $definitions[$fontKey]['class'] }}" @include('reports._g9-certificate-field-data')>
                @foreach(explode("\n",\App\Support\G9CertificateLayout::text($field['text'],$values)) as $line)
                    <span class="g9-certificate-line {{ $field['bold_first_line'] && $loop->first ? 'g9-line-bold' : '' }}">{{ $line }}</span>
                @endforeach
            </div>
        @endif
    @endforeach
</section>
