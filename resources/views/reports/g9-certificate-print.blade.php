<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>G9 Certificate (WIS)</title>
@if($pdfMode??false)
    <link rel="stylesheet" href="{{ 'file://'.str_replace('\\','/',resource_path('css/g9Certificate.css')) }}">
    <link rel="stylesheet" href="{{ 'file://'.str_replace('\\','/',resource_path('css/g9CertificatePdf.css')) }}">
@else
    @vite(['resources/css/g9Certificate.css', 'resources/js/g9Certificate.js'])
@endif
</head><body class="g9-certificate-document {{ ($pdfMode??false) ? 'g9-certificate-pdf' : 'g9-certificate-browser' }}">
@unless($pdfMode??false)
<div class="g9-print-toolbar"><button type="button" data-g9-print>Print</button><button type="button" data-g9-close>Close</button><span class="g9-print-help">Preprinted frame paper: A4 landscape, Actual Size / 100%, no margins or headers.</span></div>
@endunless
<main class="g9-print-pages">@foreach($certificates as $certificate)
@unless($pdfMode??false)<div class="g9-screen-preview">@endunless
@include('reports._g9-certificate-page')
@unless($pdfMode??false)</div>@endunless
@endforeach</main>
</body></html>
