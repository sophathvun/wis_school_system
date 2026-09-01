<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Department List</title>
    <style>
        @font-face {
            font-family: "Khmer OS Muol Light";
            font-style: normal;
            font-weight: 300;
            font-display: block;
            src: url("data:font/truetype;charset=utf-8;base64,{{ base64_encode(file_get_contents(public_path('fonts/khmer/KhmerOSmuollight.ttf'))) }}") format("truetype");
        }

        body {
            margin: 0;
            color: #172b4d;
            font-family: Arial, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        @page {
            size: A4 portrait;
            margin: 14mm 12mm 18mm;
        }

        .logo-row {
            text-align: left;
            margin-bottom: 8px;
        }

        .school-logo {
            width: 230px;
            height: auto;
        }

        .report-title {
            margin-bottom: 14px;
            text-align: center;
        }

        .khmer-title {
            margin: 0;
            color: #4f6380;
            font-family: "Khmer OS Muol Light", "Noto Sans Khmer", sans-serif;
            font-size: 22px;
            font-weight: 300;
        }

        .english-title {
            margin: 3px 0 0;
            font-size: 20px;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #9aa4b2;
            padding: 7px 6px;
            text-align: center;
            vertical-align: middle;
            font-size: 11px;
        }

        th {
            background: #e6f1fb !important;
            font-weight: 700;
        }

        .text-start {
            text-align: left;
        }

        .status-badge {
            display: inline-block;
            min-width: 62px;
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 10px;
            font-weight: 700;
        }

        .status-active {
            background: #dcfce7;
            color: #15803d;
        }

        .status-inactive {
            background: #e5e7eb;
            color: #4b5563;
        }

        .report-footer {
            position: fixed;
            bottom: 4mm;
            left: 0;
            color: #52657e;
            font-size: 10px;
        }
    </style>
</head>

<body data-pdf-print-mode="{{ ($printMode ?? false) ? '1' : '0' }}">
    <div class="logo-row">
        @if ($logoSrc)
            <img src="{{ $logoSrc }}" alt="School Logo 1" class="school-logo">
        @endif
    </div>

    <div class="report-title">
        <h3 class="khmer-title">ážáž¶ážšáž¶áž„áž•áŸ’áž“áŸ‚áž€</h3>
        <h3 class="english-title">Department List</h3>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 64px;">No.</th>
                <th>Department</th>
                <th style="width: 180px;">Code</th>
                <th style="width: 140px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($departments as $index => $department)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="text-start">{{ $department->name ?: '-' }}</td>
                    <td>{{ $department->code ?: '-' }}</td>
                    <td>
                        <span class="status-badge {{ $department->status ? 'status-active' : 'status-inactive' }}">
                            {{ $department->status ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="report-footer">{{ now('Asia/Phnom_Penh')->format('d-M-Y h:i A') }}</div>
    @vite('resources/js/pdfPrint.js')
</body>

</html>

