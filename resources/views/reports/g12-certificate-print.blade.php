<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>G12 Certificate (WIS)</title>
@if($pdfMode??false)
    <link rel="stylesheet" href="{{ 'file://'.str_replace('\\','/',resource_path('css/g12Certificate.css')) }}">
    <link rel="stylesheet" href="{{ 'file://'.str_replace('\\','/',resource_path('css/g12CertificatePdf.css')) }}">
@else
    @vite(['resources/css/g12Certificate.css', 'resources/js/g12Certificate.js'])
@endif
</head><body class="g12-certificate-document {{ ($pdfMode??false) ? 'g12-certificate-pdf' : 'g12-certificate-browser' }}">
@unless($pdfMode??false)
<div class="g12-print-toolbar"><button type="button" data-g12-print>Print</button><button type="button" data-g12-close>Close</button><span class="g12-print-help">Preprinted frame paper: A4 landscape, Actual Size / 100%, no margins or headers.</span></div>
@endunless
<main class="g12-print-pages">@foreach($certificates as $certificate)
@unless($pdfMode??false)<div class="g12-screen-preview">@endunless
@include('reports._g12-certificate-page')
@unless($pdfMode??false)</div>@endunless
@endforeach</main>
</body></html>
