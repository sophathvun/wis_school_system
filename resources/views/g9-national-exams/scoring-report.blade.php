@php($type=$type??'verify')
<!DOCTYPE html><html lang="km"><head><meta charset="utf-8"><title>{{ $type==='scores'?\App\Support\G9ScoringReport::COLLECTION_TITLE:\App\Support\G9ScoringReport::TITLE }}</title><style>
@foreach(['g9body'=>'KhmerOSsiemreap.ttf','g9heading'=>'KhmerOSmuollight.ttf','g9ornament'=>'Tacteing.ttf'] as $font=>$file)
@font-face{font-family:{{ $font }};src:url('data:font/ttf;base64,{{ base64_encode(file_get_contents(public_path('fonts/khmer/'.$file))) }}')}
@endforeach
{!! file_get_contents(resource_path('css/pages/g9-scoring-print.css')) !!}
@if($type==='scores'){!! file_get_contents(resource_path('css/pages/g9-score-collection-print.css')) !!}@endif
</style></head><body>
@foreach($pages as $page)<section class="verify-page">@include($type==='scores'?'g9-national-exams.score-collection-page':'g9-national-exams.scoring-report-page')</section>@endforeach
<script>document.fonts.ready.then(()=>window.print());</script>
</body></html>
