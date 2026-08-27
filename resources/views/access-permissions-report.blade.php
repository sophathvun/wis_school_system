<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ $titleEn }}</title>
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
            size: A4 landscape;
            margin: 14mm 10mm 18mm;
        }

        .logo-row {
            margin-bottom: 8px;
            text-align: left;
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
            padding: 6px;
            text-align: center;
            vertical-align: middle;
            font-size: 10px;
        }

        th {
            background: #e6f1fb !important;
            font-weight: 700;
        }

        .text-start {
            text-align: left;
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

<body>
    <div class="logo-row">
        @if ($logoSrc)
            <img src="{{ $logoSrc }}" alt="School Logo 1" class="school-logo">
        @endif
    </div>
    <div class="report-title">
        <h3 class="khmer-title">{{ $titleKh }}</h3>
        <h3 class="english-title">{{ $titleEn }}</h3>
    </div>

    @if ($printMode ?? false)
        <script>
            window.addEventListener('load', function() {
                window.setTimeout(function() {
                    window.print();
                }, 150);
            });
            window.addEventListener('afterprint', function() {
                window.close();
            });
        </script>
    @endif

    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td class="{{ $loop->first ? '' : 'text-start' }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}">No data found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="report-footer">{{ now('Asia/Phnom_Penh')->format('d-M-Y h:i A') }}</div>
</body>

</html>
