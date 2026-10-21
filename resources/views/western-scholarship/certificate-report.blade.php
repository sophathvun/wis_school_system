<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>WIS Scholarship Certificates</title>
@vite(['resources/css/pages/scholarship-certificates.css','resources/js/scholarshipCertificates.js'])
</head><body class="cert-report {{ $frame?'':'cert-content-only' }}" data-cert-report>
<div class="cert-report-toolbar"><strong>WIS Scholarship Certificates</strong><span>{{ count($pages) }} certificate(s) • {{ $frame?'Framed':'Content only on preprinted paper' }}</span><button type="button" data-cert-print>Print A4</button><small>Use A4 portrait, 100% scale, no margins, and turn off browser headers and footers.</small></div>
@foreach($pages as $page)<section class="cert-sheet" data-cert-page="{{ $loop->index }}" aria-label="Certificate {{ $page['values']['certificate_number'] }}"></section>@endforeach
<script type="application/json" id="certReportData">{!! json_encode(['pages'=>$pages,'assets'=>$assets,'fonts'=>\App\Support\ScholarshipCertificateLayout::fonts()],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
</body></html>
