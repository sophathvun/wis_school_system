@php
    $isPdfMode = $pdfMode ?? false;
    $fontPath = static fn ($file) => str_replace('\\', '/', public_path('fonts/khmer/' . $file));
@endphp
<!doctype html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @font-face{font-family:'Khmer OS Muol Light';src:url('{{ $isPdfMode ? $fontPath('KhmerOSmuollight.ttf') : asset('fonts/khmer/KhmerOSmuollight.ttf') }}') format('truetype');font-weight:normal;font-style:normal}
        @font-face{font-family:'Khmer OS Siemreap';src:url('{{ $isPdfMode ? $fontPath('KhmerOSsiemreap.ttf') : asset('fonts/khmer/KhmerOSsiemreap.ttf') }}') format('truetype');font-weight:normal;font-style:normal}
        @font-face{font-family:'Tacteing';src:url('{{ $isPdfMode ? $fontPath('Tacteing.ttf') : asset('fonts/khmer/Tacteing.ttf') }}') format('truetype');font-weight:normal;font-style:normal}
        @page{size:A4 landscape;margin:12mm}
        *{box-sizing:border-box}
        body{margin:0;background:#f4f7fb;color:#000;font-family:"Khmer OS Muol Light","Khmer OS Muol",serif}
        .toolbar{display:flex;gap:8px;margin:0 0 12px;font-family:Arial,sans-serif}
        .toolbar button{border:0;border-radius:4px;background:#206bc4;color:#fff;padding:8px 14px;cursor:pointer}
        .id-book-cover-page{width:100%;height:186mm;background:#fff;border:4px double #0070c0;outline:2px solid #0070c0;outline-offset:-8px;padding:14mm 18mm 12mm;display:flex;flex-direction:column;align-items:center;text-align:center;page-break-after:always}
        .id-book-cover-page:last-child{page-break-after:auto}
        .cover-kingdom{font-size:22px;line-height:1.45;margin:0}
        .cover-tacteing{font-family:Tacteing,serif;font-size:54px;line-height:.75;color:#244061;margin:0 0 9mm}
        .cover-motto{font-size:19px;line-height:1.5;margin:0}
        .cover-office{align-self:flex-start;text-align:left;font-size:18px;line-height:1.75;margin:0 0 2mm 6mm}
        .cover-school{align-self:flex-start;text-align:left;font-size:18px;line-height:1.75;margin:0 0 14mm 6mm}
        .cover-title{font-size:48px;line-height:1.35;margin:0 0 4mm}
        .cover-grade{font-size:26px;line-height:1.5;margin:0 0 3mm}
        .cover-code{font-size:24px;line-height:1.5;margin:0 0 3mm}
        .cover-year{font-size:24px;line-height:1.5;margin:0}
        @media print{
            body{background:#fff}
            .toolbar{display:none}
            .id-book-cover-page{height:186mm}
        }
    </style>
</head>
<body data-report-type="student-id-books-moeys-cover">
    @unless($isPdfMode)<div class="toolbar"><button type="button" data-report-action="print">Print</button><button type="button" data-report-action="close">Close</button></div>@endunless
    <section class="id-book-cover-page">
        <h1 class="cover-kingdom">ព្រះរាជាណាចក្រកម្ពុជា</h1>
        <div class="cover-motto">ជាតិ សាសនា ព្រះមហាក្សត្រ</div>
        <div class="cover-tacteing">6</div>
        <div class="cover-office">{{ $cover['educationOffice'] }}</div>
        <div class="cover-school">{{ $cover['schoolName'] }}</div>
        <div class="cover-title">សៀវភៅចុះអត្តលេខសិស្ស</div>
        <div class="cover-grade">{{ $cover['gradeRange'] }}</div>
        <div class="cover-code">{{ $cover['codeRange'] }}</div>
        <div class="cover-year">{{ $cover['academicYear'] }}</div>
    </section>
    @unless($isPdfMode)@vite('resources/js/reportsPrint.js')@endunless
</body>
</html>
