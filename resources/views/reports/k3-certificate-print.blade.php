<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>K3 Certificate (WIS)</title>
@if($pdfMode??false)
    <link rel="stylesheet" href="{{ 'file://'.str_replace('\\','/',resource_path('css/k3Certificate.css')) }}">
    <link rel="stylesheet" href="{{ 'file://'.str_replace('\\','/',resource_path('css/k3CertificatePdf.css')) }}">
@else
    @vite(['resources/css/k3Certificate.css', 'resources/js/k3Certificate.js'])
@endif
</head><body class="k3-certificate-document {{ ($pdfMode??false) ? 'k3-certificate-pdf' : 'k3-certificate-browser' }}">
@unless($pdfMode??false)
<div class="k3-print-toolbar"><button type="button" data-k3-print>Print</button><button type="button" data-k3-close>Close</button><span class="k3-print-help">Preprinted frame paper: A4 landscape, Actual Size / 100%, no margins or headers.</span></div>
@endunless
<main class="k3-print-pages">@foreach($certificates as $certificate)
@unless($pdfMode??false)<div class="k3-screen-preview">@endunless
@include('reports._k3-certificate-page')
@unless($pdfMode??false)</div>@endunless
@endforeach</main>
</body></html>
