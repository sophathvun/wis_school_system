<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Academic Track List</title>
    <style>
        @font-face {
            font-family: "Khmer OS Muol Light";
            font-style: normal;
            font-weight: 300;
            font-display: block;
            src: url("data:font/truetype;charset=utf-8;base64,{{ base64_encode(file_get_contents(public_path('fonts/khmer/KhmerOSmuollight.ttf'))) }}") format("truetype");
        }

        @font-face {
            font-family: "Khmer OS Siemreap";
            font-style: normal;
            font-weight: 400;
            font-display: block;
            src: url("data:font/truetype;charset=utf-8;base64,{{ base64_encode(file_get_contents(public_path('fonts/khmer/KhmerOSsiemreap.ttf'))) }}") format("truetype");
        }

        body {
            margin: 0;
            color: #222;
            font-family: Arial, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        @page {
            size: A4;
            margin: 18mm 14mm 22mm;
        }

        .report-header {
            width: 100%;
            margin-bottom: 18px;
        }

        .logo-row {
            width: 100%;
            text-align: left;
        }

        .school-logo {
            width: 235px;
            height: auto;
        }

        .report-title {
            text-align: center;
            margin-top: 6px;
        }

        .khmer-title {
            margin: 0;
            color: #4f6380;
            font-family: "Khmer OS Muol Light", "Khmer OS Siemreap", sans-serif;
            font-size: 24px;
            font-weight: 300;
        }

        .english-title {
            margin: 4px 0 0;
            font-size: 22px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #999;
            padding: 6px;
            text-align: center;
            font-size: 11px;
        }

        th {
            background: #e6f1fb !important;
            font-weight: 700;
        }

        .khmer-font {
            font-family: "Khmer OS Siemreap", sans-serif !important;
        }

        .report-footer {
            position: fixed;
            bottom: 4mm;
            left: 0;
            font-size: 10px;
            color: #52657e;
        }
    </style>
</head>
<body data-pdf-print-mode="{{ ($printMode ?? false) ? '1' : '0' }}">
    <div class="report-header">
        <div class="logo-row">
            @if ($logoSrc)
                <img src="{{ $logoSrc }}" alt="School Logo 1" class="school-logo">
            @endif
        </div>
        <div class="report-title">
            <h2 class="khmer-title">ážáž¶ážšáž¶áž„ážáŸ’áž“áž¶áž€áŸ‹áž‡áž˜áŸ’ážšáž¾ážŸ</h2>
            <h2 class="english-title">Academic Track List</h2>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th class="khmer-font">Track (Khmer)</th>
                <th>Track (English)</th>
                <th>Code</th>
                <th>Grade</th>
                <th>Stream</th>
                <th>Language</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tracks as $index => $track)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="khmer-font">{{ $track->name_kh ?: '-' }}</td>
                    <td>{{ $track->name_en ?: '-' }}</td>
                    <td>{{ $track->code ?: '-' }}</td>
                    <td>{{ $track->grade?->grade ?: '-' }}</td>
                    <td>{{ str($track->stream_type)->replace('_', ' ')->title() }}</td>
                    <td>{{ str($track->language)->title() }}</td>
                    <td>{{ $track->status ? 'Active' : 'Inactive' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="report-footer">
        {{ now()->format('d-M-Y h:i A') }}
    </div>
    @vite('resources/js/pdfPrint.js')
</body>
</html>

