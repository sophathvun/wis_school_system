@unless($pdfExport??false)
<!DOCTYPE html><html lang="km"><head><meta charset="utf-8"><title>{{ \App\Support\G9EoyReport::TITLES[$type] }}</title><style>
@foreach(['g9body'=>'KhmerOSsiemreap.ttf','g9heading'=>'KhmerOSmuollight.ttf','g9ornament'=>'Tacteing.ttf'] as $font=>$file)
@font-face{font-family:{{ $font }};src:url('data:font/ttf;base64,{{ base64_encode(file_get_contents(public_path('fonts/khmer/'.$file))) }}')}
@endforeach
{!! file_get_contents(resource_path('css/pages/g9-eoy-print.css')) !!}
</style></head><body>
@endunless
@foreach(array_chunk($pages,2) as $sheet)
<section class="eoy-print-sheet">
@foreach($sheet as $page)
<div class="eoy-print-half"><section class="eoy-sheet">
    <table class="eoy-panels {{ $type==='cover'?'eoy-cover':'eoy-card' }}"><tr>
        @foreach([1,2] as $side)
        <td class="eoy-panel {{ $side===1?'eoy-front':'eoy-back' }}">
        @include('g9-national-exams.eoy-report-panel')
        </td>
        @endforeach
    </tr></table>
</section></div>
@endforeach
</section>
@endforeach
@unless($pdfExport??false)
<script>Promise.all([document.fonts.ready,...Array.from(document.images).map(image=>image.complete?Promise.resolve():new Promise(resolve=>{image.onload=image.onerror=resolve;}))]).then(()=>window.print());</script>
</body></html>
@endunless
