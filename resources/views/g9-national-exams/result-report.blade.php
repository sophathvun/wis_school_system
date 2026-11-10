<!DOCTYPE html><html lang="km"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ \App\Support\G9ResultReport::TITLES[$type] }}</title><style>
@foreach(['g9body'=>'KhmerOSsiemreap.ttf','g9heading'=>'KhmerOSmuollight.ttf','g9ornament'=>'Tacteing.ttf'] as $font=>$file)
@font-face{font-family:{{ $font }};src:url('data:font/ttf;base64,{{ base64_encode(file_get_contents(public_path('fonts/khmer/'.$file))) }}')}
@endforeach
{!! file_get_contents(resource_path('css/pages/g9-result-print.css')) !!}
</style></head><body>
@foreach($pages as $page)<section class="result-sheet result-{{ $type }}{{ $type==='branch'?' result-doeys':'' }}">@include('g9-national-exams.result-report-page')</section>@endforeach
<script>document.fonts.ready.then(()=>{
    document.querySelectorAll('.result-students td,.result-wis .result-table tbody td').forEach(cell=>{
        let size=parseFloat(getComputedStyle(cell).fontSize)*.75;
        while(cell.scrollWidth>cell.clientWidth+1&&size>5.5){size-=.25;cell.style.fontSize=size+'pt';}
    });
    window.print();
});</script>
</body></html>
