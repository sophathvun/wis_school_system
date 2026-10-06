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
        @elseif($fontKey==='qr')
            @if(($showFrame ?? false) || (($certificateShowQr ?? false) && ($certificate->certificate_qr_image ?? null)))
                <div class="k3-certificate-qr" @if(($showFrame ?? false) && !($certificateShowQr ?? false)) hidden @endif data-k3-layout-key="qr" data-k3-x="{{ $field['x'] }}" data-k3-y="{{ $field['y'] }}" data-k3-width="{{ $field['width'] }}">
                    @if($certificate->certificate_qr_image ?? null)
                        <div class="k3-qr-frame">
                        <img src="{{ $certificate->certificate_qr_image }}" alt="QR code to verify this K3 certificate" draggable="false">
                        @if($certificate->certificate_verification_url ?? null)
                            <a class="k3-qr-verification-note" href="{{ $certificate->certificate_verification_url }}" target="_blank" rel="noopener noreferrer" title="{{ $certificate->certificate_verification_url }}" aria-label="Verify this certificate at {{ $certificate->certificate_verification_url }}" draggable="false">
                                <img class="k3-qr-verification-tick" src="{{ ($pdfMode ?? false) ? 'file://'.str_replace('\\','/',public_path('images/report-templates/k3-certificate/verified.svg')) : asset('images/report-templates/k3-certificate/verified.svg') }}" alt="" aria-hidden="true" draggable="false"> Verify: {{ $certificate->certificate_verification_site }}
                            </a>
                        @endif
                        </div>
                        <span class="k3-qr-scan-note">Scan to Verify.</span>
                    @else
                        <span class="k3-qr-placeholder">QR code<br>Assign certificate numbers to activate</span>
                    @endif
                </div>
            @endif
        @else
            <div class="k3-certificate-field {{ $definitions[$fontKey]['class'] }}" @include('reports._k3-certificate-field-data')>
                @foreach(explode("\n",\App\Support\K3CertificateLayout::text($field['text'],$values)) as $line)
                    <span class="k3-certificate-line {{ $field['bold_first_line'] && $loop->first ? 'k3-line-bold' : '' }}">{{ $line }}</span>
                @endforeach
            </div>
        @endif
    @endforeach
</section>
