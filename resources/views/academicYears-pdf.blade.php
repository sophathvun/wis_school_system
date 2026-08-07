<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Academic Years PDF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #222;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table th,
        .table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }

        .table th {
            background: #f4f4f4;
            font-weight: bold;
        }

        .text-center {
            text-align: center;
        }

        @font-face {
            font-family: 'Noto Sans Khmer';
            font-style: normal;
            font-weight: normal;
            src: url('{{ storage_path('app/public/fonts/NotoSansKhmer-Regular.ttf') }}') format('truetype');
        }

        h1 {
            font-size: 22px;
            margin-bottom: 0;
        }

        h3 {
            font-size: 30px;
            margin-bottom: 0;
        }

        .khmer-title {
            font-family: 'Noto Sans Khmer', 'Khmer OS System', sans-serif;
            font-size: 30px;
            margin-bottom: 0;
        }

        .header {
            margin-bottom: 16px;
        }
    </style>
</head>

<body>
    <div class="header">
        <div>
            <img src="{{ $logoPath }}" alt="Logo"
                style="width: 100px; height: auto;">
        </div>
        <h3 class="khmer-title text-center">តារាងឆ្នាំសិក្សា</h3>
        <h3 class="text-center">List of the Academic Year</h3>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>No.</th>
                <th>Academic Year</th>
                <th>Type</th>
                <th>AY Code</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Description</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($academicYears as $index => $year)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $year->academic_year }}</td>
                    <td>{{ $year->isSummer() ? 'Summer School' : 'Regular' }}</td>
                    <td>{{ $year->ay_code }}</td>
                    <td>{{ $year->start_date?->format('Y-m-d') ?: '' }}</td>
                    <td>{{ $year->end_date?->format('Y-m-d') ?: '' }}</td>
                    <td>{{ $year->description }}</td>
                    <td>{{ $year->status ? 'Active' : 'Inactive' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p>Generated: {{ now()->format('F j, Y H:i') }}</p>
</body>

</html>
