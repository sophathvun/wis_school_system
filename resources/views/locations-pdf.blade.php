<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>{{ $levelLabel }} List</title>
    <style>
        @font-face {
            font-family: "Khmer OS Siemreap";
            src: url("data:font/truetype;charset=utf-8;base64,{{ base64_encode(file_get_contents(public_path('fonts/khmer/KhmerOSsiemreap.ttf'))) }}") format("truetype");
        }

        @font-face {
            font-family: "Khmer OS Muol Light";
            src: url("data:font/truetype;charset=utf-8;base64,{{ base64_encode(file_get_contents(public_path('fonts/khmer/KhmerOSmuollight.ttf'))) }}") format("truetype");
        }

        @page {
            size: A4 portrait;
            margin: 14mm;
        }

        body {
            color: #263648;
            font-family: Arial, "Khmer OS Siemreap", sans-serif;
            font-size: 11px;
            margin: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .logo-row {
            min-height: 54px;
        }

        .school-logo {
            height: 58px;
            object-fit: contain;
        }

        .title-row {
            margin-bottom: 18px;
            text-align: center;
        }

        .khmer-title {
            color: #4f6380;
            font-family: "Khmer OS Muol Light", "Khmer OS Siemreap", serif;
            font-size: 25px;
            font-weight: 400;
            margin: 8px 0 0;
        }

        .english-title {
            color: #111827;
            font-size: 20px;
            font-weight: 700;
            margin: 2px 0 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #999;
            padding: 6px 7px;
            text-align: center;
            vertical-align: middle;
        }

        th {
            background: #e6f1fb;
            color: #111827;
            font-weight: 700;
        }

        .khmer {
            font-family: "Khmer OS Siemreap", Arial, sans-serif;
        }


        .print-toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 10px 0;
            margin-bottom: 10px;
            background: #fff;
        }

        .print-toolbar button {
            border: 1px solid #2f69c8;
            border-radius: 8px;
            background: #2f69c8;
            color: #fff;
            cursor: pointer;
            font: 600 14px Arial, sans-serif;
            padding: 9px 14px;
        }

        @media print {
            .print-toolbar {
                display: none !important;
            }
        }
        .generated-date {
            color: #4f6380;
            font-size: 10px;
            margin-top: 10px;
        }
    </style>
</head>

<body data-pdf-print-mode="{{ ($printMode ?? false) ? '1' : '0' }}" data-pdf-auto-print="0" data-default-print-orientation="portrait" data-pdf-redirect-url="{{ route('locations.index') }}">
    <div class="print-toolbar">
        <button type="button" data-print-orientation="portrait">Print Portrait</button>
        <button type="button" data-print-orientation="landscape">Print Landscape</button>
    </div>
    <div class="logo-row">
        @if ($logoData)
            <img src="{{ $logoData }}" alt="School Logo 1" class="school-logo">
        @endif
    </div>
    <div class="title-row">
        <h1 class="khmer-title">បញ្ជីឈ្មោះប្រទេស</h1>
        <h2 class="english-title">{{ $levelLabel }} List</h2>
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($tableRows as $cells)
                <tr>
                    @foreach ($cells as $cell)
                        <td class="{{ str_contains($headers[$loop->index] ?? '', 'Khmer') ? 'khmer' : '' }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="generated-date">Generated: {{ now()->format('d-M-Y h:i A') }}</p>
    @vite('resources/js/pdfPrint.js')
</body>

</html>




