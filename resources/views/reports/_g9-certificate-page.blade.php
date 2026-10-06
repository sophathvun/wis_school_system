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
        @elseif($fontKey==='qr')
            @if(($showFrame ?? false) || (($certificateShowQr ?? false) && ($certificate->certificate_qr_image ?? null)))
                <div class="g9-certificate-qr" @if(($showFrame ?? false) && !($certificateShowQr ?? false)) hidden @endif data-g9-layout-key="qr" data-g9-x="{{ $field['x'] }}" data-g9-y="{{ $field['y'] }}" data-g9-width="{{ $field['width'] }}">
                    @if($certificate->certificate_qr_image ?? null)
                        <div class="g9-qr-frame">
                        <img src="{{ $certificate->certificate_qr_image }}" alt="QR code to verify this G9 certificate" draggable="false">
                        @if($certificate->certificate_verification_url ?? null)
                            <a class="g9-qr-verification-note" href="{{ $certificate->certificate_verification_url }}" target="_blank" rel="noopener noreferrer" title="{{ $certificate->certificate_verification_url }}" aria-label="Verify this certificate at {{ $certificate->certificate_verification_url }}" draggable="false">
                                <img class="g9-qr-verification-tick" src="{{ ($pdfMode ?? false) ? 'file://'.str_replace('\\','/',public_path('images/report-templates/g9-certificate/verified.svg')) : asset('images/report-templates/g9-certificate/verified.svg') }}" alt="" aria-hidden="true" draggable="false"> Verify: {{ $certificate->certificate_verification_site }}
                            </a>
                        @endif
                        </div>
                        <span class="g9-qr-scan-note">Scan to Verify.</span>
                    @else
                        <span class="g9-qr-placeholder">QR code<br>Assign certificate numbers to activate</span>
                    @endif
                </div>
            @endif
        @else
            <div class="g9-certificate-field {{ $definitions[$fontKey]['class'] }}" @include('reports._g9-certificate-field-data')>
                @foreach(explode("\n",\App\Support\G9CertificateLayout::text($field['text'],$values)) as $line)
                    <span class="g9-certificate-line {{ $field['bold_first_line'] && $loop->first ? 'g9-line-bold' : '' }}">{{ $line }}</span>
                @endforeach
            </div>
        @endif
    @endforeach
</section>
