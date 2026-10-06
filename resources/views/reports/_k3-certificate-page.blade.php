@php
    $layout = $certificateLayout ?? \App\Support\K3CertificateLayout::defaults($certificateSettings?->typography);
    $values = \App\Support\K3CertificateLayout::tokens($certificate,$certificateSettings);
    $definitions = \App\Support\K3CertificateTypography::fields();
@endphp
<section class="k3-certificate-page {{ ($showFrame ?? false) ? 'k3-with-frame' : '' }}" data-k3-values="{{ json_encode($values) }}">
    @if($showFrame ?? false)<img class="k3-certificate-frame" src="{{ asset('images/report-templates/k3-certificate/frame.jpg') }}?v={{ substr(sha1_file(public_path('images/report-templates/k3-certificate/frame.jpg')), 0, 12) }}" alt="K3 certificate frame reference">@endif
    @foreach($layout['fields'] as $fontKey=>$field)
        @if($fontKey==='photo')
            <div class="k3-certificate-photo" data-k3-layout-key="photo" data-k3-x="{{ $field['x'] }}" data-k3-y="{{ $field['y'] }}" data-k3-width="{{ $field['width'] }}" data-k3-height="{{ $field['height'] }}"></div>
        @else
            <div class="k3-certificate-field {{ $definitions[$fontKey]['class'] }}" @include('reports._k3-certificate-field-data')>
                @foreach(explode("\n",\App\Support\K3CertificateLayout::text($field['text'],$values)) as $line)
                    <span class="k3-certificate-line {{ $field['bold_first_line'] && $loop->first ? 'k3-line-bold' : '' }}">{{ $line }}</span>
                @endforeach
            </div>
        @endif
    @endforeach
</section>
