<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Occupation List</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #222;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            margin: 0;
        }

        @page {
            size: A4;
            margin: 18mm 14mm 22mm;
        }

        .report-header {
            width: 100%;
            margin-bottom: 14px;
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
            font-family: "Khmer OS Muol Light", "Noto Sans Khmer", sans-serif;
            font-size: 24px;
            font-weight: 300;
            color: #4f6380;
            margin: 0;
        }

        .english-title {
            font-size: 22px;
            margin: 4px 0 0;
        }

        @font-face {
            font-family: "Khmer OS Siemreap";
            font-style: normal;
            font-weight: 400;
            font-display: block;
            src: url("data:font/truetype;charset=utf-8;base64,{{ base64_encode(file_get_contents(public_path('fonts/khmer/KhmerOSsiemreap.ttf'))) }}") format("truetype");
        }

        @font-face {
            font-family: "Khmer OS Muol Light";
            font-style: normal;
            font-weight: 300;
            font-display: block;
            src: url("data:font/truetype;charset=utf-8;base64,{{ base64_encode(file_get_contents(public_path('fonts/khmer/KhmerOSmuollight.ttf'))) }}") format("truetype");
        }

        .khmer-font,
        table th:nth-child(3),
        table td:nth-child(3) {
            font-family: "Khmer OS Siemreap", "Noto Sans Khmer", sans-serif !important;
            font-size: 12px;
            font-weight: 400;
            font-synthesis: none;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #999;
            padding: 7px;
            text-align: center;
            font-size: 12px;
        }

        th {
            background: #e6f1fb !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
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
<body>
    <div class="report-header">
        <div class="logo-row">
            @if ($logoSrc)
                <img src="{{ $logoSrc }}" alt="School Logo 1" class="school-logo">
            @endif
        </div>
        <div class="report-title">
            <h3 class="khmer-title">តារាងឈ្មោះមុខរបរ</h3>
            <h3 class="english-title">Occupation List</h3>
        </div>
    </div>

    @if ($printMode ?? false)
        <script>
            window.addEventListener('load', function () {
                const printReport = function () {
                    window.setTimeout(function () {
                        window.print();
                    }, 150);
                };

                if (document.fonts && document.fonts.ready) {
                    document.fonts.ready.then(printReport);
                } else {
                    printReport();
                }
            });

            window.addEventListener('afterprint', function () {
                window.close();
            });
        </script>
    @endif

    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Occupation (English)</th>
                <th class="khmer-font">មុខរបរ</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($occupations as $index => $occupation)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $occupation->occupation_name_en }}</td>
                    <td class="khmer-font">{{ $occupation->occupation_name_kh ?: '-' }}</td>
                    <td>{{ $occupation->status ? 'Active' : 'Inactive' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="report-footer">
        {{ now()->format('d-M-Y h:i A') }}
    </div>
</body>
</html>
